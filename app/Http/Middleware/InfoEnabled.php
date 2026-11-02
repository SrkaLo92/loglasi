<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/** The info portal answers only once it is switched on (INFO_ENABLED), so its host shows nothing before launch. */
class InfoEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('info.enabled'), 404);
        // Production forces the root URL to the main site; the portal's CSS, JS and images come from its own
        // host, so its Content-Security-Policy ('self') and fonts work without cross-origin rules.
        URL::useAssetOrigin($request->getSchemeAndHttpHost());

        return $next($request);
    }
}
