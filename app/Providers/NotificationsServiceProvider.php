<?php

namespace App\Providers;

use App\Console\Commands\SendSavedSearchAlerts;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use App\Support\ApiError;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
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

        // Every new message notifies the other side (push always, mail throttled).
        Message::created(function (Message $message) {
            $conversation = $message->conversation()->first(['id', 'buyer_id', 'seller_id']);
            $recipientId = $conversation?->buyer_id === $message->sender_id ? $conversation?->seller_id : $conversation?->buyer_id;
            $recipient = $recipientId ? User::query()->find($recipientId) : null;

            if ($recipient !== null && $recipient->id !== $message->sender_id && ! $recipient->isBlocked()) {
                $recipient->notify(new NewMessageNotification($message));
            }
        });

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            $schedule->command(SendSavedSearchAlerts::class)->hourly()->withoutOverlapping(30);
        });
    }
}
