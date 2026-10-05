<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Admin\Models\Module;

class ModuleController extends Controller
{
    public function index(Request $request)
    {
        $modules = Module::query()
            ->when($request->has('is_active') && $request->is_active !== null, function ($query) use ($request) {
                $query->where('is_active', $request->boolean('is_active'));
            })
            ->with([
                'menus' => function ($query) {
                    $query->where('is_active', true)
                        ->orderBy('sort_order')
                        ->with([
                            'childMenus' => function ($query) {
                                $query->where('is_active', true)
                                    ->orderBy('sort_order');
                            }
                        ]);
                }
            ])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $modules,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:modules,slug'],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['code'] = getGenerateCode(Module::class, 'code', 'MOD', 8);
        $module = Module::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Module created successfully.',
            'data' => $module,
        ], 201);
    }

    public function show(Module $module)
    {
        $module->load([
            'menus.childMenus',
        ]);

        return response()->json([
            'success' => true,
            'data' => $module,
        ]);
    }

    public function update(Request $request, Module $module)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'unique:modules,slug,' . $module->id,
            ],
            'icon' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $module->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Module updated successfully.',
            'data' => $module->fresh(),
        ]);
    }

    public function destroy(Module $module)
    {
        $module->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module deleted successfully.',
        ]);
    }
}