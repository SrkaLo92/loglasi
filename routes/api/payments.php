<?php

use App\Http\Controllers\Payments\Api\OrderApiController;
use Illuminate\Support\Facades\Route;

// Payments in the mobile app: status only, ordering and paying happen on the website.

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me/orders', [OrderApiController::class, 'index'])->name('api.me.orders');
});
