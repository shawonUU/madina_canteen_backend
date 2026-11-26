<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\AuthController;
use Modules\Admin\Http\Controllers\ChildMenuController;
use Modules\Admin\Http\Controllers\MenuController;
use Modules\Admin\Http\Controllers\ModuleController;
use Modules\Admin\Http\Controllers\PermissionController;
use Modules\Admin\Http\Controllers\UserAccessController;
use Modules\Admin\Http\Controllers\UserController;
use Modules\Admin\Http\Controllers\UserPermissionController;


// Public routes
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');
});

// Protected routes (Sanctum লাগবে)
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::get('/navigation', [AuthController::class, 'navigation']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
});

Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::apiResource('modules', ModuleController::class);
    Route::apiResource('menus', MenuController::class);
    Route::apiResource('child-menus', ChildMenuController::class);
    Route::apiResource('users', UserController::class)->names('user');
    Route::get( '/users/{user}/access', [UserAccessController::class, 'show']);
    Route::put('/users/{user}/access',  [UserAccessController::class, 'update'] );
    Route::apiResource('permissions', PermissionController::class);
    Route::apiResource('roles', \Modules\Admin\Http\Controllers\RoleController::class);
});


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
)->middleware('auth:sanctum');

Route::post(
    'user/remove-permission',
    [UserPermissionController::class,'removePermission']
)->middleware('auth:sanctum');
