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

    private function description(TestResponse $response): string
    {
        preg_match('#<meta name="description" content="([^"]*)">#', $response->getContent(), $m);

        return html_entity_decode($m[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
            ->assertSee('<title>Besplatni oglasi Loznica – kupovina, prodaja, usluge | loglasi.rs</title>', false)
            ->assertSee('<meta name="description" content="loglasi.rs je lokalni oglasnik za Loznicu i okolinu: automobili, nekretnine, usluge, poslovi, mobilni telefoni i besplatne stvari. Postavite oglas besplatno.">', false)
            ->assertSee('<meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">', false)
            ->assertSee('<meta property="og:url" content="https://loglasi.rs/">', false);

        $this->assertSame('https://loglasi.rs/', $this->canonical($response));
        $this->assertStringStartsWith('index, follow', $this->robots($response));

        $organization = $this->schemaOf($response, 'Organization');
        $this->assertSame([
            'https://www.facebook.com/profile.php?id=61571911496798',
            'https://www.instagram.com/loglasi.rs/',
            'https://www.tiktok.com/@loglasi.rs',
        ], $organization['sameAs']);
        $this->assertSame('https://loglasi.rs/#organization', $organization['@id']);
        $this->assertSame('+381655415128', $organization['telephone']);
        $this->assertSame('office@loglasi.rs', $organization['email']);
        $this->assertSame('15300', $organization['address']['postalCode']);
        $this->assertSame(['Loznica', 'Banja Koviljača', 'Lešnica', 'Krupanj', 'Ljubovija', 'Mali Zvornik'], array_column($organization['areaServed'], 'name'));

        $page = $this->schemaOf($response, 'WebPage');
        $this->assertSame('https://loglasi.rs/#webpage', $page['@id']);
        $this->assertSame(['@id' => 'https://loglasi.rs/#organization'], $page['about']);

        $website = $this->schemaOf($response, 'WebSite');
        $this->assertSame('SearchAction', $website['potentialAction']['@type']);
        $this->assertStringContainsString('{search_term_string}', $website['potentialAction']['target']['urlTemplate']);
    }

    public function test_staging_copy_is_never_indexable(): void
    {
        config(['seo.indexable' => false]);

        $response = $this->get('/')->assertOk();

        $this->assertSame('noindex, follow', $this->robots($response));
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
            ->assertSee('<title>Golf 4 1.9 TDI, Automobili, Banja Koviljača | loglasi.rs</title>', false)
            ->assertSee('<meta name="description" content="Golf 4 1.9 TDI u mestu Banja Koviljača. Cena 2.500 €. Pogledajte oglas i kontaktirajte prodavca na loglasi.rs.">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('<meta property="article:published_time"', false);

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

        $response = $this->get('/oglas/dogovor/')->assertOk();

        // No price, no Product (as on the old site).
        $this->assertNull($this->schemaOf($response, 'Product'));
        $this->assertStringNotContainsString('Cena ', $this->description($response));
    }

    public function test_long_listing_text_goes_into_the_description_with_price(): void
    {
        Listing::factory()->create([
            'slug' => 'dug-opis', 'title' => 'Bicikl', 'price' => 120, 'currency' => Currency::EUR,
            'description' => "Ekstra stanje\nUvoz Švajcarska, sve radi, nove gume i kočnice, može probna vožnja.",
        ]);

        $this->assertSame('Cena 120 €. Ekstra stanje Uvoz Švajcarska, sve radi, nove gume i kočnice, može probna vožnja.', $this->description($this->get('/oglas/dug-opis/')));
    }

    public function test_services_and_jobs_have_no_product_and_jobs_no_price(): void
    {
        $jobs = Category::factory()->create(['name' => 'Poslovi', 'slug' => 'poslovi']);
        Listing::factory()->for($jobs)->create(['slug' => 'konobar', 'title' => 'Konobar', 'price' => 80000, 'currency' => Currency::RSD, 'description' => 'Kratko.']);

        $response = $this->get('/oglas/konobar/');
        $this->assertNull($this->schemaOf($response, 'Product'));
        $this->assertStringNotContainsString('Cena', $this->description($response));
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

    public function test_long_listing_title_drops_the_category(): void
    {
        $category = Category::factory()->create(['name' => 'Automobili', 'slug' => 'automobili']);
        $place = Location::factory()->create(['name' => 'Lešnica', 'slug' => 'lesnica']);
        Listing::factory()->for($category)->for($place)->create(['slug' => 'dug', 'title' => 'Volkswagen Golf 7 1.6 TDI Highline, prvi vlasnik, servisna knjiga']);

        $this->get('/oglas/dug/')->assertSee('<title>Volkswagen Golf 7 1.6 TDI Highline, prvi vlasnik, servisna knjiga, Lešnica | loglasi.rs</title>', false);
    }

    public function test_category_title_h1_and_description_follow_keyword_map(): void
    {
        $category = Category::factory()->create(['name' => 'Automobili', 'slug' => 'automobili']);
        Listing::factory()->count(12)->for($category)->create();
        Listing::factory()->expired()->for($category)->create();

        $response = $this->get('/oglasi/automobili/')->assertOk()
            ->assertSee('<title>Automobili, oglasi Loznica | loglasi.rs</title>', false)
            ->assertSee('content="Pregledajte Automobili oglase u Loznici i okolini na loglasi.rs. Kontaktirajte prodavca direktno ili besplatno postavite svoj oglas."', false);

        $this->assertSame('Automobili u Loznici', (new Seo)->category($category)->getH1());
        $this->assertNotNull($this->schemaOf($response, 'CollectionPage'));
        $this->assertCount(12, $this->schemaOf($response, 'ItemList')['itemListElement']);
        $this->assertSame('https://loglasi.rs/oglasi/automobili/', $this->canonical($response));
        $this->assertStringStartsWith('index', $this->robots($response));
    }

    public function test_category_counts_subcategories_and_singular_form(): void
    {
        $parent = Category::factory()->create(['name' => 'Ogrev', 'slug' => 'ogrev']);
        $child = Category::factory()->create(['name' => 'Pelet', 'slug' => 'pelet', 'parent_id' => $parent->id]);
        Listing::factory()->for($child)->create();

        $this->get('/oglasi/ogrev/')
            ->assertSee('<title>Ogrev, oglasi Loznica | loglasi.rs</title>', false)
            ->assertSee('content="Pregledajte Ogrev oglase u Loznici', false);
    }

    public function test_subcategory_name_has_no_parent_prefix(): void
    {
        $parent = Category::factory()->create(['name' => 'Nekretnine', 'slug' => 'nekretnine']);
        $child = Category::factory()->create(['name' => 'Nekretnine | Stanovi za prodaju', 'slug' => 'stanovi-prodaja', 'parent_id' => $parent->id]);
        Listing::factory()->for($child)->create();

        $this->get('/oglasi/stanovi-prodaja/')
            ->assertSee('<title>Stanovi za prodaju, oglasi Loznica | loglasi.rs</title>', false)
            ->assertSee('<h1 class="sr-head__title">Stanovi za prodaju u Loznici</h1>', false);
    }

    public function test_manual_category_texts_intro_and_faq(): void
    {
        $category = Category::factory()->create([
            'name' => 'Automobili', 'slug' => 'automobili',
            'seo_title' => 'Automobili Loznica – polovni automobili i auto delovi | loglasi.rs',
            'seo_description' => 'Polovni automobili, auto delovi i oprema u Loznici i okolini.',
            'seo_h1' => 'Automobili – polovni automobili i delovi u Loznici',
            'seo_intro' => 'Tražite polovan automobil ili delove u Loznici?',
            'seo_faq' => "Da li je oglas besplatan?\nDa. Oglas je aktivan 30 dana, a obnavljanje je besplatno.\n\nGde mogu da pogledam auto?\nU svom mestu.",
        ]);
        Listing::factory()->for($category)->create();

        $response = $this->get('/oglasi/automobili/')->assertOk()
            ->assertSee('<title>Automobili Loznica – polovni automobili i auto delovi | loglasi.rs</title>', false)
            ->assertSee('<h1 class="sr-head__title">Automobili – polovni automobili i delovi u Loznici</h1>', false)
            ->assertSee('Tražite polovan automobil ili delove u Loznici?')
            ->assertSee('Česta pitanja – Automobili Loznica');

        $faq = $this->schemaOf($response, 'FAQPage');
        $this->assertSame(['Da li je oglas besplatan?', 'Gde mogu da pogledam auto?'], array_column($faq['mainEntity'], 'name'));
        $this->assertSame('Da. Oglas je aktivan 30 dana, a obnavljanje je besplatno.', $faq['mainEntity'][0]['acceptedAnswer']['text']);
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

    public function test_pagination_is_indexable_with_self_canonical(): void
    {
        // Three pages of 24 listings (a page past the last one is 404).
        Listing::factory()->count(49)->for(User::factory())->for(Category::factory())->for(Location::factory())->create();

        $response = $this->get('/oglasi/?page=2');
        $this->assertStringStartsWith('index, follow', $this->robots($response));
        $this->assertSame('https://loglasi.rs/oglasi/?page=2', $this->canonical($response));
        $response->assertSee('<title>Svi oglasi Loznica, strana 2 | loglasi.rs</title>', false);

        $response = $this->get('/oglasi/?paged=3&utm_source=x');
        $this->assertSame('https://loglasi.rs/oglasi/?paged=3', $this->canonical($response));

        $response = $this->get('/oglasi/page/2/');
        $this->assertStringStartsWith('index, follow', $this->robots($response));
        $this->assertSame('https://loglasi.rs/oglasi/page/2/', $this->canonical($response));
    }

    public function test_account_pages_are_noindex_without_canonical(): void
    {
        $response = $this->get('/login-registracija/')->assertOk();

        $this->assertSame('noindex, follow', $this->robots($response));
        $this->assertNull($this->canonical($response));
    }

    public function test_private_profile_is_noindex(): void
    {
        User::factory()->create(['slug' => 'petar', 'name' => 'Petar Petrović']);

        $response = $this->get('/korisnik/petar/')
            ->assertSee('<title>Petar Petrović, oglasi | loglasi.rs</title>', false);

        $this->assertSame('noindex, follow', $this->robots($response));
    }

    public function test_firm_profile_is_indexable_with_local_business(): void
    {
        $user = User::factory()->firm()->create(['slug' => 'auto-servis-drina', 'phone' => '+381641112222']);
        $user->firm->forceFill(['name' => 'Auto servis Drina', 'description' => null, 'address' => 'Jadranska 5', 'working_hours' => ['mon' => '08:00-16:00']])->save();

        $response = $this->get('/korisnik/auto-servis-drina/')
            ->assertSee('<title>Auto servis Drina, Loznica | loglasi.rs</title>', false)
            ->assertSee('content="Oglasi i kontakt firme Auto servis Drina iz mesta Loznica na loglasi.rs."', false);

        $this->assertStringStartsWith('index', $this->robots($response));
        $this->assertSame('https://loglasi.rs/korisnik/auto-servis-drina/', $this->canonical($response));

        $business = $this->schemaOf($response, 'LocalBusiness');
        $this->assertSame('Auto servis Drina', $business['name']);
        $this->assertSame('Jadranska 5', $business['address']['streetAddress']);
        $this->assertSame('+381641112222', $business['telephone']);
        $this->assertSame('Monday', $business['openingHoursSpecification'][0]['dayOfWeek']);
    }

    public function test_empty_firm_profile_is_noindex(): void
    {
        $user = User::factory()->firm()->create(['slug' => 'bez-paketa']);
        $user->firm->forceFill(['package_ends_at' => now()->subDay(), 'description' => null, 'logo_path' => null])->save();

        // An empty firm profile without listings stays out of the index.
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
