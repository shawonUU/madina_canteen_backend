<?php

namespace Modules\HRM\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Modules\Admin\Models\User;
use Modules\HRM\Models\Employee;
use Illuminate\Database\Eloquent\Builder;

class EmployeeController extends Controller
{
    /**
     * Display a listing of employees.
     */

    public function index(Request $request)
    {
        $allowedSortColumns = [
            'id',
            'employee_code',
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

        $sortDirection = strtolower(
            $request->input('sort_direction', 'desc')
        );

        $sortDirection = $sortDirection === 'asc' ? 'asc' : 'desc';

        $query = Employee::with('user.roles');

        $search = trim((string) $request->input('search', ''));

        if ($search !== '') {
            $query->where(function (Builder $query) use ($search) {
                $query->where('employee_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%")
                    ->orWhere('designation', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $filters = $request->input('filter', []);

        foreach ([
            'employee_code',
            'name',
            'department',
            'designation',
            'phone',
            'email',
        ] as $column) {
            $value = trim((string) ($filters[$column] ?? ''));

            if ($value !== '') {
                $query->where($column, 'like', "%{$value}%");
            }
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['system_access'])) {
            if ($filters['system_access'] === 'Enabled') {
                $query->whereHas('user');
            } elseif ($filters['system_access'] === 'No Login') {
                $query->whereDoesntHave('user');
            }
        }

        $perPage = (int) $request->input('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

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

    /**
     * Store a newly created employee.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'department' => [
                'nullable',
                'string',
                'max:255',
            ],

            'designation' => [
                'nullable',
                'string',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:255',
            ],

            'email' => [
                'required_if:create_user,true',
                'nullable',
                'email',
                'max:255',
                'unique:users,email',
            ],

            'status' => [
                'nullable',
                Rule::in(['Active', 'Inactive']),
            ],

            'create_user' => [
                'nullable',
                'boolean',
            ],

            'role_id' => [
                'required_if:create_user,true',
                'nullable',
                'exists:roles,id',
            ],

            'user_password' => [
                'required_if:create_user,true',
                'nullable',
                'string',
                'min:6',
            ],
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

            $employee->update([
                'employee_code' => 'EMP-' . str_pad($employee->id, 6, '0', STR_PAD_LEFT),
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
            }

            return $employee;
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully.',
            'data' => $employee->load('user'),
        ], 201);
    }

    /**
     * Display the specified employee.
     */
    public function show($id)
    {
        $employee = Employee::with('user')->find($id);

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

    /**
     * Update the specified employee.
     */
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
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'department' => [
                'nullable',
                'string',
                'max:255',
            ],

            'designation' => [
                'nullable',
                'string',
                'max:255',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:255',
            ],

            'email' => [
                'required_if:create_user,true',
                'nullable',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore(
                    $employee->user?->id
                ),
            ],

            'status' => [
                'nullable',
                Rule::in(['Active', 'Inactive']),
            ],

            'create_user' => [
                'nullable',
                'boolean',
            ],

            'role_id' => [
                'required_if:create_user,true',
                'nullable',
                'exists:roles,id',
            ],

            'user_password' => [
                'nullable',
                'string',
                'min:6',
            ],
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

            $createUser = !empty($validated['create_user']);

            if ($createUser) {

                $user = null;

                if ($employee->user) {

                    $userData = [
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'status' => 'Active',
                    ];

                    if (!empty($validated['user_password'])) {
                        $userData['password'] = Hash::make(
                            $validated['user_password']
                        );
                    }

                    $employee->user->update($userData);
                    $user = $employee->user;

                } else {

                    $user = User::create([
                        'code' => getGenerateCode(User::class, 'code', 'USR', 8),
                        'employee_id' => $employee->id,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'password' => Hash::make(
                            $validated['user_password']
                        ),
                        'status' => 'Active',
                    ]);
                }


            } elseif ($employee->user) {

                $employee->user->update([
                    'status' => 'Inactive',
                ]);

                $employee->user->syncRoles([]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully.',
            'data' => $employee->fresh()->load('user'),
        ]);
    }

    /**
     * Remove the specified employee.
     */
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

