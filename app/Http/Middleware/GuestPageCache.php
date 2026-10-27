<?php

namespace App\Http\Middleware;

use App\Support\PageCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Whole-page cache for guests on public pages (config/page_cache.php). Served only to a visitor whose
 * session holds nothing that changes the page (no flash message, form errors, revealed phone, pending
 * search) and for an address without a query string, so random ?x= values cannot fill the cache.
 * The CSRF token and CSP nonce are put back per request, so forms and inline scripts keep working.
 */
class GuestPageCache
{
    /** Session keys that do not change how a page looks ("listing" holds the counted views, not a revealed phone). */
    private const NEUTRAL_SESSION_KEYS = ['_token', '_previous', '_flash', 'url', 'filament', 'listing'];

    private const NEUTRAL_LISTING_KEYS = ['viewed', 'profiles_viewed'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->applies($request)) {
            return $next($request);
        }

        if ($entry = PageCache::get($request)) {
            PageCache::count($entry['hits'], $request);
            $response = new Response(strtr($entry['body'], [
                PageCache::TOKEN => $request->session()->token(),
                PageCache::NONCE => Vite::cspNonce(),
            ]), $entry['status'], $entry['headers']);
            $response->headers->set('X-Page-Cache', 'hit');

            return $response;
        }

        PageCache::takeHits();
        $response = $next($request);
        $hits = PageCache::takeHits();

        if ($this->storable($request, $response)) {
            $body = (string) $response->getContent();
            $token = (string) $request->session()->token();
            $nonce = (string) Vite::cspNonce();
            $headers = $response->headers->all();
            unset($headers['set-cookie'], $headers['content-security-policy'], $headers['date']);
            PageCache::put($request, $response->getStatusCode(), $headers, strtr($body, array_filter([$token => PageCache::TOKEN, $nonce => PageCache::NONCE])), $hits);
            $response->headers->set('X-Page-Cache', 'miss');
        }

        return $response;
    }

    private function applies(Request $request): bool
    {
        return config('page_cache.enabled')
            && $request->isMethod('GET')
            && $request->getQueryString() === null
            && $request->hasSession()
            && $request->user() === null
            && in_array($request->route()?->getName(), (array) config('page_cache.routes'), true)
            && $this->neutralSession($request);
    }

    private function neutralSession(Request $request): bool
    {
        $session = $request->session();
        if ($session->get('_flash.old', []) !== [] || $session->get('_flash.new', []) !== []) {
            return false;
        }

        return array_diff(array_keys($session->all()), self::NEUTRAL_SESSION_KEYS) === []
            && array_diff(array_keys((array) $session->get('listing', [])), self::NEUTRAL_LISTING_KEYS) === [];
    }

    private function storable(Request $request, Response $response): bool
    {
        return $response->getStatusCode() === 200
            && $request->user() === null
            && ! $response->headers->has('Set-Cookie')
            && str_contains((string) $response->headers->get('Content-Type'), 'text/html')
            && $this->neutralSession($request);
    }
}
