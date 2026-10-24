<?php

namespace Tests\Feature\Search;

use App\Enums\Currency;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

class ListingListPageTest extends SearchTestCase
{
    private function robots(TestResponse $response): ?string
    {
        return preg_match('~<meta name="robots" content="([^"]+)"~', $response->getContent(), $m) ? $m[1] : null;
    }

    private function canonical(TestResponse $response): ?string
    {
        return preg_match('~<link rel="canonical" href="([^"]+)"~', $response->getContent(), $m) ? $m[1] : null;
    }

    public function test_all_listings_page_lists_cards_with_count(): void
    {
        $this->priced('Golf 4 dizel', 3500);
        $this->priced('Stan u centru', 120000, Currency::RSD, ['category_id' => $this->homes->id]);

        $this->get('/oglasi/')->assertOk()
            ->assertSee('Golf 4 dizel')
            ->assertSee('Stan u centru')
            ->assertSee('2 oglasa')
            ->assertSee('120.000 din')
            ->assertSee('href="'.url_slash('/oglas/'), false);
    }

    public function test_category_page_includes_subcategories_and_is_indexable(): void
    {
        $this->listing(['title' => 'Golf 4']);
        $this->listing(['title' => 'Stan', 'category_id' => $this->homes->id]);

        $response = $this->get('/oglasi/vozila/')->assertOk()->assertSee('Golf 4')->assertDontSee('>Stan<', false);
        $this->assertStringStartsWith('index', (string) $this->robots($response));
        $this->assertSame('https://loglasi.rs/oglasi/vozila/', $this->canonical($response));
    }

    public function test_empty_category_is_noindex_and_shows_empty_state(): void
    {
        $response = $this->get('/oglasi/nekretnine/')->assertOk()->assertSee('Nema oglasa za ovu pretragu');

        $this->assertSame('noindex, follow', $this->robots($response));
    }

    public function test_nested_category_redirects_and_unknown_paths_are_404(): void
    {
        $this->get('/oglasi/vozila/automobili/')->assertStatus(301)->assertRedirect(url_slash('/oglasi/automobili/'));
        $this->get('/oglasi/nema-kategorije/')->assertNotFound();
        $this->get('/oglasi/automobili/nema-mesta/')->assertNotFound();
    }

    public function test_place_page_is_indexable_only_from_three_listings(): void
    {
        $this->listing(['title' => 'Prvi']);
        $this->listing(['title' => 'Drugi']);

        $response = $this->get('/oglasi/automobili/loznica/')->assertOk()->assertSee('Prvi');
        $this->assertSame('noindex, follow', $this->robots($response));

        $this->listing(['title' => 'Treći']);
        $response = $this->get('/oglasi/automobili/loznica/')->assertOk();
        $this->assertStringStartsWith('index', (string) $this->robots($response));
    }

    public function test_query_category_and_place_move_to_the_path(): void
    {
        $this->get('/oglasi/?kategorija=automobili&mesto=loznica&keyword=golf')
            ->assertRedirect(url_slash('/oglasi/automobili/loznica/').'?keyword=golf');
        $this->get('/oglasi/?kategorija=automobili')->assertRedirect(url_slash('/oglasi/automobili/'));
        $this->get('/oglasi/?keyword=&cena_od=&valuta=EUR&sortiranje=najnovije')->assertRedirect(url_slash('/oglasi/'));
        $this->get('/oglasi/page/1/')->assertStatus(301)->assertRedirect(url_slash('/oglasi/'));
    }

    public function test_filters_sort_and_noindex_with_canonical_to_clean_page(): void
    {
        $this->priced('Jeftin golf', 1000);
        $this->priced('Skup golf', 9000);
        $this->priced('Passat', 2000);

        $response = $this->get('/oglasi/automobili/?keyword=golf&cena_do=5000')->assertOk()
            ->assertSee('Jeftin golf')->assertDontSee('Skup golf')->assertDontSee('Passat');
        $this->assertSame('noindex, follow', $this->robots($response));
        $this->assertSame('https://loglasi.rs/oglasi/automobili/', $this->canonical($response));
        $response->assertSee('Ukloni filter', false);

        $html = $this->get('/oglasi/?sortiranje=cena-opadajuce')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'Passat'), strpos($html, 'Skup golf'));
        $this->assertLessThan(strpos($html, 'Jeftin golf'), strpos($html, 'Passat'));
    }

    public function test_legacy_wordpress_search_shows_results_noindex(): void
    {
        $this->listing(['title' => 'Ćevapi roštilj']);

        $response = $this->get('/?s=cevapi')->assertOk()->assertSee('Ćevapi roštilj');
        $this->assertSame('noindex, follow', $this->robots($response));
        $this->get('/oglasi/?s=cevapi')->assertOk()->assertSee('Ćevapi roštilj');
    }

    public function test_pagination_pages_and_noindex_self_canonical(): void
    {
        foreach (range(1, 25) as $i) {
            $this->listing(['title' => 'Oglas broj '.$i, 'bumped_at' => now()->subMinutes($i)]);
        }

        $this->get('/oglasi/')->assertOk()->assertSee('href="'.url_slash('/oglasi/page/2/').'"', false)->assertDontSee('Oglas broj 25<');
        $response = $this->get('/oglasi/page/2/')->assertOk()->assertSee('Oglas broj 25');
        $this->assertSame('noindex, follow', $this->robots($response));
        $this->assertSame('https://loglasi.rs/oglasi/page/2/', $this->canonical($response));
        $this->get('/oglasi/page/3/')->assertNotFound();
    }

    public function test_card_shows_firm_badge_and_heart_only_when_logged_in(): void
    {
        $firm = User::factory()->firm()->create();
        $listing = $this->listing(['title' => 'Servis klima', 'user_id' => $firm->id, 'featured_until' => now()->addDay()]);

        $this->get('/oglasi/')->assertOk()
            ->assertSee('Firma')->assertSee('Istaknuto')
            ->assertDontSee('/moj-nalog/omiljeni/')
            ->assertDontSee('Sačuvaj pretragu');

        $viewer = User::factory()->create();
        $this->actingAs($viewer)->get('/oglasi/')->assertOk()
            ->assertSee('action="'.url_slash('/moj-nalog/omiljeni/'.$listing->id.'/').'"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('Sačuvaj pretragu');

        DB::table('favorites')->insert(['user_id' => $viewer->id, 'listing_id' => $listing->id, 'created_at' => now()]);
        $this->app->forgetScopedInstances(); // favorites are read once per request
        $this->actingAs($viewer)->get('/oglasi/')->assertSee('aria-pressed="true"', false);
    }

    public function test_save_search_form_posts_the_current_query(): void
    {
        $this->listing(['title' => 'Golf']);

        $this->actingAs(User::factory()->create())->get('/oglasi/automobili/loznica/?keyword=golf&cena_do=5000')->assertOk()
            ->assertSee('action="'.url_slash('/moj-nalog/pretrage/').'"', false)
            ->assertSee('name="kategorija" value="automobili"', false)
            ->assertSee('name="mesto" value="loznica"', false)
            ->assertSee('name="keyword" value="golf"', false)
            ->assertSee('name="cena_do" value="5000"', false);
    }

    public function test_listing_changes_reach_the_list_at_once(): void
    {
        $listing = $this->listing(['title' => 'Prodajem bicikl']);
        $this->get('/oglasi/?keyword=bicikl')->assertSee('Prodajem bicikl');

        $listing->update(['title' => 'Prodajem trotinet']);
        $this->get('/oglasi/?keyword=bicikl')->assertDontSee('Prodajem');
        $this->get('/oglasi/?keyword=trotinet')->assertSee('Prodajem trotinet');

        $listing->delete();
        $this->get('/oglasi/')->assertDontSee('Prodajem trotinet');
    }
}
