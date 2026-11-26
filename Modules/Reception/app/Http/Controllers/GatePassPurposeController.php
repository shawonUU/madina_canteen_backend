<?php

namespace Modules\Reception\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Reception\Models\GatePassPurpose;

class GatePassPurposeController extends Controller
{
    public function index()
    {
        $purposes = GatePassPurpose::latest()->paginate(15);

        return response()->json($purposes);
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

        $purpose->delete();

        return response()->json([
            'message' => 'Gate pass purpose deleted successfully.',
        ]);
    }
}
