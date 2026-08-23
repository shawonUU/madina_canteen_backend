<?php

namespace Modules\Meal\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Meal\Models\MealType;
use Modules\Meal\Models\MealRate;

class MealTypeController extends Controller
{
    /**
     * Display a listing of meal types.
     */
    public function index(Request $request)
    {
        $query = MealType::query();

        // Search by name
        if ($request->filled('search')) {
            $query->where(
                'name',
                'like',
                '%' . $request->search . '%'
            );
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where(
                'status',
                $request->status
            );
        }

        $mealTypes = $query
            ->orderBy('id', 'desc')
            ->paginate(
                $request->get('per_page', 15)
            );

        return response()->json([
            'success' => true,
            'message' => 'Meal types retrieved successfully.',
            'data' => $mealTypes,
        ]);
    }


    /**
     * Store a newly created meal type.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                'unique:meal_types,name',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'booking_cutoff_time' => [
                'required',
                'date_format:H:i',
            ],

            'meal_rate' => [
                'required',
                'numeric',
                'min:0',
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'Active',
                    'Inactive',
                ]),
            ],
        ]);

        $mealType = DB::transaction(function () use ($validated) {

            // Create Meal Type
            $mealType = MealType::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'booking_cutoff_time' => $validated['booking_cutoff_time'],
                'meal_rate' => $validated['meal_rate'],
                'status' => $validated['status'] ?? 'Active',
            ]);

            // Create initial meal rate history
            MealRate::create([
                'meal_type_id' => $mealType->id,
                'rate' => $validated['meal_rate'],
                'status' => 'Active',
                'created_by' => auth()->id(),
            ]);

            return $mealType;
        });

        return response()->json([
            'success' => true,
            'message' => 'Meal type created successfully.',
            'data' => $mealType,
        ], 201);
    }


    /**
     * Display the specified meal type.
     */
    public function show($id)
    {
        $mealType = MealType::find($id);

        if (!$mealType) {
            return response()->json([
                'success' => false,
                'message' => 'Meal type not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Meal type retrieved successfully.',
            'data' => $mealType,
        ]);
    }


    /**
     * Update the specified meal type.
     */
    public function update(Request $request, $id)
    {
        $mealType = MealType::find($id);

        if (!$mealType) {
            return response()->json([
                'success' => false,
                'message' => 'Meal type not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('meal_types', 'name')
                    ->ignore($mealType->id),
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
            ],

            'booking_cutoff_time' => [
                'sometimes',
                'required',
                'date_format:H:i',
            ],

            'meal_rate' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
            ],

            'status' => [
                'sometimes',
                'required',
                Rule::in([
                    'Active',
                    'Inactive',
                ]),
            ],
        ]);

        DB::transaction(function () use (
            $mealType,
            $validated
        ) {

            /*
             * Check whether meal rate has changed.
             */
            $rateChanged = (
                array_key_exists('meal_rate', $validated)
                &&
                (float) $mealType->meal_rate
                    !== (float) $validated['meal_rate']
            );

            /*
             * If rate changed:
             *
             * 1. Previous history becomes Inactive
             * 2. New rate is inserted into meal_rates
             * 3. meal_types.meal_rate gets updated
             */
            if ($rateChanged) {

                // Deactivate previous active rate
                MealRate::where(
                    'meal_type_id',
                    $mealType->id
                )
                ->where('status', 'Active')
                ->update([
                    'status' => 'Inactive',
                ]);

                // Insert new rate history
                MealRate::create([
                    'meal_type_id' => $mealType->id,
                    'rate' => $validated['meal_rate'],
                    'status' => 'Active',
                    'created_by' => auth()->id(),
                ]);
            }

            // Update Meal Type
            $mealType->update($validated);
        });

        return response()->json([
            'success' => true,
            'message' => 'Meal type updated successfully.',
            'data' => $mealType->fresh(),
        ]);
    }


    /**
     * Remove the specified meal type.
     */
    public function destroy($id)
    {
        $mealType = MealType::find($id);

        if (!$mealType) {
            return response()->json([
                'success' => false,
                'message' => 'Meal type not found.',
            ], 404);
        }

        DB::transaction(function () use ($mealType) {

            // Delete meal rate history
            MealRate::where(
                'meal_type_id',
                $mealType->id
            )->delete();

            // Delete meal type
            $mealType->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Meal type deleted successfully.',
        ]);
    }
}