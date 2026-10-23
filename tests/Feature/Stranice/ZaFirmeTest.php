<?php

namespace Tests\Feature\Stranice;

use App\Models\Firm;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ProductSeeder;
use Tests\TestCase;

class ZaFirmeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    private function foundingFirms(int $n): void
    {
        Firm::factory()->count($n)->create(['user_id' => fn () => User::factory(), 'founding_free' => true]);
    }

    public function test_counter_is_total_minus_founding_free_firms(): void
    {
        $this->foundingFirms(7);
        Firm::factory()->create(['user_id' => User::factory(), 'founding_free' => false]);

        $this->get('/za-firme/')
            ->assertOk()
            ->assertSee('Još 23 besplatna mesta od 30')
            ->assertSee('Zauzeto je 7 od 30.')
            ->assertSee("Prvih 30 firmi dobija Biznis paket besplatno prvu godinu, a posle toga 6.000\u{00A0}RSD godišnje.", false)
            ->assertSee('Prijavite firmu dok ima besplatnih mesta');
    }

    public function test_counter_singular_form(): void
    {
        $this->foundingFirms(29);

        $this->get('/za-firme/')->assertSee('Još 1 besplatno mesto od 30');
    }

    public function test_total_comes_from_settings(): void
    {
        Settings::set('founding_firms_total', 50);
        $this->foundingFirms(5);

        $this->get('/za-firme/')->assertSee('Još 45 besplatnih mesta od 50');
    }

    public function test_all_spots_taken_shows_only_price(): void
    {
        $this->foundingFirms(30);

        $this->get('/za-firme/')
            ->assertSee('Sva besplatna mesta su popunjena')
            ->assertSee("Biznis paket košta 6.000\u{00A0}RSD godišnje.", false)
            ->assertDontSee('dobija Biznis paket besplatno')
            ->assertSee('Prijavite svoju firmu');
    }

    public function test_business_price_from_products(): void
    {
        Product::where('code', Product::BIZNIS_12M)->update(['price_rsd' => 9000]);

        $this->get('/za-firme/')->assertSee("9.000\u{00A0}RSD", false);
    }

    public function test_cta_goes_to_contact_form_with_firm_topic(): void
    {
        $this->get('/za-firme/')->assertSee(url_slash('kontakt').'?tema=firma#forma', false);

        $this->get('/kontakt/?tema=firma')->assertSee('<option value="Prijava firme (Biznis paket)" selected>', false);
    }

    public function test_phone_from_settings_is_shown_in_e164_link(): void
    {
        Settings::set('contact_phone', '065 123 4567');

        $this->get('/za-firme/')->assertSee('href="tel:+381651234567"', false)->assertSee('+381 65 123 4567');
    }
}
