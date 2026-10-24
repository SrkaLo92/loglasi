<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Firm;
use App\Models\GoneUrl;
use App\Models\Listing;
use App\Models\Location;
use App\Models\Redirect;
use App\Models\User;
use App\Support\Scheduling\InProcess;
use App\Support\Seo;
use App\Support\Seo\Hits;
use App\Support\Seo\Sitemap;
use App\Support\Seo\UrlTables;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/** SEO modul: jedna Seo instanca po zahtevu, brisanje keša na promenu, raspored za sitemap. */
class SeoServiceProvider extends ServiceProvider
{
    /** Kolone oglasa koje menjaju sitemap (pregledi i otkrivanja telefona ne). */
    private const LISTING_COLUMNS = ['status', 'slug', 'expires_at', 'category_id', 'location_id', 'title', 'description', 'price', 'currency', 'published_at', 'deleted_at'];

    public function register(): void
    {
        $this->app->scoped(Seo::class);
        $this->app->singleton(Sitemap::class);
        $this->app->singleton(Hits::class);
    }

    public function boot(): void
    {
        // Podaci strane važe samo za jedan zahtev.
        Event::listen(RequestHandled::class, fn () => $this->app->forgetInstance(Seo::class));
        $this->app->terminating(fn () => $this->app->make(Hits::class)->flush());

        $flushSitemap = fn () => $this->app->make(Sitemap::class)->flush();

        Listing::saved(function (Listing $listing) use ($flushSitemap) {
            if ($listing->wasRecentlyCreated || $listing->wasChanged(self::LISTING_COLUMNS)) {
                $flushSitemap();
            }
        });
        Listing::deleted($flushSitemap);
        Listing::restored($flushSitemap);

        foreach ([Category::class, Location::class, Firm::class] as $model) {
            $model::saved($flushSitemap);
            $model::deleted($flushSitemap);
        }

        User::saved(function (User $user) use ($flushSitemap) {
            if ($user->wasRecentlyCreated || $user->wasChanged(['slug', 'account_type', 'status'])) {
                $flushSitemap();
            }
        });
        User::deleted($flushSitemap);

        Redirect::saved(fn () => UrlTables::flushRedirects());
        Redirect::deleted(fn () => UrlTables::flushRedirects());
        GoneUrl::saved(fn () => UrlTables::flushGone());
        GoneUrl::deleted(fn () => UrlTables::flushGone());

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            // Istekli oglasi ispadaju iz sitemapa i bez izmene u bazi.
            InProcess::command($schedule, 'seo:sitemap')->hourly()->withoutOverlapping();
        });
    }
}
