<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Services\Catalog;
use Illuminate\Http\Response;

/** robots.txt, llms.txt i /stranica-404/. */
class RobotsController extends Controller
{
    /** Paths no crawler needs (seo-sta-mora-da-ostane.md, section 5). */
    public const DISALLOW = ['/admin', '/api/', '/panel/', '/nalog/', '/naplata/'];

    /** Crawlers with their own group: they ignore the `*` group, so the Disallow lines are repeated. */
    public const ALLOWED_BOTS = [
        'Googlebot', 'Bingbot', 'OAI-SearchBot', 'ChatGPT-User', 'GPTBot', 'ClaudeBot', 'Claude-SearchBot',
        'Claude-User', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended',
    ];

    public function robots(): Response
    {
        if (! config('seo.indexable')) {
            // Probna i lokalna verzija: ništa se ne indeksira.
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            $disallow = array_map(fn ($path) => 'Disallow: '.$path, self::DISALLOW);
            $lines = array_merge(
                ['User-agent: *'], $disallow, ['Disallow: /uporedite-oglase/', 'Disallow: /*?*kljucna-rec=', ''],
                array_map(fn ($bot) => 'User-agent: '.$bot, self::ALLOWED_BOTS), ['Allow: /'], $disallow, [''],
                ['Sitemap: '.config('seo.base_url').'/sitemap.xml'],
            );
            $body = implode("\n", $lines)."\n";
        }

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * llms.txt from the approved text; a category line stays only while that category exists
     * and is active, and every link uses the canonical domain.
     */
    public function llms(): Response
    {
        $lines = [];

        foreach (preg_split('/\R/', (string) file_get_contents(resource_path('seo/llms.txt'))) as $line) {
            if (preg_match('#\(https://loglasi\.rs/oglasi/([a-z0-9-]+)/\)#', $line, $m)) {
                $category = app(Catalog::class)->categoryBySlug($m[1]);
                if ($category === null || ! app(Catalog::class)->isVisible($category)) {
                    continue;
                }
            }
            $lines[] = str_replace('https://loglasi.rs', (string) config('seo.base_url'), $line);
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** Stara WordPress strana „stranica-404” mora da vrati pravi 404. */
    public function notFound(): never
    {
        abort(404);
    }
}
