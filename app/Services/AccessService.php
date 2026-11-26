<?php

namespace App\Services;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Models\ChildMenu;
use Modules\Admin\Models\Menu;
use Modules\Admin\Models\Module;
use Modules\Admin\Models\User;
use Modules\Admin\Models\UserAccess;

class AccessService
{
    private array $permissionCache = [];
    private array $accessCache = [];
    private array $effectiveCache = [];

    public function reset(): void
    {
        $this->permissionCache = $this->accessCache = $this->effectiveCache = [];
    }

    public function permissions(User $user): array
    {
        if (isset($this->permissionCache[$user->id])) return $this->permissionCache[$user->id];
        $direct = DB::table('permissions as p')->join('user_permissions as up', 'up.permission_id', '=', 'p.id')
            ->where('up.user_id', $user->id)->where('p.is_active', true)->pluck('p.key');
        $roles = DB::table('permissions as p')->join('role_permissions as rp', 'rp.permission_id', '=', 'p.id')
            ->join('roles as r', 'r.id', '=', 'rp.role_id')->join('user_roles as ur', 'ur.role_id', '=', 'r.id')
            ->where('ur.user_id', $user->id)->where('r.is_active', true)->where('p.is_active', true)->pluck('p.key');
        return $this->permissionCache[$user->id] = $direct->merge($roles)->unique()->values()->all();
    }

    public function accesses(User $user): Collection
    {
        if (isset($this->accessCache[$user->id])) return $this->accessCache[$user->id];
        return $this->accessCache[$user->id] = UserAccess::with(['module', 'menu', 'childMenu'])->where('user_id', $user->id)->get()
            ->filter(fn ($a) => $a->module?->is_active && $a->menu?->is_active && (!$a->child_menu_id || $a->childMenu?->is_active))
            ->map(fn ($a) => [
                'module_id' => $a->module_id, 'menu_id' => $a->menu_id, 'child_menu_id' => $a->child_menu_id,
                'module_slug' => $a->module->slug, 'menu_slug' => $a->menu->slug, 'child_menu_slug' => $a->childMenu?->slug,
                'route' => $a->childMenu?->route ?: $a->menu->route,
                'permission' => $a->childMenu?->permission ?: $a->menu->permission,
                'can_view' => (bool) $a->can_view, 'can_create' => (bool) $a->can_create,
                'can_update' => (bool) $a->can_update, 'can_delete' => (bool) $a->can_delete,
            ])->values();
    }

    public function can(?User $user, string $key): bool
    {
        if (!$user || $user->status !== 'Active') return false;
        if (in_array($key, $this->permissions($user), true)) return true;
        if ($key === 'meeting.attendance.report') return $this->canAt($user, ['meeting', 'meeting-reports', 'attendance-report']);
        if ($key === 'audit.view-all') return $this->canAt($user, ['admin', 'system-management', 'activity-log']);
        $dot = strrpos($key, '.');
        if ($dot === false) return false;
        $resource = substr($key, 0, $dot);
        $action = substr($key, $dot + 1);
        if (!in_array($action, ['view', 'create', 'update', 'delete'], true)) return false;
        $location = config('access.resources.'.$resource);
        // Dotted resource names are literal keys, not nested configuration paths.
        $location = config('access.resources')[$resource] ?? $location;
        if ($location && $this->canAt($user, $location, $action)) return true;
        foreach (config('access.aliases')[$resource] ?? [] as $alias) {
            if ($this->canAt($user, $alias, $action)) return true;
        }
        return $this->accesses($user)->contains(function ($a) use ($resource, $action) {
            return $a['permission'] && preg_replace('/\.(view|create|update|delete)$/', '', $a['permission']) === $resource && $a['can_'.$action];
        });
    }

    public function effectiveAccesses(User $user): Collection
    {
        if (isset($this->effectiveCache[$user->id])) return $this->effectiveCache[$user->id];
        $accesses = $this->accesses($user)->keyBy(fn ($a) => $a['menu_id'].':'.($a['child_menu_id'] ?? 'null'));
        $grants = $this->permissions($user);
        foreach (Module::with('menus.childMenus')->where('is_active', true)->get() as $module) {
            foreach ($module->menus->where('is_active', true) as $menu) {
                foreach (collect([$menu])->concat($menu->childMenus->where('is_active', true)) as $node) {
                    if (!$node->permission) continue;
                    $child = $node instanceof ChildMenu ? $node : null;
                    $key = $menu->id.':'.($child?->id ?? 'null');
                    $row = $accesses->get($key, [
                        'module_id' => $module->id, 'menu_id' => $menu->id, 'child_menu_id' => $child?->id,
                        'module_slug' => $module->slug, 'menu_slug' => $menu->slug, 'child_menu_slug' => $child?->slug,
                        'route' => $node->route, 'permission' => $node->permission,
                        'can_view' => false, 'can_create' => false, 'can_update' => false, 'can_delete' => false,
                    ]);
                    $prefix = preg_replace('/\.(view|create|update|delete)$/', '', $node->permission);
                    foreach (['view', 'create', 'update', 'delete'] as $action) {
                        $row['can_'.$action] = $row['can_'.$action] || in_array($prefix.'.'.$action, $grants, true);
                    }
                    $row['can_view'] = $row['can_view'] || in_array($node->permission, $grants, true);
                    $accesses->put($key, $row);
                }
            }
        }
        return $this->effectiveCache[$user->id] = $accesses->values();
    }

    public function canAt(User $user, array $location, string $action = 'view'): bool
    {
        [$module, $menu, $child] = $location;
        return $this->accesses($user)->contains(fn ($a) => $a['module_slug'] === $module && $a['menu_slug'] === $menu
            && $a['child_menu_slug'] === $child && $a['can_'.$action]);
    }

    public function authorize(?User $user, string $key): void
    {
        if (!$this->can($user, $key)) throw new AuthorizationException('You do not have permission to perform this action.');
    }

    public function hasModule(User $user, string $module): bool
    {
        return $this->effectiveAccesses($user)->contains(fn ($a) => $a['module_slug'] === $module && $a['can_view']);
    }

    public function context(array $location): ?array
    {
        [$moduleSlug, $menuSlug, $childSlug] = $location;
        $module = Module::where('slug', $moduleSlug)->where('is_active', true)->first();
        if (!$module) return null;
        $menu = Menu::where('module_id', $module->id)->where('slug', $menuSlug)->where('is_active', true)->first();
        if (!$menu) return null;
        $child = $childSlug ? ChildMenu::where('menu_id', $menu->id)->where('slug', $childSlug)->where('is_active', true)->first() : null;
        if ($childSlug && !$child) return null;
        return ['module_id' => $module->id, 'menu_id' => $menu->id, 'child_menu_id' => $child?->id];
    }
}
