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
            'employees_count',
        ];

        $sortBy = $request->input('sort_by', 'created_at');

        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $sortDirection = $request->input('sort_direction', 'desc');
        $sortDirection = $sortDirection === 'asc' ? 'asc' : 'desc';

        $query = Department::query()->withCount('employees');

        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        foreach (['code', 'name', 'description'] as $column) {
            $value = trim((string) $request->input('filter.' . $column, ''));
            if ($value !== '') {
                $query->where($column, 'like', '%' . $value . '%');
            }
        }
        if ($request->filled('filter.employees_count')) {
            $request->validate(['filter.employees_count' => 'integer|min:0']);
            $query->has('employees', '=', (int) $request->input('filter.employees_count'));
        }
        if ($request->boolean('all')) {
            return response()->json(['success' => true, 'data' => $query->orderBy('name')->get()]);
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
        $department->loadCount('employees');

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
            'data' => $department->fresh()->loadCount('employees'),
        ]);
    }

    public function destroy(Department $department)
    {
        if ($department->employees()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This department has employees. Reassign them before deleting the department.',
            ], 422);
        }

        $department->delete();

        return response()->json([
            'success' => true,
            'message' => 'Department deleted successfully.',
        ]);
    }
}