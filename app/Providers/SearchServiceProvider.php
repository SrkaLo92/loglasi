<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Services\ListingSearch;
use App\Services\SearchIndex;
use App\Support\Home\HomeSections;
use App\Support\Scheduling\InProcess;
use App\Support\Search\Favorites;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** Search: listing search, search index sync, home page cache, API rate limit. */
class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ListingSearch::class);
        $this->app->singleton(SearchIndex::class);
        $this->app->scoped(Favorites::class);
    }

    public function boot(): void
    {
        $index = fn (): SearchIndex => $this->app->make(SearchIndex::class);

        // Search index follows every listing change made through the model.
        Listing::saved(fn (Listing $listing) => $index()->sync($listing));
        Listing::deleted(fn (Listing $listing) => $index()->sync($listing));
        Listing::restored(fn (Listing $listing) => $index()->sync($listing));

        User::updated(function (User $user) use ($index) {
            if ($user->wasChanged('account_type')) {
                $index()->syncUser($user->id, $user->isFirm());
                HomeSections::flush();
            }
        });

        Category::updated(function (Category $category) use ($index) {
            if ($category->wasChanged('parent_id')) {
                $index()->syncCategories($category);
            }
        });

        // Home sections are cached for a short time; any change to listings, images,
        // categories or firms clears them at once.
        $flush = fn () => HomeSections::flush();
        foreach ([Listing::class, ListingImage::class, Category::class, Firm::class] as $model) {
            $model::saved($flush);
            $model::deleted($flush);
        }
        Listing::restored($flush);

        RateLimiter::for('search', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            InProcess::command($schedule, 'search:refresh')->everyMinute()->withoutOverlapping();
        });
    }
}
