<?php

use App\Http\Controllers\Listing\ListingController;
use App\Http\Controllers\Listing\ProfileController;
use Illuminate\Support\Facades\Route;

// Listing page and seller profiles, same URLs as the current site.

Route::get('/oglas/{slug}', [ListingController::class, 'show'])->where('slug', '[^/]+')->name('listing.show');
Route::post('/oglas/{slug}/telefon', [ListingController::class, 'revealPhone'])->where('slug', '[^/]+')
    ->middleware('throttle:listing-phone')->name('listing.phone');
Route::post('/oglas/{slug}/prijava', [ListingController::class, 'report'])->where('slug', '[^/]+')
    ->middleware('throttle:listing-report')->name('listing.report');

Route::get('/korisnik/{slug}', [ProfileController::class, 'show'])->where('slug', '[^/]+')->name('profile.show');
