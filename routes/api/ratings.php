<?php

use App\Http\Controllers\Ratings\Api\RatingApiController;
use Illuminate\Support\Facades\Route;

// Seller ratings in the app (/api/v1). Rules: docs/moduli/ocene.md.

Route::get('/users/{id}/ratings', [RatingApiController::class, 'index'])->whereNumber('id')->middleware('throttle:listing-api');

Route::middleware('auth:sanctum')->controller(RatingApiController::class)->group(function () {
    Route::get('/conversations/{conversation}/rating', 'show')->whereNumber('conversation');
    Route::put('/conversations/{conversation}/rating', 'update')->whereNumber('conversation')->middleware('throttle:10,1');
    Route::post('/ratings/{rating}/reply', 'reply')->whereNumber('rating')->middleware('throttle:10,1');
    Route::post('/ratings/{rating}/report', 'report')->whereNumber('rating')->middleware('throttle:5,1');
});
