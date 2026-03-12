<?php

use App\Http\Controllers\AuthController;
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
Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/verify-two-factor', [AuthController::class, 'verifyTwoFactor']);
Route::post('/password/forgot', [AuthController::class, 'forgotPassword']);
Route::post('/password/reset', [AuthController::class, 'resetPassword']);

/*
|--------------------------------------------------------------------------
| Authenticated routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/user', function (Request $request) {
        return ApiResponse::success(new UserResource($request->user()));
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/password/change', [AuthController::class, 'changePassword']);

    // User management: list, show, store = Super Admin only; update = Super Admin or own profile
    Route::middleware('role:Super Admin')->group(function (): void {
        Route::get('users', [UserController::class, 'index']);
        Route::get('users/{user}', [UserController::class, 'show']);
        Route::post('users', [UserController::class, 'store']);
    });
    Route::patch('users/{user}', [UserController::class, 'update']);
    Route::put('users/{user}', [UserController::class, 'update']);
});
