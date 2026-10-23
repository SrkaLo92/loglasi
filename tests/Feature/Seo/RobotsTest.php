<?php

namespace Tests\Feature\Seo;

use App\Models\Listing;
use DOMDocument;

class RobotsTest extends SeoTestCase
{
    public function test_production_robots_allows_bots_and_points_to_sitemap(): void
    {
        config(['seo.indexable' => true]);

        $response = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $body = $response->getContent();

        $this->assertStringContainsString("User-agent: *\nDisallow: /admin/\n", $body);

        foreach (['Googlebot', 'Bingbot', 'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'ClaudeBot', 'Claude-SearchBot', 'Claude-User', 'PerplexityBot', 'Perplexity-User', 'Google-Extended', 'Applebot', 'Applebot-Extended'] as $bot) {
            $this->assertStringContainsString("User-agent: {$bot}\nAllow: /\n", $body);
        }

        $this->assertStringContainsString('Sitemap: https://loglasi.rs/sitemap.xml', $body);
        $this->assertStringNotContainsString('sitemap_index', $body);
        $this->assertStringNotContainsString('Disallow: /api', $body);
        $this->assertStringNotContainsString('Disallow: /oglasi', $body);
    }

    public function test_non_production_robots_disallows_everything(): void
    {
        config(['seo.indexable' => false]);

        $this->assertSame("User-agent: *\nDisallow: /\n", $this->get('/robots.txt')->getContent());
    }

    public function test_llms_txt_is_served_as_utf8_text_unchanged(): void
    {
        $response = $this->get('/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $this->assertSame(file_get_contents(resource_path('seo/llms.txt')), $response->getContent());
        $this->assertStringContainsString('Banja Koviljača, Lešnica, Tršić', $response->getContent());
        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
    }

    public function test_feed_is_rss_with_noindex_header(): void
    {
        Listing::factory()->create(['title' => 'Golf 4', 'slug' => 'golf-4']);
        Listing::factory()->expired()->create(['title' => 'Istekao']);

        $response = $this->get('/feed/')->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex')
            ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($response->getContent()));
        $this->assertSame(1, $dom->getElementsByTagName('item')->length);
        $this->assertStringContainsString('https://loglasi.rs/oglas/golf-4/', $response->getContent());
    }

    public function test_stranica_404_returns_real_404(): void
    {
        $this->get('/stranica-404/')->assertNotFound();
    }

    public function test_unknown_url_is_404_not_redirect_to_home(): void
    {
        $this->get('/nepostojeca-strana/')->assertNotFound();
    }
}
