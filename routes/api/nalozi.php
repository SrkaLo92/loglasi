<?php

use App\Http\Controllers\Accounts\Api\AuthController;
use App\Http\Controllers\Accounts\Api\MeController;
use Illuminate\Support\Facades\Route;

// Nalog u mobilnoj aplikaciji (/api/v1). Access token 15 min, refresh token 30 dana.

Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:nalozi-register');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:nalozi-login');
Route::post('/auth/refresh', [AuthController::class, 'refresh'])->middleware('throttle:nalozi-refresh');
Route::post('/auth/password-reset', [AuthController::class, 'passwordReset'])->middleware('throttle:nalozi-password');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/me', [MeController::class, 'show']);
    Route::patch('/me', [MeController::class, 'update'])->middleware('throttle:nalozi-account');
    Route::delete('/me', [MeController::class, 'destroy'])->middleware('throttle:nalozi-account');
    Route::get('/me/limits', [MeController::class, 'limits']);
    Route::get('/me/overview', [MeController::class, 'overview']);
});
