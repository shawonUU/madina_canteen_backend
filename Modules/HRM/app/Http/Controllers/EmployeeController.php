<?php

namespace Modules\HRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Modules\Admin\Models\User;
use Modules\HRM\Models\Employee;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $allowedSortColumns = [
            'id',
            'code',
            'name',
            'department',
            'designation',
            'phone',
            'email',
            'status',
            'created_at',
        ];

        $sortBy = $request->input('sort_by', 'created_at');

        if (!in_array($sortBy, $allowedSortColumns, true)) {
            $sortBy = 'created_at';
        }

        $sortDirection = strtolower($request->input('sort_direction', 'desc'));
        $sortDirection = $sortDirection === 'asc' ? 'asc' : 'desc';

        $query = Employee::with([
            // 'user.roles',
            'departmentRelation:id,name',
            'designationRelation:id,name',
        ]);

        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('departmentRelation', function (Builder $departmentQuery) use ($search) {
                        $departmentQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('designationRelation', function (Builder $designationQuery) use ($search) {
                        $designationQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        $filters = $request->input('filter', []);

        foreach (['code', 'name', 'phone', 'email'] as $column) {
            $value = trim((string) ($filters[$column] ?? ''));

            if ($value !== '') {
                $query->where($column, 'like', "%{$value}%");
            }
        }

        foreach (['department', 'designation'] as $relation) {
            $value = trim((string) ($filters[$relation] ?? ''));

            if ($value !== '') {
                $relationName = $relation . 'Relation';

                $query->whereHas($relationName, function (Builder $relationQuery) use ($value) {
                    $relationQuery->where('name', 'like', "%{$value}%");
                });
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } elseif (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if ($request->filled('department')) {
            $query->where('department', $request->input('department'));
        }

        if ($request->filled('designation')) {
            $query->where('designation', $request->input('designation'));
        }

        $systemAccess = $request->input('system_access', $filters['system_access'] ?? null);

        if ($systemAccess === 'Enabled') {
            $query->whereHas('user');
        } elseif ($systemAccess === 'No Login') {
            $query->whereDoesntHave('user');
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        $employees = $query
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'success' => true,
            'message' => 'Employees retrieved successfully.',
            'data' => $employees,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'integer', 'exists:departments,id'],
            'designation' => [
                'nullable',
                'integer',
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => [
                'required_if:create_user,true',
                'nullable',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'create_user' => ['nullable', 'boolean'],
            // 'role_id' => ['required_if:create_user,true', 'nullable', 'integer', 'exists:roles,id'],
            'user_password' => ['required_if:create_user,true', 'nullable', 'string', 'min:6'],
        ]);

        $employee = DB::transaction(function () use ($validated) {
            $employee = Employee::create([
                'code' => getGenerateCode(Employee::class, 'code', 'EMP', 8),
                'name' => $validated['name'],
                'department' => $validated['department'] ?? null,
                'designation' => $validated['designation'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'status' => $validated['status'] ?? 'Active',
            ]);

            if (!empty($validated['create_user'])) {
                $user = User::create([
                    'code' => getGenerateCode(User::class, 'code', 'USR', 8),
                    'employee_id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'password' => Hash::make($validated['user_password']),
                    'status' => 'Active',
                ]);

                // $role = Role::findById($validated['role_id']);
                // $user->syncRoles([$role]);
            }

            return $employee;
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully.',
            'data' => $employee->fresh()->load([
                'user',
                'departmentRelation:id,name',
                'designationRelation:id,name',
            ]),
        ], 201);
    }

    public function show($id)
    {
        $employee = Employee::with([
            'user',
            'departmentRelation:id,name',
            'designationRelation:id,name',
        ])->find($id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee retrieved successfully.',
            'data' => $employee,
        ]);
    }

    public function update(Request $request, $id)
    {
        $employee = Employee::with('user')->find($id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'integer', 'exists:departments,id'],
            'designation' => [
                'nullable',
                'integer',
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => [
                'required_if:create_user,true',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($employee->user?->id),
            ],
            'status' => ['nullable', Rule::in(['Active', 'Inactive'])],
            'create_user' => ['nullable', 'boolean'],
            'user_password' => ['nullable', 'string', 'min:6'],
        ]);

        DB::transaction(function () use ($validated, $employee) {
            $employee->update([
                'name' => $validated['name'],
                'department' => $validated['department'] ?? null,
                'designation' => $validated['designation'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'status' => $validated['status'] ?? 'Active',
            ]);

            if (!empty($validated['create_user'])) {
                if ($employee->user) {
                    $userData = [
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'status' => 'Active',
                    ];

                    if (!empty($validated['user_password'])) {
                        $userData['password'] = Hash::make($validated['user_password']);
                    }

                    $employee->user->update($userData);
                    $user = $employee->user;
                } else {
                    $user = User::create([
                        'code' => getGenerateCode(User::class, 'code', 'USR', 8),
                        'employee_id' => $employee->id,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'password' => Hash::make($validated['user_password']),
                        'status' => 'Active',
                    ]);
                }

                // $role = Role::findById($validated['role_id']);
                // $user->syncRoles([$role]);
            } elseif ($employee->user) {
                $employee->user->update(['status' => 'Inactive']);
                // $employee->user->syncRoles([]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully.',
            'data' => $employee->fresh()->load([
                'user',
                'departmentRelation:id,name',
                'designationRelation:id,name',
            ]),
        ]);
    }

    public function destroy($id)
    {
        $employee = Employee::with('user')->find($id);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found.',
            ], 404);
        }

        DB::transaction(function () use ($employee) {
            if ($employee->user) {
                $employee->user->delete();
            }

            $employee->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee deleted successfully.',
        ]);
    }
}
