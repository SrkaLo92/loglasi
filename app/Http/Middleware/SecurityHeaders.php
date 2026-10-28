<?php

namespace App\Http\Middleware;

use App\Support\SecretUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser security headers on every response: no MIME sniffing, no framing by other sites, a strict
 * referrer, no camera/microphone, HSTS over HTTPS. HTML pages of the site also get a Content Security
 * Policy (config/security.php) where inline scripts run only with the request's nonce; the admin panel
 * (Filament, Livewire) gets only frame-ancestors.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // One nonce per request for our own inline scripts (cookie bar, Google tag, footer).
        Vite::useCspNonce();

        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', SecretUrl::is($request) ? 'no-referrer' : 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), payment=(), usb=(), interest-cohort=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->remove('X-Powered-By');
        if (! app()->runningInConsole() && ! headers_sent()) {
            header_remove('X-Powered-By'); // set by PHP itself (expose_php)
        }

        if ($request->isSecure() && ($maxAge = (int) config('security.hsts_max_age')) > 0) {
            $headers->set('Strict-Transport-Security', 'max-age='.$maxAge.(config('security.hsts_subdomains') ? '; includeSubDomains' : ''));
        }

        if (! $headers->has('Content-Security-Policy') && str_contains((string) $headers->get('Content-Type'), 'text/html')) {
            $headers->set('Content-Security-Policy', $this->policy($request));
        }

        return $response;
    }

    private function policy(Request $request): string
    {
        if ($request->is('admin', 'admin/*', 'livewire/*')) {
            return "frame-ancestors 'self'; base-uri 'self'; object-src 'none'";
        }

        $directives = config('security.csp', []);
        $directives['script-src'][] = "'nonce-".Vite::cspNonce()."'";
        if ($request->isSecure()) {
            $directives['upgrade-insecure-requests'] = [];
        }

        return implode('; ', array_map(
            fn (string $name, array $sources) => trim($name.' '.implode(' ', $sources)),
            array_keys($directives),
            $directives,
        ));
    }
}
