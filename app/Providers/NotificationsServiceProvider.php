<?php

namespace App\Providers;

use App\Console\Commands\SendSavedSearchAlerts;
use App\Events\MessageSent;
use App\Http\Middleware\SavePendingSearch;
use App\Notifications\Listeners\HoldOutgoingMail;
use App\Notifications\Listeners\NotifyMessageRecipient;
use App\Support\ApiError;
use App\Support\Scheduling\InProcess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/** Notifications: rate limits, new-message notification, hourly saved-search alerts. */
class NotificationsServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        $router->pushMiddlewareToGroup('web', SavePendingSearch::class);

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
        Event::listen(MessageSending::class, HoldOutgoingMail::class);

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            InProcess::command($schedule, SendSavedSearchAlerts::class)->hourly()->withoutOverlapping(30);
        });
    }
}
