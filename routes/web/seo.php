<?php

use App\Http\Controllers\Seo\RobotsController;
use App\Http\Controllers\Seo\SitemapController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// SEO: sitemap, robots.txt, llms.txt. Redirects, 410 and the trailing slash are middleware (bootstrap/app.php).
// No session or cookies, so the CDN and the browser can cache the response.

Route::withoutMiddleware([
    EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class,
    ShareErrorsFromSession::class, PreventRequestForgery::class,
])->group(function () {
    Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('seo.sitemap');
    Route::get('sitemap-oglasi-{page}.xml', [SitemapController::class, 'listings'])
        ->whereNumber('page')->name('seo.sitemap.oglasi');
    Route::get('sitemap-{part}.xml', [SitemapController::class, 'part'])
        ->whereIn('part', ['strane', 'kategorije', 'mesta', 'firme'])->name('seo.sitemap.part');

    Route::get('robots.txt', [RobotsController::class, 'robots'])->name('seo.robots');
    Route::get('llms.txt', [RobotsController::class, 'llms'])->name('seo.llms');
    Route::get('stranica-404', [RobotsController::class, 'notFound']);

});
