<?php

namespace Tests\Feature\Approval;

use App\Services\AccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\Admin\Database\Seeders\MenuSeeder;
use Modules\Admin\Models\Role;
use Modules\Admin\Models\User;
use Modules\Admin\Models\UserAccess;
use Modules\Admin\Models\UserRole;
use Modules\Approval\Models\ApprovalDelegation;
use Modules\Approval\Models\ApprovalRequest;
use Modules\Approval\Models\ApprovalWorkflow;
use Modules\Approval\Services\ApprovalService;
use Modules\Reception\Models\GatePass;
use Modules\Reception\Models\GatePassPurpose;
use Tests\TestCase;

class ApprovalStateTest extends TestCase
{
    use RefreshDatabase;

    private User $requester;
    private GatePassPurpose $purpose;
    private array $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MenuSeeder::class);
        $this->requester = $this->user('requester');
        Sanctum::actingAs($this->requester);
        $this->location = app(AccessService::class)->context(['reception', 'gatepass', 'create-gatepass-request']);
        UserAccess::create([
            'user_id' => $this->requester->id,
            ...$this->location,
            'can_view' => true,
            'can_create' => true,
            'can_update' => false,
            'can_delete' => false,
        ]);
        $this->purpose = GatePassPurpose::create([
            'code' => 'PURPOSE-APPROVAL-STATE', 'name' => 'Office visit',
            'is_active' => true, 'approval_status' => 'Approved',
        ]);
    }

    public function test_configured_levels_run_in_order_and_only_final_approval_updates_the_document(): void
    {
        $first = $this->user('first');
        $second = $this->user('second');
        $workflow = $this->workflow([
            ['level_no' => 2, 'user_id' => $second->id, 'name' => 'Final review'],
            ['level_no' => 1, 'user_id' => $first->id, 'name' => 'Initial review'],
        ]);
        [$document, $approval] = $this->submit();

        $this->assertSame($workflow->id, $approval->workflow_id);
        $this->assertSame($this->requester->id, $approval->requested_by);
        $this->assertSame(1, $approval->current_level);
        $this->assertSame(['Pending', 'Waiting'], $approval->levels()->pluck('status')->all());
        $this->assertDatabaseHas('approval_actions', ['approval_request_id' => $approval->id, 'action' => 'Submitted', 'action_by' => $this->requester->id]);
        $this->assertDocumentStatus($document, 'Pending', 'Pending');

        Sanctum::actingAs($second);
        $this->postJson("/api/approvals/{$approval->id}/approve")->assertUnprocessable()->assertJsonValidationErrors('approval');
        Sanctum::actingAs($first);
        $this->postJson("/api/approvals/{$approval->id}/approve", ['remarks' => 'Reviewed'])->assertOk();
        $this->assertSame(2, $approval->fresh()->current_level);
        $this->assertSame('Pending', $approval->fresh()->status);
        $this->assertSame(['Approved', 'Pending'], $approval->levels()->pluck('status')->all());
        $this->assertDocumentStatus($document, 'Pending', 'Pending');

        Sanctum::actingAs($second);
        $this->postJson("/api/approvals/{$approval->id}/approve", ['remarks' => 'Final approval'])->assertOk();
        $this->assertSame('Approved', $approval->fresh()->status);
        $this->assertNotNull($approval->fresh()->completed_at);
        $this->assertSame(['Approved', 'Approved'], $approval->levels()->pluck('status')->all());
        $this->assertDocumentStatus($document, 'Approved', 'Approved');
        $this->assertSame(2, $approval->actions()->where('action', 'Approved')->count());
        $this->postJson("/api/approvals/{$approval->id}/approve")->assertUnprocessable();
        $this->assertSame(2, $approval->actions()->where('action', 'Approved')->count());
    }

    public function test_document_is_auto_approved_when_no_workflow_is_configured(): void
    {
        [$document, $approval] = $this->submit();

        $this->assertNull($approval);
        $this->assertDocumentStatus($document, 'Approved', 'Approved');
        $this->assertDatabaseCount('approval_requests', 0);
        $this->assertDatabaseCount('approval_actions', 0);
    }

    public function test_rejection_updates_the_current_level_request_and_document_together(): void
    {
        $reviewer = $this->user('reviewer');
        $next = $this->user('next');
        $this->workflow([['user_id' => $reviewer->id, 'can_reject' => true], ['user_id' => $next->id]]);
        [$document, $approval] = $this->submit();
        Sanctum::actingAs($reviewer);

        $this->postJson("/api/approvals/{$approval->id}/reject", ['remarks' => 'Missing authorization'])->assertOk();
        $this->assertSame('Rejected', $approval->fresh()->status);
        $this->assertNotNull($approval->fresh()->completed_at);
        $this->assertSame(['Rejected', 'Waiting'], $approval->levels()->pluck('status')->all());
        $this->assertDocumentStatus($document, 'Rejected', 'Rejected');
        $this->assertDatabaseHas('approval_actions', ['approval_request_id' => $approval->id, 'action' => 'Rejected', 'action_by' => $reviewer->id, 'remarks' => 'Missing authorization']);
        $this->postJson("/api/approvals/{$approval->id}/approve")->assertUnprocessable();
        $this->assertSame(0, $approval->actions()->where('action', 'Approved')->count());
    }

    public function test_return_keeps_the_gate_pass_pending_and_records_the_returned_approval_state(): void
    {
        $reviewer = $this->user('reviewer');
        $next = $this->user('next');
        $this->workflow([['user_id' => $reviewer->id, 'can_return' => true], ['user_id' => $next->id]]);
        [$document, $approval] = $this->submit();
        Sanctum::actingAs($reviewer);

        $this->postJson("/api/approvals/{$approval->id}/return", ['remarks' => 'Correct the return time'])->assertOk();
        $this->assertSame('Returned', $approval->fresh()->status);
        $this->assertNull($approval->fresh()->completed_at);
        $this->assertSame(['Returned', 'Waiting'], $approval->levels()->pluck('status')->all());
        $this->assertDocumentStatus($document, 'Pending', 'Returned');
        $this->assertDatabaseHas('approval_actions', ['approval_request_id' => $approval->id, 'action' => 'Returned', 'action_by' => $reviewer->id, 'remarks' => 'Correct the return time']);
        $this->postJson("/api/approvals/{$approval->id}/approve")->assertUnprocessable();
    }

    public function test_reject_and_return_respect_the_flags_snapshotted_at_submission(): void
    {
        $reviewer = $this->user('reviewer');
        $workflow = $this->workflow([['user_id' => $reviewer->id, 'can_reject' => false, 'can_return' => false]]);
        [$document, $approval] = $this->submit();
        $this->assertDatabaseHas('approval_request_levels', ['approval_request_id' => $approval->id, 'can_reject' => false, 'can_return' => false]);
        $workflow->levels()->firstOrFail()->update(['can_reject' => true, 'can_return' => true]);
        Sanctum::actingAs($reviewer);

        foreach (['reject', 'return'] as $action) {
            $this->postJson("/api/approvals/{$approval->id}/{$action}", ['remarks' => 'Attempt disabled action'])->assertUnprocessable()->assertJsonValidationErrors('approval');
        }
        $this->assertSame('Pending', $approval->fresh()->status);
        $this->assertSame('Pending', $approval->levels()->firstOrFail()->status);
        $this->assertDocumentStatus($document, 'Pending', 'Pending');
        $this->assertSame(['Submitted'], $approval->actions()->pluck('action')->all());
    }

    public function test_role_level_requires_distinct_approvers_and_rejects_duplicate_approvals(): void
    {
        $first = $this->user('first');
        $second = $this->user('second');
        $outsider = $this->user('outsider');
        $role = Role::create(['name' => 'Gate Pass Reviewers', 'is_active' => true]);
        foreach ([$first, $second] as $reviewer) UserRole::create(['user_id' => $reviewer->id, 'role_id' => $role->id]);
        $this->workflow([['approver_type' => 'Role', 'role_id' => $role->id, 'min_approvers' => 2]]);
        [$document, $approval] = $this->submit();
        $this->assertSame(2, $approval->levels()->firstOrFail()->min_approvers);

        Sanctum::actingAs($outsider);
        $this->postJson("/api/approvals/{$approval->id}/approve")->assertUnprocessable()->assertJsonValidationErrors('approval');
        Sanctum::actingAs($first);
        $this->postJson("/api/approvals/{$approval->id}/approve")->assertOk();
        $this->assertSame('Pending', $approval->fresh()->status);
        $this->assertSame('Pending', $approval->levels()->firstOrFail()->status);
        $this->assertDocumentStatus($document, 'Pending', 'Pending');
        $this->postJson("/api/approvals/{$approval->id}/approve")->assertUnprocessable()->assertJsonValidationErrors('approval');
        $this->assertSame(1, $approval->actions()->where('action', 'Approved')->count());
        $this->assertSame('Pending', $approval->fresh()->status);

        Sanctum::actingAs($second);
        $this->postJson("/api/approvals/{$approval->id}/approve")->assertOk();
        $this->assertSame('Approved', $approval->fresh()->status);
        $this->assertDocumentStatus($document, 'Approved', 'Approved');
        $this->assertEqualsCanonicalizing([$first->id, $second->id], $approval->actions()->where('action', 'Approved')->pluck('action_by')->all());
    }

    public function test_valid_active_delegation_allows_the_delegate_and_records_the_actual_actor(): void
    {
        $original = $this->user('original');
        $delegate = $this->user('delegate');
        $this->workflow([['user_id' => $original->id]]);
        ApprovalDelegation::create([
            'code' => 'DELEGATION-VALID', 'from_user_id' => $original->id, 'to_user_id' => $delegate->id,
            'start_at' => now()->subHour(), 'end_at' => now()->addDay(), 'status' => 'Active', 'created_by' => $original->id,
        ]);
        [$document, $approval] = $this->submit();
        Sanctum::actingAs($delegate);

        $this->postJson("/api/approvals/{$approval->id}/approve", ['remarks' => 'Approved as delegate'])->assertOk();
        $this->assertSame('Approved', $approval->fresh()->status);
        $this->assertSame($original->id, $approval->levels()->firstOrFail()->user_id);
        $this->assertSame($delegate->id, $approval->levels()->firstOrFail()->action_by);
        $this->assertDocumentStatus($document, 'Approved', 'Approved');
        $this->assertDatabaseHas('approval_actions', ['approval_request_id' => $approval->id, 'action' => 'Approved', 'action_by' => $delegate->id]);
    }

    public function test_future_expired_and_cancelled_delegations_cannot_approve(): void
    {
        $original = $this->user('original');
        $delegate = $this->user('delegate');
        $this->workflow([['user_id' => $original->id]]);
        $delegation = ApprovalDelegation::create([
            'code' => 'DELEGATION-INVALID', 'from_user_id' => $original->id, 'to_user_id' => $delegate->id,
            'start_at' => now()->addDay(), 'end_at' => now()->addDays(2), 'status' => 'Active', 'created_by' => $original->id,
        ]);
        [$document, $approval] = $this->submit();
        Sanctum::actingAs($delegate);
        $this->postJson("/api/approvals/{$approval->id}/approve")->assertUnprocessable();
        $delegation->update(['start_at' => now()->subDays(2), 'end_at' => now()->subDay()]);
        $this->postJson("/api/approvals/{$approval->id}/approve")->assertUnprocessable();
        $delegation->update(['start_at' => now()->subDay(), 'end_at' => now()->addDay(), 'status' => 'Cancelled']);
        $this->postJson("/api/approvals/{$approval->id}/approve")->assertUnprocessable();

        $this->assertSame('Pending', $approval->fresh()->status);
        $this->assertDocumentStatus($document, 'Pending', 'Pending');
        $this->assertSame(0, $approval->actions()->where('action', 'Approved')->count());
    }

    public function test_submitting_an_already_pending_document_reuses_its_request(): void
    {
        $reviewer = $this->user('reviewer');
        $this->workflow([['user_id' => $reviewer->id]]);
        [$document, $approval] = $this->submit();

        $again = app(ApprovalService::class)->submit($document);
        $this->assertSame($approval->id, $again->id);
        $this->assertDatabaseCount('approval_requests', 1);
        $this->assertDatabaseCount('approval_request_levels', 1);
        $this->assertDatabaseCount('approval_actions', 1);
    }

    public function test_gate_pass_creation_uses_its_canonical_workflow_despite_spoofed_navigation_headers(): void
    {
        $correctApprover = $this->user('correct');
        $wrongApprover = $this->user('wrong');
        $canonical = $this->workflow([['user_id' => $correctApprover->id]]);
        $wrongLocation = app(AccessService::class)->context(['hrm', 'configuration', 'department-list']);
        $decoy = $this->workflow([['user_id' => $wrongApprover->id]], $wrongLocation, 'DECOY');

        [$document, $approval] = $this->submit([
            'X-Module-Id' => (string) $wrongLocation['module_id'],
            'X-Menu-Id' => (string) $wrongLocation['menu_id'],
            'X-Child-Menu-Id' => (string) $wrongLocation['child_menu_id'],
        ]);

        $this->assertSame($canonical->id, $approval->workflow_id);
        $this->assertNotSame($decoy->id, $approval->workflow_id);
        $this->assertSame($correctApprover->id, $approval->levels()->firstOrFail()->user_id);
        $this->assertSame(GatePass::class, $approval->document_type);
        $this->assertSame($document->id, $approval->document_id);
        $this->assertSame($document->code, $approval->document_no);
        $this->assertDocumentStatus($document, 'Pending', 'Pending');
        $this->assertDatabaseCount('approval_requests', 1);
    }

    private function user(string $name): User
    {
        return User::create([
            'code' => 'APPROVAL-'.$name, 'name' => ucfirst($name), 'email' => $name.'@approval-state.test',
            'password' => bcrypt('Test-password-123'), 'status' => 'Active',
        ]);
    }

    private function workflow(array $levels, ?array $location = null, string $code = 'MAIN'): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::create([
            ...($location ?? $this->location), 'code' => 'WORKFLOW-'.$code, 'name' => $code.' approval',
            'status' => 'Active', 'created_by' => $this->requester->id,
        ]);
        foreach ($levels as $index => $level) {
            $number = $level['level_no'] ?? ($index + 1);
            $workflow->levels()->create($level + [
                'code' => 'WORKFLOW-'.$code.'-LEVEL-'.$number, 'level_no' => $number, 'name' => 'Review '.$number,
                'approver_type' => 'User', 'min_approvers' => 1, 'is_required' => true,
                'can_reject' => true, 'can_return' => true, 'status' => 'Active',
            ]);
        }
        return $workflow;
    }

    private function submit(array $headers = []): array
    {
        Sanctum::actingAs($this->requester);
        $response = $this->postJson('/api/reception/gate-passes', [
            'gate_pass_type' => 'Person', 'department_name' => 'Administration', 'designation_name' => 'Officer',
            'purpose_id' => $this->purpose->id, 'expected_exit_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'expected_return_at' => now()->addHours(2)->format('Y-m-d H:i:s'), 'remarks' => 'Office visit',
        ], $headers)->assertCreated();
        $document = GatePass::findOrFail($response->json('data.id'));
        return [$document, ApprovalRequest::where('document_type', GatePass::class)->where('document_id', $document->id)->first()];
    }

    private function assertDocumentStatus(GatePass $document, string $status, string $approvalStatus): void
    {
        $this->assertDatabaseHas('gate_passes', ['id' => $document->id, 'status' => $status, 'approval_status' => $approvalStatus]);
    }
}
