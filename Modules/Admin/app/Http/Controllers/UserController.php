<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Admin\Models\User;
use Modules\Admin\Models\UserRole;
use Modules\Admin\Models\UserAccess;
use Modules\Admin\Models\UserPermission;

use Illuminate\Support\Facades\Hash;
class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['employee:id,name', 'roles'])
            ->latest()
            ->get();

        return response()->json([
            'status' => 'Success',
            'data' => $users,
            'roles' => \Modules\Admin\Models\Role::orderBy('name')->get(['id', 'name', 'is_active']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => [
                'unique:users,employee_id',
                'nullable',
                'integer',
                'exists:employees,id',
            ],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'distinct', 'exists:roles,id'],
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

        if ($request->has('role_ids')) \Illuminate\Support\Facades\Gate::authorize('user.assign_role');
        $roles = $validated['role_ids'] ?? [];
        unset($validated['role_ids']);
        $user = DB::transaction(function () use ($validated, $roles) {
            $user = User::create($validated);
            $this->syncRoles($user, $roles);
            return $user;
        });

        return response()->json([
            'status' => 'Success',
            'message' => 'User created successfully.',
            'data' => $user->load(['employee:id,name', 'roles']),
        ], 201);
    }

    public function show(User $user)
    {
        return response()->json([
            'status' => 'Success',
            'data' => $user->load(['employee:id,name', 'roles']),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'employee_id' => [
                'sometimes',
                Rule::unique('users', 'employee_id')->ignore($user->id),
                'nullable',
                'integer',
                'exists:employees,id',
            ],
            'role_ids' => ['sometimes', 'array'],
            'role_ids.*' => ['integer', 'distinct', 'exists:roles,id'],
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

        if ($request->has('role_ids')) \Illuminate\Support\Facades\Gate::authorize('user.assign_role');
        $roles = $validated['role_ids'] ?? null;
        unset($validated['role_ids']);
        abort_if((int) auth()->id() === (int) $user->id && ($validated['status'] ?? $user->status) === 'Inactive', 422, 'You cannot deactivate your own account.');
        DB::transaction(function () use ($user, $validated, $roles) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->update($validated);
            if ($roles !== null) $this->syncRoles($user, $roles);
            if ($user->status === 'Inactive' || isset($validated['password'])) $user->tokens()->delete();
        });

        return response()->json([
            'status' => 'Success',
            'message' => 'User updated successfully.',
            'data' => $user->fresh()->load(['employee:id,name', 'roles']),
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

        $references = [
            'support_tickets' => ['submitted_by', 'assigned_to'],
            'support_ticket_replies' => ['user_id'],
            'support_ticket_histories' => ['user_id'],
            'gate_passes' => ['requested_by'],
            'meetings' => ['organizer_id', 'chairperson_id'],
            'meeting_participants' => ['user_id'],
            'approval_requests' => ['requested_by'],
            'approval_actions' => ['action_by'],
            'approval_workflows' => ['created_by'],
            'approval_workflow_levels' => ['approver_user_id'],
            'approval_request_levels' => ['approver_user_id'],
        ];
        foreach ($references as $table => $columns) {
            foreach ($columns as $column) {
                if (\Illuminate\Support\Facades\Schema::hasColumn($table, $column) && DB::table($table)->where($column, $user->id)->exists()) {
                    return response()->json(['status' => 'Error', 'message' => 'This user has transaction or approval history. Deactivate the account instead.'], 422);
                }
            }
        }
        DB::transaction(function () use ($user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            $user->tokens()->delete();
            foreach ([UserRole::class, UserAccess::class, UserPermission::class] as $model) {
                $model::where('user_id', $user->id)->get()->each(fn ($row) => $row->delete());
            }
            $user->delete();
        });

        return response()->json([
            'status' => 'Success',
            'message' => 'User deleted successfully.',
        ]);
    }
    private function syncRoles(User $user, array $ids): void
    {
        \Modules\Admin\Models\Role::whereIn('id', $ids)->lockForUpdate()->get();
        UserRole::where('user_id', $user->id)->whereNotIn('role_id', $ids)->get()
            ->each(fn (UserRole $role) => $role->delete());
        foreach ($ids as $id) {
            UserRole::firstOrCreate(['user_id' => $user->id, 'role_id' => $id]);
        }
    }

}