<?php

namespace Tests\Feature\Stranice;

use App\Models\Product;
use App\Support\Settings;
use App\Support\Stranice\PageText;
use Database\Seeders\ProductSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StraniceTest extends TestCase
{
    /** Engleske reči koje ne smeju da se pojave u tekstu strane. */
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
            'cenovnik' => ['/cenovnik/', 'Cenovnik', 'Postavljanje je besplatno, plaćate samo dodatke'],
            'za firme' => ['/za-firme/', 'Predstavite svoju firmu kupcima iz Loznice', 'Šta dobijate uz Biznis paket'],
            'kontakt' => ['/kontakt/', 'Kontakt', 'Pišite nam'],
            'o nama' => ['/o-nama/', 'O nama', 'Zašto lokalni oglasi'],
            'česta pitanja' => ['/faq/', 'Česta pitanja', 'Kako da postavim oglas?'],
            'uslovi' => ['/uslovi-koriscenja/', 'Uslovi korišćenja', 'Zabranjen sadržaj'],
            'privatnost' => ['/politika-privatnosti/', 'Politika privatnosti', 'Vaša prava'],
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
            ->assertSee('<link rel="canonical" href="'.url_slash($url).'">', false)
            ->assertSee('css/stranice.css', false);
    }

    public function test_urls_without_trailing_slash_work_too(): void
    {
        $this->get('/cenovnik')->assertRedirect(url_slash('cenovnik'));
        $this->get('/faq')->assertRedirect(url_slash('faq'));
    }

    public function test_text_page_uses_admin_text_and_escapes_html(): void
    {
        Settings::set(PageText::PREFIX.'o_nama.tekst', "## Naš tim\n\nTekst iz admina <script>alert(1)</script> za {besplatni_oglasi}.");

        $this->get('/o-nama/')
            ->assertOk()
            ->assertSee('<h2>Naš tim</h2>', false)
            ->assertSee('Tekst iz admina')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('do 10 novih oglasa')
            ->assertDontSee('Zašto lokalni oglasi');
    }

    public function test_faq_answers_use_prices_from_database(): void
    {
        Product::where('code', 'bump')->update(['price_rsd' => 70]);

        $this->get('/faq/')->assertOk()->assertSee("70\u{00A0}RSD", false);
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
        // Ni u atributima koje čitač ekrana izgovara.
        preg_match_all('/(?:aria-label|placeholder|title|alt)="([^"]*)"/', $html, $m);
        foreach ($m[1] as $attr) {
            foreach (self::ENGLESKI as $word) {
                $this->assertDoesNotMatchRegularExpression('/\b'.preg_quote($word, '/').'\b/u', $attr, "Engleska reč „{$word}” u atributu.");
            }
        }
    }
}
