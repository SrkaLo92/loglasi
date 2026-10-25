<?php

namespace Tests\Feature\Seo;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Redirect;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

class RedirectsTest extends SeoTestCase
{
    public function test_kategorija_param_redirects_to_category_page(): void
    {
        Category::factory()->create(['slug' => 'automobili']);

        $this->get('/oglasi/?kategorija=automobili')
            ->assertStatus(301)
            ->assertTarget('/oglasi/automobili/');
    }

    public function test_kategorija_param_drops_tracking_parameters(): void
    {
        Category::factory()->create(['slug' => 'automobili']);

        $this->get('/oglasi/?kategorija=automobili&utm_source=fb&utm_medium=x&fbclid=abc&gclid=1')
            ->assertStatus(301)
            ->assertTarget('/oglasi/automobili/');
    }

    public function test_kategorija_param_without_trailing_slash_is_one_hop(): void
    {
        Category::factory()->create(['slug' => 'automobili']);

        $this->get('/oglasi?kategorija=automobili')
            ->assertStatus(301)
            ->assertTarget('/oglasi/automobili/');
    }

    public function test_unknown_kategorija_redirects_to_all_listings(): void
    {
        $this->get('/oglasi/?kategorija=ne-postoji')->assertStatus(301)->assertTarget('/oglasi/');
    }

    public function test_inactive_kategorija_redirects_to_all_listings(): void
    {
        Category::factory()->create(['slug' => 'stara', 'is_active' => false]);

        $this->get('/oglasi/?kategorija=stara')->assertTarget('/oglasi/');
    }

    public function test_kategorija_with_other_filters_is_not_redirected(): void
    {
        Category::factory()->create(['slug' => 'automobili']);

        $response = $this->get('/oglasi/?kategorija=automobili&cena-do=5000');

        // No permanent redirect; the list itself may move the category to the path (302).
        $this->assertNotContains($response->getStatusCode(), [301, 308]);
    }

    public function test_comma_subcategory_urls_redirect_to_the_subcategory(): void
    {
        $parent = Category::factory()->create(['slug' => 'nekretnine']);
        Category::factory()->create(['slug' => 'stanovi-prodaja', 'parent_id' => $parent->id]);

        $this->get('/oglasi/nekretnine,stanovi-prodaja/?utm_source=x')->assertStatus(301)->assertTarget('/oglasi/stanovi-prodaja/');
        $this->get('/oglasi/?kategorija=nekretnine,stanovi-prodaja')->assertStatus(301)->assertTarget('/oglasi/stanovi-prodaja/');
        $this->get('/oglasi/nekretnine,nema/')->assertStatus(301)->assertTarget('/oglasi/');
    }

    public function test_old_pagination_parameter_redirects_to_page_path(): void
    {
        $this->get('/oglasi/?paginacija=2')->assertStatus(301)->assertTarget('/oglasi/page/2/');
        $this->get('/oglasi/automobili/?paginacija=3&utm_source=x')->assertStatus(301)->assertTarget('/oglasi/automobili/page/3/');
        $this->get('/oglasi/?paginacija=1')->assertStatus(301)->assertTarget('/oglasi/');
    }

    public function test_marka_automobila_redirects_to_cars(): void
    {
        $this->get('/oglasi/?marka-automobila=bmw')->assertStatus(301)->assertTarget('/oglasi/automobili/?f%5Bmarka_automobila%5D=bmw');
        $this->get('/oglasi/?marka-automobila=bmw&kategorija=automobili&utm_campaign=x')
            ->assertTarget('/oglasi/automobili/?f%5Bmarka_automobila%5D=bmw');
        $this->get('/oglasi/?marka-automobila=nepoznata')->assertTarget('/oglasi/automobili/');
    }

    public function test_page_parameters_redirect_to_page_paths(): void
    {
        $this->get('/oglasi/automobili/?page=3')->assertStatus(301)->assertTarget('/oglasi/automobili/page/3/');
        $this->get('/oglasi/?paged=2&sort=cena')->assertStatus(301)->assertTarget('/oglasi/page/2/?sort=cena');
        $this->get('/oglasi/?page=1')->assertStatus(301)->assertTarget('/oglasi/');
    }

    /** @return array<string, array{string, string}> */
    public static function legacyPaths(): array
    {
        return [
            'yoast indeks' => ['/sitemap_index.xml', '/sitemap.xml'],
            'yoast strane' => ['/page-sitemap.xml', '/sitemap.xml'],
            'yoast oglasi' => ['/listivo_listing-sitemap.xml', '/sitemap.xml'],
            'yoast oglasi 2' => ['/listivo_listing-sitemap2.xml', '/sitemap.xml'],
            'yoast kategorije' => ['/listivo_14-sitemap.xml', '/sitemap.xml'],
            'yoast taksonomija' => ['/listivo_9147-sitemap.xml', '/sitemap.xml'],
            'yoast šabloni' => ['/listivo_template-sitemap.xml', '/sitemap.xml'],
            'yoast woo' => ['/product-sitemap.xml', '/sitemap.xml'],
            'yoast woo kat' => ['/product_cat-sitemap.xml', '/sitemap.xml'],
            'wp core sitemap' => ['/wp-sitemap.xml', '/sitemap.xml'],
            'wp core deo' => ['/wp-sitemap-posts-listivo_listing-1.xml', '/sitemap.xml'],
            'woo proizvod' => ['/product/biznis-paket/', '/cenovnik/'],
            'woo proizvod bez kose crte' => ['/product/biznis-paket', '/cenovnik/'],
            'woo kategorija' => ['/product-category/paketi/', '/cenovnik/'],
            'woo prodavnica' => ['/shop/', '/cenovnik/'],
            'woo korpa' => ['/cart/', '/cenovnik/'],
            'woo naplata' => ['/checkout/', '/cenovnik/'],
            'naplata' => ['/naplata/', '/cenovnik/'],
            'woo nalog' => ['/my-account/orders/', '/moj-nalog/'],
            'panel' => ['/panel/', '/moj-nalog/'],
            'panel novi oglas' => ['/panel/?tab=create', '/postavi-oglas/'],
            'panel poruke' => ['/panel/?tab=messages', '/moj-nalog/poruke/'],
            'panel nepoznat tab' => ['/panel/?tab=nesto', '/moj-nalog/'],
            'panel kreiraj' => ['/panel/kreiraj/', '/postavi-oglas/'],
            'uporedite oglase' => ['/uporedite-oglase/', '/oglasi/'],
            'panel lista' => ['/panel/lista/', '/moj-nalog/oglasi/'],
            'panel poruke putanja' => ['/panel/poruke/', '/moj-nalog/poruke/'],
            'wp admin' => ['/wp-admin/', '/admin/'],
            'wp admin strana' => ['/wp-admin/edit.php', '/admin/'],
            'wp login' => ['/wp-login.php', '/login-registracija/'],
        ];
    }

    #[DataProvider('legacyPaths')]
    public function test_legacy_path_redirects_in_one_hop(string $from, string $to): void
    {
        $this->get($from.(str_contains($from, '?') ? '&' : '?').'utm_source=x')
            ->assertStatus(301)
            ->assertTarget(''.$to);
    }

    public function test_old_rest_api_redirects_with_308_keeping_method_and_query(): void
    {
        $this->get('/wp-json/loglasi/v1/listings?page=2&per_page=20')
            ->assertStatus(308)
            ->assertTarget('/api/v1/listings?page=2&per_page=20');

        $this->post('/wp-json/loglasi/v1/auth/login')
            ->assertStatus(308)
            ->assertTarget('/api/v1/auth/login');
    }

    public function test_attachment_page_redirects_to_its_listing(): void
    {
        $listing = Listing::factory()->create(['slug' => 'golf-4-dizel']);
        ListingImage::create(['listing_id' => $listing->id, 'path' => 'oglasi/a.jpg', 'wp_attachment_id' => 555]);

        $this->get('/?attachment_id=555')->assertStatus(301)->assertTarget('/oglas/golf-4-dizel/');
    }

    public function test_wordpress_short_link_redirects_to_listing(): void
    {
        $listing = Listing::factory()->create(['slug' => 'stan-centar']);
        DB::table('listings')->where('id', $listing->id)->update(['wp_id' => 9001]);

        $this->get('/?p=9001')->assertTarget('/oglas/stan-centar/');
    }

    public function test_table_redirect_with_hit_counter(): void
    {
        $redirect = Redirect::create(['from_path' => '/oglas/stari-slug/', 'to_path' => '/oglas/novi-slug/']);

        $this->get('/oglas/stari-slug/?utm_source=x')->assertStatus(301)->assertTarget('/oglas/novi-slug/');
        $this->get('/oglas/stari-slug')->assertStatus(301)->assertTarget('/oglas/novi-slug/');

        $this->assertSame(2, $redirect->fresh()->hits);
        $this->assertNotNull($redirect->fresh()->last_hit_at);
    }

    public function test_table_redirect_keeps_other_query_parameters(): void
    {
        Redirect::create(['from_path' => '/stara-strana/', 'to_path' => '/nova-strana/']);

        $this->get('/stara-strana/?strana=2&gclid=x')->assertTarget('/nova-strana/?strana=2');
    }

    public function test_table_redirect_chain_is_collapsed_to_one_hop(): void
    {
        Redirect::create(['from_path' => '/a/', 'to_path' => '/b/']);
        Redirect::create(['from_path' => '/b/', 'to_path' => '/c']);

        $this->get('/a/')->assertStatus(301)->assertTarget('/c/');
    }

    public function test_table_redirect_loop_does_not_hang(): void
    {
        Redirect::create(['from_path' => '/x/', 'to_path' => '/y/']);
        Redirect::create(['from_path' => '/y/', 'to_path' => '/x/']);

        $this->get('/x/')->assertStatus(301)->assertTarget('/y/');
    }

    public function test_table_redirect_cache_is_cleared_on_change(): void
    {
        $redirect = Redirect::create(['from_path' => '/stari/', 'to_path' => '/prvi/']);
        $this->get('/stari/')->assertTarget('/prvi/');

        $redirect->update(['to_path' => '/drugi/']);
        $this->get('/stari/')->assertTarget('/drugi/');
    }

    public function test_table_redirect_honours_status_code(): void
    {
        Redirect::create(['from_path' => '/privremeno/', 'to_path' => '/tamo/', 'status_code' => 302]);

        $this->get('/privremeno/')->assertStatus(302);
    }

    public function test_rule_hits_are_counted(): void
    {
        $this->get('/shop/');
        $this->get('/shop/');

        $this->assertSame(2, (int) DB::table('seo_rule_hits')->where('rule', 'woo-shop')->value('hits'));
    }

    public function test_api_and_admin_are_not_touched(): void
    {
        Redirect::create(['from_path' => '/api/v1/nema-ovoga/', 'to_path' => '/nesto/']);

        $this->get('/api/v1/nema-ovoga')->assertNotFound();
    }
}
