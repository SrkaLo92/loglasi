<?php

namespace App\Providers;

use App\Models\Listing;
use App\Policies\ListingPolicy;
use App\Services\DuplicateListings;
use App\Services\ListingImages;
use App\Services\Posting;
use App\Support\ApiError;
use App\Support\Scheduling\InProcess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** Posting module: services, listing policy, rate limits, schedule (expiry, cleanup). */
class PostingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ListingImages::class);
        $this->app->singleton(DuplicateListings::class);
        $this->app->singleton(Posting::class);
    }

    public function boot(): void
    {
        Gate::policy(Listing::class, ListingPolicy::class);

        $key = fn (Request $request) => (string) ($request->user()?->id ?? $request->ip());

        // changes in the wizard, "my listings" and the API
        RateLimiter::for('posting-write', fn (Request $request) => [
            Limit::perMinute(40)->by('posting-write:'.$key($request))->response($this->tooMany(...)),
        ]);
        // new drafts / new listings
        RateLimiter::for('posting-create', fn (Request $request) => [
            Limit::perMinute(5)->by('posting-create:'.$key($request))->response($this->tooMany(...)),
            Limit::perHour(30)->by('posting-create-h:'.$key($request))->response($this->tooMany(...)),
        ]);
        // image uploads (one request per image from the app or the page script)
        RateLimiter::for('posting-upload', fn (Request $request) => [
            Limit::perMinute(60)->by('posting-upload:'.$key($request))->response($this->tooMany(...)),
            Limit::perHour(300)->by('posting-upload-h:'.$key($request))->response($this->tooMany(...)),
        ]);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            InProcess::command($schedule, 'listings:expire')->hourly()->withoutOverlapping();
            InProcess::command($schedule, 'listings:plus-renew')->dailyAt('06:30')->withoutOverlapping()->onOneServer();
            InProcess::command($schedule, 'images:prune')->dailyAt('04:10')->withoutOverlapping();
            InProcess::command($schedule, 'images:variants')->everyTenMinutes()->withoutOverlapping();
        });
    }

    private function tooMany(Request $request, array $headers)
    {
        $message = 'Previše pokušaja. Sačekajte malo pa probajte ponovo.';

        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiError::response('too_many_requests', $message, 429, [], $headers);
        }

        return back()->withInput()->withErrors(['limit' => $message])->setStatusCode(302);
    }
}
