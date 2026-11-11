<?php

use Illuminate\Support\Facades\Route;
use Modules\HRM\Http\Controllers\EmployeeController;
use Modules\HRM\Http\Controllers\DepartmentController;
use Modules\HRM\Http\Controllers\DesignationController;

Route::middleware(['auth:sanctum'])->group(function () {
    
});

Route::middleware(['auth:sanctum'])->prefix('hrm')->group(function () {
    Route::apiResource('employees', EmployeeController::class)->names('employee');
    Route::apiResource('departments', DepartmentController::class);
    Route::get('designations/departments',[DesignationController::class, 'departments']);
    Route::apiResource('designations', DesignationController::class);
});
