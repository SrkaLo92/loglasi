<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Api\ApiResponse;
use App\Http\Middleware\Gone;
use App\Http\Middleware\GuestPageCache;
use App\Http\Middleware\Redirects;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrailingSlash;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Only a reverse proxy on the same machine may set X-Forwarded-* headers.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        // SEO: before routing, so it also applies to addresses that no longer exist.
        // Order: redirects (one hop), then 410, then the trailing slash.
        $middleware->append([
            SecurityHeaders::class,
            Redirects::class,
            Gone::class,
            TrailingSlash::class,
        ]);

        // Inside the session and CSRF middleware: it needs the visitor's session and token.
        $middleware->web(append: [GuestPageCache::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // First login and writes (token_expired, 422), then the general error shape (400 for GET parameters, 404, 405...).
        ApiExceptionRenderer::register($exceptions);
        ApiResponse::register($exceptions);
    })->create();
