<?php

// use Illuminate\Support\Facades\Route;
// use Modules\Auth\Http\Controllers\AuthController;

// Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
//     Route::apiResource('auths', AuthController::class)->names('auth');
// });

use Illuminate\Support\Facades\Route;
// use Modules\Auth\App\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\AuthController;
use Modules\Auth\Http\Controllers\PermissionController;
use Modules\Auth\Http\Controllers\RoleController;
use Modules\Auth\Http\Controllers\UserPermissionController;

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// Protected routes (Sanctum লাগবে)
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});


Route::middleware('auth:sanctum')->group(function(){

    Route::apiResource(
        'permissions', PermissionController::class
    );

});


// Role CRUD
Route::apiResource('roles', RoleController::class);


// User Role
// Route::post(
//     'user/assign-role',
//     [UserRoleController::class,'assignRole']
// );

// Route::post(
//     'user/remove-role',
//     [UserRoleController::class,'removeRole']
// );


// User Permission
Route::post(
    'user/assign-permission',
    [UserPermissionController::class,'assignPermission']
);

Route::post(
    'user/remove-permission',
    [UserPermissionController::class,'removePermission']
);