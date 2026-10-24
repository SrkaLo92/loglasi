<?php

namespace App\Providers;

use App\Services\Messages\Messenger;
use App\Support\ApiError;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;
use Throwable;

/** Messages and favourites: service, rate limits, unread count for the header and bottom nav. */
class MessagesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Messenger::class);
    }

    public function boot(): void
    {
        $this->rateLimits();

        // App\Events\MessageSent is fired on every stored message; the notifications module listens to it.

        // $unreadMessages for partials.bottom-nav (and the header, when it shows a badge).
        View::composer(['partials.bottom-nav', 'partials.header'], function (ViewInstance $view) {
            if (array_key_exists('unreadMessages', $view->getData())) {
                return;
            }
            $user = auth()->user();
            $count = 0;
            if ($user) {
                try {
                    $count = $this->app->make(Messenger::class)->unreadTotal($user);
                } catch (Throwable) {
                    $count = 0; // error pages render without a database
                }
            }
            $view->with('unreadMessages', $count);
        });
    }

    private function rateLimits(): void
    {
        $user = fn (Request $request) => (string) ($request->user()?->id ?? 'ip:'.$request->ip());

        RateLimiter::for('messages-send', fn (Request $request) => [
            Limit::perMinute((int) config('messages.per_minute'))->by('msg-min:'.$user($request))->response($this->tooMany(...)),
            Limit::perDay((int) config('messages.per_day'))->by('msg-day:'.$user($request))->response($this->tooMany(...)),
        ]);

        RateLimiter::for('messages-poll', fn (Request $request) => [
            Limit::perMinute(30)->by('msg-poll:'.$user($request)),
        ]);

        RateLimiter::for('messages-favorites', fn (Request $request) => [
            Limit::perMinute(60)->by('fav:'.$user($request))->response($this->tooMany(...)),
        ]);
    }

    private function tooMany(Request $request, array $headers)
    {
        $message = 'Poslali ste previše poruka za kratko vreme. Sačekajte malo pa probajte ponovo.';

        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiError::response('too_many_requests', $message, 429, [], $headers);
        }

        return back()->withInput($request->only('body'))->withErrors(['body' => $message]);
    }
}
