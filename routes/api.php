<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Guest (unauthenticated) routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login/verify-two-factor', [AuthController::class, 'verifyTwoFactor'])->name('login.verify-two-factor');
Route::post('/password/forgot', [AuthController::class, 'forgotPassword'])->name('password.forgot');
Route::post('/password/reset', [AuthController::class, 'resetPassword'])->name('password.reset');

/*
|--------------------------------------------------------------------------
| Authenticated routes (any logged-in user)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', function (Request $request) {
        return ApiResponse::success(new UserResource($request->user()));
    })->name('user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/password/change', [AuthController::class, 'changePassword'])->name('password.change');
});

/*
|--------------------------------------------------------------------------
| Authenticated routes (permission required)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'permission'])->group(function (): void {
    // User management
    Route::get('users', [UserController::class, 'index'])->name('users.view');
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.detail');
    Route::post('users', [UserController::class, 'store'])->name('users.create');
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.edit');
    Route::patch('users/{user}/role', [UserController::class, 'updateRole'])->name('users.edit-role');

    // Role management
    Route::get('roles', [RoleController::class, 'index'])->name('roles.view');
    Route::get('roles/with-permissions', [RoleController::class, 'indexWithPermissions'])->name('roles.view-with-permissions');
    Route::post('roles', [RoleController::class, 'store'])->name('roles.create');

    // Permission management
    Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.view');
});
