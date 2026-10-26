<?php

use App\Http\Controllers\Firms\AccountController;
use App\Http\Controllers\Firms\DirectoryController;
use App\Http\Controllers\Firms\ImportController;
use Illuminate\Support\Facades\Route;

// Firms: public directory and firm self-service. Links are built with url_slash(): /firme/, /moj-nalog/firma/.

Route::get('/firme', DirectoryController::class)->name('firms.directory');

Route::middleware(['auth', 'firm'])->prefix('moj-nalog/firma')->group(function () {
    Route::get('/', [AccountController::class, 'edit'])->name('firms.account');
    Route::put('/', [AccountController::class, 'update'])->middleware('throttle:firms-account')->name('firms.account.update');
    Route::get('/statistika', [AccountController::class, 'stats'])->name('firms.account.stats');
    Route::get('/uvoz-oglasa', [ImportController::class, 'form'])->name('firms.import');
    Route::post('/uvoz-oglasa/pregled', [ImportController::class, 'preview'])->middleware('throttle:firms-account')->name('firms.import.preview');
    Route::post('/uvoz-oglasa', [ImportController::class, 'store'])->middleware('throttle:firms-account')->name('firms.import.store');
});
