<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RecordLogController;
use App\Http\Controllers\LookupController;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('record-logs', [RecordLogController::class, 'index']);
    Route::get('record-logs/{resource}/{record}', [RecordLogController::class, 'show'])->whereNumber('record');
    Route::get('lookups/navigation', [LookupController::class, 'navigation']);
    Route::get('lookups/{resource}', [LookupController::class, 'index']);
});

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/test', function () {
    return response()->json([
        'message' => 'API working'
    ]);
});
