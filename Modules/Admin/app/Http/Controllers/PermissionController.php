<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Admin\Models\Permission;

class PermissionController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'permissions' => DB::table('permissions')
                    ->leftJoin('modules', 'permissions.module_id', '=', 'modules.id')
                    ->leftJoin('menus', 'permissions.menu_id', '=', 'menus.id')
                    ->leftJoin('child_menus', 'permissions.child_menu_id', '=', 'child_menus.id')
                    ->select(
                        'permissions.*',
                        'modules.name as module_name',
                        'menus.name as menu_name',
                        'child_menus.name as child_menu_name'
                    )
                    ->orderByDesc('permissions.id')
                    ->get(),

                'modules' => DB::table('modules')
                    ->select('id', 'name')
                    ->orderBy('name')
                    ->get(),

                'menus' => DB::table('menus')
                    ->select('id', 'module_id', 'name')
                    ->orderBy('name')
                    ->get(),

                'child_menus' => DB::table('child_menus')
                    ->select('id', 'menu_id', 'name')
                    ->orderBy('name')
                    ->get(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePermission($request);

        $this->validatePermissionLocation($validated);

        $permission = Permission::create([
            'name' => $validated['name'],
            'key' => $validated['key'],
            'module_id' => $validated['module_id'],
            'menu_id' => $validated['menu_id'] ?? null,
            'child_menu_id' => $validated['child_menu_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully.',
            'data' => $permission,
        ], 201);
    }

    public function show($id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $permission,
        ]);
    }

    public function update(Request $request, $id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found.',
            ], 404);
        }

        $validated = $this->validatePermission($request, $id);

        $this->validatePermissionLocation($validated);

        $permission->update([
                'name' => $validated['name'],
                'key' => $validated['key'],
                'module_id' => $validated['module_id'],
                'menu_id' => $validated['menu_id'] ?? null,
                'child_menu_id' => $validated['child_menu_id'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => $validated['is_active'],
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully.',
            'data' => Permission::find($id),
        ]);
    }

    public function destroy($id)
    {
        $permission = Permission::find($id);

        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found.',
            ], 404);
        }

        if (DB::table('user_permissions')->where('permission_id', $id)->exists()
            || (\Illuminate\Support\Facades\Schema::hasTable('role_permissions') && DB::table('role_permissions')->where('permission_id', $id)->exists())) {
            return response()->json(['success' => false, 'message' => 'This permission is assigned. Remove assignments or deactivate it first.'], 422);
        }
        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully.',
        ]);
    }

    private function validatePermission(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'key')->ignore($id),
            ],
            'module_id' => [
                'required',
                'integer',
                'exists:modules,id',
            ],
            'menu_id' => [
                'nullable',
                'integer',
                'exists:menus,id',
            ],
            'child_menu_id' => [
                'nullable',
                'integer',
                'exists:child_menus,id',
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function validatePermissionLocation(array $data): void
    {
        if (!empty($data['menu_id'])) {
            $menu = DB::table('menus')
                ->where('id', $data['menu_id'])
                ->where('module_id', $data['module_id'])
                ->first();

            if (!$menu) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Selected menu does not belong to the selected module.',
                ], 422));
            }
        }

        if (!empty($data['child_menu_id'])) {
            if (empty($data['menu_id'])) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Menu is required when selecting a child menu.',
                ], 422));
            }

            $childMenu = DB::table('child_menus')
                ->where('id', $data['child_menu_id'])
                ->where('menu_id', $data['menu_id'])
                ->first();

            if (!$childMenu) {
                abort(response()->json([
                    'success' => false,
                    'message' => 'Selected child menu does not belong to the selected menu.',
                ], 422));
            }
        }
    }
}
