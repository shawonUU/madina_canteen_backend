<?php

use Illuminate\Support\Facades\Route;
use Modules\Meal\Http\Controllers\AdvanceMealBookingController;
use Modules\Meal\Http\Controllers\DashboardController;
use Modules\Meal\Http\Controllers\EmployeeMealController;
use Modules\Meal\Http\Controllers\MealController;
use Modules\Meal\Http\Controllers\MealRateController;
use Modules\Meal\Http\Controllers\MealReportController;
use Modules\Meal\Http\Controllers\MealTypeController;
use Modules\Meal\Http\Controllers\MenuController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('meal-dashboard');
    Route::apiResource('meals', MealController::class)->names('meal');
    Route::apiResource('meal-types', MealTypeController::class)->names('meal-type');
    Route::apiResource('meal-rates', MealRateController::class)->names('meal-rate');
    Route::apiResource('menus', MenuController::class)->names('menu');
    Route::get('todays-menus', [MenuController::class, 'todayMenus'])->name('todays-menus');
    Route::post('booking-meal', [EmployeeMealController::class, 'bookMeal'])->name('booking-meal.store');
    Route::put('booking-meal/{id}', [EmployeeMealController::class, 'updateBookingMeal'])->name('booking-meal.update');
    Route::get('todays-booked-meals', [EmployeeMealController::class, 'todaysBookedMeals'])->name('todays-booked-meals');
    Route::put( 'meal-bookings/{id}/serve', [EmployeeMealController::class, 'serveMeal'] )->name('meal-bookings.serve');
    Route::apiResource('advance-meal-bookings', AdvanceMealBookingController::class)->names('advance-meal-booking');
    
    Route::prefix('meal-reports')->group(function () {
        Route::get( 'daily', [MealReportController::class, 'daily'] )->name('meal-reports.daily');
        Route::get( 'employee-bookings',  [MealReportController::class, 'employeeBookings'] )->name('meal-reports.employee-bookings');
        Route::get(  'item-consumption', [MealReportController::class, 'itemConsumption'] )->name('meal-reports.item-consumption');
    });

});


