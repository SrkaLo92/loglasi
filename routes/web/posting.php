<?php

use App\Http\Controllers\Posting\MyListingsController;
use App\Http\Controllers\Posting\WizardController;
use Illuminate\Support\Facades\Route;

// Posting module: the "Postavi oglas" wizard and "Moji oglasi" (login required).

Route::middleware('auth')->group(function () {
    Route::get('/postavi-oglas', [WizardController::class, 'start'])->name('posting.start');
    Route::post('/postavi-oglas', [WizardController::class, 'create'])->middleware('throttle:posting-create')->name('posting.create');

    Route::prefix('/postavi-oglas/{listing}')->whereNumber('listing')->scopeBindings()->group(function () {
        Route::get('/kategorija', [WizardController::class, 'category'])->name('posting.category');
        Route::get('/osnovno', [WizardController::class, 'basics'])->name('posting.basics');
        Route::get('/detalji', [WizardController::class, 'details'])->name('posting.details');
        Route::get('/slike', [WizardController::class, 'images'])->name('posting.images');
        Route::get('/kontakt', [WizardController::class, 'contact'])->name('posting.contact');
        Route::get('/pregled', [WizardController::class, 'preview'])->name('posting.preview');

        Route::middleware('throttle:posting-write')->group(function () {
            Route::post('/kategorija', [WizardController::class, 'updateCategory'])->name('posting.category.update');
            Route::put('/osnovno', [WizardController::class, 'updateBasics'])->name('posting.basics.update');
            Route::put('/detalji', [WizardController::class, 'updateDetails'])->name('posting.details.update');
            Route::post('/slike/redosled', [WizardController::class, 'reorderImages'])->name('posting.images.reorder');
            Route::post('/slike/{image}/naslovna', [WizardController::class, 'coverImage'])->whereNumber('image')->name('posting.images.cover');
            Route::post('/slike/{image}/pomeri', [WizardController::class, 'moveImage'])->whereNumber('image')->name('posting.images.move');
            Route::delete('/slike/{image}', [WizardController::class, 'deleteImage'])->whereNumber('image')->name('posting.images.delete');
            Route::put('/kontakt', [WizardController::class, 'updateContact'])->name('posting.contact.update');
            Route::post('/objavi', [WizardController::class, 'submit'])->name('posting.submit');
        });
        Route::post('/slike', [WizardController::class, 'uploadImages'])->middleware('throttle:posting-upload')->name('posting.images.upload');
    });

    Route::get('/moj-nalog/oglasi', [MyListingsController::class, 'index'])->name('my-listings');
    Route::prefix('/moj-nalog/oglasi/{listing}')->whereNumber('listing')->group(function () {
        Route::get('/izmena', [MyListingsController::class, 'edit'])->name('my-listings.edit');
        Route::get('/obnova', [MyListingsController::class, 'confirmRenew'])->name('my-listings.renew.confirm');
        Route::get('/brisanje', [MyListingsController::class, 'confirmDelete'])->name('my-listings.delete.confirm');
        Route::get('/statistika', [MyListingsController::class, 'stats'])->name('my-listings.stats');

        Route::middleware('throttle:posting-write')->group(function () {
            Route::post('/obnova', [MyListingsController::class, 'renew'])->name('my-listings.renew');
            Route::post('/prodato', [MyListingsController::class, 'sold'])->name('my-listings.sold');
            Route::post('/deaktivacija', [MyListingsController::class, 'deactivate'])->name('my-listings.deactivate');
            Route::delete('/', [MyListingsController::class, 'destroy'])->name('my-listings.destroy');
        });
    });
});
