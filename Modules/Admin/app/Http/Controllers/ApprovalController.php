<?php

namespace Modules\Admin\App\Http\Controllers;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Approval\Models\ApprovalRequest;
use Modules\Approval\Services\ApprovalService;

class ApprovalController extends Controller
{
    public function index(Request $request)
    {
        $approvals = ApprovalRequest::query()
            ->with([
                'workflow',
                'requester',
                'levels',
            ])
            ->where('status', 'Pending')
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => 'Success',
            'message' => 'Approval Requests Retrieved Successfully.',
            'data' => $approvals,
        ]);
    }

    public function show(ApprovalRequest $approvalRequest)
    {
        $approvalRequest->load([
            'workflow',
            'requester',
            'levels',
            'actions.user',
        ]);

        return response()->json([
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
            'status' => 'Success',
            'message' => 'Approval Request Returned Successfully.',
        ]);
    }
}