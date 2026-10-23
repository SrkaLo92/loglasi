<?php

namespace App\Http\Middleware\Admin;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filament ships Serbian translations as "sr_Latn"; the app uses "sr".
 * In the panel the locale is sr_Latn and missing app lines fall back to "sr".
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('sr_Latn');
        app('translator')->setFallback('sr');
        Carbon::setLocale('sr_Latn');

        return $next($request);
    }
}
