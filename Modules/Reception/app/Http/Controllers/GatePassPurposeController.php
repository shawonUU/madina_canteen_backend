<?php

namespace Modules\Reception\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Reception\Models\GatePassPurpose;

class GatePassPurposeController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
            'search_code' => ['nullable', 'string', 'max:100'],
            'search_name' => ['nullable', 'string', 'max:255'],
            'approval_status' => ['nullable', 'in:Pending,Approved,Rejected,Cancelled,Completed'],
            'is_active' => ['nullable', 'boolean'],
            'created_at' => ['nullable', 'string', 'max:30'],
            'sort_field' => ['nullable', 'in:code,name,approval_status,is_active,created_at'],
            'sort_direction' => ['nullable', 'in:asc,desc'],
        ]);
        $query = GatePassPurpose::query();
        foreach (['search_code' => 'code', 'search_name' => 'name', 'created_at' => 'created_at'] as $param => $column) {
            if ($request->filled($param)) $query->where($column, 'like', '%' . $data[$param] . '%');
        }
        foreach (['approval_status', 'is_active'] as $column) {
            if ($request->filled($column)) $query->where($column, $data[$column]);
        }
        $purposes = $query->orderBy($data['sort_field'] ?? 'id', $data['sort_direction'] ?? 'desc')
            ->paginate($data['per_page'] ?? 15);
        return response()->json(['success' => true, 'data' => $purposes]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'is_active' => 'nullable|boolean',
            'approval_status' => 'nullable|in:Pending,Approved,Rejected,Cancelled,Completed',
        ]);

        $purpose = GatePassPurpose::create([
            'code' => getGenerateCode(GatePassPurpose::class, 'code', 'GPP'),
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
            'approval_status' => $validated['approval_status'] ?? 'Pending',
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Gate pass purpose created successfully.',
            'data' => $purpose,
        ], 201);
    }

    public function show($id)
    {
        $purpose = GatePassPurpose::findOrFail($id);

        return response()->json([
            'data' => $purpose,
        ]);
    }

    public function update(Request $request, $id)
    {
        $purpose = GatePassPurpose::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'is_active' => 'sometimes|boolean',
            'approval_status' => 'sometimes|in:Pending,Approved,Rejected,Cancelled,Completed',
        ]);

        $purpose->update([
            ...$validated,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'message' => 'Gate pass purpose updated successfully.',
            'data' => $purpose->fresh(),
        ]);
    }

    public function destroy($id)
    {
        $purpose = GatePassPurpose::findOrFail($id);

        if (\Modules\Reception\Models\GatePass::where('purpose_id', $purpose->id)->exists()) {
            return response()->json(['message' => 'This purpose is used by gate passes. Set it inactive instead.'], 422);
        }
        $purpose->delete();

        return response()->json([
            'message' => 'Gate pass purpose deleted successfully.',
        ]);
    }
}
