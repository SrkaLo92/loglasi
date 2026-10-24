<?php

namespace App\Http\Middleware;

use App\Support\ApiError;
use App\Support\Auth\LoginPage;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Blokiran korisnik se odjavljuje i ne može dalje (sajt), odnosno dobija 403 (API). */
class EnsureNotBlocked
{
    public const MESSAGE = 'Vaš nalog je blokiran. Za više informacija pišite nam na kontakt stranici.';

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && $user->isBlocked()) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiError::response('account_blocked', self::MESSAGE, 403);
            }

            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->to(LoginPage::url())->withErrors(['email' => self::MESSAGE]);
        }

        return $next($request);
    }
}
