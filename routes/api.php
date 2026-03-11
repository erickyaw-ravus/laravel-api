<?php

use App\Http\Controllers\AuthController;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return ApiResponse::success(new UserResource($request->user()));
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/login/verify-two-factor', [AuthController::class, 'verifyTwoFactor']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
