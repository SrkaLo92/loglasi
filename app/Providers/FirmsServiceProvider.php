<?php

namespace App\Providers;

use App\Http\Middleware\EnsureFirmAccount;
use App\Models\Firm;
use App\Models\User;
use App\Services\FirmBenefits;
use App\Support\ApiError;
use App\Support\Firms\FirmDirectory;
use App\Support\Scheduling\InProcess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** Firms module: FirmBenefits service, "firm" middleware, rate limit, directory cache, reminder schedule. */
class FirmsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FirmBenefits::class);
    }

    public function boot(Router $router): void
    {
        $router->aliasMiddleware('firm', EnsureFirmAccount::class);

        RateLimiter::for('firms-account', fn (Request $request) => [
            Limit::perMinute(20)->by('firms-account:'.($request->user()?->id ?? $request->ip()))
                ->response(function (Request $request, array $headers) {
                    $message = 'Previše izmena. Sačekajte malo pa probajte ponovo.';

                    return $request->is('api/*') || $request->expectsJson()
                        ? ApiError::response('too_many_requests', $message, 429, [], $headers)
                        : back()->withInput()->withErrors(['name' => $message]);
                }),
        ]);

        // The directory is cached; any change of a firm or of a firm account clears it.
        $forget = fn () => FirmDirectory::forget();
        Firm::saved($forget);
        Firm::deleted($forget);
        User::saved(function (User $user) use ($forget) {
            if ($user->wasChanged('account_type')
                || ($user->isFirm() && ($user->wasRecentlyCreated || $user->wasChanged(['status', 'slug', 'name', 'deleted_at'])))) {
                $forget();
            }
        });
        User::deleted(fn (User $user) => $user->isFirm() ? $forget() : null);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            InProcess::command($schedule, 'firms:package-reminders')->dailyAt('09:00')->withoutOverlapping()->onOneServer();
        });
    }
}
