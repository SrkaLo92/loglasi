<?php

namespace App\Http\Middleware;

use App\Support\Seo\Url;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Javne HTML adrese se završavaju kosom crtom, kao na WordPress-u: /oglasi/automobili -> /oglasi/automobili/ (301).
 * Upit ostaje isti. Ne dira API, admin, /up i fajlove (sitemap.xml, robots.txt, slike).
 */
class TrailingSlash
{
    /** Putanje koje se ne diraju (Request::is obrasci). */
    private const EXCEPT = ['api', 'api/*', 'admin', 'admin/*', 'up', 'livewire/*', 'storage/*', 'build/*'];

    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();

        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)
            || $path === '/'
            || str_ends_with($path, '/')
            || Url::hasExtension($path)
            || $request->is(...self::EXCEPT)) {
            return $next($request);
        }

        $query = $request->server('QUERY_STRING');
        $url = $request->getSchemeAndHttpHost().$request->getBaseUrl().$path.'/'.($query ? '?'.$query : '');

        return new RedirectResponse($url, 301);
    }
}
