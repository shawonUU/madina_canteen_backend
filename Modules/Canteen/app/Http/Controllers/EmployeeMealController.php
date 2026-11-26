<?php

namespace Modules\Canteen\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AccessService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\HRM\Models\Employee;
use Modules\Canteen\Services\MealRateService;
use Modules\Canteen\Models\EmployeeMeal;
use Modules\Canteen\Models\EmployeeMealItem;
use Modules\Canteen\Models\MealType;
use Modules\Canteen\Models\MealMenu;

use Carbon\Carbon;
class EmployeeMealController extends Controller
{
    public function bookMeal(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                'exists:employees,id',
            ],

            'meal_type_id' => [
                'required',
                'integer',
                'exists:meal_types,id',
            ],

            'booking_date' => [
                'required',
                'date',
            ],

            'menu_id' => [
                'nullable',
                'integer',
                'exists:meal_menus,id',
            ],

            'items' => [
                'nullable',
                'array',
            ],

            'items.*.main_item_id' => [
                'nullable',
                'integer',
                'exists:menu_items,id',
            ],

            'items.*.selected_item_id' => [
                'required_with:items',
                'integer',
                'exists:menu_items,id',
            ],
        ]);

        $this->authorizeEmployee($request, (int) $validated['employee_id']);

        /*
        |--------------------------------------------------------------------------
        | Meal Type
        |--------------------------------------------------------------------------
        */

        $mealType = MealType::query()
            ->findOrFail($validated['meal_type_id']);

        /*
        |--------------------------------------------------------------------------
        | Menu
        |--------------------------------------------------------------------------
        |
        | Menu is optional.
        | Booking is allowed even if there is no menu.
        |
        */

        $menu = null;

        if (!empty($validated['menu_id'])) {
            $menu = MealMenu::query()
                ->where('id', $validated['menu_id'])
                ->where('status', 'Approved')
                ->where('meal_type_id', $validated['meal_type_id'])
                ->whereDate('menu_date', $validated['booking_date'])
                ->with('items')
                ->first();

            if (!$menu) {
                throw ValidationException::withMessages([
                    'menu_id' => [
                        'Choose an approved menu for this date and meal type.',
                    ],
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Booking
        |--------------------------------------------------------------------------
        |
        | Same employee cannot book same meal type twice
        | on the same date.
        |
        */

        $existingBooking = EmployeeMeal::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('meal_type_id', $validated['meal_type_id'])
            ->whereDate('meal_date', $validated['booking_date'])
            ->where('status', '!=', 'Cancelled')
            ->exists();

        if ($existingBooking) {
            throw ValidationException::withMessages([
                'booking_date' => [
                    'You have already booked this meal for the selected date.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Meal Rate
        |--------------------------------------------------------------------------
        */

        $mealRate = app(MealRateService::class)->forDate($mealType, $validated['booking_date'] ?? $validated['start_date']);

        $quantity = 1;

        $totalAmount = $mealRate * $quantity;

        /*
        |--------------------------------------------------------------------------
        | Selected Menu Items
        |--------------------------------------------------------------------------
        */

        $selectedItems = collect($validated['items'] ?? [])
            ->map(function ($item) {
                return [
                    'main_item_id' => !empty($item['main_item_id'])
                        ? (int) $item['main_item_id']
                        : null,

                    'selected_item_id' => (int) $item['selected_item_id'],
                ];
            })
            ->unique('selected_item_id')
            ->values();

        $selectedItemIds = $selectedItems
            ->pluck('selected_item_id')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Validate Items
        |--------------------------------------------------------------------------
        */

        $this->validateSelection($menu, $selectedItems);
        if ($selectedItemIds->isNotEmpty()) {

            if (!$menu) {
                throw ValidationException::withMessages([
                    'items' => [
                        'Menu items cannot be selected because no menu is assigned to this booking.',
                    ],
                ]);
            }

            $validItemIds = $menu->items()
                ->whereIn('id', $selectedItemIds)
                ->pluck('id');

            if ($validItemIds->count() !== $selectedItemIds->count()) {
                throw ValidationException::withMessages([
                    'items' => [
                        'One or more selected items do not belong to the selected menu.',
                    ],
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Create Booking
        |--------------------------------------------------------------------------
        */

        $employeeMeal = DB::transaction(function () use (
            $validated,
            $mealRate,
            $quantity,
            $totalAmount,
            $selectedItemIds
        ) {
            Employee::whereKey($validated['employee_id'])->lockForUpdate()->firstOrFail();
            $employeeMeal = $this->saveBooking([
                'code' => getGenerateCode(EmployeeMeal::class, 'code', 'EME', 8),
                'employee_id' => $validated['employee_id'],
                'meal_type_id' => $validated['meal_type_id'],
                'meal_rate' => $mealRate,
                'meal_date' => $validated['booking_date'],
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
                'menu_id' => $validated['menu_id'] ?? null,
                'remarks' => null,
                'status' => 'Selected',
                'created_by' => auth()->id(),
            ]);

            foreach ($selectedItemIds as $menuItemId) {
                EmployeeMealItem::create([
                    'code' => getGenerateCode(EmployeeMealItem::class, 'code', 'EMI', 8),
                    'employee_meal_id' => $employeeMeal->id,
                    'menu_item_id' => $menuItemId,
                    'created_by' => auth()->id(),
                ]);
            }

            return $employeeMeal;
        });

        return response()->json([
            'success' => true,
            'message' => 'Lunch booked successfully.',
            'data' => $employeeMeal->load([
                'items.menuItem',
                'menu.items',
                'mealType',
                'employee',
            ]),
        ], 201);
    }

    public function bookMeals(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                'exists:employees,id',
            ],

            'meal_type_id' => [
                'required',
                'integer',
                'exists:meal_types,id',
            ],

            'start_date' => [
                'required',
                'date',
            ],

            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            'bookings' => [
                'required',
                'array',
                'min:1',
            ],

            'bookings.*.booking_date' => [
                'required',
                'date',
            ],

            'bookings.*.menu_id' => [
                'nullable',
                'integer',
                'exists:meal_menus,id',
            ],

            'bookings.*.items' => [
                'nullable',
                'array',
            ],

            'bookings.*.items.*.main_item_id' => [
                'nullable',
                'integer',
                'exists:menu_items,id',
            ],

            'bookings.*.items.*.selected_item_id' => [
                'required_with:bookings.*.items',
                'integer',
                'exists:menu_items,id',
            ],
        ]);

        $this->authorizeEmployee($request, (int) $validated['employee_id']);

        $startDate = \Carbon\Carbon::parse($validated['start_date'])
            ->startOfDay();

        $endDate = \Carbon\Carbon::parse($validated['end_date'])
            ->startOfDay();

        $totalDays = $startDate->diffInDays($endDate) + 1;

        if ($totalDays > 90) {
            throw ValidationException::withMessages([
                'end_date' => [
                    'You can book maximum 90 days at a time.',
                ],
            ]);
        }

        $mealType = MealType::query()
            ->findOrFail($validated['meal_type_id']);

        $bookings = collect($validated['bookings']);

        /*
        |--------------------------------------------------------------------------
        | Validate Booking Dates
        |--------------------------------------------------------------------------
        */

        $bookingDates = $bookings
            ->pluck('booking_date')
            ->map(fn ($date) => \Carbon\Carbon::parse($date)->toDateString())
            ->values();

        if ($bookingDates->unique()->count() !== $bookingDates->count()) {
            throw ValidationException::withMessages([
                'bookings' => [
                    'Duplicate booking dates are not allowed.',
                ],
            ]);
        }

        foreach ($bookingDates as $bookingDate) {
            if (
                $bookingDate < $startDate->toDateString() ||
                $bookingDate > $endDate->toDateString()
            ) {
                throw ValidationException::withMessages([
                    'bookings' => [
                        'One or more booking dates are outside the selected date range.',
                    ],
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Existing Bookings
        |--------------------------------------------------------------------------
        */

        $existingDates = EmployeeMeal::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('meal_type_id', $validated['meal_type_id'])
            ->whereBetween('meal_date', [
                $startDate->toDateString(),
                $endDate->toDateString(),
            ])
            ->whereIn('meal_date', $bookingDates)
            ->where('status', '!=', 'Cancelled')
            ->pluck('meal_date')
            ->map(fn ($date) => \Carbon\Carbon::parse($date)->toDateString());

        if ($existingDates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'bookings' => [
                    'Meal already booked for: ' .
                    $existingDates->unique()->implode(', '),
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Meal Rate
        |--------------------------------------------------------------------------
        */

        $mealRate = $mealType->meal_rate ?? 0;

        $quantity = 1;

        $totalAmount = $mealRate * $quantity;

        /*
        |--------------------------------------------------------------------------
        | Validate Menus & Items Before Transaction
        |--------------------------------------------------------------------------
        */

        $preparedBookings = [];

        foreach ($bookings as $booking) {
            $bookingDate = \Carbon\Carbon::parse(
                $booking['booking_date']
            )->toDateString();

            $menu = null;

            if (!empty($booking['menu_id'])) {
                $menu = MealMenu::query()
                    ->where('id', $booking['menu_id'])
                    ->where('status', 'Approved')
                    ->where('meal_type_id', $validated['meal_type_id'])
                    ->whereDate('menu_date', $bookingDate)
                    ->with('items')
                    ->first();

                if (!$menu) {
                    throw ValidationException::withMessages([
                        'bookings' => [
                            "Choose an approved menu for {$bookingDate}.",
                        ],
                    ]);
                }
            }

            $items = collect($booking['items'] ?? [])
                ->map(function ($item) {
                    return [
                        'main_item_id' => !empty($item['main_item_id'])
                            ? (int) $item['main_item_id']
                            : null,

                        'selected_item_id' => (int) $item['selected_item_id'],
                    ];
                })
                ->unique('selected_item_id')
                ->values();

            $selectedItemIds = $items
                ->pluck('selected_item_id')
                ->values();

            /*
            |--------------------------------------------------------------------------
            | Validate Selected Items
            |--------------------------------------------------------------------------
            */

            $this->validateSelection($menu, $items);
        if ($selectedItemIds->isNotEmpty()) {
                if (!$menu) {
                    throw ValidationException::withMessages([
                        'bookings' => [
                            "Menu items cannot be selected for {$bookingDate} because no menu is assigned.",
                        ],
                    ]);
                }

                $menuItems = $menu->items;

                $validItemIds = $menuItems
                    ->whereIn('id', $selectedItemIds)
                    ->pluck('id');

                if (
                    $validItemIds->count() !==
                    $selectedItemIds->count()
                ) {
                    throw ValidationException::withMessages([
                        'bookings' => [
                            "One or more selected items do not belong to the menu of {$bookingDate}.",
                        ],
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Validate Main / Alternative Relationship
                |--------------------------------------------------------------------------
                */

                foreach ($items as $item) {
                    $mainItemId = $item['main_item_id'];
                    $selectedItemId = $item['selected_item_id'];

                    if (!$mainItemId) {
                        continue;
                    }

                    $selectedItem = $menuItems
                        ->firstWhere('id', $selectedItemId);

                    if (!$selectedItem) {
                        throw ValidationException::withMessages([
                            'bookings' => [
                                "Selected item is invalid for {$bookingDate}.",
                            ],
                        ]);
                    }

                    $isValidSelection =
                        (int) $selectedItem->id === (int) $mainItemId
                        ||
                        (
                            $selectedItem->item_type === 'Alternative'
                            &&
                            (int) $selectedItem->alternative_of === (int) $mainItemId
                        );

                    if (!$isValidSelection) {
                        throw ValidationException::withMessages([
                            'bookings' => [
                                "Invalid menu combination selected for {$bookingDate}.",
                            ],
                        ]);
                    }
                }
            }

            $preparedBookings[] = [
                'booking_date' => $bookingDate,
                'menu_id' => $menu?->id,
                'items' => $items,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Create All Bookings In One Transaction
        |--------------------------------------------------------------------------
        */

        $createdBookings = DB::transaction(function () use (
            $preparedBookings,
            $validated,
            $mealRate,
            $quantity,
            $totalAmount
        ) {
            $createdBookings = [];

            Employee::whereKey($validated['employee_id'])->lockForUpdate()->firstOrFail();
            $mealType = MealType::findOrFail($validated['meal_type_id']);
            foreach ($preparedBookings as $booking) {
                $mealRate = app(MealRateService::class)->forDate($mealType, $booking['booking_date']);
                $totalAmount = $mealRate * $quantity;
                $employeeMeal = $this->saveBooking([
                    'code' => getGenerateCode(EmployeeMeal::class, 'code', 'EME', 8),
                    'employee_id' => $validated['employee_id'],
                    'meal_type_id' => $validated['meal_type_id'],
                    'meal_rate' => $mealRate,
                    'meal_date' => $booking['booking_date'],
                    'quantity' => $quantity,
                    'total_amount' => $totalAmount,
                    'menu_id' => $booking['menu_id'],
                    'remarks' => null,
                    'status' => 'Selected',
                    'created_by' => auth()->id(),
                ]);

                foreach ($booking['items'] as $item) {
                    EmployeeMealItem::create([
                        'code' => getGenerateCode(EmployeeMealItem::class, 'code', 'EMI', 8),
                        'employee_meal_id' => $employeeMeal->id,
                        'menu_item_id' => $item['selected_item_id'],
                        'created_by' => auth()->id(),
                    ]);
                }

                $createdBookings[] = $employeeMeal;
            }

            return $createdBookings;
        });

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        $createdBookings = collect($createdBookings)
            ->map(function ($booking) {
                return $booking->load([
                    'items.menuItem',
                    'menu.items',
                    'mealType',
                    'employee',
                ]);
            });

        return response()->json([
            'success' => true,
            'message' => $createdBookings->count() . ' lunch bookings created successfully.',
            'data' => $createdBookings,
        ], 201);
    }

    public function updateBookingMeal(Request $request, $id)
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
                'exists:employees,id',
            ],

            'meal_type_id' => [
                'required',
                'integer',
                'exists:meal_types,id',
            ],

            'booking_date' => [
                'required',
                'date',
            ],

            'menu_id' => [
                'nullable',
                'integer',
                'exists:meal_menus,id',
            ],

            'items' => [
                'nullable',
                'array',
            ],

            'items.*.main_item_id' => [
                'nullable',
                'integer',
                'exists:menu_items,id',
            ],

            'items.*.selected_item_id' => [
                'required_with:items',
                'integer',
                'exists:menu_items,id',
            ],
        ]);

        $this->authorizeEmployee($request, (int) $validated['employee_id']);

        $employeeMeal = $this->accessibleBookings($request)
            ->find($id);

        if (!$employeeMeal) {
            return response()->json([
                'success' => false,
                'message' => 'Meal booking not found.',
            ], 404);
        }

        if (in_array($employeeMeal->status, ['Cancelled', 'Served'])) {
            throw ValidationException::withMessages([
                'status' => [
                    "A {$employeeMeal->status} meal booking cannot be updated.",
                ],
            ]);
        }

        $menu = null;

        if (!empty($validated['menu_id'])) {
            $menu = MealMenu::query()
                ->where('id', $validated['menu_id'])
                ->where('status', 'Approved')
                ->where('meal_type_id', $validated['meal_type_id'])
                ->whereDate('menu_date', $validated['booking_date'])
                ->with('items')
                ->first();

            if (!$menu) {
                throw ValidationException::withMessages([
                    'menu_id' => [
                        'Choose an approved menu for this date and meal type.',
                    ],
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Booking
        |--------------------------------------------------------------------------
        */

        $duplicateBooking = EmployeeMeal::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('meal_type_id', $validated['meal_type_id'])
            ->whereDate('meal_date', $validated['booking_date'])
            ->where('id', '!=', $employeeMeal->id)
            ->exists();

        if ($duplicateBooking) {
            throw ValidationException::withMessages([
                'booking_date' => [
                    'You have already booked this meal for the selected date.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Meal Rate
        |--------------------------------------------------------------------------
        */

        $mealType = MealType::query()
            ->findOrFail($validated['meal_type_id']);

        $mealRate = ((int) $employeeMeal->meal_type_id === (int) $validated['meal_type_id'] && substr((string) $employeeMeal->meal_date, 0, 10) === $validated['booking_date'])
            ? (float) $employeeMeal->meal_rate
            : app(MealRateService::class)->forDate($mealType, $validated['booking_date']);

        $quantity = 1;

        $totalAmount = $mealRate * $quantity;

        /*
        |--------------------------------------------------------------------------
        | Selected Items
        |--------------------------------------------------------------------------
        */

        $selectedItems = collect($validated['items'] ?? [])
            ->map(function ($item) {
                return [
                    'main_item_id' => !empty($item['main_item_id'])
                        ? (int) $item['main_item_id']
                        : null,

                    'selected_item_id' => (int) $item['selected_item_id'],
                ];
            })
            ->unique('selected_item_id')
            ->values();

        $selectedItemIds = $selectedItems
            ->pluck('selected_item_id')
            ->values();

        $this->validateSelection($menu, $selectedItems);
        if ($selectedItemIds->isNotEmpty()) {

            if (!$menu) {
                throw ValidationException::withMessages([
                    'items' => [
                        'Menu items cannot be selected because no menu is assigned to this booking.',
                    ],
                ]);
            }

            $validItemIds = $menu->items()
                ->whereIn('id', $selectedItemIds)
                ->pluck('id');

            if ($validItemIds->count() !== $selectedItemIds->count()) {
                throw ValidationException::withMessages([
                    'items' => [
                        'One or more selected items do not belong to the selected menu.',
                    ],
                ]);
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Update Booking
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use (
            $request,
            $employeeMeal,
            $validated,
            $mealRate,
            $quantity,
            $totalAmount,
            $selectedItemIds
        ) {
            Employee::whereKey($validated['employee_id'])->lockForUpdate()->firstOrFail();
            $employeeMeal = $this->accessibleBookings($request)->whereKey($employeeMeal->id)->lockForUpdate()->firstOrFail();
            if (EmployeeMeal::where('employee_id', $validated['employee_id'])->where('meal_type_id', $validated['meal_type_id'])->whereDate('meal_date', $validated['booking_date'])->where('id', '<>', $employeeMeal->id)->exists()) {
                throw ValidationException::withMessages(['booking_date' => 'A booking already exists for this meal and date.']);
            }
            if ($employeeMeal->status !== 'Selected') {
                throw ValidationException::withMessages(['status' => 'This booking can no longer be edited.']);
            }
            $employeeMeal->update([
                'employee_id' => $validated['employee_id'],
                'meal_type_id' => $validated['meal_type_id'],
                'meal_rate' => $mealRate,
                'meal_date' => $validated['booking_date'],
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
                'menu_id' => $validated['menu_id'] ?? null,
            ]);

            EmployeeMealItem::query()
                ->where('employee_meal_id', $employeeMeal->id)
                ->get()->each->delete();

            foreach ($selectedItemIds as $menuItemId) {
                EmployeeMealItem::create([
                    'code' => getGenerateCode(EmployeeMealItem::class, 'code', 'EMI', 8),
                    'employee_meal_id' => $employeeMeal->id,
                    'menu_item_id' => $menuItemId,
                    'created_by' => auth()->id(),
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Lunch booking updated successfully.',
            'data' => $employeeMeal->fresh()->load([
                'items.menuItem',
                'menu.items',
                'mealType',
                'employee',
            ]),
        ]);
    }

    public function todaysBookedMeals(Request $request)
    {
        $today = now()->toDateString();

        $bookedMeals = EmployeeMeal::query()
            ->whereDate('meal_date', $today)
            ->with([
                'items.menuItem',
                'menu.items',
                'mealType',
                'employee',
            ])
            ->get();

        return response()->json([
            'success' => true,
            'message' => "Today's booked meals retrieved successfully.",
            'data' => $bookedMeals,
        ]);
    }

    public function myBookings(Request $request)
    {
        $data = $request->validate([
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'meal_type_id' => ['nullable', 'integer', 'exists:meal_types,id'],
        ]);
        $employeeId = $request->user()->employee_id;
        if (!$employeeId) {
            throw ValidationException::withMessages(['employee' => 'Your account is not linked to an employee.']);
        }
        $query = EmployeeMeal::where('employee_id', $employeeId)->with(['mealType', 'items.menuItem', 'menu.items']);
        if (!empty($data['date_from'])) $query->whereDate('meal_date', '>=', $data['date_from']);
        if (!empty($data['date_to'])) $query->whereDate('meal_date', '<=', $data['date_to']);
        if (!empty($data['meal_type_id'])) $query->where('meal_type_id', $data['meal_type_id']);
        return response()->json(['success' => true, 'data' => $query->orderByDesc('meal_date')->orderByDesc('id')->get()]);
    }

    public function cancelBooking(Request $request, $id)
    {
        $booking = DB::transaction(function () use ($request, $id) {
            $booking = $this->accessibleBookings($request)->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($booking->status !== 'Selected') {
                throw ValidationException::withMessages(['status' => 'Only a selected booking can be cancelled.']);
            }
            $booking->update(['status' => 'Cancelled']);
            return $booking->fresh();
        });
        return response()->json(['success' => true, 'message' => 'Booking cancelled successfully.', 'data' => $booking]);
    }

    public function serveMeal($id)
    {
        $employeeMeal = DB::transaction(function () use ($id) {
            $booking = EmployeeMeal::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($booking->status !== 'Selected') {
                throw ValidationException::withMessages(['status' => 'Only a selected meal can be served.']);
            }
            $booking->update(['status' => 'Served']);
            return $booking->fresh();
        });
        return response()->json(['success' => true, 'message' => 'Meal served successfully.', 'data' => $employeeMeal]);
    }

    private function authorizeEmployee(Request $request, int $employeeId): void
    {
        abort_unless(
            (int) $request->user()->employee_id === $employeeId
                || app(AccessService::class)->can($request->user(), 'meal_booking.manage-on-behalf'),
            403,
            'You can only manage your own meal bookings.'
        );
    }

    private function accessibleBookings(Request $request): Builder
    {
        $query = EmployeeMeal::query();
        if (!app(AccessService::class)->can($request->user(), 'meal_booking.manage-on-behalf')) {
            $query->where('employee_id', $request->user()->employee_id ?? 0);
        }
        return $query;
    }

    private function validateSelection(?MealMenu $menu, $selection): void
    {
        if ($selection->isEmpty()) return;
        if (!$menu) throw ValidationException::withMessages(['items' => 'Select a menu before choosing items.']);
        $groups = [];
        foreach ($selection as $choice) {
            $item = $menu->items->firstWhere('id', $choice['selected_item_id']);
            $mainId = $choice['main_item_id'] ?: ($item?->alternative_of ?: $item?->id);
            $main = $menu->items->firstWhere('id', $mainId);
            if (!$item || !$main || $main->item_type !== 'Main' || ((int) $item->id !== (int) $mainId && (int) $item->alternative_of !== (int) $mainId) || isset($groups[$mainId])) {
                throw ValidationException::withMessages(['items' => 'Choose one valid main item or its alternative for each menu item.']);
            }
            $groups[$mainId] = true;
        }
    }

    private function saveBooking(array $attributes): EmployeeMeal
    {
        $booking = EmployeeMeal::where('employee_id', $attributes['employee_id'])
            ->where('meal_type_id', $attributes['meal_type_id'])
            ->whereDate('meal_date', $attributes['meal_date'])->lockForUpdate()->first();
        if ($booking && $booking->status !== 'Cancelled') {
            throw ValidationException::withMessages(['booking_date' => 'This employee already has a booking for the selected meal and date.']);
        }
        if ($booking) {
            unset($attributes['code'], $attributes['created_by']);
            $booking->items()->get()->each->delete();
            $booking->update($attributes);
            return $booking;
        }
        return EmployeeMeal::create($attributes);
    }
}
