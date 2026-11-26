<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Admin\Models\Role;
use Modules\Admin\Models\Permission;
use Modules\Admin\Models\RolePermission;

class RoleController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => Role::with('permissions')->withCount('users')->orderBy('name')->get(), 'permissions' => Permission::where('is_active', true)->orderBy('name')->get(['id', 'name', 'key', 'is_active'])]);
    }

    public function show(Role $role)
    {
        return response()->json(['success' => true, 'data' => $role->load('permissions')->loadCount('users')]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $role = DB::transaction(function () use ($data) {
            $role = Role::create(collect($data)->except('permissions')->all());
            $this->syncPermissions($role, $data['permissions']);
            return $role;
        });
        return response()->json(['success' => true, 'message' => 'Role created successfully.', 'data' => $role->load('permissions')], 201);
    }

    public function update(Request $request, Role $role)
    {
        $data = $this->validated($request, $role);
        DB::transaction(function () use ($role, $data) {
            $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            $role->update(collect($data)->except('permissions')->all());
            $this->syncPermissions($role, $data['permissions']);
        });
        return response()->json(['success' => true, 'message' => 'Role updated successfully.', 'data' => $role->fresh()->load('permissions')]);
    }

    public function destroy(Role $role)
    {
        DB::transaction(function () use ($role) {
            $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            abort_if($role->users()->exists(), 422, 'This role is assigned to users. Remove assignments or deactivate it first.');
            RolePermission::where('role_id', $role->id)->get()->each(fn (RolePermission $permission) => $permission->delete());
            $role->delete();
        });
        return response()->json(['success' => true, 'message' => 'Role deleted successfully.']);
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', 'distinct', Rule::exists('permissions', 'id')->where('is_active', true)],
        ]);
    }

    private function syncPermissions(Role $role, array $ids): void
    {
        RolePermission::where('role_id', $role->id)->whereNotIn('permission_id', $ids)->get()
            ->each(fn (RolePermission $permission) => $permission->delete());
        foreach ($ids as $id) {
            RolePermission::firstOrCreate(['role_id' => $role->id, 'permission_id' => $id]);
        }
    }
}
