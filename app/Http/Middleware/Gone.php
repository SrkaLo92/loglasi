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
 * 410 za trajno uklonjene adrese: tabela `gone_urls` (spam, čišćenje), plus obrisani oglasi
 * i nalozi (soft delete) kad bi inače bio 404.
 */
class Gone
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            return $next($request);
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

    /** Oglas ili nalog koji postoji samo kao obrisan (soft delete). */
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

    /** Strana 410: šablon errors.410 ako postoji, inače kratak tekst. */
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
