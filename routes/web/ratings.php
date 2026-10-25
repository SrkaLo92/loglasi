<?php

use App\Http\Controllers\Ratings\RatingController;
use Illuminate\Support\Facades\Route;

// Seller ratings: public list on the profile, rating from a conversation, the seller's reply and report.

Route::get('/korisnik/{slug}/ocene', [RatingController::class, 'index'])->where('slug', '[^/]+')->name('ratings.index');

Route::middleware('auth')->controller(RatingController::class)->group(function () {
    Route::post('/moj-nalog/poruke/{conversation}/ocena', 'store')->whereNumber('conversation')->middleware('throttle:10,1')->name('ratings.store');
    Route::post('/ocene/{rating}/odgovor', 'reply')->whereNumber('rating')->middleware('throttle:10,1')->name('ratings.reply');
    Route::post('/ocene/{rating}/prijava', 'report')->whereNumber('rating')->middleware('throttle:5,1')->name('ratings.report');
});
