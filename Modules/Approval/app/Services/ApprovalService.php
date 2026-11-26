<?php

namespace Modules\Approval\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Models\User;
use Modules\Approval\Models\ApprovalAction;
use Modules\Approval\Models\ApprovalDelegation;
use Modules\Approval\Models\ApprovalRequest;
use Modules\Approval\Models\ApprovalRequestLevel;

class ApprovalService
{
    public function submit(Model $document)
    {
        return DB::transaction(function () use ($document) {
            $document->newQuery()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            $existing = ApprovalRequest::where('document_type', get_class($document))->where('document_id', $document->id)->where('status', 'Pending')->first();
            if ($existing) return $existing;
            $workflow = approvalWorkflow();
            if (!$workflow) { $this->syncDocument($document, 'Approved'); return null; }
            $workflow->load('levels');
            if ($workflow->levels->isEmpty()) throw ValidationException::withMessages(['workflow' => 'No approval level found.']);
            $first = (int) $workflow->levels->first()->level_no;
            $request = ApprovalRequest::create([
                'code' => getGenerateCode(ApprovalRequest::class, 'code', 'AR'), 'workflow_id' => $workflow->id,
                'document_type' => get_class($document), 'document_id' => $document->id,
                'document_no' => $document->code ?? $document->id, 'requested_by' => auth()->id(),
                'current_level' => $first, 'status' => 'Pending', 'submitted_at' => now(),
            ]);
            foreach ($workflow->levels as $level) ApprovalRequestLevel::create([
                'code' => getGenerateCode(ApprovalRequestLevel::class, 'code', 'ARL'), 'approval_request_id' => $request->id,
                'level_no' => $level->level_no, 'level_name' => $level->name, 'approver_type' => $level->approver_type,
                'role_id' => $level->role_id, 'user_id' => $level->user_id, 'department_id' => $level->department_id,
                'min_approvers' => $level->min_approvers, 'can_reject' => $level->can_reject, 'can_return' => $level->can_return,
                'status' => (int) $level->level_no === $first ? 'Pending' : 'Waiting',
            ]);
            $this->action($request, null, 'Submitted', (int) auth()->id(), 'Submitted for approval.');
            $this->syncDocument($document, 'Pending');
            return $request;
        });
    }

    public function approve(ApprovalRequest $approvalRequest, int $userId, ?string $remarks = null): ApprovalRequest
    {
        return DB::transaction(function () use ($approvalRequest, $userId, $remarks) {
            [$request, $level] = $this->lockCurrent($approvalRequest, $userId);
            if (ApprovalAction::where('approval_request_level_id', $level->id)->where('action', 'Approved')->where('action_by', $userId)->exists()) {
                throw ValidationException::withMessages(['approval' => 'You have already approved this level.']);
            }
            $this->action($request, $level, 'Approved', $userId, $remarks);
            $count = ApprovalAction::where('approval_request_level_id', $level->id)->where('action', 'Approved')->distinct()->count('action_by');
            if ($count < max(1, (int) $level->min_approvers)) return $request->fresh();
            $level->update(['status' => 'Approved', 'action_by' => $userId, 'action_at' => now(), 'remarks' => $remarks]);
            $next = $request->levels()->where('level_no', '>', $level->level_no)->where('status', 'Waiting')->orderBy('level_no')->first();
            if ($next) {
                $request->update(['current_level' => $next->level_no]);
                $next->update(['status' => 'Pending']);
            } else {
                $request->update(['status' => 'Approved', 'completed_at' => now()]);
                $this->syncRequestDocument($request, 'Approved');
            }
            return $request->fresh();
        });
    }

    public function reject(ApprovalRequest $approvalRequest, int $userId, string $remarks): ApprovalRequest
    {
        return $this->finish($approvalRequest, $userId, $remarks, 'Rejected');
    }

    public function return(ApprovalRequest $approvalRequest, int $userId, string $remarks): ApprovalRequest
    {
        return $this->finish($approvalRequest, $userId, $remarks, 'Returned');
    }

    private function finish(ApprovalRequest $approvalRequest, int $userId, string $remarks, string $status): ApprovalRequest
    {
        return DB::transaction(function () use ($approvalRequest, $userId, $remarks, $status) {
            [$request, $level] = $this->lockCurrent($approvalRequest, $userId);
            $allowed = $status === 'Rejected' ? $level->can_reject : $level->can_return;
            if (!$allowed) throw ValidationException::withMessages(['approval' => 'This action is disabled for this approval level.']);
            $level->update(['status' => $status, 'action_by' => $userId, 'action_at' => now(), 'remarks' => $remarks]);
            $request->update(['status' => $status, 'completed_at' => $status === 'Rejected' ? now() : null]);
            $this->action($request, $level, $status, $userId, $remarks);
            $this->syncRequestDocument($request, $status);
            return $request->fresh();
        });
    }

    private function lockCurrent(ApprovalRequest $approvalRequest, int $userId): array
    {
        $request = ApprovalRequest::whereKey($approvalRequest->id)->lockForUpdate()->firstOrFail();
        if ($request->status !== 'Pending') throw ValidationException::withMessages(['approval' => 'This approval request is not pending.']);
        $level = $request->levels()->where('level_no', $request->current_level)->where('status', 'Pending')->lockForUpdate()->first();
        if (!$level) throw ValidationException::withMessages(['approval' => 'Current approval level not found.']);
        $user = User::findOrFail($userId);
        if (!$this->canAct($level, $user)) throw ValidationException::withMessages(['approval' => 'You are not authorized to approve this request.']);
        return [$request, $level];
    }

    public function canAct(ApprovalRequestLevel $level, User $user): bool
    {
        return $this->approverScope(ApprovalRequestLevel::query(), $user)->whereKey($level->id)->exists();
    }

    public function approverScope(Builder $query, User $user): Builder
    {
        $roles = $user->roles()->where('is_active', true)->pluck('roles.id');
        $department = $user->employee?->department;
        $delegated = ApprovalDelegation::where('to_user_id', $user->id)->where('status', 'Active')
            ->where('start_at', '<=', now())->where('end_at', '>=', now())->pluck('from_user_id');
        return $query->where(function ($q) use ($user, $roles, $department, $delegated) {
            $q->where(fn ($q) => $q->where('approver_type', 'User')->where('user_id', $user->id))
                ->orWhere(fn ($q) => $q->where('approver_type', 'Role')->whereIn('role_id', $roles));
            if ($department) $q->orWhere(fn ($q) => $q->where('approver_type', 'Department Role')->where('department_id', $department)->whereIn('role_id', $roles));
            if ($delegated->isNotEmpty()) $q->orWhere(fn ($q) => $q->where('approver_type', 'User')->whereIn('user_id', $delegated));
        });
    }

    public function canView(ApprovalRequest $request, User $user): bool
    {
        return (int) $request->requested_by === (int) $user->id || $user->can('approval.view-all')
            || $request->levels()->where(fn ($q) => $this->approverScope($q, $user))->exists()
            || $request->actions()->where('action_by', $user->id)->exists();
    }

    private function action(ApprovalRequest $request, ?ApprovalRequestLevel $level, string $action, int $userId, ?string $remarks): void
    {
        ApprovalAction::create([
            'code' => getGenerateCode(ApprovalAction::class, 'code', 'AA'), 'approval_request_id' => $request->id,
            'approval_request_level_id' => $level?->id, 'action' => $action, 'action_by' => $userId, 'remarks' => $remarks,
        ]);
    }

    private function syncRequestDocument(ApprovalRequest $request, string $status): void
    {
        $classes = array_column(config('audit.resources'), 0);
        if (!in_array($request->document_type, $classes, true)) throw ValidationException::withMessages(['document' => 'Unknown approval document type.']);
        $document = $request->document_type::whereKey($request->document_id)->lockForUpdate()->first();
        if (!$document) throw ValidationException::withMessages(['document' => 'The approval document no longer exists.']);
        $this->syncDocument($document, $status);
    }

    private function syncDocument(Model $document, string $status): void
    {
        $changes = [];
        if (Schema::hasColumn($document->getTable(), 'approval_status')) $changes['approval_status'] = $status;
        if (in_array($document->getTable(), ['gate_passes', 'meal_menus'], true)) $changes['status'] = $status === 'Returned' ? 'Pending' : $status;
        if ($changes) $document->update($changes);
    }
}
