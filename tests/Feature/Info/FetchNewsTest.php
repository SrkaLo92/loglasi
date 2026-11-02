<?php

namespace Tests\Feature\Info;

use App\Models\InfoPost;
use App\Models\InfoSource;
use App\Support\Info\NewsRewriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchNewsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['info.enabled' => true, 'info.news.api_key' => 'test-key']);
        @unlink(storage_path('app/info-news-seen.json'));
        InfoSource::query()->create(['name' => 'Lokalni portal', 'url' => 'https://portal.test', 'feed_url' => 'https://portal.test/feed', 'active' => true]);
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/info-news-seen.json'));
        parent::tearDown();
    }

    private function feed(): string
    {
        $date = now()->toRfc2822String();

        return <<<XML
        <?xml version="1.0"?><rss><channel>
        <item><title>Izložba slika u Kulturnom centru</title><link>https://portal.test/izlozba</link><description>Otvara se izložba.</description><pubDate>{$date}</pubDate></item>
        <item><title>Uhapšen muškarac u Loznici</title><link>https://portal.test/hapsenje</link><description>Policija...</description><pubDate>{$date}</pubDate></item>
        <item><title>Sednica skupštine grada</title><link>https://portal.test/sednica</link><description>Odbornici...</description><pubDate>{$date}</pubDate></item>
        <item><title>Sajam meda u Banji</title><link>https://portal.test/sajam</link><description>Sajam meda.</description><pubDate>{$date}</pubDate></item>
        </channel></rss>
        XML;
    }

    public function test_culture_goes_pending_and_politics_or_crime_never_reach_the_api(): void
    {
        Http::fake([
            'portal.test/feed' => Http::response($this->feed()),
            'portal.test/*' => Http::response('<p>kratko</p>'),
            'api.anthropic.com/*' => Http::sequence()
                ->push(['content' => [['type' => 'text', 'text' => '{"allowed":true,"type":"event","topic":"kultura","title":"Nova izložba slika u Loznici","summary":"Kulturni centar otvara izložbu.","place":"Loznica","event_starts_at":"2026-11-05 19:00","event_venue":"Kulturni centar"}']]])
                ->push(['content' => [['type' => 'text', 'text' => '{"allowed":false,"reason":"nije iz kraja"}']]]),
        ]);

        $this->artisan('info:fetch-news')->assertSuccessful();

        $this->assertSame(1, InfoPost::query()->count());
        $post = InfoPost::query()->first();
        $this->assertSame('pending', $post->status);
        $this->assertSame('event', $post->type);
        $this->assertSame('Nova izložba slika u Loznici', $post->title);
        $this->assertSame('https://portal.test/izlozba', $post->source_url);
        Http::assertSentCount(1 + 2 + 2); // feed, two article pages, two API calls (crime and council were dropped first)

        // Next hour: nothing is asked again.
        Http::fake(['portal.test/feed' => Http::response($this->feed()), '*' => Http::response('', 500)]);
        $this->artisan('info:fetch-news')->assertSuccessful();
        $this->assertSame(1, InfoPost::query()->count());
    }

    public function test_word_list_matches_word_starts_only(): void
    {
        $this->assertTrue(NewsRewriter::blocked('Uhapšen muškarac'));
        $this->assertTrue(NewsRewriter::blocked('Lokalni izbori u nedelju'));
        $this->assertFalse(NewsRewriter::blocked('Čudesan koncert i umnožavanje knjiga'));
        $this->assertFalse(NewsRewriter::blocked('Bogat izbor programa za decu'));
    }

    public function test_without_api_key_nothing_happens(): void
    {
        config(['info.news.api_key' => null]);
        Http::fake();

        $this->artisan('info:fetch-news')->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(0, InfoPost::query()->count());
    }
}
