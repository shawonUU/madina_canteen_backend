<?php

namespace Modules\Meal\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Meal\Models\MealType;

class MealReportController extends Controller
{
    /**
     * Daily Meal Booking Report
     *
     * Example:
     * /meal-reports/daily?date_from=2026-08-01&date_to=2026-08-20
     */
    public function daily(Request $request)
    {
        $validated = $request->validate([
            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

            'meal_type_id' => [
                'nullable',
                'integer',
                'exists:meal_types,id',
            ],

            'status' => [
                'nullable',
                'in:Selected,Served,Cancelled',
            ],
        ]);

        $dateFrom = $validated['date_from']
            ?? now()->toDateString();

        $dateTo = $validated['date_to']
            ?? now()->toDateString();

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        $summaryQuery = DB::table('employee_meals as em')
            ->join(
                'meal_types as mt',
                'mt.id',
                '=',
                'em.meal_type_id'
            )
            ->whereBetween(
                'em.meal_date',
                [$dateFrom, $dateTo]
            );

        if (!empty($validated['meal_type_id'])) {
            $summaryQuery->where(
                'em.meal_type_id',
                $validated['meal_type_id']
            );
        }

        if (!empty($validated['status'])) {
            $summaryQuery->where(
                'em.status',
                $validated['status']
            );
        }

        $summary = $summaryQuery
            ->select([
                DB::raw('COUNT(em.id) as total_bookings'),

                DB::raw("
                    SUM(
                        CASE
                            WHEN em.status = 'Selected'
                            THEN 1
                            ELSE 0
                        END
                    ) as selected_count
                "),

                DB::raw("
                    SUM(
                        CASE
                            WHEN em.status = 'Served'
                            THEN 1
                            ELSE 0
                        END
                    ) as served_count
                "),

                DB::raw("
                    SUM(
                        CASE
                            WHEN em.status = 'Cancelled'
                            THEN 1
                            ELSE 0
                        END
                    ) as cancelled_count
                "),

                DB::raw(
                    'COALESCE(SUM(em.quantity), 0) as total_quantity'
                ),

                DB::raw(
                    'COALESCE(SUM(em.total_amount), 0) as total_amount'
                ),
            ])
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Daily Breakdown
        |--------------------------------------------------------------------------
        */

        $dailyQuery = DB::table('employee_meals as em')
            ->join(
                'meal_types as mt',
                'mt.id',
                '=',
                'em.meal_type_id'
            )
            ->whereBetween(
                'em.meal_date',
                [$dateFrom, $dateTo]
            );

        if (!empty($validated['meal_type_id'])) {
            $dailyQuery->where(
                'em.meal_type_id',
                $validated['meal_type_id']
            );
        }

        if (!empty($validated['status'])) {
            $dailyQuery->where(
                'em.status',
                $validated['status']
            );
        }

        $daily = $dailyQuery
            ->select([
                'em.meal_date',
                'em.meal_type_id',
                'mt.name as meal_type_name',

                DB::raw(
                    'COUNT(em.id) as total_bookings'
                ),

                DB::raw("
                    SUM(
                        CASE
                            WHEN em.status = 'Selected'
                            THEN 1
                            ELSE 0
                        END
                    ) as selected_count
                "),

                DB::raw("
                    SUM(
                        CASE
                            WHEN em.status = 'Served'
                            THEN 1
                            ELSE 0
                        END
                    ) as served_count
                "),

                DB::raw("
                    SUM(
                        CASE
                            WHEN em.status = 'Cancelled'
                            THEN 1
                            ELSE 0
                        END
                    ) as cancelled_count
                "),

                DB::raw(
                    'SUM(em.quantity) as total_quantity'
                ),

                DB::raw(
                    'SUM(em.total_amount) as total_amount'
                ),
            ])
            ->groupBy(
                'em.meal_date',
                'em.meal_type_id',
                'mt.name'
            )
            ->orderByDesc('em.meal_date')
            ->orderBy('mt.name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Daily meal booking report retrieved successfully.',

            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'meal_type_id' =>
                    $validated['meal_type_id'] ?? null,
                'status' =>
                    $validated['status'] ?? null,
            ],

            'summary' => [
                'total_bookings' =>
                    (int) ($summary->total_bookings ?? 0),

                'selected_count' =>
                    (int) ($summary->selected_count ?? 0),

                'served_count' =>
                    (int) ($summary->served_count ?? 0),

                'cancelled_count' =>
                    (int) ($summary->cancelled_count ?? 0),

                'total_quantity' =>
                    (int) ($summary->total_quantity ?? 0),

                'total_amount' =>
                    (float) ($summary->total_amount ?? 0),
            ],

            'data' => $daily,
        ]);
    }


    /**
     * Employee Meal Booking Report
     */
    public function employeeBookings(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => [
                'nullable',
                'integer',
            ],

            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

            'meal_type_id' => [
                'nullable',
                'integer',
                'exists:meal_types,id',
            ],

            'status' => [
                'nullable',
                'in:Selected,Served,Cancelled',
            ],
        ]);

        $dateFrom = $validated['date_from']
            ?? now()->toDateString();

        $dateTo = $validated['date_to']
            ?? now()->toDateString();

        $query = DB::table('employee_meals as em')
            ->join(
                'meal_types as mt',
                'mt.id',
                '=',
                'em.meal_type_id'
            )
            ->leftJoin(
                'menus as m',
                'm.id',
                '=',
                'em.menu_id'
            )
            ->whereBetween(
                'em.meal_date',
                [$dateFrom, $dateTo]
            );

        if (!empty($validated['employee_id'])) {
            $query->where(
                'em.employee_id',
                $validated['employee_id']
            );
        }

        if (!empty($validated['meal_type_id'])) {
            $query->where(
                'em.meal_type_id',
                $validated['meal_type_id']
            );
        }

        if (!empty($validated['status'])) {
            $query->where(
                'em.status',
                $validated['status']
            );
        }

        $bookings = $query
            ->select([
                'em.id',
                'em.employee_id',
                'em.meal_date',

                'em.meal_type_id',
                'mt.name as meal_type_name',

                'em.menu_id',

                'em.meal_rate',
                'em.quantity',
                'em.total_amount',

                'em.status',
                'em.remarks',

                'em.created_by',
                'em.created_at',
                'em.updated_at',
            ])
            ->orderByDesc('em.meal_date')
            ->orderByDesc('em.id')
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Summary
        |--------------------------------------------------------------------------
        */

        $summary = [
            'total_bookings' => $bookings->count(),

            'selected_count' => $bookings
                ->where('status', 'Selected')
                ->count(),

            'served_count' => $bookings
                ->where('status', 'Served')
                ->count(),

            'cancelled_count' => $bookings
                ->where('status', 'Cancelled')
                ->count(),

            'total_quantity' => $bookings
                ->sum('quantity'),

            'total_amount' => $bookings
                ->sum('total_amount'),
        ];

        return response()->json([
            'success' => true,
            'message' =>
                'Employee meal booking report retrieved successfully.',

            'filters' => [
                'employee_id' =>
                    $validated['employee_id'] ?? null,

                'date_from' => $dateFrom,
                'date_to' => $dateTo,

                'meal_type_id' =>
                    $validated['meal_type_id'] ?? null,

                'status' =>
                    $validated['status'] ?? null,
            ],

            'summary' => $summary,

            'data' => $bookings,
        ]);
    }


    /**
     * Meal Item Consumption Report
     */
    public function itemConsumption(Request $request)
    {
        $validated = $request->validate([
            'date_from' => [
                'nullable',
                'date',
            ],

            'date_to' => [
                'nullable',
                'date',
                'after_or_equal:date_from',
            ],

            'meal_type_id' => [
                'nullable',
                'integer',
                'exists:meal_types,id',
            ],

            'status' => [
                'nullable',
                'in:Selected,Served,Cancelled',
            ],
        ]);

        $dateFrom = $validated['date_from']
            ?? now()->toDateString();

        $dateTo = $validated['date_to']
            ?? now()->toDateString();

        $query = DB::table('employee_meal_items as emi')
            ->join(
                'employee_meals as em',
                'em.id',
                '=',
                'emi.employee_meal_id'
            )
            ->join(
                'menu_items as mi',
                'mi.id',
                '=',
                'emi.menu_item_id'
            )
            ->join(
                'meal_types as mt',
                'mt.id',
                '=',
                'em.meal_type_id'
            )
            ->whereBetween(
                'em.meal_date',
                [$dateFrom, $dateTo]
            );

        if (!empty($validated['meal_type_id'])) {
            $query->where(
                'em.meal_type_id',
                $validated['meal_type_id']
            );
        }

        if (!empty($validated['status'])) {
            $query->where(
                'em.status',
                $validated['status']
            );
        }

        $items = $query
            ->select([
                'emi.menu_item_id',

                'mi.item_name as item_name',
                'mi.item_type',
                'mi.alternative_of',

                'em.meal_type_id',
                'mt.name as meal_type_name',

                DB::raw(
                    'COUNT(emi.id) as selected_count'
                ),
            ])
            ->groupBy(
                'emi.menu_item_id',
                'mi.item_name',
                'mi.item_type',
                'mi.alternative_of',
                'em.meal_type_id',
                'mt.name'
            )
            ->orderByDesc('selected_count')
            ->get();

        return response()->json([
            'success' => true,
            'message' =>
                'Meal item consumption report retrieved successfully.',

            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,

                'meal_type_id' =>
                    $validated['meal_type_id'] ?? null,

                'status' =>
                    $validated['status'] ?? null,
            ],

            'data' => $items,
        ]);
    }
}