<?php

namespace Modules\Admin\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Modules\Admin\Models\Module;
use Modules\Admin\Models\Menu;
use Modules\Admin\Models\ChildMenu;
use Modules\Admin\Models\Permission;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (config('navigation', []) as $data) {
                $menus = $data['menus'] ?? [];
                $extras = $data['extra_permissions'] ?? [];
                unset($data['menus'], $data['extra_permissions']);
                $module = Module::firstOrNew(['slug' => $data['slug']]);
                if (!$module->exists) $module->code = getGenerateCode(Module::class, 'code', 'MOD');
                $module->fill($data)->save();
                $this->permissions($extras, $module->id);
                foreach ($menus as $data) {
                    $children = $data['child_menus'] ?? [];
                    $extras = $data['extra_permissions'] ?? [];
                    unset($data['child_menus'], $data['extra_permissions']);
                    $menu = Menu::firstOrNew(['module_id' => $module->id, 'slug' => $data['slug']]);
                    if (!$menu->exists) $menu->code = getGenerateCode(Menu::class, 'code', 'MNU');
                    $menu->fill($data)->save();
                    $this->permissions($extras, $module->id, $menu->id);
                    if (!$children) $this->crud($menu->permission, $menu->name, $module->id, $menu->id);
                    foreach ($children as $data) {
                        $extras = $data['extra_permissions'] ?? [];
                        unset($data['extra_permissions']);
                        $child = ChildMenu::firstOrNew(['menu_id' => $menu->id, 'slug' => $data['slug']]);
                        if (!$child->exists) $child->code = getGenerateCode(ChildMenu::class, 'code', 'CMNU');
                        $child->fill($data)->save();
                        $this->permissions($extras, $module->id, $menu->id, $child->id);
                        $this->crud($child->permission, $child->name, $module->id, $menu->id, $child->id);
                    }
                }
            }
        });
    }

    private function crud(?string $key, string $name, int $moduleId, ?int $menuId, ?int $childId = null): void
    {
        if (!$key || !preg_match('/\.(view|create|update|delete)$/', $key)) return;
        $base = preg_replace('/\.(view|create|update|delete)$/', '', $key);
        foreach (['view', 'create', 'update', 'delete'] as $action) {
            // Preserve a permission's first catalog location when several menus share one ability.
            if (Permission::where('key', $base.'.'.$action)->exists()) continue;
            $this->permissions([['key' => $base.'.'.$action, 'name' => ucfirst($action).' '.$name]], $moduleId, $menuId, $childId);
        }
    }

    private function permissions(array $permissions, int $moduleId, ?int $menuId = null, ?int $childId = null): void
    {
        foreach ($permissions as $data) Permission::updateOrCreate(['key' => $data['key']], [
            'name' => $data['name'], 'module_id' => $moduleId, 'menu_id' => $menuId, 'child_menu_id' => $childId,
            'description' => $data['description'] ?? null, 'is_active' => true,
        ]);
    }
}
