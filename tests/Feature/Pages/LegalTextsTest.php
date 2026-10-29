<?php

namespace Tests\Feature\Pages;

use App\Models\Product;
use App\Support\Pages\PageText;
use App\Support\Payments\PaymentDetails;
use App\Support\Settings;
use Database\Seeders\ProductSeeder;
use Illuminate\Support\Once;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Uslovi kupovine i plaćanja, Odustanak od ugovora, Reklamacije: static pages with admin texts. */
class LegalTextsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    public static function pages(): array
    {
        return [
            'plaćanje' => ['/uslovi-placanja/', 'Uslovi kupovine i plaćanja', 'Uslovi kupovine i plaćanja | loglasi.rs', '3. Kako se naručuje'],
            'odustanak' => ['/odustanak-od-ugovora/', 'Odustanak od ugovora', 'Odustanak od ugovora i obrazac | loglasi.rs', 'Kada pravo na odustanak prestaje'],
            'pravila' => ['/pravila-oglasavanja/', 'Pravila oglašavanja', 'Pravila oglašavanja | loglasi.rs', 'Šta nije dozvoljeno'],
            'reklamacije' => ['/reklamacije/', 'Reklamacije i povraćaj novca', 'Reklamacije i povraćaj novca | loglasi.rs', 'Kako da podnesete reklamaciju'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_renders_with_seo_and_self_canonical(string $url, string $h1, string $title, string $h2): void
    {
        $response = $this->get($url)->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*'.preg_quote(e($h1), '#').'\s*</h1>#u', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $response->assertSee('<h2>'.e($h2).'</h2>', false)
            ->assertSee('<title>'.e($title).'</title>', false)
            ->assertSee('<meta name="description" content="', false)
            ->assertSee('<link rel="canonical" href="'.config('seo.base_url').$url.'">', false)
            ->assertDontSee('noindex', false)
            ->assertDontSee('nacrt');
        $this->assertStringNotContainsStringIgnoringCase('lorem', $html);
        // Every placeholder is filled in.
        preg_match('#<main\b.*</main>#s', $html, $main);
        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $main[0] ?? '');
    }

    #[DataProvider('pages')]
    public function test_page_is_in_pages_sitemap_and_footer(string $url, string $h1, string $title, string $h2): void
    {
        $this->assertContains($url, config('seo.pages'));
        $this->get('/sitemap-strane.xml')->assertOk()->assertSee('<loc>'.config('seo.base_url').$url.'</loc>', false);
        $this->get('/kontakt/')->assertOk()->assertSee('<a href="'.url_slash($url).'" >'.e($h1).'</a>', false);
    }

    public function test_footer_has_the_link_labels(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Uslovi kupovine i plaćanja')
            ->assertSee('Odustanak od ugovora')
            ->assertSee('Reklamacije i povraćaj novca')
            ->assertSee('Pravila oglašavanja');
    }

    public function test_privacy_names_the_controller_cookies_and_processors(): void
    {
        $this->get('/politika-privatnosti/')->assertOk()
            ->assertSee('Rukovalac podacima je SRĐAN MARINKOVIĆ PR VEB PORTALI ALPHASOFT DEV KLUPCI, Marka Kulića 57G, Klupci, 15300 Loznica')
            ->assertSee('Ne, hvala')
            ->assertSee('Ko još obrađuje podatke')
            ->assertSee('Poverenik', false);
    }

    public function test_payment_terms_list_services_and_prices_from_products(): void
    {
        Product::where('code', 'top_7')->update(['price_rsd' => 455, 'name' => 'TOP oglas nedelja']);
        Product::where('code', 'listing_slots_30')->update(['is_active' => false]);

        $this->get('/uslovi-placanja/')->assertOk()
            ->assertSee('TOP oglas nedelja')
            ->assertSee("455\u{00A0}RSD", false)
            ->assertSee('Business Pro')
            ->assertDontSee('+30 oglasa')
            ->assertSee('href="/cenovnik/"', false)
            ->assertSee('u roku od '.config('payments.pending_days').' dana');
    }

    public function test_vat_note_is_shown_only_when_set(): void
    {
        $this->get('/uslovi-placanja/')->assertOk()->assertDontSee('nije u sistemu PDV-a');

        Settings::set('payment_vat_note', 'Prodavac nije u sistemu PDV-a.');
        Once::flush();
        $this->get('/uslovi-placanja/')->assertOk()->assertSee('Prodavac nije u sistemu PDV-a.');
    }

    public function test_seller_block_comes_from_settings(): void
    {
        $this->get('/uslovi-placanja/')->assertOk()
            ->assertSee('<li><strong>PIB:</strong> 115879910</li>', false)
            ->assertSee('<li><strong>Adresa:</strong> Marka Kulića 57G, Klupci, 15300 Loznica</li>', false)
            ->assertSee('<li><strong>E-pošta:</strong> <a href="mailto:kontakt@loglasi.rs">kontakt@loglasi.rs</a></li>', false)
            ->assertSee('<li><strong>Telefon:</strong> +381 65 541 5128</li>', false)
            ->assertSee('pozovite <a href="tel:+381655415128">', false);

        // Empty impresum settings fall back to the payment recipient.
        foreach (['company_name', 'company_address', 'company_pib', 'company_mb'] as $key) {
            Settings::set($key, '');
        }
        Settings::set('payment_recipient', "PRIMALAC PR TEST\nGlavna 1, Loznica");
        Settings::set('payment_pib', '100002887');
        Settings::set('payment_mb', '12345678');
        Settings::set('payment_account', '160-0000000123456-54');
        Once::flush();

        foreach (['/uslovi-placanja/', '/reklamacije/', '/odustanak-od-ugovora/'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('PRIMALAC PR TEST')
                ->assertSee('Glavna 1, Loznica')
                ->assertSee('100002887')
                ->assertSee('12345678')
                ->assertSee('160-0000000123456-54');
        }
    }

    public function test_seller_values_are_not_markdown(): void
    {
        Settings::set('company_name', '[Klik](/kontakt/) *masno* <b>x</b>');

        $this->get('/uslovi-placanja/')->assertOk()
            ->assertDontSee('>Klik</a>', false)
            ->assertDontSee('<em>masno</em>', false)
            ->assertDontSee('<b>x</b>', false)
            ->assertSee('[Klik](/kontakt/) *masno*', false);
    }

    public function test_withdrawal_page_has_printable_form(): void
    {
        $this->get('/odustanak-od-ugovora/')->assertOk()
            ->assertSee('Obrazac za odustanak od ugovora zaključenog na daljinu')
            ->assertSee('Ovim obaveštavam da odustajem od ugovora o pružanju sledeće usluge:')
            ->assertSee('Broj narudžbine (poziv na broj)')
            ->assertSee('Potpis potrošača (samo ako se obrazac šalje na papiru)')
            ->assertSee('<input type="text" id="lgx-wform-0"', false)
            ->assertSee('<label for="lgx-wform-0">', false)
            ->assertSee('14 dana')
            ->assertSee('Odštampajte obrazac');

        $this->assertStringContainsString('@media print', (string) file_get_contents(public_path('css/pages.css')));
    }

    public function test_complaints_page_states_legal_deadlines(): void
    {
        $this->get('/reklamacije/')->assertOk()
            ->assertSee('<strong>8 dana</strong>', false)
            ->assertSee('<strong>15 dana</strong>', false)
            ->assertSee('<strong>14 dana</strong>', false)
            ->assertSee('broj narudžbine');
    }

    public function test_admin_can_replace_the_text(): void
    {
        Settings::set(PageText::PREFIX.'reklamacije.tekst', "## Nov tekst\n\nPišite na {email}.");

        $this->get('/reklamacije/')->assertOk()
            ->assertSee('<h2>Nov tekst</h2>', false)
            ->assertDontSee('Šta nam je potrebno');
    }

    public function test_privacy_policy_keeps_old_sections_and_adds_payment_data(): void
    {
        $this->get('/politika-privatnosti/')->assertOk()
            ->assertSee('Koje podatke prikupljamo')
            ->assertSee('Vaša prava')
            ->assertSee('<h2>Podaci o plaćanju</h2>', false)
            ->assertSee('Zakon o računovodstvu')
            ->assertSee('fiskalni račun');
    }

    public function test_legal_links_default_to_the_new_pages(): void
    {
        $this->assertSame(url_slash('uslovi-placanja'), PaymentDetails::legalLink('terms'));
        $this->assertSame(url_slash('odustanak-od-ugovora'), PaymentDetails::legalLink('withdrawal'));
        $this->assertSame(url_slash('reklamacije'), PaymentDetails::legalLink('complaints'));

        Settings::set('payment_page_terms', '/uslovi-koriscenja/');
        $this->assertSame(url_slash('uslovi-koriscenja'), PaymentDetails::legalLink('terms'));
    }

    public function test_urls_without_trailing_slash_redirect(): void
    {
        $this->get('/reklamacije')->assertRedirect(url_slash('reklamacije'));
    }
}
