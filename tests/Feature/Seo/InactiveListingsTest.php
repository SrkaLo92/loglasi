<?php

namespace Tests\Feature\Seo;

use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\WordPressImport\Steps\GoneImport;
use Illuminate\Support\Facades\DB;

class InactiveListingsTest extends SeoTestCase
{
    public function test_sold_listing_is_shown_as_sold_with_noindex(): void
    {
        $listing = Listing::factory()->create(['status' => ListingStatus::Sold]);

        $this->get('/oglas/'.$listing->slug.'/')->assertOk()
            ->assertSee('Prodato.')
            ->assertSee('content="noindex, follow"', false);
    }

    public function test_listing_inactive_for_90_days_is_gone_but_owner_still_sees_it(): void
    {
        $this->travelTo('2026-10-25 12:00:00');
        $old = Listing::factory()->create(['status' => ListingStatus::Expired, 'expires_at' => now()->subDays(91)]);
        $recent = Listing::factory()->create(['status' => ListingStatus::Expired, 'expires_at' => now()->subDays(89)]);
        $sold = Listing::factory()->create(['status' => ListingStatus::Sold]);
        $sold->forceFill(['updated_at' => now()->subDays(120)])->saveQuietly();

        $this->get('/oglas/'.$old->slug.'/')->assertStatus(410);
        $this->get('/oglas/'.$sold->slug.'/')->assertStatus(410);
        $this->get('/oglas/'.$recent->slug.'/')->assertOk();
        $this->actingAs($old->user)->get('/oglas/'.$old->slug.'/')->assertOk();

        // Renewal brings the same address back.
        $old->forceFill(['status' => ListingStatus::Published, 'expires_at' => now()->addDays(30)])->save();
        auth()->logout();
        $this->get('/oglas/'.$old->slug.'/')->assertOk();
    }

    public function test_car_listing_is_a_car_in_schema_and_og_type_is_product(): void
    {
        $category = Category::factory()->create(['name' => 'Automobili', 'slug' => 'automobili']);
        $listing = Listing::factory()->for($category)->create(['price' => 5000]);

        $response = $this->get('/oglas/'.$listing->slug.'/')->assertOk()
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('"Car"', false);
    }

    public function test_redirect_file_lines_are_parsed(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'pr');
        file_put_contents($file, "# komentar\n/oglas/stari-golf/ /oglasi/automobili/\nhttps://loglasi.rs/oglas/kuca/  https://loglasi.rs/oglasi/kuce/ # klikovi\npogresno\n");

        $this->assertSame([
            '/oglas/stari-golf/' => '/oglasi/automobili/',
            '/oglas/kuca/' => '/oglasi/kuce/',
        ], GoneImport::listedRedirects($file));
        unlink($file);
    }

    public function test_default_share_image_has_size_and_alt(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('<meta property="og:image:width" content="1200">', false)
            ->assertSee('<meta property="og:image:alt" content="loglasi.rs: Oglasi za Loznicu i okolinu">', false);
    }

    public function test_redirect_command_loads_the_file_and_clears_410(): void
    {
        Category::factory()->create(['slug' => 'stanovi-prodaja']);
        DB::table('gone_urls')->insert(['path' => '/oglas/%D0%B0%D0%B1/', 'created_at' => now(), 'updated_at' => now()]);
        $file = tempnam(sys_get_temp_dir(), 'pr');
        file_put_contents($file, "/oglas/аб/ /oglasi/stanovi-prodaja/\n/oglas/x/ /oglasi/nema/\n");

        $this->artisan('seo:redirects', ['file' => $file])->assertSuccessful();
        unlink($file);

        $this->get('/oglas/%D0%B0%D0%B1/')->assertStatus(301)->assertTarget('/oglasi/stanovi-prodaja/');
        $this->get('/oglas/x/')->assertStatus(301)->assertTarget('/oglasi/');
    }
}
