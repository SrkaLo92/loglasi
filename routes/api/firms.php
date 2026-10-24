<?php

use App\Http\Controllers\Firms\Api\MyFirmController;
use Illuminate\Support\Facades\Route;

// Firm self-service in the mobile app (/api/v1). GET /firms/{id} belongs to the listing module.

Route::middleware(['auth:sanctum', 'firm'])->group(function () {
    Route::get('/me/firm', [MyFirmController::class, 'show']);
    Route::patch('/me/firm', [MyFirmController::class, 'update'])->middleware('throttle:firms-account');
    Route::get('/me/firm/stats', [MyFirmController::class, 'stats']);
});
