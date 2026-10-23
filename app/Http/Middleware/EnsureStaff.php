<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** Samo administrator i moderator (gate "staff"); "staff:admin" traži administratora. */
class EnsureStaff
{
    public function handle(Request $request, Closure $next, string $ability = 'staff'): Response
    {
        $user = $request->user();

        if ($user === null) {
            throw new AuthenticationException('Unauthenticated.', [], $request->expectsJson() ? null : url_slash('prijava'));
        }

        abort_unless(Gate::forUser($user)->allows($ability === 'admin' ? 'admin' : 'staff'), 403);

        return $next($request);
    }
}
