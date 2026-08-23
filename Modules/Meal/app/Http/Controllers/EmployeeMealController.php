<?php

namespace Modules\Meal\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Meal\Models\EmployeeMeal;
use Modules\Meal\Models\EmployeeMealItem;
use Modules\Meal\Models\Menu;
use Modules\Meal\Models\MealType;
use Modules\Auth\Models\User;

class EmployeeMealController extends Controller
{
    /**
     * Book a meal
     */
    public function bookMeal(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
            ],

            'menu_id' => [
                'required',
                'integer',
                'exists:menus,id',
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

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.main_item_id' => [
                'required',
                'integer',
                'exists:menu_items,id',
            ],

            'items.*.selected_item_id' => [
                'required',
                'integer',
                'exists:menu_items,id',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Check Menu
        |--------------------------------------------------------------------------
        */

        $menu = Menu::query()
            ->where('id', $validated['menu_id'])
            ->where('meal_type_id', $validated['meal_type_id'])
            ->whereDate('menu_date', $validated['booking_date'])
            ->first();

        if (!$menu) {
            throw ValidationException::withMessages([
                'menu_id' => [
                    'The selected menu does not exist for this date and meal type.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Duplicate Booking
        |--------------------------------------------------------------------------
        */

        $existingBooking = EmployeeMeal::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('menu_id', $validated['menu_id'])
            ->whereDate('meal_date', $validated['booking_date'])
            ->where('status', '!=', 'Cancelled')
            ->first();

        if ($existingBooking) {
            throw ValidationException::withMessages([
                'menu_id' => [
                    'You have already booked this meal.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Get Meal Rate
        |--------------------------------------------------------------------------
        */

        $mealType = MealType::query()
            ->findOrFail($validated['meal_type_id']);

        $mealRate = $mealType->meal_rate ?? 0;

        $quantity = 1;
        $totalAmount = $mealRate * $quantity;

        /*
        |--------------------------------------------------------------------------
        | Validate Selected Items Belong to Menu
        |--------------------------------------------------------------------------
        */

        $selectedItemIds = collect($validated['items'])
            ->map(fn ($item) => (int) $item['selected_item_id'])
            ->unique()
            ->values();

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

            $employeeMeal = EmployeeMeal::create([
                'employee_id' => $validated['employee_id'],
                'meal_type_id' => $validated['meal_type_id'],
                'meal_rate' => $mealRate,
                'meal_date' => $validated['booking_date'],
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
                'menu_id' => $validated['menu_id'],
                'remarks' => null,
                'status' => 'Selected',
                'created_by' => auth()->id(),
            ]);

            foreach ($selectedItemIds as $menuItemId) {
                EmployeeMealItem::create([
                    'employee_meal_id' => $employeeMeal->id,
                    'menu_item_id' => $menuItemId,
                    'created_by' => auth()->id(),
                ]);
            }

            return $employeeMeal;
        });

        return response()->json([
            'success' => true,
            'message' => 'Meal booked successfully.',
            'data' => $employeeMeal->load('items'),
        ], 201);
    }


    /**
     * Update existing meal booking
     */
    public function updateBookingMeal(Request $request, $id)
    {
        $validated = $request->validate([
            'employee_id' => [
                'required',
                'integer',
            ],

            'menu_id' => [
                'required',
                'integer',
                'exists:menus,id',
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

            'items' => [
                'required',
                'array',
                'min:1',
            ],

            'items.*.main_item_id' => [
                'required',
                'integer',
                'exists:menu_items,id',
            ],

            'items.*.selected_item_id' => [
                'required',
                'integer',
                'exists:menu_items,id',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Find Booking
        |--------------------------------------------------------------------------
        */

        $employeeMeal = EmployeeMeal::query()
            ->where('id', $id)
            ->first();

        if (!$employeeMeal) {
            return response()->json([
                'success' => false,
                'message' => 'Meal booking not found.',
            ], 404);
        }

        /*
        |--------------------------------------------------------------------------
        | Prevent Updating Cancelled / Served Booking
        |--------------------------------------------------------------------------
        */

        if (in_array($employeeMeal->status, ['Cancelled', 'Served'])) {
            throw ValidationException::withMessages([
                'status' => [
                    "A {$employeeMeal->status} meal booking cannot be updated.",
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Menu
        |--------------------------------------------------------------------------
        */

        $menu = Menu::query()
            ->where('id', $validated['menu_id'])
            ->where('meal_type_id', $validated['meal_type_id'])
            ->whereDate('menu_date', $validated['booking_date'])
            ->first();

        if (!$menu) {
            throw ValidationException::withMessages([
                'menu_id' => [
                    'The selected menu does not exist for this date and meal type.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Check Duplicate Booking
        |--------------------------------------------------------------------------
        */

        $duplicateBooking = EmployeeMeal::query()
            ->where('employee_id', $validated['employee_id'])
            ->where('menu_id', $validated['menu_id'])
            ->whereDate('meal_date', $validated['booking_date'])
            ->where('id', '!=', $employeeMeal->id)
            ->where('status', '!=', 'Cancelled')
            ->exists();

        if ($duplicateBooking) {
            throw ValidationException::withMessages([
                'menu_id' => [
                    'You have already booked this meal.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Get Meal Rate
        |--------------------------------------------------------------------------
        */

        $mealType = MealType::query()
            ->findOrFail($validated['meal_type_id']);

        $mealRate = $mealType->meal_rate ?? 0;

        $quantity = 1;
        $totalAmount = $mealRate * $quantity;

        /*
        |--------------------------------------------------------------------------
        | Validate Selected Items
        |--------------------------------------------------------------------------
        */

        $selectedItemIds = collect($validated['items'])
            ->map(fn ($item) => (int) $item['selected_item_id'])
            ->unique()
            ->values();

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

        /*
        |--------------------------------------------------------------------------
        | Update Booking
        |--------------------------------------------------------------------------
        */

        DB::transaction(function () use (
            $employeeMeal,
            $validated,
            $mealRate,
            $quantity,
            $totalAmount,
            $selectedItemIds
        ) {

            $employeeMeal->update([
                'employee_id' => $validated['employee_id'],
                'meal_type_id' => $validated['meal_type_id'],
                'meal_rate' => $mealRate,
                'meal_date' => $validated['booking_date'],
                'quantity' => $quantity,
                'total_amount' => $totalAmount,
                'menu_id' => $validated['menu_id'],
            ]);

            /*
            |--------------------------------------------------------------------------
            | Delete Old Selected Items
            |--------------------------------------------------------------------------
            */

            EmployeeMealItem::query()
                ->where('employee_meal_id', $employeeMeal->id)
                ->delete();

            /*
            |--------------------------------------------------------------------------
            | Insert New Selected Items
            |--------------------------------------------------------------------------
            */

            foreach ($selectedItemIds as $menuItemId) {
                EmployeeMealItem::create([
                    'employee_meal_id' => $employeeMeal->id,
                    'menu_item_id' => $menuItemId,
                    'created_by' => auth()->id(),
                ]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Meal booking updated successfully.',
            'data' => $employeeMeal->fresh()->load('items'),
        ]);
    }

    public function todaysBookedMeals(Request $request)
    {
        $today = now()->toDateString();

        $bookedMeals = EmployeeMeal::query()
            ->whereDate('meal_date', $today)
            ->with(['items.menuItem', 'menu.items', 'mealType','employee'])
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Today\'s booked meals retrieved successfully.',
            'data' => $bookedMeals,
        ]);
    }

    public function serveMeal($id)
    {
        $employeeMeal = EmployeeMeal::find($id);

        if (!$employeeMeal) {
            return response()->json([
                'success' => false,
                'message' => 'Meal booking not found.',
            ], 404);
        }

        if ($employeeMeal->status === 'Cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cancelled meal cannot be served.',
            ], 422);
        }

        if ($employeeMeal->status === 'Served') {
            return response()->json([
                'success' => false,
                'message' => 'Meal has already been served.',
            ], 422);
        }

        $employeeMeal->update([
            'status' => 'Served',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Meal served successfully.',
            'data' => $employeeMeal->fresh(),
        ]);
    }
}