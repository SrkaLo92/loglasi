<?php

namespace App\Http\Middleware;

use App\Support\Seo\Url;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public HTML addresses end with a slash, as on WordPress: /oglasi/automobili -> /oglasi/automobili/ (301).
 * The query stays the same. Does not touch the API, admin, /up and files (sitemap.xml, robots.txt, images).
 */
class TrailingSlash
{
    /** Paths left alone (Request::is patterns). */
    private const EXCEPT = ['api', 'api/*', 'admin', 'admin/*', 'up', 'livewire/*', 'storage/*', 'build/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)
            || $path === '/'
            || str_ends_with($path, '/')
            || Url::hasExtension($path)
            || $request->is(...self::EXCEPT)
            || $request->query->has('signature')) { // signed links (email confirmation) work without the slash too
            return $next($request);
        }

        $query = $request->server('QUERY_STRING');
        $url = $request->getSchemeAndHttpHost().$request->getBaseUrl().$path.'/'.($query ? '?'.$query : '');

        return new RedirectResponse($url, 301);
    }
}
