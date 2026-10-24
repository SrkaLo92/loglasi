<?php

use App\Http\Controllers\Search\ListingApiController;
use Illuminate\Support\Facades\Route;

// Listing search for the mobile app (public, no login).
Route::get('/listings', [ListingApiController::class, 'index'])
    ->middleware('throttle:search')
    ->name('api.listings.index');
