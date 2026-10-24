<?php

use App\Http\Controllers\Search\HomeController;
use App\Http\Controllers\Search\ListingListController;
use Illuminate\Support\Facades\Route;

// Home page and listing lists, at the same addresses as the current site.
// Page 2+ is /page/N/ (WordPress style); ?page= and ?paged= are accepted too.

Route::get('/', HomeController::class)->name('home');

Route::controller(ListingListController::class)->group(function () {
    Route::get('/oglasi', 'all')->name('listings.all');
    Route::get('/oglasi/page/{page}', 'all')->where('page', '[0-9]+')->name('listings.all.page');
    Route::get('/oglasi/{category}', 'category')->where('category', '[a-z0-9-]+')->name('listings.category');
    Route::get('/oglasi/{category}/page/{page}', 'category')->where(['category' => '[a-z0-9-]+', 'page' => '[0-9]+'])->name('listings.category.page');
    Route::get('/oglasi/{category}/{second}', 'categoryPlace')->where(['category' => '[a-z0-9-]+', 'second' => '[a-z0-9-]+'])->name('listings.place');
    Route::get('/oglasi/{category}/{second}/page/{page}', 'categoryPlace')->where(['category' => '[a-z0-9-]+', 'second' => '[a-z0-9-]+', 'page' => '[0-9]+'])->name('listings.place.page');
});
