<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Models\User;
use Modules\Admin\Models\Permission;
use Modules\Admin\Models\UserPermission;

class UserPermissionController extends Controller
{
    public function assignPermission(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ]);
        $ids = Permission::where('is_active', true)->whereIn('id', $validated['permissions'])->pluck('id')->all();
        abort_if(count($ids) !== count($validated['permissions']), 422, 'Only active permissions may be assigned.');
        DB::transaction(function () use ($validated, $ids) {
            User::whereKey($validated['user_id'])->lockForUpdate()->firstOrFail();
            UserPermission::where('user_id', $validated['user_id'])->whereNotIn('permission_id', $ids)->get()
                ->each(fn (UserPermission $permission) => $permission->delete());
            foreach ($ids as $id) {
                UserPermission::firstOrCreate(['user_id' => $validated['user_id'], 'permission_id' => $id]);
            }
        });
        return response()->json(['success' => true, 'message' => 'Permissions assigned successfully.', 'data' => $ids]);
    }

    public function removePermission(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'permission' => ['required', 'integer', 'exists:permissions,id'],
        ]);
        DB::transaction(function () use ($validated) {
            User::whereKey($validated['user_id'])->lockForUpdate()->firstOrFail();
            UserPermission::where('user_id', $validated['user_id'])->where('permission_id', $validated['permission'])->get()
                ->each(fn (UserPermission $permission) => $permission->delete());
        });
        return response()->json(['success' => true, 'message' => 'Permission removed.']);
    }
}
