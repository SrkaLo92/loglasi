<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** The info portal answers only once it is switched on (INFO_ENABLED), so its host shows nothing before launch. */
class InfoEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(config('info.enabled'), 404);

        return $next($request);
    }
}
