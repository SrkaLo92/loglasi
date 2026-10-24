<?php

namespace App\Http\Middleware;

use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Firm-only pages and endpoints: 403 on the site, 404 not_found on the API (contract of /me/firm). */
class EnsureFirmAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isFirm()) {
            if ($request->is('api/*')) {
                return ApiError::response('not_found', 'Nalog nije nalog firme.', 404);
            }
            abort(403, 'Ova strana je samo za naloge firmi.');
        }

        return $next($request);
    }
}
