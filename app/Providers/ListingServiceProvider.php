<?php

namespace App\Providers;

use App\Http\Api\ApiResponse;
use App\Models\Listing;
use App\Support\Listing\Seller;
use App\Support\RateKey;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** Listing page and seller profiles: rate limits and small caches. */
class ListingServiceProvider extends ServiceProvider
{
    public const TOO_MANY = 'Previše pokušaja. Sačekajte malo pa probajte ponovo.';

    public function boot(): void
    {
        $who = fn (Request $request) => $request->user()?->id ? 'u'.$request->user()->id : 'ip'.$request->ip();

        // Phone reveal: enough for a real buyer, too little for scraping numbers.
        RateLimiter::for('listing-phone', fn (Request $request) => [
            Limit::perMinute(10)->by('phone:'.$who($request))->response($this->tooMany(...)),
            Limit::perDay(100)->by('phone-day:'.$who($request))->response($this->tooMany(...)),
        ]);

        RateLimiter::for('listing-report', fn (Request $request) => [
            Limit::perMinutes(10, 5)->by('report:'.$who($request))->response($this->tooMany(...)),
            Limit::perDay(20)->by('report-day:'.$who($request))->response($this->tooMany(...)),
        ]);

        // Public reads in the API (detail, similar, firm).
        RateLimiter::for('listing-api', fn (Request $request) => [
            Limit::perMinute(120)->by('listing-api:'.RateKey::client($request))->response($this->tooMany(...)),
        ]);

        // The active listings count in the seller card follows status changes.
        $forget = fn (Listing $listing) => Seller::forgetCount((int) $listing->user_id);
        Listing::saved($forget);
        Listing::deleted($forget);
        Listing::restored($forget);
    }

    /** 429: contract error for the API and fetch, message on the page for forms. */
    private function tooMany(Request $request, array $headers)
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiResponse::error('too_many_requests', self::TOO_MANY, 429, [], $headers);
        }

        return back()->with('listing_error', self::TOO_MANY)->setStatusCode(302)->withHeaders($headers);
    }
}
