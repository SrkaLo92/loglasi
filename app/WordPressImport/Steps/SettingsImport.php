<?php

namespace App\WordPressImport\Steps;

use App\Models\Product;
use App\Support\Settings;
use App\WordPressImport\Text;

/** Euro rate, limits and price list from the WordPress options. */
class SettingsImport extends Step
{
    /** Price list item (loglasi_cenovnik) → product code. */
    private const PRODUCTS = [
        'podizanje' => Product::BUMP,
        'istaknuto-7' => Product::FEATURED_7,
        'istaknuto-30' => Product::FEATURED_30,
        'biznis' => Product::BIZNIS_12M,
    ];

    public function run(): void
    {
        $options = $this->wp->options([
            'loglasi_kurs_eur', 'loglasi_cenovnik', 'loglasi_pocetna_besplatni_oglasi',
            'loglasi_pocetna_biznis_besplatno', 'loglasi_pocetna_biznis_cena',
        ]);

        $this->setting($options, 'loglasi_kurs_eur', 'eur_rate', function ($raw) {
            $rate = Text::number($raw);

            return $rate !== null && $rate >= 1 && $rate <= 10000 ? $rate : null;
        });
        // loglasi_pocetna_besplatni_oglasi (new listings per month) is not imported: the rule is now active listings.
        $this->setting($options, 'loglasi_pocetna_biznis_besplatno', 'founding_firms_total', fn ($raw) => is_numeric($raw) ? max(0, (int) $raw) : null);

        $this->products($options);
        Settings::flush();
    }

    /** @param  array<string, string>  $options */
    private function setting(array $options, string $option, string $key, callable $parse): void
    {
        if (! array_key_exists($option, $options)) {
            return;
        }
        $this->report->wp('settings');

        $value = $parse($options[$option]);
        if ($value === null) {
            $this->report->error('settings', "{$option} = „{$options[$option]}” nije ispravna vrednost");

            return;
        }

        Settings::set($key, $value);
        $this->report->imported('settings');
        $this->report->note('settings', "{$key} = {$value} (iz {$option})");
    }

    /** @param  array<string, string>  $options */
    private function products(array $options): void
    {
        $items = Text::unserialize($options['loglasi_cenovnik'] ?? '');
        if (! is_array($items) || ! $items) {
            // Without a saved price list the ProductSeeder prices apply; the Biznis price can be separate on the home page.
            if (isset($options['loglasi_pocetna_biznis_cena']) && is_numeric($options['loglasi_pocetna_biznis_cena'])) {
                $items = [['id' => 'biznis', 'cena' => (int) $options['loglasi_pocetna_biznis_cena']]];
            } else {
                return;
            }
        }

        foreach ($items as $item) {
            if (! is_array($item) || ! isset($item['id'])) {
                continue;
            }
            $this->report->wp('settings');
            $code = self::PRODUCTS[(string) $item['id']] ?? null;
            if ($code === null) {
                $this->report->skipped('settings', "stavka cenovnika „{$item['id']}” nema proizvod u novoj šemi");

                continue;
            }

            $product = Product::query()->where('code', $code)->first();
            $values = ['price_rsd' => max(0, (int) ($item['cena'] ?? 0))];
            if (array_key_exists('aktivno', $item)) {
                $values['is_active'] = (bool) $item['aktivno'];
            }

            if ($product === null) {
                Product::query()->create($values + ['code' => $code, 'name' => (string) ($item['naziv'] ?? $code), 'description' => $item['opis'] ?? null]);
            } else {
                $product->update($values);
            }

            $this->report->imported('settings');
            $this->report->note('settings', "proizvod {$code}: {$values['price_rsd']} din".(isset($values['is_active']) && ! $values['is_active'] ? ' (neaktivan)' : ''));
        }
    }
}
