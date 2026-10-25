<?php

use App\Http\Controllers\Payments\OrderController;
use Illuminate\Support\Facades\Route;

// Paid features of the price list (promotions, extra listings, firm packages), paid by bank transfer.
// Links are built with url_slash(): /moj-nalog/narudzbine/...

Route::middleware('auth')->controller(OrderController::class)->group(function () {
    Route::get('/moj-nalog/narudzbine', 'index')->name('payments.orders.index');

    Route::middleware('verified')->group(function () {
        Route::get('/moj-nalog/narudzbine/nova', 'create')->name('payments.orders.create');
        Route::post('/moj-nalog/narudzbine', 'store')->middleware('throttle:payments-orders')->name('payments.orders.store');
        // Included promotion (firm monthly credit or a bought bump pack): applied at once, no order.
        Route::post('/moj-nalog/narudzbine/iskoristi', 'useCredit')->middleware('throttle:payments-orders')->name('payments.credits.use');
    });

    Route::get('/moj-nalog/narudzbine/{order}', 'show')->whereNumber('order')->name('payments.orders.show');
    // Firm invoice and credit note, printable (save as PDF from the browser).
    Route::get('/moj-nalog/narudzbine/{order}/racun', 'invoice')->whereNumber('order')->name('payments.orders.invoice');
    Route::get('/moj-nalog/narudzbine/{order}/odobrenje', 'creditNote')->whereNumber('order')->name('payments.orders.credit');
    Route::post('/moj-nalog/narudzbine/{order}/otkazi', 'cancel')->whereNumber('order')
        ->middleware('throttle:payments-orders')->name('payments.orders.cancel');
});
