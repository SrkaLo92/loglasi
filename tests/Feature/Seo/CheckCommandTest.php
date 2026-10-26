<?php

namespace Tests\Feature\Seo;

use App\Models\Category;
use App\Models\GoneUrl;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class CheckCommandTest extends SeoTestCase
{
    private const BASE = 'http://loglasirs.local';

    private string $tsv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tsv = tempnam(sys_get_temp_dir(), 'gsc');
    }

    protected function tearDown(): void
    {
        @unlink($this->tsv);
        parent::tearDown();
    }

    private function html(string $robots, ?string $canonical): string
    {
        return '<html><head><meta name="robots" content="'.$robots.'">'
            .($canonical ? '<link rel="canonical" href="'.$canonical.'">' : '').'</head><body></body></html>';
    }

    private function writeTsv(array $paths): void
    {
        $lines = ["URL\tKlikovi\tPrikazi"];

        foreach ($paths as $path) {
            $lines[] = 'https://loglasi.rs'.$path."\t3\t40";
        }

        file_put_contents($this->tsv, implode("\n", $lines)."\n");
    }

    private function seedSite(): void
    {
        $category = Category::factory()->create(['slug' => 'automobili']);
        Listing::factory()->for($category)->create(['slug' => 'golf']);
        Listing::factory()->for($category)->expired()->create(['slug' => 'stari']);
        Listing::factory()->for($category)->create(['slug' => 'obrisan'])->delete();
        GoneUrl::create(['path' => '/oglas/spam/']);
        User::factory()->firm()->create(['slug' => 'firma']);
        User::factory()->create(['slug' => 'pera']);
    }

    public function test_all_urls_pass_when_site_behaves_as_expected(): void
    {
        $this->seedSite();
        $this->writeTsv([
            '/', '/', '/oglas/golf/', '/oglas/stari/', '/oglas/spam/', '/oglas/obrisan/',
            '/oglasi/?kategorija=automobili', '/oglasi/?kategorija=nema', '/oglasi/?marka-automobila=bmw',
            '/product/paket/', '/stranica-404/', '/korisnik/firma/', '/korisnik/pera/', '/sitemap_index.xml',
            '/feed/', '/oglasi/page/2/', '/o-nama/', '/login-registracija/',
        ]);

        $b = self::BASE;
        Http::fake([
            $b.'/' => Http::response($this->html('index, follow', 'https://loglasi.rs/')),
            $b.'/oglas/golf/' => Http::response($this->html('index, follow', 'https://loglasi.rs/oglas/golf/')),
            $b.'/oglas/stari/' => Http::response($this->html('noindex, follow', null)),
            $b.'/oglas/spam/' => Http::response('', 410),
            $b.'/oglas/obrisan/' => Http::response('', 410),
            $b.'/oglasi/?kategorija=automobili' => Http::response('', 301, ['Location' => $b.'/oglasi/automobili/']),
            $b.'/oglasi/?kategorija=nema' => Http::response('', 301, ['Location' => $b.'/oglasi/']),
            $b.'/oglasi/?marka-automobila=bmw' => Http::response('', 301, ['Location' => '/oglasi/automobili/']),
            $b.'/oglasi/automobili/' => Http::response($this->html('index, follow', 'https://loglasi.rs/oglasi/automobili/')),
            $b.'/oglasi/' => Http::response($this->html('index, follow', 'https://loglasi.rs/oglasi/')),
            $b.'/product/paket/' => Http::response('', 301, ['Location' => $b.'/cenovnik/']),
            $b.'/cenovnik/' => Http::response($this->html('index, follow', 'https://loglasi.rs/cenovnik/')),
            $b.'/stranica-404/' => Http::response('', 404),
            $b.'/korisnik/firma/' => Http::response($this->html('index, follow', 'https://loglasi.rs/korisnik/firma/')),
            $b.'/korisnik/pera/' => Http::response($this->html('noindex, follow', null)),
            $b.'/sitemap_index.xml' => Http::response('', 301, ['Location' => $b.'/sitemap.xml']),
            $b.'/sitemap.xml' => Http::response('<sitemapindex/>'),
            $b.'/feed/' => Http::response('', 410),
            $b.'/oglasi/page/2/' => Http::response($this->html('index, follow', 'https://loglasi.rs/oglasi/page/2/')),
            $b.'/o-nama/' => Http::response($this->html('index, follow', 'https://loglasi.rs/o-nama/')),
            $b.'/login-registracija/' => Http::response($this->html('noindex, follow', null)),
        ]);

        $csv = tempnam(sys_get_temp_dir(), 'rez');

        $this->artisan('seo:check', ['file' => $this->tsv, 'base_url' => $b, '--pause' => 0, '--csv' => $csv])
            ->expectsOutputToContain('Ukupno: 17, OK: 17, GREŠKA: 0')
            ->assertSuccessful();

        $rows = array_map('str_getcsv', file($csv, FILE_IGNORE_NEW_LINES));
        @unlink($csv);

        $this->assertSame('URL', $rows[0][0]);
        $this->assertCount(18, $rows); // zaglavlje + 17 jedinstvenih URL-ova
        $this->assertSame(['OK'], array_values(array_unique(array_column(array_slice($rows, 1), 7))));
    }

    public function test_reports_errors(): void
    {
        $this->seedSite();
        $this->writeTsv(['/oglas/golf/', '/oglasi/?kategorija=automobili', '/oglas/nepoznat/', '/korisnik/pera/', '/product/paket/']);

        $b = self::BASE;
        Http::fake([
            // indeksabilan oglas ima noindex i canonical na lokalni domen
            $b.'/oglas/golf/' => Http::response($this->html('noindex', 'http://loglasirs.local/oglas/golf/')),
            // lanac: 301 -> 301
            $b.'/oglasi/?kategorija=automobili' => Http::response('', 301, ['Location' => $b.'/oglasi/automobili']),
            $b.'/oglasi/automobili' => Http::response('', 301, ['Location' => $b.'/oglasi/automobili/']),
            $b.'/oglas/nepoznat/' => Http::response('', 404),
            // privatni profil bez noindex
            $b.'/korisnik/pera/' => Http::response($this->html('index, follow', 'https://loglasi.rs/korisnik/pera/')),
            // 5xx
            $b.'/product/paket/' => Http::response('', 500),
        ]);

        $this->artisan('seo:check', ['file' => $this->tsv, 'base_url' => $b, '--pause' => 0, '--errors-only' => true])
            ->expectsOutputToContain('Ukupno: 5, OK: 0, GREŠKA: 5 (5xx: 1, neočekivan 404: 1, lanci: 1)')
            ->assertFailed();
    }

    public function test_checks_sitemap_and_gone_list(): void
    {
        $this->seedSite();
        $this->writeTsv(['/']);

        $b = self::BASE;
        Http::fake([
            $b.'/' => Http::response($this->html('index, follow', 'https://loglasi.rs/')),
            $b.'/sitemap.xml' => Http::response('<sitemapindex><sitemap><loc>https://loglasi.rs/sitemap-oglasi-1.xml</loc></sitemap></sitemapindex>'),
            $b.'/sitemap-oglasi-1.xml' => Http::response('<urlset><url><loc>https://loglasi.rs/oglas/golf/</loc></url><url><loc>https://loglasi.rs/oglas/spam/</loc></url></urlset>'),
            $b.'/oglas/golf/' => Http::response($this->html('index, follow', 'https://loglasi.rs/oglas/golf/')),
            $b.'/oglas/spam/' => Http::response('', 410),
        ]);

        // spam je u sitemapu: greška i za sitemap (410 umesto 200) i za 410 listu (u sitemapu)
        $this->artisan('seo:check', ['file' => $this->tsv, 'base_url' => $b, '--pause' => 0, '--sitemap' => true, '--gone' => true])
            ->expectsOutputToContain('Ukupno: 4, OK: 2, GREŠKA: 2')
            ->assertFailed();
    }

    public function test_invalid_arguments(): void
    {
        $this->artisan('seo:check', ['file' => '/nema/fajla.tsv', 'base_url' => self::BASE])->assertExitCode(2);

        $this->writeTsv(['/']);
        $this->artisan('seo:check', ['file' => $this->tsv, 'base_url' => 'loglasirs.local/putanja'])->assertExitCode(2);
    }
}
