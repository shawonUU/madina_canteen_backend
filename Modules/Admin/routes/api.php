<?php

use Illuminate\Support\Facades\Route;
use Modules\Admin\Http\Controllers\AuthController;
use Modules\Admin\Http\Controllers\ChildMenuController;
use Modules\Admin\Http\Controllers\MenuController;
use Modules\Admin\Http\Controllers\ModuleController;
use Modules\Admin\Http\Controllers\PermissionController;
use Modules\Admin\Http\Controllers\RoleController;
use Modules\Admin\Http\Controllers\UserAccessController;
use Modules\Admin\Http\Controllers\UserController;
use Modules\Admin\Http\Controllers\UserPermissionController;


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

Route::prefix('admin')->group(function () {
    Route::apiResource('modules', ModuleController::class);
    Route::apiResource('menus', MenuController::class);
    Route::apiResource('child-menus', ChildMenuController::class);
    Route::apiResource('users', UserController::class)->names('user');
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('permissions', PermissionController::class);
    Route::get( '/users/{user}/access', [UserAccessController::class, 'show']);
    Route::put('/users/{user}/access',  [UserAccessController::class, 'update'] );
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
);

Route::post(
    'user/remove-permission',
    [UserPermissionController::class,'removePermission']
);
