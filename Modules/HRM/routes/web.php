<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\UserController;
use Modules\HRM\Http\Controllers\EmployeeController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('employees', EmployeeController::class)->names('employee');
});
