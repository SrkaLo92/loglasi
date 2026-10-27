<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // An N+1 query fails loudly in tests and locally, never on the live site.
        Model::preventLazyLoading(! $this->app->isProduction());

        // In production every URL is built on APP_URL, so a request on another host or scheme
        // (www, IP, http) can never leak its host into cached page sections.
        if ($this->app->isProduction()) {
            URL::forceRootUrl(config('app.url'));

            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
        }
    }
}
