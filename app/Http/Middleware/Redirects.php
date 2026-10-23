<?php

namespace App\Http\Middleware;

use App\Support\Seo\Hits;
use App\Support\Seo\LegacyUrls;
use App\Support\Seo\Url;
use App\Support\Seo\UrlTables;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 301 preusmerenja: tabela `redirects` (iz admina) pa pravila za stare adrese (LegacyUrls).
 * Uvek jedan skok: odredište se razrešava do kraja pre slanja, parametri za praćenje se odbacuju.
 */
class Redirects
{
    private const MAX_DEPTH = 5;

    public function __construct(private LegacyUrls $legacy) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/*', 'admin', 'admin/*', 'up')) {
            return $next($request);
        }

        $path = $request->getPathInfo();
        $query = $request->query->all();
        $root = $request->getSchemeAndHttpHost();

        $map = UrlTables::redirects();
        $withQuery = UrlTables::key($path, $query);
        $plain = UrlTables::key($path);

        if ($row = ($withQuery !== $plain ? $map[$withQuery] ?? null : null) ?? $map[$plain] ?? null) {
            Hits::redirect($row['id']);
            // Zapis sa upitom (/?p=1) troši upit; zapis samo sa putanjom prenosi ostale parametre.
            $keep = isset($map[$withQuery]) && $withQuery !== $plain ? [] : Url::withoutTracking($query);
            $target = $this->final($row['to'], $keep, $plain);

            return $this->send($request, Url::target($root, $target['to'], $target['query']), $row['code']);
        }

        if ($rule = $this->legacy->resolve($request)) {
            Hits::rule($rule['rule']);

            if ($rule['raw']) {
                $qs = $request->server('QUERY_STRING');

                return $this->send($request, $root.$rule['to'].($qs ? '?'.$qs : ''), $rule['code']);
            }

            $target = $this->final($rule['to'], $rule['query'], $plain);

            return $this->send($request, Url::target($root, $target['to'], $target['query']), $rule['code']);
        }

        return $next($request);
    }

    /**
     * Ako je odredište i samo u tabeli preusmerenja, ide se odmah na krajnje (bez lanca).
     *
     * @return array{to: string, query: array<string, mixed>}
     */
    private function final(string $to, array $query, string $origin): array
    {
        $seen = [$origin => true];

        for ($i = 0; $i < self::MAX_DEPTH; $i++) {
            if (preg_match('#^https?://#i', $to)) {
                break;
            }

            $key = UrlTables::keyFromStored($to);
            $seen[$key] = true;
            $next = UrlTables::redirects()[$key] ?? null;

            if ($next === null || isset($seen[UrlTables::keyFromStored($next['to'])])) {
                break;
            }

            $to = $next['to'];
        }

        return ['to' => $to, 'query' => $query];
    }

    private function send(Request $request, string $url, int $code): Response
    {
        // Zaštita od petlje: odredište isto kao trenutni URL.
        if ($url === $request->fullUrl() || $url === $request->url()) {
            abort(404);
        }

        // RedirectResponse direktno: redirect()->to() bi skinuo kosu crtu na kraju.
        return new RedirectResponse($url, $code, ['Cache-Control' => 'public, max-age=86400']);
    }
}
