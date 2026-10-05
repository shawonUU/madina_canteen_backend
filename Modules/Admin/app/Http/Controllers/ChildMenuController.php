<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Admin\Models\ChildMenu;
use Modules\Admin\Models\Menu;

class ChildMenuController extends Controller
{
    public function index(Request $request)
    {
        $query = ChildMenu::query()
            ->with([
                'menu.module',
            ])
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($request->filled('menu_id')) {
            $query->where('menu_id', $request->menu_id);
        }

        $childMenus = $query->get();

        return response()->json([
            'success' => true,
            'data' => $childMenus,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu_id' => [
                'required',
                'integer',
                'exists:menus,id',
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

        $exists = ChildMenu::where('menu_id', $validated['menu_id'])
            ->where('slug', $validated['slug'])
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'This child menu already exists under the selected menu.',
            ], 422);
        }

        $validated['code'] = getGenerateCode(ChildMenu::class, 'code', 'CHM', 8);
        $childMenu = ChildMenu::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Child menu created successfully.',
            'data' => $childMenu->load('menu.module'),
        ], 201);
    }

    public function show(ChildMenu $childMenu)
    {
        $childMenu->load([
            'menu.module',
        ]);

        return response()->json([
            'success' => true,
            'data' => $childMenu,
        ]);
    }

    public function update(Request $request, ChildMenu $childMenu)
    {
        $validated = $request->validate([
            'menu_id' => [
                'required',
                'integer',
                'exists:menus,id',
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

        $exists = ChildMenu::where('menu_id', $validated['menu_id'])
            ->where('slug', $validated['slug'])
            ->where('id', '!=', $childMenu->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'This child menu already exists under the selected menu.',
            ], 422);
        }

        $childMenu->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Child menu updated successfully.',
            'data' => $childMenu->fresh()->load('menu.module'),
        ]);
    }

    public function destroy(ChildMenu $childMenu)
    {
        $childMenu->delete();

        return response()->json([
            'success' => true,
            'message' => 'Child menu deleted successfully.',
        ]);
    }
}