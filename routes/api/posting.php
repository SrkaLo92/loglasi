<?php

use App\Http\Controllers\Posting\Api\MediaController;
use App\Http\Controllers\Posting\Api\MyListingsController;
use Illuminate\Support\Facades\Route;

// Posting module in the app (/api/v1): images and the user's listings.

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/media', [MediaController::class, 'store'])->middleware('throttle:posting-upload');

    Route::get('/me/listings', [MyListingsController::class, 'index']);
    Route::post('/me/listings', [MyListingsController::class, 'store'])->middleware('throttle:posting-create');
    Route::get('/me/listings/{listing}', [MyListingsController::class, 'show'])->whereNumber('listing');
    Route::get('/me/listings/{listing}/stats', [MyListingsController::class, 'stats'])->whereNumber('listing');

    Route::middleware('throttle:posting-write')->group(function () {
        Route::patch('/me/listings/{listing}', [MyListingsController::class, 'update'])->whereNumber('listing');
        Route::delete('/me/listings/{listing}', [MyListingsController::class, 'destroy'])->whereNumber('listing');
        Route::post('/me/listings/{listing}/renew', [MyListingsController::class, 'renew'])->whereNumber('listing');
        Route::post('/me/listings/{listing}/sold', [MyListingsController::class, 'sold'])->whereNumber('listing');
        Route::post('/me/listings/{listing}/deactivate', [MyListingsController::class, 'deactivate'])->whereNumber('listing');
    });
});
