<?php

namespace Tests\Feature\Pages;

use App\Models\Product;
use App\Support\Pages\PageText;
use App\Support\Settings;
use Database\Seeders\ProductSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PagesTest extends TestCase
{
    /** English words that must not appear in the page text. */
    private const ENGLESKI = [
        'Login', 'Log in', 'Register', 'Submit', 'Search', 'Home', 'Contact us', 'Read more', 'Price',
        'Next', 'Previous', 'Not Found', 'Server Error', 'Too Many Requests', 'Page Expired', 'Forbidden',
        'Email', 'Password', 'Send', 'Messages', 'Account', 'Pricing', 'About', 'Privacy', 'Terms', 'FAQ',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProductSeeder::class);
    }

    public static function pages(): array
    {
        return [
            'cenovnik' => ['/cenovnik/', 'Cenovnik', 'Za privatne korisnike'],
            'za firme' => ['/za-firme/', 'Predstavite svoju firmu kupcima iz Loznice', 'Šta dobija firma uz profil firme'],
            'kontakt' => ['/kontakt/', 'Kontakt', 'Pišite nam'],
            'o nama' => ['/o-nama/', 'Oglasi iz Loznice, za ljude iz Loznice', 'Zašto lokalni oglasi'],
            'česta pitanja' => ['/faq/', 'Česta pitanja', 'Kako da postavim oglas?'],
            'uslovi' => ['/uslovi-koriscenja/', 'Uslovi korišćenja', 'Zabranjen sadržaj'],
            'privatnost' => ['/politika-privatnosti/', 'Politika privatnosti', 'Vaša prava'],
            'uslovi plaćanja' => ['/uslovi-placanja/', 'Uslovi kupovine i plaćanja', 'Kako se naručuje'],
            'odustanak' => ['/odustanak-od-ugovora/', 'Odustanak od ugovora', 'Obrazac za odustanak od ugovora zaključenog na daljinu'],
            'reklamacije' => ['/reklamacije/', 'Reklamacije i povraćaj novca', 'Šta nam je potrebno'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_renders_in_serbian_with_expected_headings(string $url, string $h1, string $h2): void
    {
        $response = $this->get($url)->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression('#<h1[^>]*>\s*'.preg_quote(e($h1), '#').'\s*</h1>#u', $html);
        $response->assertSee($h2);
        $response->assertSee('<html lang="sr-Latn">', false);
        $this->assertSame(1, substr_count($html, '<h1'), 'Tačno jedan H1 na strani.');
        $this->assertNoEnglish($html);
        $this->assertStringNotContainsStringIgnoringCase('lorem', $html);
    }

    #[DataProvider('pages')]
    public function test_page_has_site_chrome_and_canonical(string $url, string $h1, string $h2): void
    {
        $this->get($url)
            ->assertOk()
            ->assertSee('class="lg-header"', false)
            ->assertSee('class="lg-footer"', false)
            ->assertSee('class="lg-mnav"', false)
            ->assertSee('loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.')
            ->assertSee('<link rel="canonical" href="'.config('seo.base_url').rtrim($url, '/').'/">', false)
            ->assertSee('css/stranice.css', false);
    }

    public function test_urls_without_trailing_slash_work_too(): void
    {
        $this->get('/cenovnik')->assertRedirect(url_slash('cenovnik'));
        $this->get('/faq')->assertRedirect(url_slash('faq'));
    }

    public function test_text_page_uses_admin_text_and_escapes_html(): void
    {
        Settings::set(PageText::PREFIX.'uslovi.tekst', "## Naš tim\n\nTekst iz admina <script>alert(1)</script> za {besplatni_oglasi}.");

        $this->get('/uslovi-koriscenja/')
            ->assertOk()
            ->assertSee('<h2>Naš tim</h2>', false)
            ->assertSee('Tekst iz admina')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('do 10 aktivnih oglasa');
    }

    public function test_about_page_has_sections_from_admin_texts_and_live_numbers(): void
    {
        Settings::set(PageText::PREFIX.'o_nama.hero', 'Naslov iz admina <b>x</b>');

        $this->get('/o-nama/')
            ->assertOk()
            ->assertSee('Naslov iz admina &lt;b&gt;x&lt;/b&gt;', false)
            ->assertSee('Zašto lokalni oglasi')
            ->assertSee('Kako funkcioniše')
            ->assertSee('Kako zarađujemo')
            ->assertSee('besplatnih oglasa za privatna lica')
            ->assertDontSee('Imate nešto za prodaju?');
    }

    public function test_faq_answers_use_prices_from_database(): void
    {
        Product::where('code', 'featured_7')->update(['price_rsd' => 270]);

        $this->get('/faq/')->assertOk()->assertSee("270\u{00A0}RSD", false);
    }

    public function test_footer_marks_current_page(): void
    {
        $this->get('/kontakt/')->assertSee('<a href="'.url_slash('kontakt').'"  aria-current="page" >Kontakt</a>', false);
    }

    private function assertNoEnglish(string $html): void
    {
        $text = html_entity_decode(strip_tags((string) preg_replace('#<(script|style|head)\b.*?</\1>#is', '', $html)));
        foreach (self::ENGLESKI as $word) {
            $this->assertDoesNotMatchRegularExpression('/\b'.preg_quote($word, '/').'\b/u', $text, "Engleska reč „{$word}” na strani.");
        }
        // Not in the attributes a screen reader reads out either.
        preg_match_all('/(?:aria-label|placeholder|title|alt)="([^"]*)"/', $html, $m);
        foreach ($m[1] as $attr) {
            foreach (self::ENGLESKI as $word) {
                $this->assertDoesNotMatchRegularExpression('/\b'.preg_quote($word, '/').'\b/u', $attr, "Engleska reč „{$word}” u atributu.");
            }
        }
    }
}
