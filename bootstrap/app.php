<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Api\ApiResponse;
use App\Http\Middleware\Gone;
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

        // SEO: pre rutiranja, da važi i za adrese koje više ne postoje.
        // Redosled: preusmerenja (jedan skok), pa 410, pa kosa crta na kraju.
        $middleware->append([
            SecurityHeaders::class,
            Redirects::class,
            Gone::class,
            TrailingSlash::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Prvo prijava i upis (token_expired, 422), zatim opšti oblik grešaka (400 za GET parametre, 404, 405...).
        ApiExceptionRenderer::register($exceptions);
        ApiResponse::register($exceptions);
    })->create();
