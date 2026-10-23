<?php

namespace App\Http\Middleware\Admin;

use App\Filament\Pages\Auth\Login;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The site login (/prijava/) has no second factor. The panel is only usable
 * after signing in through the panel login page, which runs the TOTP challenge.
 */
class EnsureAdminLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && (int) $request->session()->get(Login::SESSION_KEY) !== (int) $user->getAuthIdentifier()) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->to(Filament::getLoginUrl());
        }

        return $next($request);
    }
}
