<?php

use Illuminate\Support\Facades\Route;
use Modules\Reception\Http\Controllers\GatePassController;
use Modules\Reception\Http\Controllers\ReceptionController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('receptions', ReceptionController::class)->names('reception');

    Route::prefix('reception')->group(function () {
        Route::get('gate-passes/{gatePass}/pdf',[GatePassController::class, 'pdf']);
        Route::apiResource('gate-passes', GatePassController::class);
    });
});
