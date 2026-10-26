<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Field;
use App\Models\Listing;
use App\Models\Location;
use App\Models\Product;
use App\Services\Catalog;
use App\Services\ListingFields;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** Katalog: servisi i brisanje keša kad se promene kategorije, polja, mesta, cene ili oglasi. */
class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Catalog::class);
        $this->app->singleton(ListingFields::class);
    }

    public function boot(): void
    {
        $catalog = fn (): Catalog => $this->app->make(Catalog::class);

        foreach ([Category::class, Field::class] as $model) {
            $model::saved(fn () => $catalog()->flush());
            $model::deleted(fn () => $catalog()->flush());
        }

        Location::saved(fn () => $catalog()->flushLocations());
        Location::deleted(fn () => $catalog()->flushLocations());

        Product::saved(fn () => $catalog()->flushPrices());
        Product::deleted(fn () => $catalog()->flushPrices());

        // Brojevi oglasa: samo kad se promeni nešto što utiče na broj (ne na pregled ili podizanje).
        Listing::created(fn () => $catalog()->flushCounts());
        Listing::deleted(fn () => $catalog()->flushCounts());
        Listing::restored(fn () => $catalog()->flushCounts());
        Listing::updated(function (Listing $listing) use ($catalog) {
            if ($listing->wasChanged(['status', 'category_id', 'deleted_at'])) {
                $catalog()->flushCounts();
            }
        });

        RateLimiter::for('katalog', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));
    }
}
