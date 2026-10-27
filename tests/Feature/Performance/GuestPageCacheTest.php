<?php

namespace Tests\Feature\Performance;

use App\Models\Listing;
use App\Models\User;
use App\Support\Listing\Stats;
use Tests\TestCase;

class GuestPageCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['page_cache.enabled' => true]);
    }

    private function url(Listing $listing): string
    {
        return '/oglas/'.$listing->slug.'/';
    }

    public function test_second_guest_gets_the_cached_page_with_own_token_and_nonce(): void
    {
        $listing = Listing::factory()->create(['title' => 'Golf 7 dizel']);

        $first = $this->get($this->url($listing))->assertOk()->assertHeader('X-Page-Cache', 'miss');
        $second = $this->withSession(['_token' => 'drugi-posetilac-token-1234567890abcdefghijk'])
            ->get($this->url($listing))->assertOk()->assertHeader('X-Page-Cache', 'hit')->assertSee('Golf 7 dizel');

        $html = (string) $second->getContent();
        $this->assertStringNotContainsString('__LG_', $html);
        $this->assertStringContainsString('value="drugi-posetilac-token-1234567890abcdefghijk"', $html);
        preg_match("/'nonce-([A-Za-z0-9]+)'/", (string) $second->headers->get('Content-Security-Policy'), $nonce);
        $this->assertStringContainsString('<script nonce="'.$nonce[1].'">', $html);
        $this->assertNotSame((string) $first->headers->get('Content-Security-Policy'), (string) $second->headers->get('Content-Security-Policy'));
    }

    public function test_a_view_is_counted_also_from_the_cache(): void
    {
        $listing = Listing::factory()->create(['views' => 0]);

        $this->get($this->url($listing))->assertHeader('X-Page-Cache', 'miss');
        $this->flushSession();
        $this->get($this->url($listing))->assertHeader('X-Page-Cache', 'hit');
        $this->get($this->url($listing))->assertHeader('X-Page-Cache', 'hit'); // same visitor: counted once
        $this->flushSession();
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get($this->url($listing))->assertHeader('X-Page-Cache', 'hit');

        $this->assertSame(2, (int) $listing->fresh()->views);
    }

    public function test_signed_in_users_query_strings_and_flash_messages_get_a_fresh_page(): void
    {
        $listing = Listing::factory()->create();
        $this->get($this->url($listing))->assertHeader('X-Page-Cache', 'miss');

        $this->actingAs(User::factory()->create())->get($this->url($listing))->assertOk()->assertHeaderMissing('X-Page-Cache');
        auth()->logout();
        $this->get($this->url($listing).'?utm_source=fb')->assertOk()->assertHeaderMissing('X-Page-Cache');
        $this->withSession(['status' => 'Poruka je poslata.', '_flash' => ['old' => ['status'], 'new' => []]])
            ->get($this->url($listing))->assertHeaderMissing('X-Page-Cache');
        $this->withSession([Stats::SESSION_REVEALS => [$listing->id]])->get($this->url($listing))->assertHeaderMissing('X-Page-Cache');
    }

    public function test_a_changed_listing_is_built_again(): void
    {
        $listing = Listing::factory()->create(['title' => 'Stari naslov']);
        $this->get($this->url($listing))->assertHeader('X-Page-Cache', 'miss');

        usleep(2000);
        $listing->update(['title' => 'Novi naslov']);

        $this->get($this->url($listing))->assertHeader('X-Page-Cache', 'miss')->assertSee('Novi naslov');
    }

    public function test_only_public_pages_are_cached(): void
    {
        $this->get('/login-registracija/')->assertOk()->assertHeaderMissing('X-Page-Cache');
        $this->get('/')->assertOk()->assertHeader('X-Page-Cache', 'miss');
        $this->get('/')->assertOk()->assertHeader('X-Page-Cache', 'hit');
    }
}
