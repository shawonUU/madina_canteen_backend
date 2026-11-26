<?php

namespace Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Approval\Services\ApprovalService;
use Modules\Canteen\Models\EmployeeMeal;
use Modules\Canteen\Models\EmployeeMealItem;
use Modules\Canteen\Models\MealMenu;
use Modules\Canteen\Models\MealType;
use Modules\Canteen\Models\MenuItem;

class MealMenuController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

    public function index(Request $request)
    {
        $query = MealMenu::query()
            ->with(['mealType', 'items']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
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
                $q->where(
                    'name',
                    'like',
                    "%{$search}%"
                );
            });
        }

        $menus = $query
            ->orderByDesc('menu_date')
            ->orderBy('meal_type_id')
            ->get();

        $menus->each(function ($menu) {
            $items = $menu->items->sortBy('id')->values();

            $mainItems = $items
                ->where('item_type', 'Main')
                ->values();

            $menu->setRelation('items', $mainItems->map(
                function ($main) use ($items) {

                    $alternative = $items
                        ->where('item_type', 'Alternative')
                        ->where(
                            'alternative_of',
                            $main->id
                        )
                        ->first();

                    return [
                        'id' => $main->id,
                        'name' => $main->name,
                        'item_type' => $main->item_type,

                        'alternate' => $alternative
                            ? [
                                'id' => $alternative->id,
                                'name' =>
                                    $alternative->name,
                                'item_type' =>
                                    $alternative->item_type,
                                'alternative_of' =>
                                    $alternative->alternative_of,
                            ]
                            : null,
                    ];
                }
            )->values());
        });

        return response()->json([
            'success' => true,
            'data' => $menus,
        ]);
    }

    public function approvedMenus(Request $request)
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'meal_type_id' => ['nullable', 'integer', 'exists:meal_types,id'],
        ]);

        $menus = MealMenu::query()
            ->select(['id', 'menu_date', 'meal_type_id', 'status'])
            ->where('status', 'Approved')
            ->with('items:id,menu_id,name,item_type,alternative_of')
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query->whereDate('menu_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query->whereDate('menu_date', '<=', $date))
            ->when($filters['meal_type_id'] ?? null, fn ($query, $id) => $query->where('meal_type_id', $id))
            ->orderBy('menu_date')
            ->orderBy('meal_type_id')
            ->get();

        return response()->json(['success' => true, 'data' => $menus]);
    }


    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

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

            'items.*.name' => [
                'required',
                'string',
                'max:255',
            ],

            'items.*.alternate_name' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | Check duplicate menu
        |--------------------------------------------------------------------------
        */

        $alreadyExists = MealMenu::query()
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


        /*
        |--------------------------------------------------------------------------
        | Create MealMenu
        |--------------------------------------------------------------------------
        */

        $menu = DB::transaction(function () use ($validated) {

            $menu = MealMenu::create([
                'code' => getGenerateCode(MealMenu::class, 'code', 'MMN', 8),
                'menu_date' =>
                    $validated['menu_date'],

                'meal_type_id' =>
                    $validated['meal_type_id'],

                'created_by' =>
                    auth()->id(),
            ]);


            foreach ($validated['items'] as $item) {

                $mainItem = MenuItem::create([
                    'code' => getGenerateCode(MenuItem::class, 'code', 'MNI', 8),
                    'menu_id' =>
                        $menu->id,

                    'name' =>
                        trim(
                            $item['name']
                        ),

                    'item_type' =>
                        'Main',

                    'alternative_of' =>
                        null,
                ]);


                /*
                |--------------------------------------------------------------------------
                | Alternative Item
                |--------------------------------------------------------------------------
                */

                if (
                    !empty(
                        $item['alternate_name']
                    )
                    &&
                    trim(
                        $item['alternate_name']
                    ) !== ''
                ) {

                    MenuItem::create([
                        'code' => getGenerateCode(MenuItem::class, 'code', 'MNI', 8),
                        'menu_id' =>
                            $menu->id,

                        'name' =>
                            trim(
                                $item[
                                    'alternate_name'
                                ]
                            ),

                        'item_type' =>
                            'Alternative',

                        'alternative_of' =>
                            $mainItem->id,
                    ]);
                }
            }


            $this->createAdvanceBookingsForMenu($menu);

            app(ApprovalService::class)->submit($menu);

            return $menu;
        });


        /*
        |--------------------------------------------------------------------------
        | AUTO CREATE EMPLOYEE MEALS FROM ADVANCE BOOKINGS
        |--------------------------------------------------------------------------
        */

       


        return response()->json([
            'success' => true,

            'message' =>
                'MealMenu created successfully.',

            'data' =>
                $menu->load('mealType'),
        ], 201);
    }


    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */

public function show($id)
{
    $menu = MealMenu::with('mealType')->findOrFail($id);

    $items = MenuItem::where('menu_id', $menu->id)
        ->orderBy('id')
        ->get();

    $mainItems = $items
        ->where('item_type', 'Main')
        ->values();

    $formattedItems = $mainItems->map(function ($main) use ($items) {

        $alternative = $items
            ->where('item_type', 'Alternative')
            ->where('alternative_of', $main->id)
            ->first();

        return [
            'id' => $main->id,
            'name' => $main->name,
            'item_type' => $main->item_type,
            'alternate' => $alternative
                ? [
                    'id' => $alternative->id,
                    'name' => $alternative->name,
                    'item_type' => $alternative->item_type,
                    'alternative_of' => $alternative->alternative_of,
                ]
                : null,
        ];
    })->values();

    return response()->json([
        'success' => true,
        'data' => [
            'id' => $menu->id,
            'meal_type_id' => $menu->meal_type_id,
            'menu_date' => $menu->menu_date,
            'mealType' => $menu->mealType,
            'items' => $formattedItems,
        ],
    ]);
}
    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Request $request, $id)
    {
        $menu = MealMenu::findOrFail($id);

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

            'items.*.name' => [
                'required',
                'string',
                'max:255',
            ],

            'items.*.alternate_name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'items.*.alternate_id' => [
                'nullable',
                'integer',
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | Check Duplicate MealMenu
        |--------------------------------------------------------------------------
        */

        $duplicate = MealMenu::query()
            ->whereDate('menu_date', $validated['menu_date'])
            ->where('meal_type_id', $validated['meal_type_id'])
            ->where('id', '!=', $menu->id)
            ->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'menu_date' => [
                    "Another menu already exists. Current ID: {$menu->id}, Duplicate ID: {$duplicate->id}",
                ],
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Store Old MealMenu Information
        |--------------------------------------------------------------------------
        |
        | We need the old date + meal type because admin may change:
        |
        | 01 Sep Lunch
        |        ↓
        | 02 Sep Dinner
        |
        */

        $oldMenuDate = $menu->menu_date instanceof \Carbon\Carbon
            ? $menu->menu_date->format('Y-m-d')
            : substr(
                (string) $menu->menu_date,
                0,
                10
            );

        $oldMealTypeId =
            (int) $menu->meal_type_id;

        if (($oldMenuDate !== $validated['menu_date'] || $oldMealTypeId !== (int) $validated['meal_type_id']) && EmployeeMeal::where('menu_id', $menu->id)->exists()) {
            throw ValidationException::withMessages(['menu_date' => 'The date and meal type cannot change after this menu has bookings.']);
        }
        $submittedIds = collect($validated['items'])->pluck('id')->filter()->map(fn ($id) => (int) $id);
        if ($submittedIds->isNotEmpty() && $menu->items()->where('item_type', 'Main')->whereIn('id', $submittedIds)->count() !== $submittedIds->unique()->count()) {
            throw ValidationException::withMessages(['items' => 'One or more items do not belong to this menu.']);
        }


        /*
        |--------------------------------------------------------------------------
        | Update MealMenu + Items
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use (
            $validated,
            $menu
        ) {

            $menu->update([
                'menu_date' =>
                    $validated['menu_date'],

                'meal_type_id' =>
                    $validated['meal_type_id'],
            ]);


            /*
            |--------------------------------------------------------------------------
            | Existing Main Items
            |--------------------------------------------------------------------------
            */

            $existingMainIds = MenuItem::query()
                ->where(
                    'menu_id',
                    $menu->id
                )
                ->where(
                    'item_type',
                    'Main'
                )
                ->pluck('id')
                ->toArray();


            /*
            |--------------------------------------------------------------------------
            | Submitted Main Items
            |--------------------------------------------------------------------------
            */

            $submittedMainIds = collect(
                $validated['items']
            )
                ->pluck('id')
                ->filter()
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->toArray();


            /*
            |--------------------------------------------------------------------------
            | Delete Removed Main Items
            |--------------------------------------------------------------------------
            */

            $removedMainIds = array_diff(
                $existingMainIds,
                $submittedMainIds
            );


            foreach (
                $removedMainIds
                as $removedMainId
            ) {

                /*
                |--------------------------------------------------------------------------
                | Delete Alternative
                |--------------------------------------------------------------------------
                */

                $removedItems = MenuItem::where('id', $removedMainId)->orWhere('alternative_of', $removedMainId)->get();
                if (EmployeeMealItem::whereIn('menu_item_id', $removedItems->pluck('id'))->exists()) {
                    throw ValidationException::withMessages(['items' => 'A booked menu item cannot be removed.']);
                }
                $removedItems->sortByDesc('alternative_of')->each->delete();


                /*
                |--------------------------------------------------------------------------
                | Delete Main
                |--------------------------------------------------------------------------
                */

                MenuItem::query()
                    ->where(
                        'id',
                        $removedMainId
                    )
                    ->where(
                        'menu_id',
                        $menu->id
                    )
                    ->get()->each->delete();
            }


            /*
            |--------------------------------------------------------------------------
            | Create / Update Items
            |--------------------------------------------------------------------------
            */

            foreach (
                $validated['items']
                as $item
            ) {

                $mainItem = null;


                /*
                |--------------------------------------------------------------------------
                | Find Existing Main Item
                |--------------------------------------------------------------------------
                */

                if (
                    !empty(
                        $item['id']
                    )
                ) {

                    $mainItem =
                        MenuItem::query()
                            ->where(
                                'id',
                                $item['id']
                            )
                            ->where(
                                'menu_id',
                                $menu->id
                            )
                            ->where(
                                'item_type',
                                'Main'
                            )
                            ->first();
                }


                /*
                |--------------------------------------------------------------------------
                | Create New Main Item
                |--------------------------------------------------------------------------
                */

                if (!$mainItem) {

                    $mainItem =
                        MenuItem::create([
                            'code' => getGenerateCode(MenuItem::class, 'code', 'MNI', 8),
                            'menu_id' =>
                                $menu->id,

                            'name' =>
                                trim(
                                    $item[
                                        'name'
                                    ]
                                ),

                            'item_type' =>
                                'Main',

                            'alternative_of' =>
                                null,
                        ]);

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Update Existing Main Item
                    |--------------------------------------------------------------------------
                    */

                    $mainItem->update([
                        'name' =>
                            trim(
                                $item[
                                    'name'
                                ]
                            ),
                    ]);
                }


                /*
                |--------------------------------------------------------------------------
                | Find Alternative
                |--------------------------------------------------------------------------
                */

                $alternative =
                    MenuItem::query()
                        ->where(
                            'menu_id',
                            $menu->id
                        )
                        ->where(
                            'item_type',
                            'Alternative'
                        )
                        ->where(
                            'alternative_of',
                            $mainItem->id
                        )
                        ->first();


                $alternateName =
                    isset(
                        $item[
                            'alternate_name'
                        ]
                    )
                        ? trim(
                            $item[
                                'alternate_name'
                            ]
                        )
                        : '';


                /*
                |--------------------------------------------------------------------------
                | Create / Update Alternative
                |--------------------------------------------------------------------------
                */

                if (
                    $alternateName !== ''
                ) {

                    if ($alternative) {

                        $alternative->update([
                            'name' =>
                                $alternateName,
                        ]);

                    } else {

                        MenuItem::create([
                            'code' => getGenerateCode(MenuItem::class, 'code', 'MNI', 8),
                            'menu_id' =>
                                $menu->id,

                            'name' =>
                                $alternateName,

                            'item_type' =>
                                'Alternative',

                            'alternative_of' =>
                                $mainItem->id,
                        ]);
                    }

                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Remove Alternative
                    |--------------------------------------------------------------------------
                    */

                    if ($alternative) {
                        if (EmployeeMealItem::where('menu_item_id', $alternative->id)->exists()) {
                            throw ValidationException::withMessages(['items' => 'A booked alternative cannot be removed.']);
                        }
                        $alternative->delete();
                    }
                }
            }
            if ($menu->approval_status === 'Returned') {
                app(ApprovalService::class)->submit($menu);
            }
        });


        /*
        |--------------------------------------------------------------------------
        | MENU DATE / MEAL TYPE CHANGED
        |--------------------------------------------------------------------------
        */

        $newMenuDate =
            $validated['menu_date'];

        $newMealTypeId =
            (int) $validated['meal_type_id'];


        $this->createAdvanceBookingsForMenu( $menu->fresh() );


        return response()->json([
            'success' => true,

            'message' =>
                'MealMenu updated successfully.',

            'data' =>
                $menu
                    ->fresh()
                    ->load('mealType'),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | DESTROY
    |--------------------------------------------------------------------------
    */

    public function destroy($id)
    {
        $menu = MealMenu::findOrFail($id);
        DB::transaction(function () use ($menu) {
            if (EmployeeMeal::where('menu_id', $menu->id)->exists()) {
                throw ValidationException::withMessages(['menu' => 'This menu has bookings and cannot be deleted.']);
            }
            $menu->items()->orderByDesc('alternative_of')->get()->each->delete();
            $menu->delete();
        });
        return response()->json(['success' => true, 'message' => 'Meal menu deleted successfully.']);
    }

    public function todayMenus(Request $request)
    {
        $today =
            now()->toDateString();

        $menus = MealMenu::query()
            ->with([
                'mealType',
                'items',
            ])
            ->whereDate(
                'menu_date',
                $today
            )
            ->get();

        return response()->json([
            'success' => true,
            'data' => $menus,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | CREATE EMPLOYEE MEALS FROM ADVANCE BOOKINGS
    |--------------------------------------------------------------------------
    |
    | This method is called automatically when:
    |
    | 1. New menu is created
    | 2. Existing menu is updated
    |
    | Advance booking remains permanent.
    |
    */

    private function createAdvanceBookingsForMenu(MealMenu $menu): void
    {
        // Existing bookings are explicit; publishing a menu does not create extra bookings.
    }
}
