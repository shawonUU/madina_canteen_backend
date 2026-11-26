<?php

namespace Tests\Feature\Approval;

use App\Http\Middleware\EnforceApiAccess;
use App\Models\RecordLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Models\ChildMenu;
use Modules\Admin\Models\Menu;
use Modules\Admin\Models\Module;
use Modules\Admin\Models\Role;
use Modules\Admin\Models\User;
use Modules\Admin\Models\UserRole;
use Modules\Approval\Models\ApprovalDelegation;
use Modules\Approval\Models\ApprovalRequest;
use Modules\Approval\Models\ApprovalRequestLevel;
use Modules\Approval\Models\ApprovalWorkflow;
use Modules\Approval\Models\ApprovalWorkflowLevel;
use Modules\HRM\Models\Department;
use Tests\TestCase;

class ApprovalControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;
    private Module $module;

    protected function setUp(): void
    {
        parent::setUp();
        // Route grants are tested separately; approval record visibility stays enabled here.
        $this->withoutMiddleware(EnforceApiAccess::class);
        $this->actor = $this->user('actor');
        Sanctum::actingAs($this->actor);
        $this->module = Module::create(['code' => 'M-APPROVAL', 'name' => 'Approval Test', 'slug' => 'approval-test', 'is_active' => true]);
    }

    public function test_workflow_requires_the_approver_id_for_each_type(): void
    {
        foreach (['User' => 'user_id', 'Role' => 'role_id', 'Department Role' => 'role_id'] as $type => $field) {
            $payload = $this->payload();
            $payload['levels'][0] = ['level_no' => 1, 'name' => 'Review', 'approver_type' => $type];
            $this->postJson('/api/approval-workflows', $payload)->assertUnprocessable()->assertJsonValidationErrors('levels.0.'.$field);
        }
        $role = Role::create(['name' => 'Reviewer', 'is_active' => true]);
        $payload['levels'][0]['role_id'] = $role->id;
        $this->postJson('/api/approval-workflows', $payload)->assertUnprocessable()->assertJsonValidationErrors('levels.0.department_id');
        $this->assertDatabaseCount('approval_workflows', 0);
    }

    public function test_workflow_rejects_mismatched_navigation_parents(): void
    {
        $otherModule = Module::create(['code' => 'M-OTHER', 'name' => 'Other', 'slug' => 'other', 'is_active' => true]);
        $otherMenu = Menu::create(['code' => 'MENU-OTHER', 'module_id' => $otherModule->id, 'name' => 'Other Menu', 'slug' => 'other-menu']);
        $ownMenu = Menu::create(['code' => 'MENU-OWN', 'module_id' => $this->module->id, 'name' => 'Own Menu', 'slug' => 'own-menu']);
        $child = ChildMenu::create(['code' => 'CHILD-OTHER', 'menu_id' => $otherMenu->id, 'name' => 'Other Child', 'slug' => 'other-child']);
        $this->postJson('/api/approval-workflows', [...$this->payload(), 'menu_id' => $otherMenu->id])->assertUnprocessable()->assertJsonValidationErrors('menu_id');
        $this->postJson('/api/approval-workflows', [...$this->payload(), 'menu_id' => $ownMenu->id, 'child_menu_id' => $child->id])->assertUnprocessable()->assertJsonValidationErrors('child_menu_id');
        $this->assertDatabaseCount('approval_workflows', 0);
    }

    public function test_workflow_rejects_duplicate_nullable_location_but_can_update_itself(): void
    {
        $response = $this->postJson('/api/approval-workflows', $this->payload())->assertCreated();
        $id = $response->json('data.id');
        $this->postJson('/api/approval-workflows', [...$this->payload(), 'name' => 'Duplicate'])->assertUnprocessable()->assertJsonValidationErrors('module_id');
        $this->putJson('/api/approval-workflows/'.$id, [...$this->payload(), 'name' => 'Renamed'])->assertOk()->assertJsonPath('data.name', 'Renamed');
        $this->assertDatabaseCount('approval_workflows', 1);
        $this->getJson('/api/approval-workflows?all=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.module.name', 'Approval Test');
    }

    public function test_workflow_requires_distinct_consecutive_level_numbers(): void
    {
        $payload = $this->payload();
        $payload['levels'][] = [...$payload['levels'][0], 'name' => 'Second', 'level_no' => 1];
        $this->postJson('/api/approval-workflows', $payload)->assertUnprocessable()->assertJsonValidationErrors('levels.0.level_no');
        $payload['levels'][1]['level_no'] = 3;
        $this->postJson('/api/approval-workflows', $payload)->assertUnprocessable()->assertJsonValidationErrors('levels');
        $this->assertDatabaseCount('approval_workflows', 0);
    }

    public function test_level_reorder_retains_ids_and_logs_only_the_real_before_and_after_positions(): void
    {
        $payload = $this->payload();
        $payload['levels'][] = [...$payload['levels'][0], 'name' => 'Final Review', 'level_no' => 2];
        $created = $this->postJson('/api/approval-workflows', $payload)->assertCreated()->json('data');
        $first = $created['levels'][0];
        $second = $created['levels'][1];
        $payload['levels'] = [[...$second, 'level_no' => 1], [...$first, 'level_no' => 2]];
        $this->putJson('/api/approval-workflows/'.$created['id'], $payload)->assertOk()
            ->assertJsonPath('data.levels.0.id', $second['id'])->assertJsonPath('data.levels.1.id', $first['id']);
        $logs = RecordLog::where('resource', 'approval_workflow_levels')->where('event', 'updated')->get()->keyBy('record_id');
        $this->assertCount(2, $logs);
        $this->assertSame(1, $logs[$first['id']]->old_values['level_no']);
        $this->assertSame(2, $logs[$first['id']]->new_values['level_no']);
        $this->assertSame(2, $logs[$second['id']]->old_values['level_no']);
        $this->assertSame(1, $logs[$second['id']]->new_values['level_no']);
        $this->assertDatabaseCount('approval_workflow_levels', 2);
    }

    public function test_removed_levels_are_individually_logged_and_foreign_level_ids_are_rejected(): void
    {
        $payload = $this->payload();
        $payload['levels'][] = [...$payload['levels'][0], 'name' => 'Final Review', 'level_no' => 2];
        $created = $this->postJson('/api/approval-workflows', $payload)->assertCreated()->json('data');
        $other = $this->workflow('FOREIGN');
        $foreign = $other->levels()->create(['code' => 'L-FOREIGN', 'level_no' => 1, 'name' => 'Foreign', 'approver_type' => 'User', 'user_id' => $this->actor->id]);
        $invalid = $this->payload();
        $invalid['levels'][0]['id'] = $foreign->id;
        $this->putJson('/api/approval-workflows/'.$created['id'], $invalid)->assertUnprocessable()->assertJsonValidationErrors('levels.0.id');
        $payload['levels'] = [[...$created['levels'][1], 'level_no' => 1]];
        $this->putJson('/api/approval-workflows/'.$created['id'], $payload)->assertOk();
        $this->assertDatabaseMissing('approval_workflow_levels', ['id' => $created['levels'][0]['id']]);
        $deleted = RecordLog::where('resource', 'approval_workflow_levels')->where('event', 'deleted')->where('record_id', $created['levels'][0]['id'])->firstOrFail();
        $this->assertSame(1, $deleted->old_values['level_no']);
        $this->assertSame($created['id'], $deleted->old_values['workflow_id']);
        $this->assertDatabaseHas('approval_workflow_levels', ['id' => $created['levels'][1]['id'], 'level_no' => 1]);
    }

    public function test_pending_list_includes_roles_and_delegates_only_at_the_current_level(): void
    {
        $role = Role::create(['name' => 'Approver', 'is_active' => true]);
        UserRole::create(['user_id' => $this->actor->id, 'role_id' => $role->id]);
        $owner = $this->user('owner');
        $delegateFrom = $this->user('absent');
        ApprovalDelegation::create(['code' => 'D-ACTIVE', 'from_user_id' => $delegateFrom->id, 'to_user_id' => $this->actor->id, 'start_at' => now()->subDay(), 'end_at' => now()->addDay(), 'status' => 'Active', 'created_by' => $this->actor->id]);
        $workflow = $this->workflow('PENDING');
        $roleRequest = $this->approval('ROLE', $workflow, $owner, ['approver_type' => 'Role', 'role_id' => $role->id]);
        $delegatedRequest = $this->approval('DELEGATE', $workflow, $owner, ['user_id' => $delegateFrom->id]);
        $later = $this->approval('LATER', $workflow, $owner, ['user_id' => $this->actor->id]);
        $later->update(['current_level' => 2]);
        $later->levels()->create(['code' => 'LEVEL-LATER-2', 'level_no' => 2, 'level_name' => 'Current', 'approver_type' => 'User', 'user_id' => $owner->id, 'status' => 'Pending']);
        $ids = $this->getJson('/api/approvals?all=1')->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertEqualsCanonicalizing([$roleRequest->id, $delegatedRequest->id], array_column($ids, 'id'));
        $this->getJson('/api/approvals?all=1&filter[document]=ROLE')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.total_levels', 1)->assertJsonPath('data.0.current_level_data.name', 'Review');
    }

    public function test_pending_all_mode_returns_more_than_the_default_page(): void
    {
        $workflow = $this->workflow('MANY');
        for ($number = 1; $number <= 21; $number++) $this->approval('MANY-'.$number, $workflow, $this->actor, ['user_id' => $this->actor->id]);
        $this->getJson('/api/approvals')->assertOk()->assertJsonCount(20, 'data.data')->assertJsonPath('data.total', 21);
        $this->getJson('/api/approvals?all=1')->assertOk()->assertJsonCount(21, 'data');
    }

    public function test_show_and_versioned_alias_protect_unrelated_requests_and_bind_the_id(): void
    {
        $owner = $this->user('owner');
        $approval = $this->approval('PRIVATE', $this->workflow('PRIVATE'), $owner, ['user_id' => $owner->id]);
        $this->getJson('/api/approvals/'.$approval->id)->assertForbidden();
        $this->getJson('/api/v1/approvals/'.$approval->id)->assertForbidden();
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/approvals/'.$approval->id)->assertOk()->assertJsonPath('data.id', $approval->id);
        $this->postJson('/api/v1/approvals', [])->assertStatus(405);
    }

    public function test_delegation_offsets_are_normalized_and_cancel_keeps_an_audited_record(): void
    {
        $timezone = date_default_timezone_get();
        config(['app.timezone' => 'Asia/Dhaka']);
        date_default_timezone_set('Asia/Dhaka');
        try {
            $target = $this->user('delegate');
            $payload = ['from_user_id' => $this->actor->id, 'to_user_id' => $target->id, 'start_at' => '2026-11-26T09:00:00.000Z', 'end_at' => '2026-11-26T11:00:00.000Z', 'reason' => 'Travel'];
            $id = $this->postJson('/api/approval-delegations', $payload)->assertCreated()->json('data.id');
            $this->assertDatabaseHas('approval_delegations', ['id' => $id, 'start_at' => '2026-11-26 15:00:00', 'end_at' => '2026-11-26 17:00:00', 'status' => 'Active']);
            $this->getJson('/api/approval-delegations?all=1&filter[reason]=Travel')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.to_user.id', $target->id);
            $this->putJson('/api/approval-delegations/'.$id, [...$payload, 'end_at' => '2026-11-26T12:00:00Z', 'status' => 'Active'])->assertOk();
            $this->assertDatabaseHas('approval_delegations', ['id' => $id, 'end_at' => '2026-11-26 18:00:00']);
            $this->deleteJson('/api/approval-delegations/'.$id)->assertOk();
            $this->assertDatabaseHas('approval_delegations', ['id' => $id, 'status' => 'Cancelled']);
            $log = RecordLog::where('resource', 'approval_delegations')->where('record_id', $id)->where('event', 'updated')->latest('id')->firstOrFail();
            $this->assertSame('Active', $log->old_values['status']);
            $this->assertSame('Cancelled', $log->new_values['status']);
        } finally {
            date_default_timezone_set($timezone);
        }
    }

    private function payload(): array
    {
        return ['module_id' => $this->module->id, 'menu_id' => null, 'child_menu_id' => null, 'name' => 'Review Workflow', 'status' => 'Active',
            'levels' => [['level_no' => 1, 'name' => 'Review', 'approver_type' => 'User', 'user_id' => $this->actor->id, 'is_required' => true, 'can_reject' => true, 'can_return' => true]]];
    }

    private function user(string $key): User
    {
        return User::create(['code' => 'U-'.$key, 'name' => ucfirst($key), 'email' => $key.'@approval.test', 'password' => bcrypt('Strong-password-123'), 'status' => 'Active']);
    }

    private function workflow(string $key): ApprovalWorkflow
    {
        $menu = Menu::create(['code' => 'MENU-'.$key, 'module_id' => $this->module->id, 'name' => $key, 'slug' => strtolower($key)]);
        return ApprovalWorkflow::create(['code' => 'WF-'.$key, 'module_id' => $this->module->id, 'menu_id' => $menu->id, 'name' => $key, 'status' => 'Active', 'created_by' => $this->actor->id]);
    }

    private function approval(string $key, ApprovalWorkflow $workflow, User $owner, array $level): ApprovalRequest
    {
        $request = ApprovalRequest::create(['code' => 'AR-'.$key, 'workflow_id' => $workflow->id, 'document_type' => Department::class, 'document_id' => 1, 'document_no' => $key, 'requested_by' => $owner->id, 'current_level' => 1, 'status' => 'Pending', 'submitted_at' => now()]);
        $request->levels()->create(['code' => 'ARL-'.$key, 'level_no' => 1, 'level_name' => 'Review', 'approver_type' => 'User', 'status' => 'Pending', ...$level]);
        return $request;
    }
}
