<?php

namespace Tests\Feature\Seo;

use App\Models\Category;

class RobotsTest extends SeoTestCase
{
    public function test_production_robots_is_the_exact_block_from_the_seo_plan(): void
    {
        config(['seo.indexable' => true]);

        $response = $this->get('/robots.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $expected = <<<'TXT'
User-agent: *
Disallow: /admin
Disallow: /api/
Disallow: /panel/
Disallow: /nalog/
Disallow: /naplata/
Disallow: /uporedite-oglase/
Disallow: /*?*kljucna-rec=

User-agent: Googlebot
User-agent: Bingbot
User-agent: OAI-SearchBot
User-agent: ChatGPT-User
User-agent: GPTBot
User-agent: ClaudeBot
User-agent: Claude-SearchBot
User-agent: Claude-User
User-agent: PerplexityBot
User-agent: Google-Extended
User-agent: Applebot-Extended
Allow: /
Disallow: /admin
Disallow: /api/
Disallow: /panel/
Disallow: /nalog/
Disallow: /naplata/

Sitemap: https://loglasi.rs/sitemap.xml

TXT;

        $this->assertSame($expected, $response->getContent());
    }

    public function test_non_production_robots_disallows_everything(): void
    {
        config(['seo.indexable' => false]);

        $this->assertSame("User-agent: *\nDisallow: /\n", $this->get('/robots.txt')->getContent());
    }

    public function test_llms_txt_links_only_existing_categories(): void
    {
        Category::factory()->create(['name' => 'Automobili', 'slug' => 'automobili']);

        $response = $this->get('/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $body = $response->getContent();

        $this->assertStringContainsString('[Automobili](https://loglasi.rs/oglasi/automobili/)', $body);
        $this->assertStringNotContainsString('oglasi/nekretnine/', $body);
        $this->assertStringContainsString('[O nama](https://loglasi.rs/o-nama/)', $body);
        $this->assertStringContainsString('Banja Koviljača, Lešnica, Tršić', $body);
        $this->assertStringNotContainsString('Podrinj', $body);
        $this->assertStringContainsString('max-age=86400', $response->headers->get('Cache-Control'));
    }

    public function test_every_feed_url_is_gone(): void
    {
        foreach (['/feed/', '/feed', '/comments/feed/', '/oglasi/feed/', '/oglasi/automobili/feed/', '/oglas/golf-4/feed/', '/korisnik/pera/feed/'] as $url) {
            $this->get($url)->assertStatus(410);
        }
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
