<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\Setting;
use App\Services\ListingImages;
use App\Services\Payments\FiscalDriver;
use App\Services\Payments\PlusSeller;
use App\Support\Home\HomeSettings;
use App\Support\Payments\PaymentDetails;
use App\Support\Payments\PaymentMode;
use App\Support\Settings;
use App\Support\Stranice\Cenovnik;
use App\Support\Stranice\PageText;

/**
 * Settings shown on the admin "Podešavanja" page: key, label, type, default.
 * Values equal to the default (or empty) are not stored, so a changed default in code applies.
 * Keys not listed here are edited on the key/value page (SettingResource).
 */
class SettingsSchema
{
    public const MODERATION = [
        'all' => 'Svi oglasi čekaju odobrenje',
        'new_users' => 'Čekaju samo oglasi novih korisnika',
        'none' => 'Bez odobravanja (oglas se odmah objavljuje)',
    ];

    /** @return array<string, array{label: string, type: string, help?: string, default: mixed}> */
    public static function general(): array
    {
        return [
            'private_active_limit' => ['label' => 'Besplatnih aktivnih oglasa (privatna lica)', 'type' => 'integer', 'help' => 'Koliko aktivnih oglasa privatno lice može da ima bez plaćanja. Cenovnik: 10, a kad sajt ima oko 2.500 aktivnih oglasa može da se spusti na 5.', 'default' => Settings::DEFAULTS['private_active_limit']],
            PlusSeller::EXCLUDED_SETTING => ['label' => 'Plus prodavac ne važi u kategorijama', 'type' => 'text', 'help' => 'Slugovi kategorija, odvojeni zarezom (sa svim podkategorijama). Tamo važi samo besplatan limit.', 'default' => implode(', ', PlusSeller::EXCLUDED_DEFAULT)],
            'listing_duration_days' => ['label' => 'Trajanje oglasa (dana)', 'type' => 'integer', 'default' => Settings::DEFAULTS['listing_duration_days']],
            'listing_max_images' => ['label' => 'Najviše slika po oglasu', 'type' => 'integer', 'default' => ListingImages::DEFAULT_MAX_IMAGES],
            'image_max_mb' => ['label' => 'Najveća slika (MB)', 'type' => 'decimal', 'default' => ListingImages::DEFAULT_MAX_MB],
            'moderation' => ['label' => 'Odobravanje oglasa', 'type' => 'select', 'options' => self::MODERATION, 'default' => Settings::DEFAULTS['moderation']],
            'new_condition_firms_only' => ['label' => 'Stanje „Novo” samo za firme', 'type' => 'boolean', 'help' => 'Privatna lica ne mogu da označe predmet kao nov.', 'default' => Settings::DEFAULTS['new_condition_firms_only']],
            'founding_firms_total' => ['label' => 'Broj mesta u akciji „Prvih 30 firmi”', 'type' => 'integer', 'help' => 'Brojač na stranama Cenovnik i Za firme.', 'default' => Settings::DEFAULTS['founding_firms_total']],
            'eur_rate' => ['label' => 'Kurs evra (1 € = … din)', 'type' => 'decimal', 'help' => 'Za sortiranje i filter po ceni (oglasi u dinarima se preračunavaju u evre).', 'default' => Settings::DEFAULTS['eur_rate']],
            'seo_place_min_listings' => ['label' => 'Najmanje oglasa za stranu „kategorija + mesto” u pretrazi', 'type' => 'integer', 'help' => 'Ispod ovog broja strana ima noindex i nije u sitemapu.', 'default' => (int) config('seo.place_min_listings', 3)],
        ];
    }

    /** @return array<string, array{label: string, type: string, help?: string, default: mixed}> */
    public static function contact(): array
    {
        return [
            'contact_email' => ['label' => 'E-pošta za kontakt', 'type' => 'email', 'help' => 'Na nju stižu poruke sa kontakt forme.', 'default' => Settings::DEFAULTS['contact_email']],
            'contact_phone' => ['label' => 'Telefon za kontakt', 'type' => 'text', 'help' => 'U obliku +381651234567. Prikazuje se u podnožju i na strani Česta pitanja.', 'default' => Settings::DEFAULTS['contact_phone']],
            'contact_address' => ['label' => 'Adresa', 'type' => 'text', 'default' => Settings::DEFAULTS['contact_address']],
            'contact_hours' => ['label' => 'Radno vreme podrške', 'type' => 'textarea', 'help' => 'Red po red, npr. „Radnim danima 9–17 h”.', 'default' => ''],
        ];
    }

    /** Google tag (production only, after cookie consent) and the Search Console meta tag. */
    public static function google(): array
    {
        return [
            'google_tag_id' => ['label' => 'Google tag ID', 'type' => 'text', 'help' => 'Npr. GT-XXXXXXX. Prazno = podrazumevani tag sajta.', 'default' => Settings::DEFAULTS['google_tag_id']],
            'ga4_measurement_id' => ['label' => 'GA4 Measurement ID', 'type' => 'text', 'help' => 'Npr. G-XXXXXXXXXX.', 'default' => Settings::DEFAULTS['ga4_measurement_id']],
            'google_site_verification' => ['label' => 'Search Console potvrda (meta tag)', 'type' => 'text', 'help' => 'Samo vrednost atributa content iz <meta name="google-site-verification">.', 'default' => Settings::DEFAULTS['google_site_verification']],
        ];
    }

    /** Home page blocks (HomeSettings, keys home.*). */
    public static function home(): array
    {
        $labels = [
            'home.h1' => 'Naslov (H1)', 'home.intro_text' => 'Uvodni tekst', 'home.intro_label' => 'Oznaka ispred čestih pretraga',
            'home.intro_terms' => 'Česte pretrage (zarezom odvojene)', 'home.intro_terms_count' => 'Broj čestih pretraga',
            'home.main_categories' => 'Glavne kategorije (do 5 slugova, zarezom)',
            'home.popular_categories' => 'Popularno (slugovi kategorija ili grupa, zarezom, do 12)',
            'home.categories_title' => 'Popularno: naslov', 'home.categories_count' => 'Kategorije: broj',
            'home.featured_title' => 'Istaknuti: naslov', 'home.featured_count' => 'Istaknuti: broj',
            'home.featured_link_text' => 'Istaknuti: tekst linka', 'home.featured_link_url' => 'Istaknuti: adresa linka',
            'home.premium_title' => 'Premium: naslov', 'home.premium_count' => 'Premium: broj',
            'home.latest_title' => 'Najnoviji: naslov', 'home.latest_count' => 'Najnoviji: broj', 'home.latest_mobile_count' => 'Najnoviji: broj na telefonu',
            'home.tabs_title' => 'Kartice: naslov', 'home.tabs_categories' => 'Kartice: kategorije (slugovi, zarezom)', 'home.tabs_count' => 'Kartice: broj oglasa',
            'home.firms_title' => 'Firme: naslov', 'home.firms_text' => 'Firme: tekst', 'home.firms_cta_title' => 'Firme: naslov poziva',
            'home.firms_cta_text' => 'Firme: tekst poziva', 'home.firms_button' => 'Firme: dugme', 'home.firms_url' => 'Firme: adresa dugmeta',
            'home.firms_min' => 'Firme: prikaži od ovoliko firmi', 'home.firms_max' => 'Firme: najviše',
            'home.about_title' => 'O sajtu: naslov', 'home.about' => 'O sajtu: tekst (prazno = podrazumevani tekst)',
        ];
        $out = [];
        foreach (HomeSettings::DEFAULTS as $key => $default) {
            $out[$key] = [
                'label' => $labels[$key] ?? $key,
                'type' => match (true) {
                    is_int($default) => 'integer',
                    $key === 'home.about' => 'markdown',
                    mb_strlen((string) $default) > 90 => 'textarea',
                    default => 'text',
                },
                'default' => $default,
            ];
        }

        return $out;
    }

    /** Payments module switches. */
    public static function payments(): array
    {
        return [
            PaymentMode::TEST_MODE => ['label' => 'Test režim plaćanja', 'type' => 'boolean', 'help' => 'Dok je uključen, narudžbine nisu prave uplate: računi su probni, a na strani Narudžbine piše „TEST REŽIM”.', 'default' => (bool) config('payments.test_mode', true)],
            PaymentMode::PRIVATE_PAYMENTS => ['label' => 'Plaćanje za privatna lica', 'type' => 'boolean', 'help' => 'Uključiti tek kad fiskalna kasa radi. Do tada plaćaju samo firme i preduzetnici, a privatna lica vide cene sa oznakom „Uskoro”.', 'default' => (bool) config('payments.private_payments_enabled', false)],
            PaymentMode::MIN_ORDER => ['label' => 'Najmanja porudžbina (RSD)', 'type' => 'integer', 'help' => 'Jeftinije usluge (npr. jedno podizanje) ne mogu da se naruče same, već kao paket ili iz uključenih podizanja.', 'default' => (int) config('payments.min_order_rsd', 149)],
        ];
    }

    /** Fiscal register: who issues receipts and the V-PFR address (keys and certificates stay in .env). */
    public static function fiscal(): array
    {
        return [
            'fiscal.driver' => ['label' => 'Izdavanje fiskalnih računa', 'type' => 'select', 'options' => FiscalDriver::OPTIONS, 'help' => 'Automatski tek kad su FISCAL_VPFR_* podaci upisani na serveru i `php artisan fiskal:provera` prođe.', 'default' => (string) config('fiscal.driver', FiscalDriver::MANUAL)],
            'fiscal.vpfr_url' => ['label' => 'Adresa V-PFR-a', 'type' => 'text', 'help' => 'Bez /api/v3 na kraju. Prazno = FISCAL_VPFR_URL iz .env.', 'default' => ''],
            'fiscal.tax_label' => ['label' => 'Poreska oznaka na računu', 'type' => 'text', 'help' => 'Oznaka za obveznika van sistema PDV-a; proveriti sa knjigovođom (podrazumevano „А”, ćirilica).', 'default' => (string) config('fiscal.vpfr.tax_label', 'А')],
            'fiscal.cashier' => ['label' => 'Kasir na računu', 'type' => 'text', 'default' => (string) config('fiscal.vpfr.cashier', 'loglasi.rs')],
        ];
    }

    /** Bank transfer data (price list, order page, IPS QR code) and the legal pages linked from the order step. */
    public static function payment(): array
    {
        $out = [];
        foreach (Cenovnik::PAYMENT_KEYS as $key => $label) {
            $out[$key] = ['label' => $label, 'type' => 'text', 'default' => ''];
        }
        $out['payment_account']['help'] = 'U obliku 160-0000000000000-00. Dok primalac i račun nisu uneti, cenovnik kaže da se podaci šalju na zahtev.';
        $out['payment_model']['help'] = 'Npr. 97, ili prazno.';
        $out['payment_reference']['help'] = 'Tekst za poziv na broj, npr. „broj narudžbine”.';

        return $out + [
            'payment_pib' => ['label' => 'PIB primaoca', 'type' => 'text', 'default' => ''],
            'payment_mb' => ['label' => 'Matični broj primaoca', 'type' => 'text', 'default' => ''],
            'payment_code' => ['label' => 'Šifra plaćanja', 'type' => 'text', 'help' => 'Tri cifre; prazno = 221.', 'default' => ''],
            'payment_vat_note' => ['label' => 'Napomena o PDV-u', 'type' => 'text', 'help' => 'Prikazuje se ispod cena. Prazno dok poreski status nije potvrđen.', 'default' => ''],
            'payment_page_terms' => ['label' => 'Strana: uslovi kupovine', 'type' => 'text', 'help' => 'Putanja, npr. /uslovi-placanja/.', 'default' => PaymentDetails::LEGAL_PAGES['terms']],
            'payment_page_withdrawal' => ['label' => 'Strana: odustanak od ugovora', 'type' => 'text', 'default' => PaymentDetails::LEGAL_PAGES['withdrawal']],
            'payment_page_complaints' => ['label' => 'Strana: reklamacije', 'type' => 'text', 'default' => PaymentDetails::LEGAL_PAGES['complaints']],
        ];
    }

    /**
     * page_text.* keys with defaults from resources/stranice/tekstovi.php, grouped by page.
     *
     * @return array<string, array<string, array{label: string, type: string, default: mixed, item_keys?: list<string>}>>
     */
    public static function pageTexts(): array
    {
        $groups = [];

        foreach (PageText::defaults() as $key => $default) {
            $page = explode('.', $key, 2)[0];
            $type = match (true) {
                is_array($default) && $default !== [] && is_array($default[0] ?? null) => 'items',
                is_array($default) => 'list',
                is_string($default) && (mb_strlen($default) > 400 || str_contains($default, "\n")) => 'markdown',
                is_string($default) && mb_strlen($default) > 90 => 'textarea',
                default => 'text',
            };

            $field = [
                'label' => self::labelFor($key),
                'type' => $type,
                'default' => $default,
            ];
            if ($type === 'items') {
                $field['item_keys'] = array_keys($default[0]);
            }

            $groups[$page][PageText::PREFIX.$key] = $field;
        }

        return $groups;
    }

    /** @return array<string, string> */
    public static function pageLabels(): array
    {
        return [
            'pocetna' => 'Početna',
            'seo' => 'SEO naslovi i opisi',
            'oglas' => 'Strana oglasa',
            'cenovnik' => 'Cenovnik',
            'za_firme' => 'Za firme',
            'kontakt' => 'Kontakt',
            'o_nama' => 'O nama',
            'faq' => 'Česta pitanja',
            'uslovi' => 'Uslovi korišćenja',
            'pravila' => 'Pravila oglašavanja',
            'privatnost' => 'Privatnost',
            'placanje' => 'Uslovi kupovine i plaćanja',
            'odustanak' => 'Odustanak od ugovora',
            'reklamacije' => 'Reklamacije',
            'greska' => 'Strane grešaka',
            'brisanje' => 'Brisanje naloga',
        ];
    }

    /** @return array<string, array{label: string, type: string, default: mixed}> every field on the page */
    public static function all(): array
    {
        $all = self::general() + self::contact() + self::google() + self::home() + self::payments() + self::fiscal() + self::payment();
        foreach (self::pageTexts() as $fields) {
            $all += $fields;
        }

        return $all;
    }

    /** Field name in the form (dots would nest the state). */
    public static function stateKey(string $key): string
    {
        return str_replace('.', '__', $key);
    }

    /** @return array<string, mixed> form state */
    public static function load(): array
    {
        $state = [];
        foreach (self::all() as $key => $field) {
            $value = Settings::get($key, null);
            $state[self::stateKey($key)] = ($value === null || $value === '' || $value === []) ? $field['default'] : $value;
        }

        return $state;
    }

    /**
     * Saves the form state. Returns the changed keys as [old, new].
     *
     * @param  array<string, mixed>  $state
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function save(array $state): array
    {
        $changes = [];
        $stored = Setting::query()->pluck('value', 'key')->all();

        foreach (self::all() as $key => $field) {
            $name = self::stateKey($key);
            if (! array_key_exists($name, $state)) {
                continue;
            }

            $value = self::clean($state[$name], $field);
            $old = $stored[$key] ?? null;
            // Switches are always stored: a missing key must not read as "off" elsewhere.
            $isDefault = ! is_bool($field['default'])
                && ($value === null || $value === '' || $value === [] || $value == $field['default']);

            if ($isDefault) {
                if (array_key_exists($key, $stored)) {
                    Setting::query()->whereKey($key)->delete();
                    $changes[$key] = [self::short($old), null];
                }

                continue;
            }

            if ($old !== $value) {
                Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
                $changes[$key] = [self::short($old), self::short($value)];
            }
        }

        Settings::flush();
        Cenovnik::forgetFounding();

        if ($changes !== []) {
            AuditLog::record('settings.update', null, $changes);
        }

        return $changes;
    }

    private static function clean(mixed $value, array $field): mixed
    {
        return match ($field['type']) {
            'integer' => $value === null || $value === '' ? null : (int) $value,
            'decimal' => $value === null || $value === '' ? null : round((float) $value, 4),
            'boolean' => (bool) $value,
            'list' => array_values(array_filter(array_map(fn ($v) => trim((string) (is_array($v) ? ($v['value'] ?? '') : $v)), (array) $value), 'strlen')),
            'items' => array_values(array_map(
                fn ($row) => collect($field['item_keys'])->mapWithKeys(fn ($k) => [$k => ($row[$k] ?? null) === '' ? null : ($row[$k] ?? null)])->all(),
                array_filter((array) $value, 'is_array'),
            )),
            default => is_string($value) ? str_replace("\r\n", "\n", trim($value)) : $value,
        };
    }

    private static function short(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return is_string($value) && mb_strlen($value) > 300 ? mb_substr($value, 0, 300).'…' : $value;
    }

    /** "cenovnik.uplata.koraci" → "Uplata › koraci". */
    private static function labelFor(string $key): string
    {
        $parts = array_slice(explode('.', $key), 1);
        $words = [
            'naslov' => 'naslov', 'podnaslov' => 'podnaslov', 'uvod' => 'uvod', 'tekst' => 'tekst',
            'pitanja' => 'pitanja i odgovori', 'koraci' => 'koraci', 'stavke' => 'stavke', 'prednosti' => 'prednosti',
            'cta' => 'poziv na akciju', 'opis' => 'opis', 'uplata' => 'uplata', 'besplatno' => 'besplatan oglas',
            'naziv' => 'naziv', 'nema' => 'kad podaci za uplatu nisu uneti', 'forma' => 'forma', 'poslato' => 'poruka posle slanja',
            'teme' => 'teme poruka', 'poredjenje' => 'poređenje', 'oznaka' => 'oznaka', 'naslov_popunjeno' => 'naslov kad su mesta popunjena',
            'bump' => 'podizanje', 'featured_7' => 'istaknuto 7 dana', 'featured_30' => 'istaknuto 30 dana', 'biznis_12m' => 'Biznis paket',
        ];
        $label = implode(' › ', array_map(fn ($p) => $words[$p] ?? str_replace('_', ' ', $p), $parts));

        return mb_strtoupper(mb_substr($label, 0, 1)).mb_substr($label, 1);
    }
}
