<?php

namespace Modules\Meeting\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Database\Seeders\MenuSeeder;
use Modules\Admin\Models\Module;
use Modules\Admin\Models\Permission;
use Modules\Admin\Models\User;
use Modules\Admin\Models\UserAccess;
use Modules\Admin\Models\UserPermission;
use Modules\HRM\Models\Employee;
use Modules\Meeting\Models\Meeting;
use Modules\Meeting\Models\MeetingMinute;
use Modules\Meeting\Models\MeetingParticipant;
use Tests\TestCase;

class MeetingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MenuSeeder::class);
    }

    private function user(string $code, array $permissions = []): User
    {
        $employee = Employee::create(['code' => $code, 'name' => $code, 'status' => 'Active']);
        $user = User::create(['code' => $code, 'name' => $code, 'email' => $code . '@example.test', 'employee_id' => $employee->id, 'password' => bcrypt('test-password'), 'status' => 'Active']);
        $module = Module::with('menus.childMenus')->where('slug', 'meeting')->firstOrFail();
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

    private function meeting(User $owner, array $data = []): Meeting
    {
        return Meeting::create($data + ['meeting_number' => 'MTG-' . uniqid(), 'title' => 'Planning', 'organizer_id' => $owner->id, 'meeting_date' => '2026-11-26', 'start_time' => '09:00', 'end_time' => '10:00', 'status' => 'scheduled']);
    }

    private function keepMenu(User $user, string $slug, bool $canUpdate = false): void
    {
        UserAccess::where('user_id', $user->id)->whereHas('menu', fn ($query) => $query->where('slug', '!=', $slug))->delete();
        UserAccess::where('user_id', $user->id)->update(['can_create' => false, 'can_update' => $canUpdate, 'can_delete' => false]);
    }

    public function test_column_filter_is_applied_before_pagination(): void
    {
        $owner = $this->user('owner');
        $this->meeting($owner, ['title' => 'Board meeting']);
        $this->meeting($owner, ['title' => 'Team planning']);
        Sanctum::actingAs($owner);
        $this->getJson('/api/meeting-management/meetings?filter[meeting_number]=Board')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.title', 'Board meeting');
    }

    public function test_an_unrelated_user_cannot_read_a_meeting(): void
    {
        $owner = $this->user('owner');
        $other = $this->user('other');
        $meeting = $this->meeting($owner);
        Sanctum::actingAs($other);
        $this->getJson("/api/meeting-management/meetings/{$meeting->id}")->assertForbidden();
    }

    public function test_invitation_reply_is_owned_and_stores_the_enum_case_and_timestamp(): void
    {
        $owner = $this->user('owner');
        $guest = $this->user('guest');
        $meeting = $this->meeting($owner);
        $participant = MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $guest->employee_id, 'participation_type' => 'required', 'invitation_status' => 'Pending']);
        Sanctum::actingAs($owner);
        $this->patchJson("/api/meeting-management/meetings/{$meeting->id}/invitation/{$participant->id}", ['invitation_status' => 'accepted'])->assertNotFound();
        Sanctum::actingAs($guest);
        $this->patchJson("/api/meeting-management/meetings/{$meeting->id}/invitation/{$participant->id}", ['invitation_status' => 'accepted'])->assertOk();
        $this->assertSame('Accepted', $participant->fresh()->invitation_status);
        $this->assertNotNull($participant->fresh()->responded_at);
        $this->patchJson("/api/meeting-management/meetings/{$meeting->id}/invitation/{$participant->id}", ['invitation_status' => 'Declined'])->assertStatus(422);
    }

    public function test_attendance_save_is_returned_on_the_participant_list(): void
    {
        $owner = $this->user('owner');
        $meeting = $this->meeting($owner);
        $participant = MeetingParticipant::create(['meeting_id' => $meeting->id, 'guest_name' => 'Visitor', 'participation_type' => 'required', 'invitation_status' => 'Pending']);
        Sanctum::actingAs($owner);
        $this->postJson("/api/meeting-management/meetings/{$meeting->id}/attendance", ['meeting_participant_id' => $participant->id, 'status' => 'present'])->assertOk();
        $this->getJson("/api/meeting-management/meetings/{$meeting->id}/participants")->assertOk()->assertJsonPath('data.0.attendance_status', 'present');
    }

    public function test_attendance_cannot_reference_a_different_meeting(): void
    {
        $owner = $this->user('owner');
        $first = $this->meeting($owner);
        $second = $this->meeting($owner);
        $participant = MeetingParticipant::create(['meeting_id' => $second->id, 'guest_name' => 'Visitor', 'participation_type' => 'required', 'invitation_status' => 'Pending']);
        Sanctum::actingAs($owner);
        $this->putJson("/api/meeting-management/meetings/{$first->id}/attendance", ['attendances' => [['meeting_participant_id' => $participant->id, 'status' => 'present']]])->assertStatus(422);
        $this->assertDatabaseMissing('meeting_attendances', ['meeting_id' => $first->id]);
    }

    public function test_agenda_fields_sent_by_the_form_are_persisted(): void
    {
        $owner = $this->user('owner');
        $meeting = $this->meeting($owner);
        Sanctum::actingAs($owner);
        $this->postJson("/api/meeting-management/meetings/{$meeting->id}/agendas", ['title' => 'Budget', 'presenter' => 'Finance Manager', 'duration_minutes' => 30, 'sort_order' => 1])->assertCreated();
        $this->assertDatabaseHas('meeting_agendas', ['meeting_id' => $meeting->id, 'presenter' => 'Finance Manager', 'duration_minutes' => 30]);
    }

    public function test_invitation_only_user_can_list_their_invitations(): void
    {
        $owner = $this->user('owner');
        $guest = $this->user('guest');
        $this->keepMenu($guest, 'invitations', true);
        $meeting = $this->meeting($owner);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $guest->employee_id, 'participation_type' => 'required', 'invitation_status' => 'Pending']);
        Sanctum::actingAs($guest);

        $this->getJson('/api/meeting-management/my-invitations')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_invitation_only_user_can_reply_to_their_invitation(): void
    {
        $owner = $this->user('owner');
        $guest = $this->user('guest');
        $this->keepMenu($guest, 'invitations', true);
        $meeting = $this->meeting($owner);
        $participant = MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $guest->employee_id, 'participation_type' => 'required', 'invitation_status' => 'Pending']);
        Sanctum::actingAs($guest);

        $this->patchJson("/api/meeting-management/meetings/{$meeting->id}/invitation/{$participant->id}", ['invitation_status' => 'Accepted'])->assertOk();
        $this->assertSame('Accepted', $participant->fresh()->invitation_status);
    }

    public function test_attendance_only_user_can_load_the_attendance_participants(): void
    {
        $owner = $this->user('owner');
        $this->keepMenu($owner, 'meeting-attendance', true);
        $meeting = $this->meeting($owner);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'guest_name' => 'Visitor', 'participation_type' => 'required', 'invitation_status' => 'Pending']);
        Sanctum::actingAs($owner);

        $this->getJson('/api/meeting-management/meetings?options=1')->assertOk();
        $this->getJson("/api/meeting-management/meetings/{$meeting->id}/participants")->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_invitation_only_user_cannot_read_minutes_through_direct_history(): void
    {
        $owner = $this->user('owner');
        $guest = $this->user('guest');
        $this->keepMenu($guest, 'invitations', true);
        $meeting = $this->meeting($owner);
        MeetingParticipant::create(['meeting_id' => $meeting->id, 'employee_id' => $guest->employee_id, 'participation_type' => 'required', 'invitation_status' => 'Pending']);
        $minute = MeetingMinute::create(['meeting_id' => $meeting->id, 'discussion_summary' => 'Restricted minutes detail', 'status' => 'draft']);
        Sanctum::actingAs($guest);

        $this->getJson("/api/record-logs/meeting_minutes/{$minute->id}")->assertForbidden();
    }

    public function test_meeting_viewer_does_not_receive_minutes_in_meeting_history_without_minutes_access(): void
    {
        $owner = $this->user('owner');
        $this->keepMenu($owner, 'meeting-scheduling');
        $meeting = $this->meeting($owner);
        MeetingMinute::create(['meeting_id' => $meeting->id, 'discussion_summary' => 'Restricted aggregate secret', 'status' => 'draft']);
        Sanctum::actingAs($owner);

        $this->getJson("/api/record-logs/meetings/{$meeting->id}")->assertOk()->assertDontSee('Restricted aggregate secret');
    }

    public function test_minutes_approval_does_not_require_the_permission_to_edit_minutes(): void
    {
        $owner = $this->user('owner', ['meeting.minutes.approve']);
        $this->keepMenu($owner, 'meeting-minutes');
        $meeting = $this->meeting($owner);
        MeetingMinute::create(['meeting_id' => $meeting->id, 'discussion_summary' => 'Ready for approval', 'status' => 'draft']);
        Sanctum::actingAs($owner);

        $this->patchJson("/api/meeting-management/meetings/{$meeting->id}/minutes/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    }

    public function test_direct_minutes_permission_can_use_the_shared_meeting_options(): void
    {
        $owner = $this->user('owner', ['meeting.minutes.view']);
        UserAccess::where('user_id', $owner->id)->delete();
        $meeting = $this->meeting($owner);
        Sanctum::actingAs($owner);

        $this->getJson("/api/meeting-management/meetings/{$meeting->id}/minutes")->assertOk();
        $this->getJson('/api/meeting-management/meetings?options=1')->assertOk()->assertJsonFragment(['id' => $meeting->id]);
    }

    public function test_direct_attendance_report_permission_is_in_effective_navigation_access(): void
    {
        $owner = $this->user('owner', ['meeting.attendance.report']);
        UserAccess::where('user_id', $owner->id)->delete();
        Sanctum::actingAs($owner);

        $this->getJson('/api/meeting-management/reports/attendance')->assertOk();
        $response = $this->getJson('/api/auth/navigation')->assertOk()->assertJsonPath('success', true);
        $access = collect($response->json('accesses'))->first(fn ($row) => $row['module_slug'] === 'meeting' && $row['child_menu_slug'] === 'attendance-report');
        $this->assertNotNull($access);
        $this->assertTrue($access['can_view']);
    }

    public function test_attendance_report_user_can_read_an_unmarked_participant_history(): void
    {
        $owner = $this->user('owner', ['meeting.attendance.report']);
        UserAccess::where('user_id', $owner->id)->delete();
        $meeting = $this->meeting($owner);
        $participant = MeetingParticipant::create(['meeting_id' => $meeting->id, 'guest_name' => 'Unmarked guest', 'participation_type' => 'required', 'invitation_status' => 'Pending']);
        Sanctum::actingAs($owner);

        $this->getJson('/api/meeting-management/reports/attendance')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/record-logs/meeting_participants/{$participant->id}")->assertOk();
    }

    public function test_meeting_detail_does_not_expose_minutes_without_minutes_view_permission(): void
    {
        $owner = $this->user('owner');
        $this->keepMenu($owner, 'meeting-scheduling');
        $meeting = $this->meeting($owner);
        MeetingMinute::create(['meeting_id' => $meeting->id, 'discussion_summary' => 'Restricted meeting detail secret', 'status' => 'draft']);
        Sanctum::actingAs($owner);

        $this->getJson("/api/meeting-management/meetings/{$meeting->id}")->assertOk()->assertDontSee('Restricted meeting detail secret');
    }
}
