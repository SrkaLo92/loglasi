<?php

use App\Http\Controllers\Messages\ConversationController;
use App\Http\Controllers\Messages\FavoriteController;
use App\Http\Controllers\Messages\InboxController;
use Illuminate\Support\Facades\Route;

// Messages (/moj-nalog/poruke/) and favourites (/moj-nalog/omiljeni/). Rules: docs/modules/messages.md.

Route::middleware('auth')->group(function () {
    Route::get('/moj-nalog/poruke', [InboxController::class, 'index'])->name('messages.index');

    Route::middleware('verified')->group(function () {
        Route::get('/moj-nalog/poruke/novo', [ConversationController::class, 'create'])->name('messages.create');
        Route::post('/moj-nalog/poruke/novo', [ConversationController::class, 'store'])->middleware('throttle:messages-send')->name('messages.store');
    });

    Route::get('/moj-nalog/poruke/{conversation}', [ConversationController::class, 'show'])->whereNumber('conversation')->name('messages.show');
    Route::post('/moj-nalog/poruke/{conversation}', [ConversationController::class, 'reply'])->whereNumber('conversation')->middleware('throttle:messages-send')->name('messages.reply');
    Route::get('/moj-nalog/poruke/{conversation}/nove', [ConversationController::class, 'poll'])->whereNumber('conversation')->middleware('throttle:messages-poll')->name('messages.poll');

    Route::get('/moj-nalog/omiljeni', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/moj-nalog/omiljeni/{listing}', [FavoriteController::class, 'store'])->whereNumber('listing')->middleware('throttle:messages-favorites')->name('favorites.store');
    Route::delete('/moj-nalog/omiljeni/{listing}', [FavoriteController::class, 'destroy'])->whereNumber('listing')->middleware('throttle:messages-favorites')->name('favorites.destroy');
});
