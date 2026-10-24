<?php

namespace Tests\Feature\Seo;

use Database\Seeders\ProductSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Titles and descriptions kept word for word from the WordPress SEO module. */
class PageTitlesTest extends TestCase
{
    public static function pages(): array
    {
        return [
            'o nama' => ['/o-nama/', 'O nama – lokalni oglasi za Loznicu i okolinu | loglasi.rs', 'loglasi.rs je besplatan sajt za lokalne oglase u Loznici, Banji Koviljači, Lešnici i okolini.'],
            'kontakt' => ['/kontakt/', 'Kontakt – pišite nam | loglasi.rs', 'Imate pitanje o oglasu, nalogu ili oglašavanju firme na loglasi.rs?'],
            'faq' => ['/faq/', 'Česta pitanja (FAQ) – kako postaviti oglas | loglasi.rs', 'Odgovori na najčešća pitanja: kako postaviti i obnoviti oglas'],
            'uslovi' => ['/uslovi-koriscenja/', 'Uslovi korišćenja | loglasi.rs', 'Pravila korišćenja sajta loglasi.rs'],
            'privatnost' => ['/politika-privatnosti/', 'Politika privatnosti | loglasi.rs', 'Kako loglasi.rs prikuplja, koristi i čuva vaše lične podatke'],
            'brisanje' => ['/brisanje-licnih-podataka/', 'Brisanje ličnih podataka | loglasi.rs', 'Uputstvo kako da zatražite brisanje svog naloga'],
            'cenovnik' => ['/cenovnik/', 'Cenovnik: promocije oglasa i paketi za firme | loglasi.rs', 'Postavljanje oglasa je besplatno. Pogledajte cene promocija oglasa'],
        ];
    }

    #[DataProvider('pages')]
    public function test_page_title_and_description(string $url, string $title, string $description): void
    {
        $this->seed(ProductSeeder::class);

        $this->get($url)->assertOk()
            ->assertSee('<title>'.e($title).'</title>', false)
            ->assertSee('<meta name="description" content="'.e($description), false)
            ->assertSee('<h1', false);
    }

    public function test_not_found_title(): void
    {
        $this->get('/nema-ove-strane/')->assertNotFound()
            ->assertSee('<title>Stranica nije pronađena | loglasi.rs</title>', false);
    }

    public function test_search_title(): void
    {
        $this->get('/oglasi/?keyword=bicikl')->assertOk()
            ->assertSee('<title>Pretraga: bicikl | loglasi.rs</title>', false)
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }
}
