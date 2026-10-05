<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Admin\Models\Menu;
use Modules\Admin\Models\Module;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $query = Menu::query()
            ->with('module')
            ->with('childMenus')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($request->filled('module_id')) {
            $query->where('module_id', $request->module_id);
        }

        $menus = $query->get();

        return response()->json([
            'success' => true,
            'data' => $menus,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'module_id' => [
                'required',
                'integer',
                'exists:modules,id',
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
            ],
            'route' => ['nullable', 'string', 'max:255'],
            'permission' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $exists = Menu::where('module_id', $validated['module_id'])
            ->where('slug', $validated['slug'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'This menu already exists in the selected module.',
            ], 422);
        }

        $validated['code'] = getGenerateCode(Menu::class, 'code', 'MNU', 8);
        $menu = Menu::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Menu created successfully.',
            'data' => $menu->load('module'),
        ], 201);
    }

    public function show(Menu $menu)
    {
        $menu->load([
            'module',
            'childMenus',
        ]);

        return response()->json([
            'success' => true,
            'data' => $menu,
        ]);
    }

    public function update(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'module_id' => [
                'required',
                'integer',
                'exists:modules,id',
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
            ],
            'route' => ['nullable', 'string', 'max:255'],
            'permission' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $exists = Menu::where('module_id', $validated['module_id'])
            ->where('slug', $validated['slug'])
            ->where('id', '!=', $menu->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'This menu already exists in the selected module.',
            ], 422);
        }

        $menu->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Menu updated successfully.',
            'data' => $menu->fresh()->load('module'),
        ]);
    }

    public function destroy(Menu $menu)
    {
        $menu->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu deleted successfully.',
        ]);
    }
}