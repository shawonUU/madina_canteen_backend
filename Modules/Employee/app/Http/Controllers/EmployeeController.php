<?php

namespace Modules\Employee\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Modules\Auth\Models\User;
use Modules\Employee\Models\Employee;
use Spatie\Permission\Models\Role;

class EmployeeController extends Controller
{
    /**
     * Display a listing of employees.
     */
    public function index(Request $request)
    {
        $employees = Employee::with('user.roles')
            ->latest()
            ->paginate($request->get('per_page', 15));

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
                'employee_code' => 'TEMP-' . uniqid(),
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
                    'employee_id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'password' => Hash::make($validated['user_password']),
                    'status' => 'Active',
                ]);

                $role = Role::findOrFail($validated['role_id']);
                $user->assignRole($role);
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
                        'employee_id' => $employee->id,
                        'name' => $employee->name,
                        'email' => $employee->email,
                        'password' => Hash::make(
                            $validated['user_password']
                        ),
                        'status' => 'Active',
                    ]);
                }

                $role = Role::findOrFail($validated['role_id']);
                $user->syncRoles($role);

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

