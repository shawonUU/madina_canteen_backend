<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Admin\Models\User;

use Illuminate\Support\Facades\Hash;
class UserController extends Controller
{
    public function index()
    {
        $users = User::with('employee:id,name')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'Success',
            'data' => $users,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => [
                'nullable',
                'integer',
                'exists:employees,id',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
            ],
            'status' => [
                'required',
                Rule::in(['Active', 'Inactive']),
            ],
        ]);

        $validated['code'] = getGenerateCode(User::class, 'code', 'USR');
        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        return response()->json([
            'status' => 'Success',
            'message' => 'User created successfully.',
            'data' => $user->load('employee:id,name'),
        ], 201);
    }

    public function show(User $user)
    {
        return response()->json([
            'status' => 'Success',
            'data' => $user->load('employee:id,name'),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'employee_id' => [
                'nullable',
                'integer',
                'exists:employees,id',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => [
                'nullable',
                'string',
                'min:8',
            ],
            'status' => [
                'required',
                Rule::in(['Active', 'Inactive']),
            ],
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'status' => 'Success',
            'message' => 'User updated successfully.',
            'data' => $user->fresh()->load('employee:id,name'),
        ]);
    }

    public function destroy(User $user)
    {
        if (auth()->id() === $user->id) {
            return response()->json([
                'status' => 'Error',
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        return response()->json([
            'status' => 'Success',
            'message' => 'User deleted successfully.',
        ]);
    }
}