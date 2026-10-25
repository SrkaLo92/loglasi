<?php

use App\Http\Controllers\Katalog\CatalogApiController;
use Illuminate\Support\Facades\Route;

// Katalog za mobilnu aplikaciju (javno, bez prijave).
Route::middleware('throttle:katalog')->controller(CatalogApiController::class)->group(function () {
    Route::get('/categories', 'categories')->name('api.categories');
    Route::get('/categories/browse', 'browse')->name('api.categories.browse');
    Route::get('/categories/{id}/fields', 'fields')->whereNumber('id')->name('api.categories.fields');
    Route::get('/locations', 'locations')->name('api.locations');
    Route::get('/fields', 'fields')->name('api.fields');
    Route::get('/prices', 'prices')->name('api.prices');
});
