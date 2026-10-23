<?php

use App\Http\Controllers\Notifications\SavedSearchController;
use App\Http\Controllers\Notifications\SettingsController;
use App\Http\Controllers\Notifications\UnsubscribeController;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

// Notifications: settings, one-click unsubscribe, saved searches. Links via url_slash().

Route::middleware('auth')->group(function () {
    Route::get('/moj-nalog/obavestenja', [SettingsController::class, 'show'])->name('notifications.settings');
    Route::put('/moj-nalog/obavestenja', [SettingsController::class, 'update'])
        ->middleware('throttle:notifications-account')->name('notifications.settings.update');

    Route::get('/moj-nalog/pretrage', [SavedSearchController::class, 'index'])->name('saved-searches.index');
    Route::middleware('throttle:notifications-account')->group(function () {
        Route::post('/moj-nalog/pretrage', [SavedSearchController::class, 'store'])->name('saved-searches.store');
        Route::patch('/moj-nalog/pretrage/{savedSearch}', [SavedSearchController::class, 'update'])
            ->whereNumber('savedSearch')->name('saved-searches.update');
        Route::delete('/moj-nalog/pretrage/{savedSearch}', [SavedSearchController::class, 'destroy'])
            ->whereNumber('savedSearch')->name('saved-searches.destroy');
    });
});

// Signed, works without signing in (the link is opened from the mail).
Route::middleware(['signed', 'throttle:20,1'])
    ->whereNumber('user')
    ->whereIn('category', array_keys(NotificationPreferences::DEFAULTS))
    ->group(function () {
        Route::get('/moj-nalog/obavestenja/odjava/{user}/{category}', [UnsubscribeController::class, 'show'])
            ->name('notifications.unsubscribe');
        Route::post('/moj-nalog/obavestenja/odjava/{user}/{category}', [UnsubscribeController::class, 'store'])
            ->withoutMiddleware(ValidateCsrfToken::class);
    });
