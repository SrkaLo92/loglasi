<?php

namespace App\Services\Admin;

use App\Models\AuditLog;
use App\Models\Setting;
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
            'eur_rate' => ['label' => 'Kurs evra (1 € = … din)', 'type' => 'decimal', 'help' => 'Za sortiranje i filter po ceni (oglasi u dinarima se preračunavaju u evre).', 'default' => Settings::DEFAULTS['eur_rate']],
            'private_monthly_limit' => ['label' => 'Besplatnih oglasa mesečno (privatna lica)', 'type' => 'integer', 'default' => Settings::DEFAULTS['private_monthly_limit']],
            'listing_duration_days' => ['label' => 'Trajanje oglasa (dana)', 'type' => 'integer', 'default' => Settings::DEFAULTS['listing_duration_days']],
            'moderation' => ['label' => 'Odobravanje oglasa', 'type' => 'select', 'options' => self::MODERATION, 'default' => Settings::DEFAULTS['moderation']],
            'founding_firms_total' => ['label' => 'Broj firmi sa besplatnim Biznis paketom', 'type' => 'integer', 'help' => 'Brojač na strani Za firme.', 'default' => Settings::DEFAULTS['founding_firms_total']],
            'seo_place_min_listings' => ['label' => 'Najmanje oglasa za stranu „kategorija + mesto” u pretrazi', 'type' => 'integer', 'help' => 'Ispod ovog broja strana ima noindex i nije u sitemapu.', 'default' => (int) config('seo.place_min_listings', 3)],
        ];
    }

    /** @return array<string, array{label: string, type: string, help?: string, default: mixed}> */
    public static function contact(): array
    {
        return [
            'contact_email' => ['label' => 'E-pošta za kontakt', 'type' => 'email', 'help' => 'Na nju stižu poruke sa kontakt forme.', 'default' => Settings::DEFAULTS['contact_email']],
            'contact_phone' => ['label' => 'Telefon za kontakt', 'type' => 'text', 'help' => 'Prazno = telefon se ne prikazuje.', 'default' => ''],
            'contact_hours' => ['label' => 'Radno vreme podrške', 'type' => 'textarea', 'help' => 'Red po red, npr. „Radnim danima 9–17 h”.', 'default' => ''],
        ];
    }

    /** Payments module switches. */
    public static function payments(): array
    {
        return [
            'payments.test_mode' => ['label' => 'Test režim plaćanja', 'type' => 'boolean', 'help' => 'Dok je uključen, narudžbine nisu prave uplate (na strani Narudžbine piše „TEST REŽIM”).', 'default' => true],
            'payments.private_payments_enabled' => ['label' => 'Plaćanje za privatna lica', 'type' => 'boolean', 'help' => 'Kad je isključeno, plaćene usluge mogu da naruče samo firme.', 'default' => false],
        ];
    }

    /** @return array<string, array{label: string, type: string, help?: string, default: mixed}> */
    public static function payment(): array
    {
        $out = [];
        foreach (Cenovnik::PAYMENT_KEYS as $key => $label) {
            $out[$key] = ['label' => $label, 'type' => 'text', 'default' => ''];
        }
        $out['payment_account']['help'] = 'U obliku 160-0000000000000-00. Dok primalac i račun nisu uneti, cenovnik kaže da se podaci šalju na zahtev.';
        $out['payment_model']['help'] = 'Npr. 97, ili prazno.';
        $out['payment_reference']['help'] = 'Tekst za poziv na broj, npr. „broj narudžbine”.';

        return $out;
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
            'cenovnik' => 'Cenovnik',
            'za_firme' => 'Za firme',
            'kontakt' => 'Kontakt',
            'o_nama' => 'O nama',
            'faq' => 'Česta pitanja',
            'uslovi' => 'Uslovi korišćenja',
            'privatnost' => 'Privatnost',
            'greska' => 'Strane grešaka',
        ];
    }

    /** @return array<string, array{label: string, type: string, default: mixed}> every field on the page */
    public static function all(): array
    {
        $all = self::general() + self::contact() + self::payments() + self::payment();
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
