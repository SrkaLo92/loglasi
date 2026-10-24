<?php

use App\Http\Controllers\Notifications\Api\DeviceController;
use Illuminate\Support\Facades\Route;

// Notifications (/api/v1): push tokens of the mobile app.

Route::middleware(['auth:sanctum', 'throttle:notifications-devices'])->group(function () {
    Route::post('/devices', [DeviceController::class, 'store']);
    Route::delete('/devices/{token}', [DeviceController::class, 'destroy'])->where('token', '[A-Za-z0-9_:\-\.]+');
});
