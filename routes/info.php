<?php

use App\Http\Controllers\Info\BusinessController;
use App\Http\Controllers\Info\InfoController;
use App\Http\Controllers\Info\SubmitController;
use App\Http\Middleware\InfoEnabled;
use App\Support\Info\InfoUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Info portal on its own host (config/info.php). Links are built with InfoUrl::to(), always with a trailing slash.

Route::domain(config('info.domain'))->middleware(InfoEnabled::class)->name('info.')->group(function () {
    Route::get('/', [InfoController::class, 'home'])->name('home');
    Route::get('/robots.txt', [InfoController::class, 'robots'])->name('robots');
    Route::get('/sitemap.xml', [InfoController::class, 'sitemap'])->name('sitemap');

    Route::get('/servisne-informacije', [InfoController::class, 'services'])->name('services');
    Route::get('/desavanja', [InfoController::class, 'events'])->name('events');

    foreach (['vesti' => 'news', 'price' => 'stories', 'vodici' => 'guides'] as $path => $name) {
        Route::get('/'.$path, [InfoController::class, 'section'])->defaults('section', $path)->name($name);
    }
    Route::get('/{section}/{slug}', [InfoController::class, 'show'])
        ->where(['section' => 'vesti|desavanja|price|vodici', 'slug' => '[a-z0-9-]+'])
        ->name('post');

    Route::get('/firme', [BusinessController::class, 'index'])->name('businesses');
    Route::get('/firme/{slug}', [BusinessController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('business');
    Route::post('/firme/{slug}/zahtev', [BusinessController::class, 'request'])->where('slug', '[a-z0-9-]+')
        ->middleware('throttle:info-submit')->name('business.request');

    Route::get('/posalji-vest', [SubmitController::class, 'form'])->name('submit');
    Route::post('/posalji-vest', [SubmitController::class, 'store'])->middleware('throttle:info-submit')->name('submit.store');

    // Every other address on this host belongs to the classifieds site.
    Route::any('/{any}', fn (Request $request) => redirect()->away(InfoUrl::main($request->path()), 301))
        ->where('any', '.*')->name('elsewhere');
});
