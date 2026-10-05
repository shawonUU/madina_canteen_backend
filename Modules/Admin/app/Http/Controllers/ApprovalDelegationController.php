<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Approval\Models\ApprovalDelegation;

class ApprovalDelegationController extends Controller
{
    public function index()
    {
        $delegations = ApprovalDelegation::with([
            'fromUser',
            'toUser',
            'creator',
        ])
            ->latest()
            ->paginate(20);

        return response()->json([
            'status' => 'Success',
            'message' => 'Approval Delegations Retrieved Successfully.',
            'data' => $delegations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'from_user_id' => [
                'required',
                'exists:users,id',
            ],

            'to_user_id' => [
                'required',
                'exists:users,id',
                'different:from_user_id',
            ],

            'start_at' => [
                'required',
                'date',
            ],

            'end_at' => [
                'required',
                'date',
                'after:start_at',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $delegation = ApprovalDelegation::create([
            'code' => getGenerateCode(ApprovalDelegation::class, 'code', 'AD', 8),
            'from_user_id' => $validated['from_user_id'],
            'to_user_id' => $validated['to_user_id'],
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'reason' => $validated['reason'] ?? null,
            'status' => 'Active',
            'created_by' => auth()->id(),
        ]);

        return response()->json([
            'status' => 'Success',
            'message' => 'Approval Delegation Created Successfully.',
            'data' => $delegation,
        ], 201);
    }

    public function update(
        Request $request,
        ApprovalDelegation $approvalDelegation
    ) {
        $validated = $request->validate([
            'start_at' => [
                'required',
                'date',
            ],

            'end_at' => [
                'required',
                'date',
                'after:start_at',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'status' => [
                'required',
                'in:Active,Inactive,Cancelled',
            ],
        ]);

        $approvalDelegation->update($validated);

        return response()->json([
            'status' => 'Success',
            'message' => 'Approval Delegation Updated Successfully.',
            'data' => $approvalDelegation,
        ]);
    }

    public function destroy(
        ApprovalDelegation $approvalDelegation
    ) {
        $approvalDelegation->update([
            'status' => 'Cancelled',
        ]);

        return response()->json([
            'status' => 'Success',
            'message' => 'Approval Delegation Cancelled Successfully.',
        ]);
    }
}
