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
            'user',
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

        foreach (['employee' => ['code', 'name'], 'contact' => ['phone', 'email']] as $key => $fields) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $query->where(function (Builder $q) use ($fields, $value) {
                    foreach ($fields as $field) $q->orWhere($field, 'like', '%' . $value . '%');
                });
            }
        }
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
            $query->whereHas('user', fn (Builder $q) => $q->where('status', 'Active'));
        } elseif ($systemAccess === 'Disabled') {
            $query->whereHas('user', fn (Builder $q) => $q->where('status', 'Inactive'));
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
                'exists:designations,id',
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
            'user_password' => ['required_if:create_user,true', 'nullable', 'string', 'min:8'],
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
                    'status' => $employee->status,
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
                'exists:designations,id',
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
            'user_password' => [Rule::requiredIf($request->boolean('create_user') && !$employee->user), 'nullable', 'string', 'min:8'],
        ]);

        if (!array_key_exists('create_user', $validated)) {
            $validated['create_user'] = $employee->user?->status === 'Active';
        }
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
                        'status' => $employee->status,
                    ];

                    if (!empty($validated['user_password'])) {
                        $userData['password'] = Hash::make($validated['user_password']);
                    }

                    $employee->user->update($userData);
                    if ($employee->user->status === 'Inactive' || isset($userData['password'])) $employee->user->tokens()->delete();
                    $user = $employee->user;
                } else {
                    $user = User::create([
                        'code' => getGenerateCode(User::class, 'code', 'USR', 8),
                        'employee_id' => $employee->id,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'password' => Hash::make($validated['user_password']),
                        'status' => $employee->status,
                    ]);
                }

                // $role = Role::findById($validated['role_id']);
                // $user->syncRoles([$role]);
            } elseif ($employee->user) {
                $employee->user->update(['status' => 'Inactive']);
                $employee->user->tokens()->delete();
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

        if ($employee->user && (int) $employee->user->id === (int) auth()->id()) {
            return response()->json(['success' => false, 'message' => 'You cannot delete your own employee account.'], 422);
        }
        if ($employee->user) {
            return response()->json(['success' => false, 'message' => 'This employee has a login account. Deactivate the employee or manage the linked account in Users first.'], 422);
        }
        $references = ['employee_meals' => 'employee_id', 'gate_passes' => 'employee_id', 'meeting_participants' => 'employee_id', 'meeting_attendances' => 'employee_id'];
        foreach ($references as $table => $column) {
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, $column) && DB::table($table)->where($column, $employee->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'This employee has transaction history. Mark the employee Inactive instead.'], 422);
            }
        }
        DB::transaction(function () use ($employee) {
            if ($employee->user) {
                $employee->user->tokens()->delete();
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
