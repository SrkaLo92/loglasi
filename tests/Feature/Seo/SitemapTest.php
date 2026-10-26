<?php

namespace Tests\Feature\Seo;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Location;
use App\Models\User;
use DOMDocument;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;

class SitemapTest extends SeoTestCase
{
    /** Proverava da je XML ispravan i vraća sve <loc>. */
    private function locs(TestResponse $response): array
    {
        $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($response->getContent()), 'XML nije ispravan');
        $this->assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $dom->documentElement->namespaceURI);

        $locs = [];

        foreach ($dom->getElementsByTagName('loc') as $node) {
            $locs[] = $node->textContent;
        }

        return $locs;
    }

    public function test_index_lists_all_parts(): void
    {
        config(['seo.sitemap.per_file' => 2]);
        $category = Category::factory()->create(['slug' => 'automobili']);
        $place = Location::factory()->create(['slug' => 'loznica']);
        Listing::factory()->count(3)->for($category)->for($place)->create();
        User::factory()->firm()->create();

        $locs = $this->locs($this->get('/sitemap.xml'));

        $this->assertSame([
            'https://loglasi.rs/sitemap-strane.xml',
            'https://loglasi.rs/sitemap-kategorije.xml',
            'https://loglasi.rs/sitemap-mesta.xml',
            'https://loglasi.rs/sitemap-firme.xml',
            'https://loglasi.rs/sitemap-oglasi-1.xml',
            'https://loglasi.rs/sitemap-oglasi-2.xml',
        ], $locs);
        $this->assertStringContainsString('<lastmod>', $this->get('/sitemap.xml')->getContent());
    }

    public function test_listings_sitemap_has_only_active_listings_with_lastmod(): void
    {
        $active = Listing::factory()->create(['slug' => 'aktivan']);
        Listing::factory()->expired()->create(['slug' => 'istekao']);
        Listing::factory()->create(['slug' => 'istekao-datum', 'expires_at' => now()->subMinute()]);
        Listing::factory()->pending()->create(['slug' => 'na-cekanju']);
        Listing::factory()->create(['slug' => 'obrisan'])->delete();

        $response = $this->get('/sitemap-oglasi-1.xml');

        $this->assertSame(['https://loglasi.rs/oglas/aktivan/'], $this->locs($response));
        $this->assertStringContainsString('<lastmod>'.$active->updated_at->toAtomString().'</lastmod>', $response->getContent());
        $this->get('/sitemap-oglasi-2.xml')->assertNotFound();
    }

    public function test_categories_sitemap_has_only_non_empty_active_categories(): void
    {
        $parent = Category::factory()->create(['slug' => 'vozila']);
        $child = Category::factory()->create(['slug' => 'automobili', 'parent_id' => $parent->id]);
        Category::factory()->create(['slug' => 'prazna']);
        $inactive = Category::factory()->create(['slug' => 'ugasena', 'is_active' => false]);
        Listing::factory()->for($child)->create();
        Listing::factory()->for($inactive)->create();

        $locs = $this->locs($this->get('/sitemap-kategorije.xml'));
        sort($locs);

        $this->assertSame(['https://loglasi.rs/oglasi/automobili/', 'https://loglasi.rs/oglasi/vozila/'], $locs);
    }

    public function test_places_sitemap_has_only_category_place_with_three_listings(): void
    {
        $category = Category::factory()->create(['slug' => 'drva']);
        $lesnica = Location::factory()->create(['slug' => 'lesnica']);
        $klupci = Location::factory()->create(['slug' => 'klupci']);
        Listing::factory()->count(3)->for($category)->for($lesnica)->create();
        Listing::factory()->count(2)->for($category)->for($klupci)->create();

        $this->assertSame(['https://loglasi.rs/oglasi/drva/lesnica/'], $this->locs($this->get('/sitemap-mesta.xml')));
    }

    public function test_places_sitemap_is_404_when_empty(): void
    {
        $this->get('/sitemap-mesta.xml')->assertNotFound();
    }

    public function test_firms_sitemap_has_firms_with_a_listing_or_a_filled_profile(): void
    {
        $empty = ['package' => null, 'package_ends_at' => null, 'description' => null, 'logo_path' => null];
        User::factory()->firm()->create(['slug' => 'firma-sa-paketom']);
        User::factory()->firm()->create(['slug' => 'firma-sa-opisom'])->firm->forceFill(['package' => null, 'package_ends_at' => null])->save();
        $withListing = User::factory()->firm()->create(['slug' => 'firma-sa-oglasom']);
        $withListing->firm->forceFill($empty)->save();
        Listing::factory()->for($withListing)->create();
        User::factory()->firm()->create(['slug' => 'prazna-firma'])->firm->forceFill($empty)->save();
        User::factory()->create(['slug' => 'privatni']);

        $this->assertEqualsCanonicalizing([
            'https://loglasi.rs/korisnik/firma-sa-oglasom/',
            'https://loglasi.rs/korisnik/firma-sa-opisom/',
            'https://loglasi.rs/korisnik/firma-sa-paketom/',
        ], $this->locs($this->get('/sitemap-firme.xml')));
    }

    public function test_pages_sitemap_has_configured_pages(): void
    {
        $locs = $this->locs($this->get('/sitemap-strane.xml'));

        $this->assertContains('https://loglasi.rs/', $locs);
        $this->assertContains('https://loglasi.rs/oglasi/', $locs);
        $this->assertContains('https://loglasi.rs/faq/', $locs);
        $this->assertNotContains('https://loglasi.rs/prijava/', $locs);
    }

    public function test_empty_firm_directory_is_left_out_of_the_pages_sitemap(): void
    {
        $this->assertNotContains('https://loglasi.rs/firme/', $this->locs($this->get('/sitemap-strane.xml')));

        User::factory()->firm()->create();
        Cache::flush();

        $this->assertContains('https://loglasi.rs/firme/', $this->locs($this->get('/sitemap-strane.xml')));
    }

    public function test_sitemap_is_cached_and_flushed_on_listing_change(): void
    {
        Listing::factory()->create(['slug' => 'prvi']);
        $this->assertCount(1, $this->locs($this->get('/sitemap-oglasi-1.xml')));

        $second = Listing::factory()->create(['slug' => 'drugi']);
        $this->assertCount(2, $this->locs($this->get('/sitemap-oglasi-1.xml')));

        $second->delete();
        $this->assertCount(1, $this->locs($this->get('/sitemap-oglasi-1.xml')));
    }

    public function test_views_counter_does_not_flush_sitemap(): void
    {
        $listing = Listing::factory()->create();
        $this->get('/sitemap-oglasi-1.xml');
        $version = cache('seo.sitemap.version');

        $listing->increment('views');

        $this->assertSame($version, cache('seo.sitemap.version'));
    }

    public function test_rebuild_command(): void
    {
        Listing::factory()->count(2)->create();

        $this->artisan('seo:sitemap')->expectsOutputToContain('Sitemap napravljen')->assertSuccessful();
    }

    public function test_sitemap_is_scheduled_hourly(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('seo:sitemap')->assertSuccessful();
    }

    public function test_sitemap_responses_have_no_session_cookie(): void
    {
        $this->get('/sitemap.xml')->assertCookieMissing(config('session.cookie'));
    }
}
