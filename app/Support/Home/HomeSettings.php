<?php

namespace App\Support\Home;

use App\Support\Settings;

/**
 * Home page settings (admin): Settings keys "home.*" with defaults.
 * Ported from loglasi-core inc/home (shortcode attributes and loglasi_pocetna_* options).
 */
class HomeSettings
{
    public const DEFAULTS = [
        // 1. Intro
        'home.h1' => 'Oglasi za Loznicu i okolinu',
        'home.intro_text' => 'Besplatno postavljanje oglasa. Kupujte i prodajte direktno, od ljudi iz svog kraja.',
        'home.intro_label' => 'Često se traži:',
        'home.intro_terms' => '',            // "bicikl, stan Loznica, Golf"; empty = the biggest categories
        'home.intro_terms_count' => 5,
        // 2. Main categories (big tiles, the sixth is "Sve kategorije"), then the popular ones
        'home.main_categories' => 'automobili,nekretnine,besplatne-stvari,poslovi,usluge',
        'home.categories_title' => 'Popularno',
        'home.categories_count' => 11,
        // 2b. Premium XL + početna (hidden when empty)
        'home.premium_title' => 'Premium oglasi',
        'home.premium_count' => 4,
        // 3. Featured
        'home.featured_title' => 'Istaknuti oglasi',
        'home.featured_count' => 4,
        'home.featured_link_text' => 'Istaknite svoj oglas',
        'home.featured_link_url' => '/cenovnik/',
        // 4. Latest
        'home.latest_title' => 'Najnoviji oglasi',
        'home.latest_count' => 8,
        'home.latest_mobile_count' => 6,
        // 5. Tabs (desktop only)
        'home.tabs_title' => 'Najtraženije kategorije',
        'home.tabs_categories' => 'automobili,nekretnine,usluge,bicikli-i-delovi',
        'home.tabs_count' => 4,
        // 6. Firms
        'home.firms_title' => 'Firme iz Loznice',
        'home.firms_text' => 'Proverene lokalne radnje, servisi i zanatlije sa svojim oglasima',
        'home.firms_cta_title' => 'Imate radnju ili servis u Loznici?',
        'home.firms_cta_text' => 'Predstavite svoju radnju, servis ili zanat kupcima iz Loznice i okoline, sa profilom firme i svim oglasima na jednom mestu.',
        'home.firms_button' => 'Prijavite svoju firmu',
        'home.firms_url' => '/za-firme/',
        'home.firms_min' => 1,                // the section shows from this many firms
        'home.firms_max' => 4,
        // 8. About the site (Markdown; empty = default text)
        'home.about_title' => 'O sajtu loglasi.rs',
        'home.about' => '',
    ];

    public const ABOUT_DEFAULT = "loglasi.rs je sajt za besplatne male oglase namenjen Loznici i okolini. Ovde ljudi iz Loznice, Banje Koviljače, Lešnice, Tršića, Klupaca, Lipnice, Jadranske Lešnice i okolnih sela kupuju i prodaju direktno, bez posrednika i bez provizije.\n\n"
        ."Na jednom mestu su oglasi za automobile i delove, stanove, kuće i placeve, poljoprivrednu mehanizaciju, nameštaj, tehniku, odeću i stvari za decu, kao i oglasi za posao i lokalne usluge: majstore, prevoz, popravke i servis. Pošto su svi oglasi iz našeg kraja, stvar možete da pogledate uživo, a dogovor obavite telefonom ili porukom.\n\n"
        ."Postavljanje oglasa je besplatno. Dovoljno je da napravite nalog, dodate nekoliko fotografija, opis i cenu, i oglas je spreman. Firme iz Loznice mogu da otvore profil sa logoom i svim svojim oglasima, pa ih kupci lakše pronađu.\n\n"
        .'Ako tražite polovan auto u Loznici, stan za izdavanje u Banji Koviljači ili majstora u Lešnici, krenite od pretrage ili izaberite kategoriju. Novi oglasi stižu svakog dana.';

    public static function get(string $key): mixed
    {
        $default = self::DEFAULTS[$key] ?? null;
        $value = Settings::get($key, $default);

        if (is_int($default)) {
            return is_numeric($value) ? (int) $value : $default;
        }
        if (is_bool($default)) {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        return is_string($value) && trim($value) !== '' ? trim($value) : (string) $default;
    }

    public static function int(string $key, int $min, int $max): int
    {
        return max($min, min($max, (int) self::get($key)));
    }

    /** @return list<string> comma separated setting as a list */
    public static function list(string $key): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) self::get($key))), 'strlen'));
    }

    /** Relative path or full https URL; anything else falls back to the default. */
    public static function url(string $key): string
    {
        $value = (string) self::get($key);
        if (preg_match('~^https?://~i', $value)) {
            return $value;
        }
        if (! str_starts_with($value, '/') || str_starts_with($value, '//')) {
            $value = (string) self::DEFAULTS[$key];
        }

        // A query string or fragment stays as typed: "/oglasi/?keyword=bicikl", "/cenovnik/#biznis".
        return strpbrk($value, '?#') !== false ? url($value) : url_slash($value);
    }
}
