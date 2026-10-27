<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use App\Services\Catalog;
use App\Support\Settings;
use Illuminate\Http\Response;

/** robots.txt, llms.txt and /stranica-404/. */
class RobotsController extends Controller
{
    /** Paths no crawler needs (seo-sta-mora-da-ostane.md, section 5). */
    public const DISALLOW = ['/admin', '/api/', '/panel/', '/nalog/', '/naplata/'];

    /** Search results by keyword (new `keyword`/`q`, old WordPress `?s=`): endless combinations, no own content. */
    public const SEARCH_QUERIES = ['/*?*keyword=', '/*?*q=', '/*?s='];

    /** Crawlers with their own group: they ignore the `*` group, so the Disallow lines are repeated. */
    public const ALLOWED_BOTS = [
        'Googlebot', 'Bingbot', 'OAI-SearchBot', 'ChatGPT-User', 'GPTBot', 'ClaudeBot', 'Claude-SearchBot',
        'Claude-User', 'PerplexityBot', 'Google-Extended', 'Applebot-Extended',
    ];

    public function robots(): Response
    {
        if (! config('seo.indexable')) {
            // Test and local versions: nothing is indexed.
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            // Bots with their own group ignore the `*` group, so both get the same Disallow lines.
            $disallow = array_map(fn ($path) => 'Disallow: '.$path, array_merge(self::DISALLOW, self::SEARCH_QUERIES));
            $lines = array_merge(
                ['User-agent: *'], $disallow, [''],
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
     * and is active, every link uses the canonical domain and {email} is the contact address from the settings.
     */
    public function llms(): Response
    {
        $lines = [];
        $email = (string) Settings::get('contact_email');

        foreach (preg_split('/\R/', (string) file_get_contents(resource_path('seo/llms.txt'))) as $line) {
            if (preg_match('#\(https://loglasi\.rs/oglasi/([a-z0-9-]+)/\)#', $line, $m)) {
                $category = app(Catalog::class)->categoryBySlug($m[1]);
                if ($category === null || ! app(Catalog::class)->isVisible($category)) {
                    continue;
                }
            }
            $lines[] = str_replace(['https://loglasi.rs', '{email}'], [(string) config('seo.base_url'), $email], $line);
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /** The old WordPress page "stranica-404" must return a real 404. */
    public function notFound(): never
    {
        abort(404);
    }
}
