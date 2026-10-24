<?php

use App\Http\Controllers\Notifications\Api\DeviceController;
use App\Http\Controllers\Notifications\Api\NotificationSettingsApiController;
use App\Http\Controllers\Notifications\Api\SavedSearchApiController;
use Illuminate\Support\Facades\Route;

// Notifications (/api/v1): push tokens, notification switches and saved searches of the mobile app.

Route::middleware(['auth:sanctum', 'throttle:notifications-devices'])->group(function () {
    Route::post('/devices', [DeviceController::class, 'store']);
    Route::delete('/devices/{token}', [DeviceController::class, 'destroy'])->where('token', '[A-Za-z0-9_:\-\.]+');
});

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/me/notification-settings', [NotificationSettingsApiController::class, 'show']);
    Route::patch('/me/notification-settings', [NotificationSettingsApiController::class, 'update']);

    Route::get('/me/saved-searches', [SavedSearchApiController::class, 'index']);
    Route::post('/me/saved-searches', [SavedSearchApiController::class, 'store']);
    Route::patch('/me/saved-searches/{savedSearch}', [SavedSearchApiController::class, 'update'])->whereNumber('savedSearch');
    Route::delete('/me/saved-searches/{savedSearch}', [SavedSearchApiController::class, 'destroy'])->whereNumber('savedSearch');
});
