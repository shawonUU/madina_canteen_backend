<?php

namespace Modules\Support\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Database\Seeders\MenuSeeder;
use Modules\Admin\Models\Module;
use Modules\Admin\Models\Permission;
use Modules\Admin\Models\User;
use Modules\Admin\Models\UserAccess;
use Modules\Admin\Models\UserPermission;
use Modules\Support\Models\SupportTicket;
use Modules\Support\Models\SupportTicketReply;
use Tests\TestCase;

class SupportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MenuSeeder::class);
    }

    private function user(string $code, array $permissions = []): User
    {
        $user = User::create(['code' => $code, 'name' => $code, 'email' => $code . '@example.test', 'password' => bcrypt('test-password'), 'status' => 'Active']);
        $module = Module::with('menus.childMenus')->where('slug', 'support')->firstOrFail();
        foreach ($module->menus as $menu) {
            foreach ($menu->childMenus->isEmpty() ? [null] : $menu->childMenus as $child) {
                UserAccess::create(['user_id' => $user->id, 'module_id' => $module->id, 'menu_id' => $menu->id, 'child_menu_id' => $child?->id, 'can_view' => true, 'can_create' => true, 'can_update' => true, 'can_delete' => true]);
            }
        }
        foreach ($permissions as $key) {
            $permission = Permission::firstOrCreate(['key' => $key], ['name' => $key, 'is_active' => true]);
            UserPermission::create(['user_id' => $user->id, 'permission_id' => $permission->id]);
        }
        return $user;
    }

    private function ticket(User $owner, array $attributes = []): SupportTicket
    {
        return SupportTicket::create($attributes + ['ticket_number' => 'TEST-' . uniqid(), 'subject' => 'Printer issue', 'description' => 'Cannot print', 'submitted_by' => $owner->id, 'issue_type' => 'bug', 'priority' => 'medium', 'status' => 'open']);
    }

    public function test_assignment_requires_the_explicit_permission(): void
    {
        $owner = $this->user('owner');
        $staff = $this->user('staff');
        $ticket = $this->ticket($owner);
        Sanctum::actingAs($owner);
        $this->putJson("/api/support/tickets/{$ticket->id}/assign", ['assigned_to' => $staff->id])->assertForbidden();
        $this->assertNull($ticket->fresh()->assigned_to);
    }

    public function test_authorized_assignment_records_the_assignee_and_status(): void
    {
        $owner = $this->user('owner', ['support.ticket.assign']);
        $staff = $this->user('staff');
        $ticket = $this->ticket($owner);
        Sanctum::actingAs($owner);
        $this->putJson("/api/support/tickets/{$ticket->id}/assign", ['assigned_to' => $staff->id])->assertOk();
        $this->assertDatabaseHas('support_tickets', ['id' => $ticket->id, 'assigned_to' => $staff->id, 'status' => 'assigned']);
        $this->assertDatabaseHas('support_ticket_histories', ['ticket_id' => $ticket->id, 'field_name' => 'assigned_to', 'new_value' => (string) $staff->id]);
    }

    public function test_report_totals_include_more_than_one_page_and_keep_visibility_scope(): void
    {
        $owner = $this->user('owner', ['support.report.view']);
        $other = $this->user('other');
        for ($index = 0; $index < 105; $index++) $this->ticket($owner);
        $this->ticket($other);
        Sanctum::actingAs($owner);
        $this->getJson('/api/support/reports?per_page=25')->assertOk()->assertJsonPath('summary.total', 105)->assertJsonPath('meta.total', 105)->assertJsonCount(25, 'data');
    }

    public function test_assigned_scope_means_the_current_user(): void
    {
        $owner = $this->user('owner', ['support.ticket.view-all']);
        $other = $this->user('other');
        $mine = $this->ticket($other, ['assigned_to' => $owner->id]);
        $this->ticket($owner, ['assigned_to' => $other->id]);
        Sanctum::actingAs($owner);
        $this->getJson('/api/support/tickets?scope=assigned')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_internal_replies_are_hidden_without_internal_permission(): void
    {
        $owner = $this->user('owner');
        $ticket = $this->ticket($owner);
        SupportTicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $owner->id, 'message' => 'Public update', 'is_internal' => false]);
        SupportTicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $owner->id, 'message' => 'Private note', 'is_internal' => true]);
        Sanctum::actingAs($owner);
        $this->getJson("/api/support/tickets/{$ticket->id}")->assertOk()->assertJsonCount(1, 'data.replies')->assertDontSee('Private note');
    }

    public function test_report_only_user_can_view_history_of_a_ticket_in_the_report(): void
    {
        $owner = $this->user('reporter');
        UserAccess::where('user_id', $owner->id)->whereHas('menu', fn ($query) => $query->where('slug', '!=', 'support-reports'))->delete();
        UserAccess::where('user_id', $owner->id)->update(['can_create' => false, 'can_update' => false, 'can_delete' => false]);
        $ticket = $this->ticket($owner);
        Sanctum::actingAs($owner);

        $this->getJson('/api/support/reports')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson("/api/record-logs/support_tickets/{$ticket->id}")->assertOk()->assertJsonFragment(['resource' => 'support_tickets']);
    }

    public function test_ticket_history_hides_internal_replies_without_internal_permission(): void
    {
        $owner = $this->user('owner');
        $ticket = $this->ticket($owner);
        SupportTicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $owner->id, 'message' => 'Public history update', 'is_internal' => false]);
        $private = SupportTicketReply::create(['ticket_id' => $ticket->id, 'user_id' => $owner->id, 'message' => 'Private history secret', 'is_internal' => true]);
        Sanctum::actingAs($owner);

        $this->getJson("/api/record-logs/support_tickets/{$ticket->id}")->assertOk()->assertSee('Public history update')->assertDontSee('Private history secret');
        $this->getJson("/api/record-logs/support_ticket_replies/{$private->id}")->assertForbidden();
    }
}
