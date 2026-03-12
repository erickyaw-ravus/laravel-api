<?php

use App\Http\Controllers\AuthController;
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
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', function (Request $request) {
        return ApiResponse::success(new UserResource($request->user()));
    })->name('user');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::post('/password/change', [AuthController::class, 'changePassword'])->name('password.change');

    // User management: list, show, store = Super Admin only; update = Super Admin or own profile
    Route::middleware('role:Super Admin')->group(function (): void {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::post('users', [UserController::class, 'store'])->name('users.store');

        // Role management: list (no pagination), create
        Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
    });
    Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
});
