<?php

namespace Modules\HRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\HRM\Models\Department;
use Modules\HRM\Models\Designation;

class DesignationController extends Controller
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

        $query = Designation::query();

        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $filters = $request->input('filter', []);


        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['name'])) {
            $query->where('name', 'like', '%' . trim($filters['name']) . '%');
        }

        if (!empty($filters['code'])) {
            $query->where('code', 'like', '%' . trim($filters['code']) . '%');
        }

        $perPage = min(
            max((int) $request->input('per_page', 15), 1),
            100
        );

        $designations = $query
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Designations retrieved successfully.',
            'data' => $designations,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('designations', 'name'),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['Active', 'Inactive'])],
        ]);

        $validated['code'] = getGenerateCode(Designation::class, 'code', 'DEG');
        $designation = Designation::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Designation created successfully.',
            'data' => $designation,
        ], 201);
    }

    public function show(Designation $designation)
    {

        return response()->json([
            'success' => true,
            'message' => 'Designation retrieved successfully.',
            'data' => $designation,
        ]);
    }

    public function update(Request $request, Designation $designation)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('designations', 'name')
                    ->ignore($designation->id),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', Rule::in(['Active', 'Inactive'])],
        ]);

        $designation->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Designation updated successfully.',
            'data' => $designation,
        ]);
    }

    public function destroy(Designation $designation)
    {
        $designation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Designation deleted successfully.',
        ]);
    }

    public function departments()
    {
        $departments = Department::query()
            ->where('status', 'Active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'status']);

        return response()->json([
            'success' => true,
            'message' => 'Departments retrieved successfully.',
            'data' => $departments,
        ]);
    }
}