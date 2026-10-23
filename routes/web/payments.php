<?php

use App\Http\Controllers\Payments\OrderController;
use Illuminate\Support\Facades\Route;

// Paid features (bump, featured listing, Biznis package), paid by bank transfer.
// Links are built with url_slash(): /moj-nalog/narudzbine/...

Route::middleware('auth')->controller(OrderController::class)->group(function () {
    Route::get('/moj-nalog/narudzbine', 'index')->name('payments.orders.index');

    Route::middleware('verified')->group(function () {
        Route::get('/moj-nalog/narudzbine/nova', 'create')->name('payments.orders.create');
        Route::post('/moj-nalog/narudzbine', 'store')->middleware('throttle:payments-orders')->name('payments.orders.store');
    });

    Route::get('/moj-nalog/narudzbine/{order}', 'show')->whereNumber('order')->name('payments.orders.show');
    Route::post('/moj-nalog/narudzbine/{order}/otkazi', 'cancel')->whereNumber('order')
        ->middleware('throttle:payments-orders')->name('payments.orders.cancel');
});
