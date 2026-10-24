<?php

namespace App\Providers;

use App\Console\Commands\SendSavedSearchAlerts;
use App\Events\MessageSent;
use App\Notifications\Listeners\NotifyMessageRecipient;
use App\Support\ApiError;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** Notifications: rate limits, new-message notification, hourly saved-search alerts. */
class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('notifications-account', fn (Request $request) => [
            Limit::perMinute(30)->by('notifications:'.($request->user()?->id ?? $request->ip())),
        ]);

        RateLimiter::for('notifications-devices', fn (Request $request) => [
            Limit::perMinute(30)->by('devices:'.($request->user()?->id ?? $request->ip()))
                ->response(fn (Request $request, array $headers) => ApiError::response(
                    'too_many_requests', 'Previše pokušaja. Pokušajte ponovo malo kasnije.', 429, [], $headers,
                )),
        ]);

        // The messages module fires MessageSent after commit and sends nothing itself.
        // (Listener lives outside app/Listeners so event discovery does not register it twice.)
        Event::listen(MessageSent::class, NotifyMessageRecipient::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command(SendSavedSearchAlerts::class)->hourly()->withoutOverlapping(30);
        });
    }
}
