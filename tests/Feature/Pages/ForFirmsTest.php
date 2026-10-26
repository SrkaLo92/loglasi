<?php

namespace Tests\Feature\Pages;

use App\Enums\AccountType;
use App\Models\Firm;
use App\Models\Product;
use App\Models\User;
use App\Support\Pages\Pricing;
use App\Support\Settings;
use Database\Seeders\ProductSeeder;
use Tests\TestCase;

class ForFirmsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    private function foundingFirms(int $n): void
    {
        Firm::factory()->count($n)->create(['user_id' => fn () => User::factory()->create(['account_type' => AccountType::Firm]), 'founding_free' => true]);
    }

    public function test_blocked_or_converted_founding_firm_frees_its_place(): void
    {
        $this->foundingFirms(2);
        Firm::factory()->create(['user_id' => User::factory()->create(['account_type' => AccountType::Firm, 'status' => 'blocked']), 'founding_free' => true]);
        Firm::factory()->create(['user_id' => User::factory(), 'founding_free' => true]);

        $this->assertSame(28, Pricing::foundingLeft());
    }

    public function test_counter_is_total_minus_founding_free_firms(): void
    {
        $this->foundingFirms(7);
        Firm::factory()->create(['user_id' => User::factory(), 'founding_free' => false]);

        $this->get('/za-firme/')
            ->assertOk()
            ->assertSee('Slobodno još 23 od 30 besplatnih mesta')
            ->assertSee('Zauzeto je 7 od 30.')
            ->assertSee("Prvih 30 firmi: 12 meseci besplatno. Posle toga paketi počinju od 490\u{00A0}RSD mesečno, bez automatske naplate.", false)
            ->assertSee('Za firme')
            ->assertDontSee('Founder')
            ->assertSee('Prijavite firmu dok ima besplatnih mesta');
    }

    public function test_counter_singular_form(): void
    {
        $this->foundingFirms(29);

        $this->get('/za-firme/')->assertSee('Slobodno još 1 od 30 besplatnih mesta');
    }

    public function test_total_comes_from_settings(): void
    {
        Settings::set('founding_firms_total', 50);
        $this->foundingFirms(5);

        $this->get('/za-firme/')->assertSee('Slobodno još 45 od 50 besplatnih mesta');
    }

    public function test_all_spots_taken_shows_only_price(): void
    {
        $this->foundingFirms(30);

        $this->get('/za-firme/')
            ->assertSee('Sva besplatna mesta su popunjena')
            ->assertSee("Paketi za firme počinju od 490\u{00A0}RSD mesečno.", false)
            ->assertDontSee('firmi: 12 meseci besplatno')
            ->assertSee('Prijavite svoju firmu');
    }

    public function test_package_prices_from_products(): void
    {
        Product::where('code', 'business_vizitka_monthly')->update(['price_rsd' => 590]);

        $this->get('/za-firme/')
            ->assertSee("paketi počinju od 590\u{00A0}RSD mesečno", false)
            ->assertSeeInOrder(['Vizitka', "590\u{00A0}RSD", 'Business Start', "1.490\u{00A0}RSD", 'Business Pro', 'Business Max'], false)
            ->assertSee('<th scope="col" class="lgx-tabela__biznis">Profil firme</th>', false)
            ->assertDontSee('Biznis paket');
    }

    public function test_offer_uses_serbian_plural(): void
    {
        Settings::set('founding_firms_total', 21);

        $this->get('/za-firme/')->assertSee('Prva 21 firma: 12 meseci besplatno.');
    }

    public function test_cta_goes_to_contact_form_with_firm_topic(): void
    {
        $this->get('/za-firme/')->assertSee(url_slash('kontakt').'?tema=firma#forma', false);

        $this->get('/kontakt/?tema=firma')->assertSee('<option value="Za firme" selected>', false);
    }

    public function test_phone_from_settings_is_shown_in_e164_link(): void
    {
        Settings::set('contact_phone', '065 123 4567');

        $this->get('/za-firme/')->assertSee('href="tel:+381651234567"', false)->assertSee('+381 65 123 4567');
    }
}
