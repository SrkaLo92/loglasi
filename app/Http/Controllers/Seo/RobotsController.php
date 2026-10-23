<?php

namespace App\Http\Controllers\Seo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/** robots.txt, llms.txt i /stranica-404/. */
class RobotsController extends Controller
{
    /** Botovi pretraživača koji eksplicitno smeju sve (09-deploy-seo.md, odeljak 5). */
    public const ALLOWED_BOTS = [
        'Googlebot', 'Bingbot', 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-SearchBot',
        'Claude-User', 'PerplexityBot', 'Perplexity-User', 'Google-Extended', 'Applebot', 'Applebot-Extended',
    ];

    public function robots(): Response
    {
        if (! config('seo.indexable')) {
            // Probna i lokalna verzija: ništa se ne indeksira.
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            // Filteri, paginacija i strane naloga se NE blokiraju: Google mora da vidi noindex.
            $lines = ['User-agent: *', 'Disallow: /admin/', ''];

            foreach (self::ALLOWED_BOTS as $bot) {
                array_push($lines, 'User-agent: '.$bot, 'Allow: /', '');
            }

            $lines[] = 'Sitemap: '.config('seo.base_url').'/sitemap.xml';
            $body = implode("\n", $lines)."\n";
        }

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function llms(): Response
    {
        return response((string) file_get_contents(resource_path('seo/llms.txt')), 200, [
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
