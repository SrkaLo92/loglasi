<?php

namespace App\Providers;

use App\Http\Middleware\EnsureNotBlocked;
use App\Http\Middleware\EnsureStaff;
use App\Mail\Welcome;
use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Notifications\Listeners\HoldOutgoingMail;
use App\Support\ApiError;
use App\Support\Auth\LoginPage;
use App\Support\Auth\LoglasiHasher;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/** Nalozi i prijava: heš lozinki, dozvole, ograničenja zahteva, tokeni za aplikaciju, mejlovi. */
class AccountsServiceProvider extends ServiceProvider
{
    public function boot(Router $router): void
    {
        Hash::extend('loglasi', fn ($app) => new LoglasiHasher($app['config']->get('hashing.argon', [])));

        $this->gates();
        $this->rateLimits();
        $this->middleware($router);
        $this->tokens();
        $this->mails();

        // Promena broja telefona skida proveru, osim kad admin u istom koraku upiše i proveru.
        User::saving(function (User $user) {
            if ($user->exists && $user->isDirty('phone') && ! $user->isDirty('phone_verified_at')) {
                $user->phone_verified_at = null;
            }
        });
    }

    private function gates(): void
    {
        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('staff', fn (User $user) => $user->isStaff());
    }

    private function rateLimits(): void
    {
        $email = fn (Request $request) => Str::lower(trim((string) $request->input('email')));

        RateLimiter::for('nalozi-login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.$email($request).'|'.$request->ip())->response($this->tooMany(...)),
            Limit::perMinute(30)->by('login-ip:'.$request->ip())->response($this->tooMany(...)),
        ]);

        RateLimiter::for('nalozi-register', fn (Request $request) => [
            Limit::perHour(5)->by('register:'.$request->ip())->response($this->tooMany(...)),
        ]);

        RateLimiter::for('nalozi-password', fn (Request $request) => [
            Limit::perMinutes(10, 3)->by('reset:'.$email($request))->response($this->tooMany(...)),
            Limit::perHour(10)->by('reset-ip:'.$request->ip())->response($this->tooMany(...)),
        ]);

        RateLimiter::for('nalozi-refresh', fn (Request $request) => [
            Limit::perMinute(30)->by('refresh:'.$request->ip())->response($this->tooMany(...)),
        ]);

        RateLimiter::for('nalozi-account', fn (Request $request) => [
            Limit::perMinute(20)->by('account:'.($request->user()?->id ?? $request->ip()))->response($this->tooMany(...)),
        ]);
    }

    /** 429: JSON po ugovoru za API, poruka iznad forme za sajt. */
    private function tooMany(Request $request, array $headers)
    {
        $message = 'Previše pokušaja. Sačekajte malo pa probajte ponovo.';

        if ($request->is('api/*') || $request->expectsJson()) {
            return ApiError::response('too_many_requests', $message, 429, [], $headers);
        }

        return back()->withInput($request->except('password', 'password_confirmation', 'current_password'))
            ->withErrors(['email' => $message])
            ->setStatusCode(302);
    }

    private function middleware(Router $router): void
    {
        $router->aliasMiddleware('staff', EnsureStaff::class);
        $router->aliasMiddleware('not-blocked', EnsureNotBlocked::class);
        $router->pushMiddlewareToGroup('web', EnsureNotBlocked::class);

        Authenticate::redirectUsing(fn () => LoginPage::url());
        RedirectIfAuthenticated::redirectUsing(fn () => url_slash('moj-nalog'));
    }

    private function tokens(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Blokiran korisnik ne može da koristi ni postojeći token.
        Sanctum::authenticateAccessTokensUsing(
            fn (PersonalAccessToken $token, bool $isValid) => $isValid
                && $token->tokenable instanceof User
                && ! $token->tokenable->isBlocked()
        );
    }

    private function mails(): void
    {
        // Welcome mail once the new account's email address is confirmed.
        Event::listen(Verified::class, function (Verified $event) {
            $user = $event->user;
            if ($user instanceof User && $user->email && ! $user->isBlocked()) {
                Mail::to($user)->queue(new Welcome($user));
            }
        });

        VerifyEmail::toMailUsing(fn ($notifiable, string $url) => (new MailMessage)
            ->subject('Potvrdite email adresu na loglasi.rs')
            ->greeting('Zdravo!')
            ->line('Kliknite na dugme da potvrdite email adresu svog naloga na loglasi.rs.')
            ->action('Potvrdi email adresu', $url)
            ->line('Ako niste napravili nalog, ništa ne morate da radite.')
            ->salutation('Pozdrav, loglasi.rs')
            ->withSymfonyMessage(HoldOutgoingMail::markRequested(...)));

        ResetPassword::createUrlUsing(fn ($user, string $token) => url_slash('nova-lozinka/'.$token).'?email='.urlencode($user->getEmailForPasswordReset()));

        ResetPassword::toMailUsing(fn ($notifiable, string $token) => (new MailMessage)
            ->subject('Nova lozinka za loglasi.rs')
            ->greeting('Zdravo!')
            ->line('Dobili ste ovaj mejl jer je zatražena nova lozinka za vaš nalog.')
            ->action('Postavi novu lozinku', url_slash('nova-lozinka/'.$token).'?email='.urlencode($notifiable->getEmailForPasswordReset()))
            ->line('Link važi '.config('auth.passwords.users.expire').' minuta.')
            ->line('Ako niste tražili novu lozinku, ništa ne morate da radite.')
            ->salutation('Pozdrav, loglasi.rs')
            ->withSymfonyMessage(HoldOutgoingMail::markRequested(...)));
    }
}
