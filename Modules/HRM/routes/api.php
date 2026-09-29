<?php

use Illuminate\Support\Facades\Route;
use Modules\HRM\Http\Controllers\EmployeeController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('employees', EmployeeController::class)->names('employee');
});
