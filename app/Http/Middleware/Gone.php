<?php

namespace App\Http\Middleware;

use App\Models\Listing;
use App\Models\User;
use App\Support\Seo;
use App\Support\Seo\Hits;
use App\Support\Seo\UrlTables;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 410 for permanently removed addresses: the `gone_urls` table (spam, clean-up), plus deleted listings
 * and accounts (soft delete) when it would otherwise be a 404.
 */
class Gone
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
        }

        // The site has no feeds and no blog: every old WordPress feed and blog URL is gone for good.
        // ... and so is every Listivo template page (/listivo_template/...).
        if (preg_match('#(^|/)feed/?$#', $request->getPathInfo()) || preg_match('#^/(listivo_template|blog)(/|$)#', $request->getPathInfo())) {
            return static::response();
        }

        if ($id = UrlTables::findGone($request->getPathInfo())) {
            Hits::gone($id);

            return static::response();
        }

        $response = $next($request);

        if ($response->getStatusCode() === 404 && $this->wasDeleted($request->getPathInfo())) {
            return static::response();
        }

        return $response;
    }

    /** A listing or account that exists only as deleted (soft delete). */
    private function wasDeleted(string $path): bool
    {
        $path = rawurldecode($path);

        if (preg_match('#^/oglas/([^/]+)/?$#', $path, $m)) {
            return Listing::onlyTrashed()->where('slug', $m[1])->exists();
        }

        if (preg_match('#^/korisnik/([^/]+)/?$#', $path, $m)) {
            return User::onlyTrashed()->where('slug', $m[1])->exists();
        }

        return false;
    }

    /** 410 page: the errors.410 template if it exists, otherwise a short text. */
    public static function response(): Response
    {
        $headers = ['X-Robots-Tag' => 'noindex', 'Cache-Control' => 'public, max-age=3600'];

        if (view()->exists('errors.410')) {
            Seo::get()->noindex()->title('Strana je uklonjena | loglasi.rs');

            return response()->view('errors.410', [], 410, $headers);
        }

        return response("Ova strana je trajno uklonjena.\n", 410, $headers + ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
