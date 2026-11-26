<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Admin\Models\Module;
use Modules\Admin\Models\User;
use Modules\Admin\Models\UserAccess;
use Modules\Admin\Models\Permission;
use Modules\Admin\Models\UserPermission;

class UserAccessController extends Controller
{
    public function show(User $user)
    {
        $modules = Module::with([
            'menus' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sort_order');
            },
            'menus.childMenus' => function ($query) {
                $query->where('is_active', true)
                    ->orderBy('sort_order');
            },
        ])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Existing CRUD permissions
        $permissions = UserAccess::where('user_id', $user->id)
            ->get()
            ->keyBy(function ($permission) {
                return $permission->menu_id . ':' .
                    ($permission->child_menu_id ?? 'null');
            });

        // Extra permission catalog
        $extraPermissions = Permission::where('is_active', true)
            ->get();

        // User assigned extra permission IDs
        $assignedPermissionIds = DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->pluck('permission_id')
            ->toArray();

        $assignedPermissionIds = array_flip($assignedPermissionIds);

        // Group extra permissions by module/menu/child menu
        $extraPermissionsByLocation = $extraPermissions->groupBy(
            function ($permission) {
                return $permission->module_id . ':' .
                    ($permission->menu_id ?? 'null') . ':' .
                    ($permission->child_menu_id ?? 'null');
            }
        );

        // Format extra permissions
        $formatExtraPermissions = function ($moduleId, $menuId = null, $childMenuId = null) use (
            $extraPermissionsByLocation,
            $assignedPermissionIds
        ) {
            $key = $moduleId . ':' .
                ($menuId ?? 'null') . ':' .
                ($childMenuId ?? 'null');

            return ($extraPermissionsByLocation->get($key) ?? collect())
                ->map(function ($permission) use ($assignedPermissionIds) {
                    return [
                        'id' => $permission->id,
                        'name' => $permission->name,
                        'key' => $permission->key,
                        'description' => $permission->description,
                        'allowed' => isset($assignedPermissionIds[$permission->id]),
                    ];
                })
                ->values();
        };

        $data = $modules->map(function ($module) use (
            $permissions,
            $formatExtraPermissions
        ) {
            $menus = $module->menus->map(function ($menu) use (
                $module,
                $permissions,
                $formatExtraPermissions
            ) {
                $childMenus = $menu->childMenus->map(function ($childMenu) use (
                    $permissions,
                    $formatExtraPermissions,
                    $module,
                    $menu
                ) {
                    $key = $childMenu->menu_id . ':' . $childMenu->id;

                    $permission = $permissions->get($key);

                    return [
                        'id' => $childMenu->id,
                        'code' => $childMenu->code,
                        'name' => $childMenu->name,
                        'slug' => $childMenu->slug,
                        'route' => $childMenu->route,
                        'icon' => $childMenu->icon,
                        'sort_order' => $childMenu->sort_order,

                        'permission' => [
                            'can_view' => (bool) ($permission?->can_view ?? false),
                            'can_create' => (bool) ($permission?->can_create ?? false),
                            'can_update' => (bool) ($permission?->can_update ?? false),
                            'can_delete' => (bool) ($permission?->can_delete ?? false),
                        ],

                        'extra_permissions' => $formatExtraPermissions(
                            $module->id,
                            $menu->id,
                            $childMenu->id
                        ),
                    ];
                });

                $menuPermission = null;

                if ($childMenus->isEmpty()) {
                    $key = $menu->id . ':null';
                    $menuPermission = $permissions->get($key);
                }

                return [
                    'id' => $menu->id,
                    'code' => $menu->code,
                    'name' => $menu->name,
                    'slug' => $menu->slug,
                    'route' => $menu->route,
                    'icon' => $menu->icon,
                    'sort_order' => $menu->sort_order,

                    'has_child_menu' => $childMenus->isNotEmpty(),

                    'permission' => $childMenus->isEmpty()
                        ? [
                            'can_view' => (bool) ($menuPermission?->can_view ?? false),
                            'can_create' => (bool) ($menuPermission?->can_create ?? false),
                            'can_update' => (bool) ($menuPermission?->can_update ?? false),
                            'can_delete' => (bool) ($menuPermission?->can_delete ?? false),
                        ]
                        : null,

                    'extra_permissions' => $formatExtraPermissions(
                        $module->id,
                        $menu->id
                    ),

                    'child_menus' => $childMenus,
                ];
            });

            return [
                'id' => $module->id,
                'code' => $module->code,
                'name' => $module->name,
                'slug' => $module->slug,
                'icon' => $module->icon,
                'sort_order' => $module->sort_order,

                'extra_permissions' => $formatExtraPermissions(
                    $module->id
                ),

                'menus' => $menus,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'User access loaded successfully.',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'modules' => $data,
            ],
        ]);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'permissions' => [
                'required',
                'array',
            ],

            'permissions.*.module_id' => [
                'required',
                'integer',
                'exists:modules,id',
            ],

            'permissions.*.menu_id' => [
                'required',
                'integer',
                'exists:menus,id',
            ],

            'permissions.*.child_menu_id' => [
                'nullable',
                'integer',
                'exists:child_menus,id',
            ],

            'permissions.*.can_view' => [
                'nullable',
                'boolean',
            ],

            'permissions.*.can_create' => [
                'nullable',
                'boolean',
            ],

            'permissions.*.can_update' => [
                'nullable',
                'boolean',
            ],

            'permissions.*.can_delete' => [
                'nullable',
                'boolean',
            ],

            'extra_permissions' => [
                'sometimes',
                'array',
            ],

            'extra_permissions.*' => [
                'integer',
                'distinct',
                'exists:permissions,id',
            ],
        ]);

        DB::transaction(function () use ($validated, $user) {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail();

            $permissions = [];
            $permissionKeys = [];

            foreach ($validated['permissions'] as $permission) {

                $moduleId = $permission['module_id'];
                $menuId = $permission['menu_id'];
                $childMenuId = $permission['child_menu_id'] ?? null;

                // Check menu belongs to module
                $menu = DB::table('menus')
                    ->where('id', $menuId)
                    ->where('module_id', $moduleId)
                    ->first();

                if (!$menu) {
                    throw ValidationException::withMessages([
                        'permissions' => [
                            "Menu ID {$menuId} does not belong to Module ID {$moduleId}.",
                        ],
                    ]);
                }

                // Check child menu belongs to menu
                if ($childMenuId !== null) {

                    $childMenu = DB::table('child_menus')
                        ->where('id', $childMenuId)
                        ->where('menu_id', $menuId)
                        ->first();

                    if (!$childMenu) {
                        throw ValidationException::withMessages([
                            'permissions' => [
                                "Child Menu ID {$childMenuId} does not belong to Menu ID {$menuId}.",
                            ],
                        ]);
                    }
                }

                // Prevent duplicate CRUD permission
                $key = $menuId . ':' . ($childMenuId ?? 'null');

                if (isset($permissionKeys[$key])) {
                    throw ValidationException::withMessages([
                        'permissions' => [
                            'Duplicate menu/child menu permission found.',
                        ],
                    ]);
                }

                $permissionKeys[$key] = true;

                $permissions[] = [
                    'user_id' => $user->id,
                    'module_id' => $moduleId,
                    'menu_id' => $menuId,
                    'child_menu_id' => $childMenuId,
                    'can_view' => $permission['can_view'] ?? false,
                    'can_create' => $permission['can_create'] ?? false,
                    'can_update' => $permission['can_update'] ?? false,
                    'can_delete' => $permission['can_delete'] ?? false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $keptIds = [];
            foreach ($permissions as $attributes) {
                unset($attributes['created_at'], $attributes['updated_at']);
                $access = UserAccess::updateOrCreate([
                    'user_id' => $user->id,
                    'menu_id' => $attributes['menu_id'],
                    'child_menu_id' => $attributes['child_menu_id'],
                ], $attributes);
                $keptIds[] = $access->id;
            }
            UserAccess::where('user_id', $user->id)->whereNotIn('id', $keptIds)
                ->get()->each(fn (UserAccess $access) => $access->delete());

            // Update extra permissions when provided
            if (array_key_exists('extra_permissions', $validated)) {

                $extraPermissionIds = $validated['extra_permissions'];

                // Only active permissions can be assigned
                $validPermissionIds = Permission::where('is_active', true)
                    ->whereIn('id', $extraPermissionIds)
                    ->pluck('id')
                    ->toArray();

                if (count($validPermissionIds) !== count($extraPermissionIds)) {
                    throw ValidationException::withMessages([
                        'extra_permissions' => [
                            'One or more selected extra permissions are invalid or inactive.',
                        ],
                    ]);
                }

                UserPermission::where('user_id', $user->id)
                    ->whereNotIn('permission_id', $validPermissionIds)->get()
                    ->each(fn (UserPermission $permission) => $permission->delete());
                foreach ($validPermissionIds as $permissionId) {
                    UserPermission::firstOrCreate(['user_id' => $user->id, 'permission_id' => $permissionId]);
                }
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'User access updated successfully.',
        ]);
    }
}