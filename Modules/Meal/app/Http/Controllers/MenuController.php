<?php

namespace Modules\Meal\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
// use Modules\Meal\Models\AdvanceMealBooking;
use Modules\Meal\Models\EmployeeMeal;
use Modules\Meal\Models\EmployeeMealItem;
use Modules\Meal\Models\MealType;
use Modules\Meal\Models\Menu;
use Modules\Meal\Models\MenuItem;

use Illuminate\Support\Facades\Auth;
class MenuController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */

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
            $items = MenuItem::query()
                ->where('menu_id', $menu->id)
                ->orderBy('id')
                ->get();

            $mainItems = $items
                ->where('item_type', 'Main')
                ->values();

            $menu->items = $mainItems->map(
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
            )->values();
        });

        return response()->json([
            'success' => true,
            'data' => $menus,
        ]);
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


        /*
        |--------------------------------------------------------------------------
        | Create Menu
        |--------------------------------------------------------------------------
        */

        $menu = DB::transaction(function () use ($validated) {

            $menu = Menu::create([
                'menu_date' =>
                    $validated['menu_date'],

                'meal_type_id' =>
                    $validated['meal_type_id'],

                'created_by' =>
                    auth()->id(),
            ]);


            foreach ($validated['items'] as $item) {

                $mainItem = MenuItem::create([
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


            return $menu;
        });


        /*
        |--------------------------------------------------------------------------
        | AUTO CREATE EMPLOYEE MEALS FROM ADVANCE BOOKINGS
        |--------------------------------------------------------------------------
        */

        $this->createAdvanceBookingsForMenu($menu);


        return response()->json([
            'success' => true,

            'message' =>
                'Menu created successfully.',

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
    $menu = Menu::with('mealType')->findOrFail($id);

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
        $menu = Menu::findOrFail($id);

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
        | Check Duplicate Menu
        |--------------------------------------------------------------------------
        */

        $duplicate = Menu::query()
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
        | Store Old Menu Information
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


        /*
        |--------------------------------------------------------------------------
        | Update Menu + Items
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

                MenuItem::query()
                    ->where(
                        'alternative_of',
                        $removedMainId
                    )
                    ->delete();


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
                    ->delete();
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
                        $alternative->delete();
                    }
                }
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


        if (
            $oldMenuDate !== $newMenuDate ||
            $oldMealTypeId !== $newMealTypeId
        ) {

            /*
            |--------------------------------------------------------------------------
            | Remove Old Generated Employee Meals
            |--------------------------------------------------------------------------
            */

            EmployeeMeal::query()
                ->where(
                    'menu_id',
                    $menu->id
                )
                ->where(
                    'meal_type_id',
                    $oldMealTypeId
                )
                ->whereDate(
                    'booking_date',
                    $oldMenuDate
                )
                ->delete();
        }


        /*
        |--------------------------------------------------------------------------
        | Sync Current Menu With Advance Bookings
        |--------------------------------------------------------------------------
        */

        $this->createAdvanceBookingsForMenu( $menu->fresh() );


        return response()->json([
            'success' => true,

            'message' =>
                'Menu updated successfully.',

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

    public function destroy(Menu $menu)
    {
        /*
        |--------------------------------------------------------------------------
        | Remove Actual Employee Meals
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        | Advance bookings are NOT deleted.
        |
        */

        EmployeeMeal::query()
            ->where(
                'menu_id',
                $menu->id
            )
            ->delete();


        /*
        |--------------------------------------------------------------------------
        | Delete Menu Items + Menu
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use ($menu) {

            MenuItem::query()
                ->where(
                    'menu_id',
                    $menu->id
                )
                ->delete();

            $menu->delete();
        });


        return response()->json([
            'success' => true,

            'message' =>
                'Menu deleted successfully. Advance bookings have been preserved.',
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | TODAY MENUS
    |--------------------------------------------------------------------------
    */

    public function todayMenus(Request $request)
    {
        $today =
            now()->toDateString();

        $menus = Menu::query()
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

    private function createAdvanceBookingsForMenu( Menu $menu ): void {

        // /*
        // |--------------------------------------------------------------------------
        // | Get Active Advance Bookings
        // |--------------------------------------------------------------------------
        // */
        // // dd($menu);

        // $mealType = MealType::query()->find($menu->meal_type_id);

        // $advanceBookings =
        //     AdvanceMealBooking::query()
        //         ->whereDate(
        //             'booking_date',
        //             $menu->menu_date
        //         )
        //         ->where(
        //             'meal_type_id',
        //             $menu->meal_type_id
        //         )
        //         ->where(
        //             'status',
        //             'Booked'
        //         )
        //         ->get();


        // /*
        // |--------------------------------------------------------------------------
        // | Create Employee Meal
        // |--------------------------------------------------------------------------
        // */

        // // dd($advanceBookings);

        // foreach (
        //     $advanceBookings
        //     as $advanceBooking
        // ) {
            

        //     /*
        //     |--------------------------------------------------------------------------
        //     | Prevent Duplicate Booking
        //     |--------------------------------------------------------------------------
        //     */

        //     $employeeMeal =
        //         EmployeeMeal::firstOrCreate(
        //             [
        //                 'employee_id' =>
        //                     $advanceBooking->employee_id,

        //                 'menu_id' =>
        //                     $menu->id,

        //                 'meal_type_id' =>
        //                     $menu->meal_type_id,

        //                 'meal_date' =>
        //                     $menu->menu_date,

        //                     'total_amount' =>
        //                         $mealType->meal_rate,
        //             ],
        //             [
        //                 'status' => 'Selected',
        //             ]
        //         );

        //     EmployeeMealItem::where(
        //             'employee_meal_id',
        //             $employeeMeal->id
        //         )->delete();

                

        //     foreach ( $menu->items as $item ) {

        //         if($item->item_type === 'Alternative') { continue; }

        //         EmployeeMealItem::create([
        //             'employee_meal_id' =>
        //                 $employeeMeal->id,

        //             'menu_item_id' =>
        //                 $item->id,
        //             'created_by' => Auth::user()->id,
        //         ]);
        //     }


        //     /*
        //     |--------------------------------------------------------------------------
        //     | If Employee Meal Already Exists
        //     |--------------------------------------------------------------------------
        //     */

        //     if (
        //         $employeeMeal->status !== 'Cancelled'
        //     ) {

        //         $employeeMeal->update([
        //             'status' =>
        //                 'Selected',
        //         ]);
        //     }
        // }


        // /*
        // |--------------------------------------------------------------------------
        // | Remove Employee Meals For Cancelled
        // | Advance Bookings
        // |--------------------------------------------------------------------------
        // */

        // $cancelledEmployeeIds =
        //     AdvanceMealBooking::query()
        //         ->whereDate(
        //             'booking_date',
        //             $menu->menu_date
        //         )
        //         ->where(
        //             'meal_type_id',
        //             $menu->meal_type_id
        //         )
        //         ->where(
        //             'status',
        //             'Cancelled'
        //         )
        //         ->pluck(
        //             'employee_id'
        //         );


        // if (
        //     $cancelledEmployeeIds->isNotEmpty()
        // ) {

        //     EmployeeMeal::query()
        //         ->where(
        //             'menu_id',
        //             $menu->id
        //         )
        //         ->where(
        //             'meal_type_id',
        //             $menu->meal_type_id
        //         )
        //         ->whereDate(
        //             'meal_date',
        //             $menu->menu_date
        //         )
        //         ->whereIn(
        //             'employee_id',
        //             $cancelledEmployeeIds
        //         )
        //         ->delete();
        // }
    }
}

