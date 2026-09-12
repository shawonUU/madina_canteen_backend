<?php

use Illuminate\Support\Facades\Route;
use Modules\Meal\Http\Controllers\DashboardController;
use Modules\Meal\Http\Controllers\EmployeeMealController;
use Modules\Meal\Http\Controllers\MealRateController;
use Modules\Meal\Http\Controllers\MealReportController;
use Modules\Meal\Http\Controllers\MealTypeController;
use Modules\Meal\Http\Controllers\MenuController;

Route::middleware('auth:sanctum')->group(function () {

    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'index']);

    // Meal Types
    Route::get('meal-types', [MealTypeController::class, 'index']);
    Route::post('meal-types', [MealTypeController::class, 'store']);
    Route::get('meal-types/{id}', [MealTypeController::class, 'show']);
    Route::put('meal-types/{id}', [MealTypeController::class, 'update']);
    Route::delete('meal-types/{id}', [MealTypeController::class, 'destroy']);

    // Meal Rates
    Route::get('meal-rates', [MealRateController::class, 'index']);
    Route::post('meal-rates', [MealRateController::class, 'store']);
    Route::get('meal-rates/{id}', [MealRateController::class, 'show']);
    Route::put('meal-rates/{id}', [MealRateController::class, 'update']);
    Route::delete('meal-rates/{id}', [MealRateController::class, 'destroy']);

    // Menus
    Route::get('menus', [MenuController::class, 'index']);
    Route::post('menus', [MenuController::class, 'store']);
    Route::get('menus/{id}', [MenuController::class, 'show']);
    Route::put('menus/{id}', [MenuController::class, 'update']);
    Route::delete('menus/{id}', [MenuController::class, 'destroy']);
    Route::get('todays-menus', [MenuController::class, 'todayMenus']);

    // Meal Booking
    Route::post('booking-meal', [EmployeeMealController::class, 'bookMeal']);
    Route::post('booking-meals', [EmployeeMealController::class, 'bookMeals']);
    Route::put('booking-meal/{id}', [EmployeeMealController::class, 'updateBookingMeal']);
    Route::get('todays-booked-meals', [ EmployeeMealController::class, 'todaysBookedMeals' ]);
    Route::put('meal-bookings/{id}/serve', [ EmployeeMealController::class, 'serveMeal' ]);

    // Meal Reports
    Route::get('meal-reports/daily', [MealReportController::class, 'daily']);
    Route::get('meal-reports/employee-bookings', [MealReportController::class, 'employeeBookings' ]);
    Route::get('meal-reports/item-consumption', [MealReportController::class,'itemConsumption']);
});