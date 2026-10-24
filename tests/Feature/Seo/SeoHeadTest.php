<?php

namespace Tests\Feature\Seo;

use App\Enums\Currency;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Location;
use App\Models\User;
use App\Support\Seo;
use App\Support\Settings;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

class SeoHeadTest extends SeoTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Probne rute: prave strane pravi modul oglasa/kataloga, ovde se proverava samo <head>.
        Route::middleware('web')->group(function () {
            Route::get('oglas/{slug}', function (string $slug) {
                Seo::for(Listing::withTrashed()->where('slug', $slug)->firstOrFail());

                return view('pocetna.index');
            });
            Route::get('oglasi', function () {
                Seo::get()->listings();

                return view('pocetna.index');
            });
            Route::get('oglasi/page/{n}', function () {
                Seo::get()->listings();

                return view('pocetna.index');
            });
            Route::get('oglasi/{kategorija}/{mesto?}', function (string $kategorija, ?string $mesto = null) {
                Seo::for(Category::where('slug', $kategorija)->firstOrFail(), [
                    'location' => $mesto ? Location::where('slug', $mesto)->firstOrFail() : null,
                ]);

                return view('pocetna.index');
            });
            Route::get('korisnik/{slug}', function (string $slug) {
                Seo::for(User::where('slug', $slug)->firstOrFail());

                return view('pocetna.index');
            });
            Route::get('prijava', fn () => view('pocetna.index'));
            Route::get('faq', function () {
                Seo::get()->page('Česta pitanja', 'Odgovori na najčešća pitanja.', '/faq/')
                    ->faq([['q' => 'Da li je oglas besplatan?', 'a' => 'Da, postavljanje oglasa je besplatno.']]);

                return view('pocetna.index');
            });
            Route::get('stara-strana', fn () => view('pocetna.index', [
                'title' => 'Stara strana | loglasi.rs', 'robots' => 'noindex, follow',
            ]));
        });
    }

    private function robots(TestResponse $response): string
    {
        preg_match('#<meta name="robots" content="([^"]+)">#', $response->getContent(), $m);

        return $m[1] ?? '';
    }

    private function canonical(TestResponse $response): ?string
    {
        return preg_match('#<link rel="canonical" href="([^"]+)">#', $response->getContent(), $m) ? $m[1] : null;
    }

    /** @return list<array<string, mixed>> */
    private function graph(TestResponse $response): array
    {
        preg_match_all('#<script type="application/ld\+json">(.+?)</script>#s', $response->getContent(), $m);

        return collect($m[1])->flatMap(fn ($json) => json_decode($json, true)['@graph'])->all();
    }

    private function schemaOf(TestResponse $response, string $type): ?array
    {
        return collect($this->graph($response))->firstWhere('@type', $type);
    }

    public function test_home_has_defaults_and_site_wide_schema(): void
    {
        $response = $this->get('/')->assertOk()
            ->assertSee('<title>Oglasi Loznica – besplatni mali oglasi | loglasi.rs</title>', false)
            ->assertSee('<meta property="og:url" content="https://loglasi.rs/">', false);

        $this->assertSame('https://loglasi.rs/', $this->canonical($response));
        $this->assertStringStartsWith('index, follow', $this->robots($response));

        $organization = $this->schemaOf($response, 'Organization');
        $this->assertContains('https://www.instagram.com/loglasi.rs/', $organization['sameAs']);
        $this->assertSame('Loznica', $organization['areaServed']['name']);

        $website = $this->schemaOf($response, 'WebSite');
        $this->assertSame('SearchAction', $website['potentialAction']['@type']);
        $this->assertStringContainsString('{search_term_string}', $website['potentialAction']['target']['urlTemplate']);
    }

    public function test_canonical_never_uses_request_domain_or_tracking_params(): void
    {
        $response = $this->get('http://loglasirs.local/?utm_source=fb&fbclid=1');

        $this->assertSame('https://loglasi.rs/', $this->canonical($response));
        $this->assertStringStartsWith('index', $this->robots($response));
        $response->assertSee('<meta property="og:url" content="https://loglasi.rs/">', false);

        foreach ($this->graph($response) as $node) {
            $this->assertStringNotContainsString('loglasirs.local', json_encode($node));
        }
    }

    public function test_active_listing_is_indexable_with_product_schema(): void
    {
        $category = Category::factory()->create(['name' => 'Automobili', 'slug' => 'automobili']);
        $location = Location::factory()->create(['name' => 'Banja Koviljača', 'slug' => 'banja-koviljaca']);
        Listing::factory()->for($category)->for($location)->create([
            'title' => 'Golf 4 1.9 TDI', 'slug' => 'golf-4', 'price' => 2500, 'currency' => Currency::EUR,
            'description' => '<p>Odličan auto, redovno servisiran.</p>',
        ]);

        $response = $this->get('/oglas/golf-4/')->assertOk()
            ->assertSee('<title>Golf 4 1.9 TDI, Banja Koviljača – 2.500 € | loglasi.rs</title>', false)
            ->assertSee('<meta name="description" content="Odličan auto, redovno servisiran. (Banja Koviljača)">', false)
            ->assertSee('<meta property="og:type" content="product">', false);

        $this->assertSame('https://loglasi.rs/oglas/golf-4/', $this->canonical($response));
        $this->assertStringStartsWith('index', $this->robots($response));

        $product = $this->schemaOf($response, 'Product');
        $this->assertSame('Golf 4 1.9 TDI', $product['name']);
        $this->assertSame('2500', $product['offers']['price']);
        $this->assertSame('EUR', $product['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $product['offers']['availability']);

        $breadcrumbs = $this->schemaOf($response, 'BreadcrumbList');
        $this->assertSame(['Početna', 'Oglasi', 'Automobili', 'Golf 4 1.9 TDI'], array_column($breadcrumbs['itemListElement'], 'name'));
        $this->assertSame('https://loglasi.rs/oglasi/automobili/', $breadcrumbs['itemListElement'][2]['item']);
    }

    public function test_rsd_listing_offer_uses_rsd(): void
    {
        Listing::factory()->rsd(12500)->create(['slug' => 'bicikl']);

        $product = $this->schemaOf($this->get('/oglas/bicikl/'), 'Product');

        $this->assertSame('RSD', $product['offers']['priceCurrency']);
        $this->assertSame('12500', $product['offers']['price']);
    }

    public function test_negotiable_listing_has_no_offer(): void
    {
        Listing::factory()->negotiable()->create(['slug' => 'dogovor', 'title' => 'Plac']);

        $response = $this->get('/oglas/dogovor/')->assertSee('– Dogovor | loglasi.rs</title>', false);

        $this->assertArrayNotHasKey('offers', $this->schemaOf($response, 'Product'));
    }

    public function test_expired_listing_is_noindex_follow_without_canonical(): void
    {
        Listing::factory()->expired()->create(['slug' => 'istekao']);
        Listing::factory()->create(['slug' => 'istekao-datum', 'expires_at' => now()->subHour()]);

        foreach (['/oglas/istekao/', '/oglas/istekao-datum/'] as $path) {
            $response = $this->get($path)->assertOk();
            $this->assertSame('noindex, follow', $this->robots($response), $path);
            $this->assertNull($this->canonical($response), $path);
            $this->assertSame('https://schema.org/Discontinued', $this->schemaOf($response, 'Product')['offers']['availability']);
        }
    }

    public function test_long_listing_title_is_cut_to_60_characters(): void
    {
        Listing::factory()->create(['slug' => 'dug', 'title' => str_repeat('Veoma dugačak naslov oglasa ', 4)]);

        preg_match('#<title>(.+)</title>#u', $this->get('/oglas/dug/')->getContent(), $m);

        $this->assertLessThanOrEqual(60, mb_strlen(html_entity_decode($m[1])));
    }

    public function test_category_title_h1_and_description_follow_keyword_map(): void
    {
        $category = Category::factory()->create(['name' => 'Automobili', 'slug' => 'automobili']);
        Listing::factory()->count(12)->for($category)->create();
        Listing::factory()->expired()->for($category)->create();

        $response = $this->get('/oglasi/automobili/')->assertOk()
            ->assertSee('<title>Polovni automobili Loznica – 12 oglasa | loglasi.rs</title>', false)
            ->assertSee('content="12 oglasa za polovne automobile u Loznici, Banji Koviljači i Lešnici. Pogledaj cene ili besplatno objavi oglas."', false);

        $this->assertSame('Polovni automobili u Loznici i okolini', (new Seo)->category($category)->getH1());
        $this->assertSame('https://loglasi.rs/oglasi/automobili/', $this->canonical($response));
        $this->assertStringStartsWith('index', $this->robots($response));
    }

    public function test_category_counts_subcategories_and_singular_form(): void
    {
        $parent = Category::factory()->create(['name' => 'Ogrev', 'slug' => 'ogrev']);
        $child = Category::factory()->create(['name' => 'Pelet', 'slug' => 'pelet', 'parent_id' => $parent->id]);
        Listing::factory()->for($child)->create();

        $this->get('/oglasi/ogrev/')
            ->assertSee('<title>Ogrev Loznica – 1 oglas | loglasi.rs</title>', false)
            ->assertSee('content="1 oglas u kategoriji Ogrev u Loznici', false);
    }

    public function test_empty_category_is_noindex(): void
    {
        Category::factory()->create(['slug' => 'prazna']);

        $this->assertSame('noindex, follow', $this->robots($this->get('/oglasi/prazna/')));
    }

    public function test_category_place_is_indexed_only_from_three_listings(): void
    {
        $category = Category::factory()->create(['name' => 'Drva', 'slug' => 'drva']);
        $lesnica = Location::factory()->create(['name' => 'Lešnica', 'slug' => 'lesnica']);
        Listing::factory()->count(2)->for($category)->for($lesnica)->create();

        $response = $this->get('/oglasi/drva/lesnica/')
            ->assertSee('<title>Drva Lešnica – oglasi | loglasi.rs</title>', false)
            ->assertSee('content="2 oglasa za drva u mestu Lešnica, opština Loznica."', false);
        $this->assertSame('noindex, follow', $this->robots($response));

        Listing::factory()->for($category)->for($lesnica)->create();

        $response = $this->get('/oglasi/drva/lesnica/');
        $this->assertStringStartsWith('index', $this->robots($response));
        $this->assertSame('https://loglasi.rs/oglasi/drva/lesnica/', $this->canonical($response));
        $this->assertSame('Lešnica', last($this->schemaOf($response, 'BreadcrumbList')['itemListElement'])['name']);
    }

    public function test_place_threshold_comes_from_admin_setting(): void
    {
        Settings::set('seo_place_min_listings', 1);
        $category = Category::factory()->create(['slug' => 'drva']);
        $place = Location::factory()->create(['slug' => 'tekeris']);
        Listing::factory()->for($category)->for($place)->create();

        $this->assertStringStartsWith('index', $this->robots($this->get('/oglasi/drva/tekeris/')));
    }

    public function test_filtered_search_is_noindex_with_canonical_to_clean_page(): void
    {
        $category = Category::factory()->create(['slug' => 'automobili']);
        Listing::factory()->for($category)->create();

        $response = $this->get('/oglasi/automobili/?cena-do=5000&sort=cena');
        $this->assertSame('noindex, follow', $this->robots($response));
        $this->assertSame('https://loglasi.rs/oglasi/automobili/', $this->canonical($response));

        $response = $this->get('/oglasi/?kategorija=automobili&keyword=golf');
        $this->assertSame('noindex, follow', $this->robots($response));
        $this->assertSame('https://loglasi.rs/oglasi/', $this->canonical($response));
    }

    public function test_site_search_is_noindex(): void
    {
        Listing::factory()->create(['title' => 'Golf 4']);
        $response = $this->get('/?s=golf');

        // The old WordPress search shows the listing list, so it points to it.
        $this->assertSame('noindex, follow', $this->robots($response));
        $this->assertSame('https://loglasi.rs/oglasi/', $this->canonical($response));
    }

    public function test_pagination_is_noindex_with_self_canonical(): void
    {
        // Three pages of 24 listings (a page past the last one is 404).
        Listing::factory()->count(49)->for(User::factory())->for(Category::factory())->for(Location::factory())->create();

        $response = $this->get('/oglasi/?page=2');
        $this->assertSame('noindex, follow', $this->robots($response));
        $this->assertSame('https://loglasi.rs/oglasi/?page=2', $this->canonical($response));

        $response = $this->get('/oglasi/?paged=3&utm_source=x');
        $this->assertSame('https://loglasi.rs/oglasi/?paged=3', $this->canonical($response));

        $response = $this->get('/oglasi/page/2/');
        $this->assertSame('noindex, follow', $this->robots($response));
        $this->assertSame('https://loglasi.rs/oglasi/page/2/', $this->canonical($response));
    }

    public function test_account_pages_are_noindex_without_canonical(): void
    {
        $response = $this->get('/prijava/')->assertOk();

        $this->assertSame('noindex, follow', $this->robots($response));
        $this->assertNull($this->canonical($response));
    }

    public function test_private_profile_is_noindex(): void
    {
        User::factory()->create(['slug' => 'petar', 'name' => 'Petar Petrović']);

        $response = $this->get('/korisnik/petar/')
            ->assertSee('<title>Petar Petrović – oglasi korisnika | loglasi.rs</title>', false);

        $this->assertSame('noindex, follow', $this->robots($response));
    }

    public function test_firm_profile_is_indexable_with_local_business(): void
    {
        $user = User::factory()->firm()->create(['slug' => 'auto-servis-drina', 'phone' => '+381641112222']);
        $user->firm->forceFill(['name' => 'Auto servis Drina', 'address' => 'Jadranska 5', 'working_hours' => ['mon' => '08:00-16:00']])->save();

        $response = $this->get('/korisnik/auto-servis-drina/')
            ->assertSee('<title>Auto servis Drina – firma iz Loznice | loglasi.rs</title>', false);

        $this->assertStringStartsWith('index', $this->robots($response));
        $this->assertSame('https://loglasi.rs/korisnik/auto-servis-drina/', $this->canonical($response));

        $business = $this->schemaOf($response, 'LocalBusiness');
        $this->assertSame('Auto servis Drina', $business['name']);
        $this->assertSame('Jadranska 5', $business['address']['streetAddress']);
        $this->assertSame('+381641112222', $business['telephone']);
        $this->assertSame('Monday', $business['openingHoursSpecification'][0]['dayOfWeek']);
    }

    public function test_firm_without_biznis_package_is_noindex(): void
    {
        $user = User::factory()->firm()->create(['slug' => 'bez-paketa']);
        $user->firm->forceFill(['biznis_until' => now()->subDay()])->save();

        $response = $this->get('/korisnik/bez-paketa/');

        $this->assertSame('noindex, follow', $this->robots($response));
        $this->assertNull($this->schemaOf($response, 'LocalBusiness'));
    }

    public function test_faq_helper_outputs_faq_page(): void
    {
        $response = $this->get('/faq/')->assertSee('<title>Česta pitanja | loglasi.rs</title>', false);

        $faq = $this->schemaOf($response, 'FAQPage');
        $this->assertSame('Da li je oglas besplatan?', $faq['mainEntity'][0]['name']);
        $this->assertSame('https://loglasi.rs/faq/', $this->canonical($response));
    }

    public function test_view_variables_still_work(): void
    {
        $response = $this->get('/stara-strana/')->assertSee('<title>Stara strana | loglasi.rs</title>', false);

        $this->assertSame('noindex, follow', $this->robots($response));
    }

    public function test_json_ld_cannot_break_out_of_script_tag(): void
    {
        Listing::factory()->create(['slug' => 'xss', 'title' => 'Naslov </script><script>alert(1)</script>']);

        $content = $this->get('/oglas/xss/')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $content);
    }

    public function test_seo_state_does_not_leak_between_requests(): void
    {
        Listing::factory()->expired()->create(['slug' => 'istekao']);

        $this->get('/oglas/istekao/');

        $this->assertStringStartsWith('index', $this->robots($this->get('/')));
    }
}
