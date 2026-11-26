<?php

namespace Modules\Approval\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Http\Request;
use Modules\Approval\Models\ApprovalDelegation;

class ApprovalDelegationController extends Controller
{
    public function index(Request $request)
    {
        $query = ApprovalDelegation::with(['fromUser:id,name,email', 'toUser:id,name,email', 'creator:id,name'])->latest();
        foreach (['code', 'reason'] as $field) {
            if ($request->filled('filter.'.$field)) $query->where($field, 'like', '%'.trim((string) $request->input('filter.'.$field)).'%');
        }
        foreach (['from' => 'fromUser', 'to' => 'toUser'] as $filter => $relation) {
            if ($request->filled('filter.'.$filter)) $query->whereHas($relation, fn ($q) => $q->where('name', 'like', '%'.trim((string) $request->input('filter.'.$filter)).'%'));
        }
        foreach (['start' => 'start_at', 'end' => 'end_at'] as $filter => $field) {
            if ($request->filled('filter.'.$filter)) {
                $request->validate(['filter.'.$filter => 'date_format:Y-m-d']);
                $query->whereDate($field, $request->input('filter.'.$filter));
            }
        }
        if ($request->filled('filter.status')) {
            $request->validate(['filter.status' => 'in:Active,Inactive,Expired,Cancelled']);
            $query->where('status', $request->input('filter.status'));
        }
        $delegations = $request->boolean('all') ? $query->get() : $query->paginate(min(max((int) $request->input('per_page', 20), 1), 200))->withQueryString();

        return response()->json([
            'success' => true,
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
                'integer',
                'exists:users,id',
            ],

            'to_user_id' => [
                'required',
                'integer',
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
            'code' => getGenerateCode(ApprovalDelegation::class, 'code', 'ADL', 8),
            'from_user_id' => $validated['from_user_id'],
            'to_user_id' => $validated['to_user_id'],
            'start_at' => $this->normalizeDate($validated['start_at']),
            'end_at' => $this->normalizeDate($validated['end_at']),
            'reason' => $validated['reason'] ?? null,
            'status' => 'Active',
            'created_by' => auth()->id(),
        ])->load(['fromUser:id,name,email', 'toUser:id,name,email']);

        return response()->json([
            'success' => true,
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

        $validated['start_at'] = $this->normalizeDate($validated['start_at']);
        $validated['end_at'] = $this->normalizeDate($validated['end_at']);
        $approvalDelegation->update($validated);
        $approvalDelegation->load(['fromUser:id,name,email', 'toUser:id,name,email']);

        return response()->json([
            'success' => true,
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
            'success' => true,
            'status' => 'Success',
            'message' => 'Approval Delegation Cancelled Successfully.',
        ]);
    }

    private function normalizeDate(string $value): Carbon
    {
        $timezone = config('app.timezone', 'UTC');
        return Carbon::parse($value, $timezone)->setTimezone($timezone);
    }
}
