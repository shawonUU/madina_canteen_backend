<?php

namespace Modules\Approval\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Approval\Models\ApprovalRequest;
use Modules\Approval\Services\ApprovalService;

class ApprovalController extends Controller
{
    public function index(Request $request, ApprovalService $approvalService)
    {
        $user = $request->user();

        $query = ApprovalRequest::query()
            ->with(['workflow', 'requester:id,name,email', 'levels'])
            ->withCount('levels as total_levels')
            ->where('status', 'Pending')
            ->whereHas('levels', function ($query) use ($user, $approvalService) {
                $approvalService->approverScope($query, $user)
                    ->where('approval_request_levels.status', 'Pending')
                    ->whereColumn('approval_request_levels.level_no', 'approval_requests.current_level');
            })
            ->latest();

        $document = trim((string) $request->input('filter.document', $request->input('search', '')));
        if ($document !== '') $query->where(fn ($q) => $q->where('document_type', 'like', '%'.$document.'%')->orWhere('document_no', 'like', '%'.$document.'%'));
        if ($request->filled('filter.requester')) $query->whereHas('requester', fn ($q) => $q->where('name', 'like', '%'.trim((string) $request->input('filter.requester')).'%'));
        if ($request->filled('filter.workflow')) $query->whereHas('workflow', fn ($q) => $q->where('name', 'like', '%'.trim((string) $request->input('filter.workflow')).'%'));
        if ($request->filled('filter.current_level')) {
            $request->validate(['filter.current_level' => 'integer|min:1']);
            $query->where('current_level', $request->input('filter.current_level'));
        }
        if ($request->filled('filter.submitted')) {
            $request->validate(['filter.submitted' => 'date_format:Y-m-d']);
            $query->whereDate('submitted_at', $request->input('filter.submitted'));
        }
        $approvals = $request->boolean('all') ? $query->get() : $query->paginate(min(max((int) $request->input('per_page', 20), 1), 200))->withQueryString();
        foreach ($approvals as $approval) $this->setCurrentLevel($approval);

        return response()->json([
            'success' => true,
            'status' => 'Success',
            'message' => 'Approval Requests Retrieved Successfully.',
            'data' => $approvals,
        ]);
    }

    public function show(Request $request, ApprovalRequest $approvalRequest, ApprovalService $approvalService)
    {
        abort_unless($approvalService->canView($approvalRequest, $request->user()), 403, 'You do not have access to this approval request.');
        $approvalRequest->load([
            'workflow',
            'requester',
            'levels',
            'actions.user',
        ])->loadCount('levels as total_levels');
        $this->setCurrentLevel($approvalRequest);

        return response()->json([
            'success' => true,
            'status' => 'Success',
            'message' => 'Approval Request Retrieved Successfully.',
            'data' => $approvalRequest,
        ]);
    }

    public function approve(
        Request $request,
        ApprovalRequest $approvalRequest,
        ApprovalService $approvalService
    ) {
        $request->validate([
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $approvalService->approve(
            $approvalRequest,
            auth()->id(),
            $request->remarks
        );

        return response()->json([
            'success' => true,
            'status' => 'Success',
            'message' => 'Approval Request Approved Successfully.',
        ]);
    }

    public function reject(
        Request $request,
        ApprovalRequest $approvalRequest,
        ApprovalService $approvalService
    ) {
        $request->validate([
            'remarks' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        $approvalService->reject(
            $approvalRequest,
            auth()->id(),
            $request->remarks
        );

        return response()->json([
            'success' => true,
            'status' => 'Success',
            'message' => 'Approval Request Rejected Successfully.',
        ]);
    }

    public function return(
        Request $request,
        ApprovalRequest $approvalRequest,
        ApprovalService $approvalService
    ) {
        $request->validate([
            'remarks' => [
                'required',
                'string',
                'max:2000',
            ],
        ]);

        $approvalService->return(
            $approvalRequest,
            auth()->id(),
            $request->remarks
        );

        return response()->json([
            'success' => true,
            'status' => 'Success',
            'message' => 'Approval Request Returned Successfully.',
        ]);
    }

    private function setCurrentLevel(ApprovalRequest $approvalRequest): void
    {
        $level = $approvalRequest->levels->firstWhere('level_no', $approvalRequest->current_level);
        $approvalRequest->setAttribute('current_level_data', $level ? [...$level->toArray(), 'name' => $level->level_name] : null);
    }
}
