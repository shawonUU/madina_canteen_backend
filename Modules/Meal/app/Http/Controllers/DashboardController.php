<?php

namespace Modules\Meal\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Meal\Models\EmployeeMeal;
use Modules\Meal\Models\Menu;

class DashboardController extends Controller
{
    /**
     * Meal Dashboard
     */
    public function index(Request $request)
    {
        $today = now()->toDateString();

        /*
        |--------------------------------------------------------------------------
        | Today's Meal Statistics
        |--------------------------------------------------------------------------
        */

        $todayMeals = EmployeeMeal::query()
            ->whereDate('meal_date', $today);

        $totalBookings = (clone $todayMeals)->count();

        $servedMeals = (clone $todayMeals)
            ->where('status', 'Served')
            ->count();

        $pendingMeals = (clone $todayMeals)
            ->where('status', 'Selected')
            ->count();

        $cancelledMeals = (clone $todayMeals)
            ->where('status', 'Cancelled')
            ->count();

        /*
        |--------------------------------------------------------------------------
        | Today's Total Amount
        |--------------------------------------------------------------------------
        */

        $totalAmount = (clone $todayMeals)
            ->where('status', '!=', 'Cancelled')
            ->sum('total_amount');


        /*
        |--------------------------------------------------------------------------
        | Total Employees
        |--------------------------------------------------------------------------
        */

        $totalEmployees = DB::table('employees')
            ->count();


        /*
        |--------------------------------------------------------------------------
        | Today's Menus
        |--------------------------------------------------------------------------
        */

        $menus = Menu::query()
            ->with([
                'mealType',
                'items'
            ])
            ->whereDate('menu_date', $today)
            ->orderBy('meal_type_id')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | Meal Type Wise Statistics
        |--------------------------------------------------------------------------
        */

        $mealStatistics = EmployeeMeal::query()
            ->select(
                'meal_type_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("
                    SUM(
                        CASE
                            WHEN status = 'Served'
                            THEN 1
                            ELSE 0
                        END
                    ) as served
                "),
                DB::raw("
                    SUM(
                        CASE
                            WHEN status = 'Selected'
                            THEN 1
                            ELSE 0
                        END
                    ) as pending
                "),
                DB::raw("
                    SUM(
                        CASE
                            WHEN status = 'Cancelled'
                            THEN 1
                            ELSE 0
                        END
                    ) as cancelled
                "),
                DB::raw("
                    SUM(
                        CASE
                            WHEN status != 'Cancelled'
                            THEN total_amount
                            ELSE 0
                        END
                    ) as amount
                ")
            )
            ->with('mealType:id,name')
            ->whereDate('meal_date', $today)
            ->groupBy('meal_type_id')
            ->orderBy('meal_type_id')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | Recent Bookings
        |--------------------------------------------------------------------------
        */

        $recentBookings = EmployeeMeal::query()
            ->with([
                'mealType',
                'employee',
                'items.menuItem'
            ])
            ->whereDate('meal_date', $today)
            ->latest('id')
            ->limit(10)
            ->get();


        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'Dashboard data retrieved successfully.',

            'data' => [

                'date' => $today,

                'summary' => [
                    'total_bookings' => $totalBookings,
                    'served' => $servedMeals,
                    'pending' => $pendingMeals,
                    'cancelled' => $cancelledMeals,
                    'total_amount' => round($totalAmount, 2),
                    'total_employees' => $totalEmployees,
                ],

                'meal_statistics' => $mealStatistics,

                'menus' => $menus,

                'recent_bookings' => $recentBookings,
            ],
        ]);
    }
}