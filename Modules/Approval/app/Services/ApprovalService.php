<?php

namespace Modules\Approval\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Approval\Models\ApprovalAction;
use Modules\Approval\Models\ApprovalDelegation;
use Modules\Approval\Models\ApprovalRequest;
use Modules\Approval\Models\ApprovalRequestLevel;
use Modules\Approval\Models\ApprovalWorkflow;
use Modules\Approval\Models\User;

class ApprovalService
{

    public function submit($document)
    {
        return DB::transaction(function () use ($document) {
            $documentType = get_class($document);
            $documentId = $document->id;
            $documentNo = $document->id;
            $userId = auth()->id();

            $workflow = approvalWorkflow();

            if ($workflow) {
                $workflow->load('levels');

                if ($workflow->levels->isEmpty()) {
                    throw ValidationException::withMessages([
                        'workflow' => 'No Approval Level Found.',
                    ]);
                }

                $approvalRequest = ApprovalRequest::create([
                    'code' => getGenerateCode(ApprovalRequestLevel::class, 'code', 'AR', 8),
                    'workflow_id' => $workflow->id,
                    'document_type' => $documentType,
                    'document_id' => $documentId,
                    'document_no' => $documentNo,
                    'requested_by' => $userId,
                    'current_level' => 1,
                    'status' => 'Pending',
                    'submitted_at' => now(),
                ]);

                foreach ($workflow->levels as $workflowLevel) {
                    ApprovalRequestLevel::create([
                        'code' => getGenerateCode(ApprovalRequestLevel::class, 'code', 'ARL', 8),
                        'approval_request_id' => $approvalRequest->id,
                        'level_no' => $workflowLevel->level_no,
                        'level_name' => $workflowLevel->name,
                        'approver_type' => $workflowLevel->approver_type,
                        'role_id' => $workflowLevel->role_id,
                        'user_id' => $workflowLevel->user_id,
                        'department_id' => $workflowLevel->department_id,
                        'min_approvers' => $workflowLevel->min_approvers,
                        'status' => $workflowLevel->level_no === 1
                            ? 'Pending'
                            : 'Waiting',
                    ]);
                }

                ApprovalAction::create([
                    'code' => getGenerateCode(ApprovalAction::class, 'code', 'AA', 8),
                    'approval_request_id' => $approvalRequest->id,
                    'approval_request_level_id' => null,
                    'action' => 'Submitted',
                    'action_by' => $userId,
                    'remarks' => 'Submitted For Approval.',
                ]);

                return $approvalRequest;
            }else{
                $document->update(['status' => 'Approved',]);
                return null;
            }

        });
    }

    public function approve(
        ApprovalRequest $approvalRequest,
        int $userId,
        ?string $remarks = null
    ): ApprovalRequest {
        return DB::transaction(function () use (
            $approvalRequest,
            $userId,
            $remarks
        ) {
            $this->ensurePending($approvalRequest);

            $level = $this->getCurrentLevel($approvalRequest);

            $this->ensureCanApprove($level, $userId);

            $level->update([
                'status' => 'Approved',
                'action_by' => $userId,
                'action_at' => now(),
                'remarks' => $remarks,
            ]);

            ApprovalAction::create([
                'code' => getGenerateCode(ApprovalAction::class, 'code', 'AA', 8),
                'approval_request_id' => $approvalRequest->id,
                'approval_request_level_id' => $level->id,
                'action' => 'Approved',
                'action_by' => $userId,
                'remarks' => $remarks,
            ]);

            $nextLevel = $approvalRequest
                ->levels()
                ->where('level_no', '>', $level->level_no)
                ->where('status', 'Waiting')
                ->orderBy('level_no')
                ->first();

            if (!$nextLevel) {
                $approvalRequest->update([
                    'status' => 'Approved',
                    'completed_at' => now(),
                ]);

                return $approvalRequest->fresh();
            }

            $approvalRequest->update([
                'current_level' => $nextLevel->level_no,
            ]);

            $nextLevel->update([
                'status' => 'Pending',
            ]);

            return $approvalRequest->fresh();
        });
    }

    public function reject(
        ApprovalRequest $approvalRequest,
        int $userId,
        string $remarks
    ): ApprovalRequest {
        return DB::transaction(function () use (
            $approvalRequest,
            $userId,
            $remarks
        ) {
            $this->ensurePending($approvalRequest);

            $level = $this->getCurrentLevel($approvalRequest);

            $this->ensureCanApprove($level, $userId);

            $level->update([
                'status' => 'Rejected',
                'action_by' => $userId,
                'action_at' => now(),
                'remarks' => $remarks,
            ]);

            $approvalRequest->update([
                'status' => 'Rejected',
                'completed_at' => now(),
            ]);

            ApprovalAction::create([
                'code' => getGenerateCode(ApprovalAction::class, 'code', 'AA', 8),
                'approval_request_id' => $approvalRequest->id,
                'approval_request_level_id' => $level->id,
                'action' => 'Rejected',
                'action_by' => $userId,
                'remarks' => $remarks,
            ]);

            return $approvalRequest->fresh();
        });
    }

    public function return(
        ApprovalRequest $approvalRequest,
        int $userId,
        string $remarks
    ): ApprovalRequest {
        return DB::transaction(function () use (
            $approvalRequest,
            $userId,
            $remarks
        ) {
            $this->ensurePending($approvalRequest);

            $level = $this->getCurrentLevel($approvalRequest);

            $this->ensureCanApprove($level, $userId);

            $level->update([
                'status' => 'Returned',
                'action_by' => $userId,
                'action_at' => now(),
                'remarks' => $remarks,
            ]);

            $approvalRequest->update([
                'status' => 'Returned',
            ]);

            ApprovalAction::create([
                'code' => getGenerateCode(ApprovalAction::class, 'code', 'AA', 8),
                'approval_request_id' => $approvalRequest->id,
                'approval_request_level_id' => $level->id,
                'action' => 'Returned',
                'action_by' => $userId,
                'remarks' => $remarks,
            ]);

            return $approvalRequest->fresh();
        });
    }

    private function ensurePending(
        ApprovalRequest $approvalRequest
    ): void {
        if ($approvalRequest->status !== 'Pending') {
            throw ValidationException::withMessages([
                'approval' => 'This Approval Request Is Not Pending.',
            ]);
        }
    }

    private function getCurrentLevel(
        ApprovalRequest $approvalRequest
    ): ApprovalRequestLevel {
        $level = $approvalRequest
            ->levels()
            ->where('level_no', $approvalRequest->current_level)
            ->where('status', 'Pending')
            ->first();

        if (!$level) {
            throw ValidationException::withMessages([
                'approval' => 'Current Approval Level Not Found.',
            ]);
        }

        return $level;
    }

    private function ensureCanApprove(
        ApprovalRequestLevel $level,
        int $userId
    ): void {
        $isAllowed = false;

        if (
            $level->approver_type === 'User' &&
            $level->user_id === $userId
        ) {
            $isAllowed = true;
        }

        if (
            $level->approver_type === 'Role'
        ) {
            $user = User::find($userId);

            if (
                $user &&
                $level->role_id &&
                $user->hasRole(
                    $level->role_id
                )
            ) {
                $isAllowed = true;
            }
        }

        if (!$isAllowed) {
            $delegated = ApprovalDelegation::query()
                ->where('from_user_id', $level->user_id)
                ->where('to_user_id', $userId)
                ->where('status', 'Active')
                ->where('start_at', '<=', now())
                ->where('end_at', '>=', now())
                ->exists();

            if ($delegated) {
                $isAllowed = true;
            }
        }

        if (!$isAllowed) {
            throw ValidationException::withMessages([
                'approval' => 'You Are Not Authorized To Approve This Request.',
            ]);
        }
    }
}