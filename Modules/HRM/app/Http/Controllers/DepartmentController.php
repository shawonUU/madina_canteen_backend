<?php

namespace Modules\HRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\HRM\Models\Department;

class DepartmentController extends Controller
{
    public function index(Request $request)
    {
        $allowedSorts = [
            'id',
            'code',
            'name',
            'status',
            'created_at',
        ];

        $sortBy = $request->input('sort_by', 'created_at');

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $sortDirection = $request->input('sort_direction', 'desc');
        $sortDirection = $sortDirection === 'asc' ? 'asc' : 'desc';

        $query = Department::query();

        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $status = $request->input('filter.status');

        if (in_array($status, ['Active', 'Inactive'], true)) {
            $query->where('status', $status);
        }

        $perPage = min(
            max((int) $request->input('per_page', 15), 1),
            100
        );

        $departments = $query
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Departments retrieved successfully.',
            'data' => $departments,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            // 'code' => ['nullable', 'string', 'max:50', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:150', 'unique:departments,name'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['Active', 'Inactive'])],
        ]);

        $validated['code'] = getGenerateCode(Department::class, 'code', 'DEP');

        $department = Department::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Department created successfully.',
            'data' => $department,
        ], 201);
    }

    public function show(Department $department)
    {
        $department->load('designations');

        return response()->json([
            'success' => true,
            'message' => 'Department retrieved successfully.',
            'data' => $department,
        ]);
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('departments', 'name')->ignore($department->id),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['Active', 'Inactive'])],
        ]);

        $department->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Department updated successfully.',
            'data' => $department->fresh()->loadCount('designations'),
        ]);
    }

    public function destroy(Department $department)
    {
        if ($department->designations()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This department has designations. Delete or reassign them before deleting the department.',
            ], 422);
        }

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully.',
        ]);
    }
}