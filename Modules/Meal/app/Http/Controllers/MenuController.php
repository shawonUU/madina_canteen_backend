<?php

namespace Modules\Meal\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Meal\Models\Menu;
use Modules\Meal\Models\MenuItem;
use Modules\Meal\Models\MealType;

class MenuController extends Controller
{

    public function index(Request $request)
    {
        $query = Menu::query()
            ->with('mealType');

        if ($request->filled('menu_date')) {
            $query->whereDate(
                'menu_date',
                $request->menu_date
            );
        }

        if ($request->filled('meal_type_id')) {
            $query->where(
                'meal_type_id',
                $request->meal_type_id
            );
        }

        if ($request->filled('search')) {
            $search = trim($request->search);

            $query->whereHas('items', function ($q) use ($search) {
                $q->where('Item_name', 'like', "%{$search}%");
            });
        }

        $menus = $query
            ->orderByDesc('menu_date')
            ->orderBy('meal_type_id')
            ->get();

        $menus->each(function ($menu) {
            $items = MenuItem::query()
                ->where('menu_id', $menu->id)
                ->orderBy('id')
                ->get();

            $mainItems = $items
                ->where('item_type', 'Main')
                ->values();

            $menu->items = $mainItems->map(function ($main) use ($items) {

                $alternative = $items
                    ->where('item_type', 'Alternative')
                    ->where('alternative_of', $main->id)
                    ->first();

                return [
                    'id' => $main->id,
                    'item_name' => $main->Item_name,
                    'item_type' => $main->item_type,

                    'alternate' => $alternative
                        ? [
                            'id' => $alternative->id,
                            'item_name' => $alternative->Item_name,
                            'item_type' => $alternative->item_type,
                            'alternative_of' => $alternative->alternative_of,
                        ]
                        : null,
                ];
            })->values();
        });

        return response()->json([
            'success' => true,
            'data' => $menus,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu_date' => [
                'required',
                'date',
            ],

            'meal_type_id' => [
                'required',
                'integer',
                'exists:meal_types,id',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.item_name' => [
                'required',
                'string',
                'max:255',
            ],

            'items.*.alternate_item_name' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $alreadyExists = Menu::query()
            ->whereDate(
                'menu_date',
                $validated['menu_date']
            )
            ->where(
                'meal_type_id',
                $validated['meal_type_id']
            )
            ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'menu_date' => [
                    'A menu already exists for this date and meal type.',
                ],
            ]);
        }


        $menu = DB::transaction(function () use ($validated, $request) {

            $menu = Menu::create([
                'menu_date' => $validated['menu_date'],
                'meal_type_id' => $validated['meal_type_id'],
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {

                $mainItem = MenuItem::create([
                    'menu_id' => $menu->id,
                    'Item_name' => trim($item['item_name']),
                    'item_type' => 'Main',
                    'alternative_of' => null,
                ]);

                if (
                    !empty($item['alternate_item_name']) &&
                    trim($item['alternate_item_name']) !== ''
                ) {
                    MenuItem::create([
                        'menu_id' => $menu->id,
                        'Item_name' => trim(
                            $item['alternate_item_name']
                        ),
                        'item_type' => 'Alternative',
                        'alternative_of' => $mainItem->id,
                    ]);
                }
            }


            return $menu;
        });


        return response()->json([
            'success' => true,
            'message' => 'Menu created successfully.',
            'data' => $menu->load('mealType'),
        ], 201);
    }

    public function show(Menu $menu)
    {
        $items = MenuItem::query()
            ->where('menu_id', $menu->id)
            ->orderBy('id')
            ->get();

        $mainItems = $items
            ->where('item_type', 'Main')
            ->values();

        $menu->items = $mainItems->map(function ($main) use ($items) {

            $alternative = $items
                ->where('item_type', 'Alternative')
                ->where('alternative_of', $main->id)
                ->first();

            return [
                'id' => $main->id,
                'item_name' => $main->Item_name,
                'item_type' => $main->item_type,

                'alternate' => $alternative
                    ? [
                        'id' => $alternative->id,
                        'item_name' => $alternative->Item_name,
                        'item_type' => $alternative->item_type,
                        'alternative_of' => $alternative->alternative_of,
                    ]
                    : null,
            ];
        })->values();

        $menu->load('mealType');

        return response()->json([
            'success' => true,
            'data' => $menu,
        ]);
    }

    public function update(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'menu_date' => [
                'required',
                'date',
            ],

            'meal_type_id' => [
                'required',
                'integer',
                'exists:meal_types,id',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.id' => [
                'nullable',
                'integer',
            ],

            'items.*.item_name' => [
                'required',
                'string',
                'max:255',
            ],

            'items.*.alternate_item_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'items.*.alternate_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $duplicate = Menu::query()
            ->whereDate(
                'menu_date',
                $validated['menu_date']
            )
            ->where(
                'meal_type_id',
                $validated['meal_type_id']
            )
            ->where(
                'id',
                '!=',
                $menu->id
            )
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'menu_date' => [
                    'Another menu already exists for this date and meal type.',
                ],
            ]);
        }


        DB::transaction(function () use (
            $validated,
            $menu
        ) {

            $menu->update([
                'menu_date' => $validated['menu_date'],
                'meal_type_id' => $validated['meal_type_id'],
            ]);

            $existingMainIds = MenuItem::query()
                ->where('menu_id', $menu->id)
                ->where('item_type', 'Main')
                ->pluck('id')
                ->toArray();

            $submittedMainIds = collect($validated['items'])
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->toArray();



            $removedMainIds = array_diff(
                $existingMainIds,
                $submittedMainIds
            );

            foreach ($removedMainIds as $removedMainId) {

                MenuItem::query()
                    ->where('alternative_of', $removedMainId)
                    ->delete();

                MenuItem::query()
                    ->where('id', $removedMainId)
                    ->where('menu_id', $menu->id)
                    ->delete();
            }

            foreach ($validated['items'] as $item) {

                $mainItem = null;


                if (!empty($item['id'])) {

                    $mainItem = MenuItem::query()
                        ->where('id', $item['id'])
                        ->where('menu_id', $menu->id)
                        ->where('item_type', 'Main')
                        ->first();
                }

                if (!$mainItem) {

                    $mainItem = MenuItem::create([
                        'menu_id' => $menu->id,
                        'Item_name' => trim($item['item_name']),
                        'item_type' => 'Main',
                        'alternative_of' => null,
                    ]);
                } else {

                    $mainItem->update([
                        'Item_name' => trim($item['item_name']),
                    ]);
                }

                $alternative = MenuItem::query()
                    ->where('menu_id', $menu->id)
                    ->where('item_type', 'Alternative')
                    ->where(
                        'alternative_of',
                        $mainItem->id
                    )
                    ->first();


                $alternateName = isset(
                    $item['alternate_item_name']
                )
                    ? trim($item['alternate_item_name'])
                    : '';


                if ($alternateName !== '') {

                    if ($alternative) {


                        $alternative->update([
                            'Item_name' => $alternateName,
                        ]);

                    } else {

                        MenuItem::create([
                            'menu_id' => $menu->id,
                            'Item_name' => $alternateName,
                            'item_type' => 'Alternative',
                            'alternative_of' => $mainItem->id,
                        ]);
                    }

                } else {

                    if ($alternative) {
                        $alternative->delete();
                    }
                }
            }
        });


        return response()->json([
            'success' => true,
            'message' => 'Menu updated successfully.',
            'data' => $menu->fresh()->load('mealType'),
        ]);
    }


    public function destroy(Menu $menu)
    {
        DB::transaction(function () use ($menu) {
            MenuItem::query()
                ->where('menu_id', $menu->id)
                ->delete();
            $menu->delete();
        });


        return response()->json([
            'success' => true,
            'message' => 'Menu deleted successfully.',
        ]);
    }

    public function todayMenus(Request $request)
    {
        $today = now()->toDateString();

        $menus = Menu::query()
            ->with(['mealType', 'items'])
            ->whereDate('menu_date', $today)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $menus,
        ]);
    }
}