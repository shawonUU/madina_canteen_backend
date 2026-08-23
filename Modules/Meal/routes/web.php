<?php

use Illuminate\Support\Facades\Route;
use Modules\Meal\Http\Controllers\MealController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('meals', MealController::class)->names('meal');
});
