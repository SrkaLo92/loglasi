<?php

namespace Tests\Feature\Stranice;

use App\Models\Firm;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ProductSeeder;
use Illuminate\Support\Once;
use Tests\TestCase;

class CenovnikTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    public function test_shows_sections_with_prices_from_products_table(): void
    {
        $this->get('/cenovnik/')
            ->assertOk()
            ->assertSeeInOrder(['Za privatne korisnike', 'Treba vam više oglasa?', 'Promocije oglasa', 'Za firme', 'Paketi posle prve godine', 'Dodatno oglašavanje za firme', 'Kako da platim'])
            ->assertSee("149\u{00A0}RSD", false)
            ->assertSee("799\u{00A0}RSD", false)
            ->assertSee("49\u{00A0}RSD", false)
            ->assertSee("199\u{00A0}RSD", false)
            ->assertSee("899\u{00A0}RSD", false)
            ->assertSeeInOrder(['Podizanje oglasa', 'Paket 10 podizanja', 'Istaknut oglas 7 dana', 'Prioritetni oglas', 'TOP oglas', 'Premium XL kartica', 'Premium XL + početna'])
            ->assertSeeInOrder(['Business Start', '1.490', "14.900\u{00A0}RSD", 'Business Pro', 'Najčešći izbor', 'Business Max', '4.990'], false)
            ->assertSee('Do 20 aktivnih oglasa')
            ->assertDontSee('Founder');
    }

    public function test_at_most_three_packages_per_row_and_no_five_card_row(): void
    {
        $html = $this->get('/cenovnik/')->getContent();

        $this->assertSame(3, substr_count($html, '<li class="lgc-paket'));
        $this->assertStringNotContainsString('lgx-cene', $html);
        $this->assertStringContainsString('repeat(3, minmax(0, 1fr))', file_get_contents(public_path('css/cenovnik.css')));
    }

    public function test_changed_price_in_database_is_shown_everywhere_on_page(): void
    {
        Product::where('code', Product::FEATURED_7)->update(['price_rsd' => 350]);
        Product::where('code', 'business_pro_monthly')->update(['price_rsd' => 3200]);

        $this->get('/cenovnik/')
            ->assertOk()
            ->assertSee("350\u{00A0}RSD", false)
            ->assertDontSee("199\u{00A0}RSD", false)
            ->assertSee('3.200')
            ->assertDontSee('2.990')
            ->assertSee('Isticanje na 7 dana košta 350', false);
    }

    public function test_inactive_product_is_hidden_with_its_question(): void
    {
        Product::where('code', Product::BUMP)->update(['is_active' => false]);

        $this->get('/cenovnik/')
            ->assertOk()
            ->assertDontSee('data-proizvod="bump"', false)
            ->assertDontSee('Šta je podizanje oglasa?');
    }

    public function test_display_only_products_are_listed_but_not_sold(): void
    {
        $this->assertFalse((bool) Product::where('code', 'top_7')->value('is_active'));
        $this->get('/cenovnik/')->assertSee('data-proizvod="top_7"', false);
        $this->getJson('/api/v1/prices')->assertJsonMissing(['product' => 'top_7']);

        Product::where('code', 'top_7')->update(['display_only' => false]);
        Once::flush(); // products are read once per request
        $this->get('/cenovnik/')->assertDontSee('data-proizvod="top_7"', false);
    }

    public function test_sections_without_products_are_not_rendered(): void
    {
        Product::where('code', 'like', 'business_%')->delete();
        Product::where('code', 'like', 'listing_slots_%')->delete();

        $this->get('/cenovnik/')->assertOk()
            ->assertDontSee('Paketi posle prve godine')
            ->assertDontSee('Treba vam više oglasa?')
            ->assertSee('12 meseci besplatno za prvih 30 firmi');
    }

    public function test_product_description_from_database_wins(): void
    {
        Product::where('code', Product::BUMP)->update(['description' => 'Opis podizanja iz admina.']);

        $this->get('/cenovnik/')->assertSee('Opis podizanja iz admina.')->assertDontSee('Korisno kad oglas potone');
    }

    public function test_free_listings_limit_comes_from_settings(): void
    {
        Settings::set('private_active_limit', 12);

        $this->get('/cenovnik/')->assertSee('do 12 aktivnih oglasa');
    }

    public function test_firms_block_shows_free_spots_counter(): void
    {
        Firm::factory()->count(2)->create(['user_id' => fn () => User::factory(), 'founding_free' => true]);
        Firm::factory()->create(['user_id' => User::factory(), 'founding_free' => false]);

        $this->get('/cenovnik/')
            ->assertSee('Prvih 30 firmi besplatno')
            ->assertSee('Još 28 besplatnih mesta od 30')
            ->assertSee('Zauzeto je 2 od 30.')
            ->assertSee('aria-valuenow="7"', false);
    }

    public function test_without_payment_data_no_account_is_invented(): void
    {
        $this->get('/cenovnik/')
            ->assertSee('Podatke za uplatu šaljemo na zahtev.')
            ->assertDontSee('data-lgx-kopiraj', false)
            ->assertDontSee('js/stranice.js', false);
    }

    public function test_payment_data_from_settings_with_copy_button(): void
    {
        Settings::set('payment_recipient', "Primalac d.o.o.\nLoznica");
        Settings::set('payment_account', '160-0000000012345-67');
        Settings::set('payment_purpose', 'Usluga na loglasi.rs');

        $this->get('/cenovnik/')
            ->assertSee('Primalac d.o.o.<br />', false)
            ->assertSee('data-lgx-kopiraj="160-0000000012345-67"', false)
            ->assertSee('Svrha uplate')
            ->assertDontSee('Poziv na broj')
            ->assertSee('js/stranice.js', false)
            ->assertDontSee('Podatke za uplatu šaljemo na zahtev.');
    }
}
