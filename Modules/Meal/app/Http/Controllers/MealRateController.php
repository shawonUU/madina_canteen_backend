<?php

namespace Modules\Meal\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Modules\Meal\Models\MealRate;

class MealRateController extends Controller
{
    /**
     * Display a listing of meal rates.
     */
    public function index(Request $request)
    {
        $query = MealRate::query();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by meal type
        if ($request->filled('meal_type_id')) {
            $query->where('meal_type_id', $request->meal_type_id);
        }

        // Search by rate
        if ($request->filled('rate')) {
            $query->where('rate', $request->rate);
        }

        $mealRates = $query
            ->orderBy('effective_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'message' => 'Meal rates retrieved successfully.',
            'data' => $mealRates,
        ]);
    }

    /**
     * Store a newly created meal rate.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'meal_type_id' => [
                'required',
                'integer',
            ],

            'rate' => [
                'required',
                'numeric',
                'min:0',
            ],

            'effective_date' => [
                'required',
                'date',
            ],

            'status' => [
                'nullable',
                Rule::in(['Active', 'Inactive']),
            ],
        ]);

        $mealRate = MealRate::create([
            'meal_type_id' => $validated['meal_type_id'],
            'rate' => $validated['rate'],
            'effective_date' => $validated['effective_date'],
            'status' => $validated['status'] ?? 'Active',
            'created_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Meal rate created successfully.',
            'data' => $mealRate,
        ], 201);
    }

    /**
     * Display the specified meal rate.
     */
    public function show($id)
    {
        $mealRate = MealRate::find($id);

        if (!$mealRate) {
            return response()->json([
                'success' => false,
                'message' => 'Meal rate not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Meal rate retrieved successfully.',
            'data' => $mealRate,
        ]);
    }

    /**
     * Update the specified meal rate.
     */
    public function update(Request $request, $id)
    {
        $mealRate = MealRate::find($id);

        if (!$mealRate) {
            return response()->json([
                'success' => false,
                'message' => 'Meal rate not found.',
            ], 404);
        }

        $validated = $request->validate([
            'meal_type_id' => [
                'sometimes',
                'required',
                'integer',
            ],

            'rate' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
            ],

            'effective_date' => [
                'sometimes',
                'required',
                'date',
            ],

            'status' => [
                'sometimes',
                'required',
                Rule::in(['Active', 'Inactive']),
            ],
        ]);

        $mealRate->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Meal rate updated successfully.',
            'data' => $mealRate->fresh(),
        ]);
    }

    /**
     * Remove the specified meal rate.
     */
    public function destroy($id)
    {
        $mealRate = MealRate::find($id);

        if (!$mealRate) {
            return response()->json([
                'success' => false,
                'message' => 'Meal rate not found.',
            ], 404);
        }

        $mealRate->delete();

        return response()->json([
            'success' => true,
            'message' => 'Meal rate deleted successfully.',
        ]);
    }
}