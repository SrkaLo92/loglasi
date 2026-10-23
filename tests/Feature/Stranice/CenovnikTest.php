<?php

namespace Tests\Feature\Stranice;

use App\Models\Firm;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ProductSeeder;
use Tests\TestCase;

class CenovnikTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    public function test_shows_prices_from_products_table(): void
    {
        $this->get('/cenovnik/')
            ->assertOk()
            ->assertSee("50\u{00A0}RSD", false)
            ->assertSee("300\u{00A0}RSD", false)
            ->assertSee("800\u{00A0}RSD", false)
            ->assertSee("6.000\u{00A0}RSD", false)
            ->assertSee('Podizanje oglasa')
            ->assertSee('Biznis paket 12 meseci')
            ->assertSeeInOrder(['Podizanje oglasa', 'Istaknut oglas 7 dana', 'Istaknut oglas 30 dana', 'Biznis paket 12 meseci']);
    }

    public function test_changed_price_in_database_is_shown_everywhere_on_page(): void
    {
        Product::where('code', Product::FEATURED_7)->update(['price_rsd' => 350]);
        Product::where('code', Product::BIZNIS_12M)->update(['price_rsd' => 7500]);

        $this->get('/cenovnik/')
            ->assertOk()
            ->assertSee("350\u{00A0}RSD", false)
            ->assertDontSee("300\u{00A0}RSD", false)
            ->assertSee("7.500\u{00A0}RSD", false)
            ->assertSee('Isticanje na 7 dana košta 350', false)
            ->assertDontSee("6.000\u{00A0}RSD", false);
    }

    public function test_inactive_product_is_hidden_with_its_question(): void
    {
        Product::where('code', Product::BUMP)->update(['is_active' => false]);

        $this->get('/cenovnik/')
            ->assertOk()
            ->assertDontSee('data-proizvod="bump"', false)
            ->assertDontSee('Šta je podizanje oglasa?');
    }

    public function test_product_description_from_database_wins(): void
    {
        Product::where('code', Product::BUMP)->update(['description' => 'Opis podizanja iz admina.']);

        $this->get('/cenovnik/')->assertSee('Opis podizanja iz admina.')->assertDontSee('Korisno kad oglas potone');
    }

    public function test_free_listings_limit_comes_from_settings(): void
    {
        Settings::set('private_monthly_limit', 12);

        $this->get('/cenovnik/')->assertSee('Do 12 novih oglasa mesečno za privatne korisnike.');
    }

    public function test_business_card_shows_free_spots_counter(): void
    {
        Firm::factory()->count(2)->create(['user_id' => fn () => User::factory(), 'founding_free' => true]);
        Firm::factory()->create(['user_id' => User::factory(), 'founding_free' => false]);

        $this->get('/cenovnik/')
            ->assertSee('Prvih 30 firmi besplatno prvu godinu')
            ->assertSee('Još 28 besplatnih mesta od 30')
            ->assertSee('Zauzeto je 2 od 30.');
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
