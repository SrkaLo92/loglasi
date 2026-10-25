<?php

namespace App\Support\Stranice;

use App\Models\Firm;
use App\Models\Product;
use App\Services\Payments\ProductCatalog;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Podaci za Cenovnik i Za firme: cene iz tabele products (menjaju se u adminu),
 * limiti iz podešavanja, broj zauzetih besplatnih mesta za firme.
 */
class Cenovnik
{
    /** Zamena u tekstu => šifra proizvoda. */
    public const PLACEHOLDERS = [
        'cena_podizanje' => Product::BUMP,
        'cena_istaknuto_7' => Product::FEATURED_7,
        'cena_istaknuto_30' => Product::FEATURED_30,
        'cena_biznis' => Product::BIZNIS_12M, // retired; kept so old admin texts do not break
        'cena_paket_podizanja' => 'bump_10',
        'cena_prioritet' => 'priority_3',
        'cena_top' => 'top_7',
        'cena_premium_xl' => 'premium_xl_7',
        'cena_start' => 'business_start_monthly',
        'cena_pro' => 'business_pro_monthly',
        'cena_max' => 'business_max_monthly',
        'cena_brza_prodaja' => 'quick_sale_7',
        'cena_plus_prodavac' => 'plus_seller_30',
        'cena_vizitka' => 'business_vizitka_monthly',
        'cena_plus_3' => 'listing_slots_3',
        'cena_plus_10' => 'listing_slots_10',
    ];

    /** Podešavanja za uplatu i kontakt (prazno dok ih admin ne unese; račun se nigde ne izmišlja). */
    public const PAYMENT_KEYS = [
        'payment_recipient' => 'Primalac',
        'payment_account' => 'Račun primaoca',
        'payment_model' => 'Model',
        'payment_reference' => 'Poziv na broj',
        'payment_purpose' => 'Svrha uplate',
    ];

    private const FOUNDING_CACHE = 'stranice.firme_besplatno';

    /** @return Collection<int, Product> Aktivni proizvodi, redosled kao u adminu. */
    public static function products(): Collection
    {
        return once(fn () => Product::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get());
    }

    /** Codes of the /cenovnik/ sections, in order (shown when the product exists). */
    public const SLOTS = ['listing_slots_3', 'listing_slots_10'];

    public const PLUS_SELLER = ['plus_seller_30', 'plus_seller_90', 'plus_seller_365'];

    public const PROMOTIONS = ['bump', 'bump_10', 'featured_7', 'quick_sale_7', 'priority_3', 'top_7', 'premium_xl_7', 'premium_xl_home_7'];

    public const PACKAGES = ['business_vizitka', 'business_start', 'business_pro', 'business_max'];

    /**
     * Products for the price list: active ones and display-only ones (listed, not sold yet), by code.
     *
     * @return Collection<string, Product>
     */
    public static function listed(): Collection
    {
        return once(fn () => Product::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('display_only', true))
            ->orderBy('sort')->orderBy('id')
            ->get()->keyBy('code'));
    }

    /** @return list<Product> listed products of the given codes, in that order */
    public static function listedOf(array $codes): array
    {
        $listed = self::listed();

        return array_values(array_filter(array_map(fn (string $code) => $listed->get($code), $codes)));
    }

    /**
     * Business packages: monthly product (required) and yearly product (optional).
     *
     * @return list<array{code: string, monthly: Product, yearly: ?Product, features: list<string>}>
     */
    public static function packages(): array
    {
        $listed = self::listed();
        $packages = [];
        foreach (self::PACKAGES as $code) {
            $monthly = $listed->get($code.'_monthly');
            if ($monthly === null) {
                continue;
            }
            $packages[] = [
                'code' => $code,
                'monthly' => $monthly,
                'yearly' => $listed->get($code.'_yearly'),
                'features' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $monthly->description) ?: []), 'strlen')),
            ];
        }

        return $packages;
    }

    /**
     * Markdown list of the services that can be ordered now (active, sellable), grouped like the
     * price list, with name, price and duration from `products`. For the {usluge_cenovnik} placeholder.
     */
    public static function servicesMarkdown(): string
    {
        $groups = ['Promocije oglasa' => [], 'Dodatni oglasi' => [], 'Paketi za firme' => []];
        foreach (self::products() as $product) {
            $type = ProductCatalog::type($product);
            if ($type === null || $product->price_rsd <= 0) {
                continue;
            }
            $group = match ($type) {
                ProductCatalog::SLOTS => 'Dodatni oglasi',
                ProductCatalog::FIRM_PACKAGE => 'Paketi za firme',
                default => 'Promocije oglasa',
            };
            $days = ProductCatalog::days($product);
            $duration = match (true) {
                $type === ProductCatalog::BUMP => 'jednokratno',
                $type === ProductCatalog::BUMP_PACK => 'podizanja koristite kad želite',
                $type === ProductCatalog::FIRM_PACKAGE => $days >= 365 ? 'godišnje' : 'mesečno',
                default => 'traje '.$days.' '.self::plural($days, 'dan', 'dana', 'dana'),
            };
            $groups[$group][] = '- **'.Prodavac::escape($product->name).':** '.self::price((int) $product->price_rsd).', '.$duration;
        }

        $out = [];
        foreach ($groups as $title => $lines) {
            if ($lines !== []) {
                $out[] = '**'.$title.'**'."\n\n".implode("\n", $lines);
            }
        }

        return $out === [] ? 'Spisak usluga i cene su na strani [Cenovnik](/cenovnik/).' : implode("\n\n", $out);
    }

    /** Duration column of the promotions table: „jednom”, „10 puta”, „7 dana”. */
    public static function promotionDuration(Product $product): string
    {
        if (preg_match('/_(\d+)$/', $product->code, $m) && $product->duration_days === null) {
            return $m[1].' puta';
        }
        $days = (int) $product->duration_days;

        return $days > 0 ? $days.' '.self::plural($days, 'dan', 'dana', 'dana') : 'jednom';
    }

    public static function product(string $code): ?Product
    {
        return self::products()->firstWhere('code', $code);
    }

    /** „6.000 RSD” (bez prelamanja), 0 je „Besplatno”. */
    public static function price(int $rsd): string
    {
        return $rsd > 0 ? number_format($rsd, 0, ',', '.')."\u{00A0}RSD" : 'Besplatno';
    }

    /** Trajanje usluge za karticu: jednokratno, 7 dana, godišnje… */
    public static function durationLabel(Product $product): string
    {
        $days = $product->duration_days;

        return match (true) {
            $days === null || $days === 0 => 'jednokratno',
            $days === 365 || $days === 366 => 'godišnje',
            $days === 30 && $product->code !== Product::FEATURED_30 => 'mesečno',
            default => $days.' '.self::plural($days, 'dan', 'dana', 'dana'),
        };
    }

    /** Opis iz baze, a ako ga nema, podrazumevani tekst za tu šifru. */
    public static function description(Product $product): string
    {
        $description = trim((string) $product->description);

        return $description !== '' ? $description : PageText::get('cenovnik.opis.'.$product->code);
    }

    /** Srpski oblik imenice uz broj: 1 mesto, 2 mesta, 5 mesta, 21 mesto. */
    public static function plural(int $n, string $one, string $few, string $many): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return $one;
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return $few;
        }

        return $many;
    }

    public static function freeListings(): int
    {
        return max(0, (int) Settings::get('private_active_limit'));
    }

    /** „do 5 aktivnih oglasa” */
    public static function freeListingsText(): string
    {
        $n = self::freeListings();

        return 'do '.$n.' '.self::plural($n, 'aktivnog oglasa', 'aktivna oglasa', 'aktivnih oglasa');
    }

    /** „mesec dana” ili „45 dana” */
    public static function durationText(): string
    {
        $days = max(1, (int) Settings::get('listing_duration_days'));

        return in_array($days, [30, 31], true) ? 'mesec dana' : $days.' '.self::plural($days, 'dan', 'dana', 'dana');
    }

    public static function foundingTotal(): int
    {
        return max(0, (int) Settings::get('founding_firms_total'));
    }

    /** Broj firmi koje su dobile besplatan paket (founding_free). Keš 5 minuta. */
    public static function foundingTaken(): int
    {
        return (int) Cache::remember(self::FOUNDING_CACHE, 300, fn () => Firm::query()->where('founding_free', true)->count());
    }

    public static function foundingLeft(): int
    {
        return max(0, self::foundingTotal() - self::foundingTaken());
    }

    public static function forgetFounding(): void
    {
        Cache::forget(self::FOUNDING_CACHE);
    }

    /** Da li ponuda „prvih 30 firmi besplatno” još važi. */
    public static function offerOpen(): bool
    {
        return self::foundingTotal() > 0 && self::foundingLeft() > 0;
    }

    /** Monthly price of the cheapest business package (Business Start), null when it is not listed. */
    public static function packagesFrom(): ?int
    {
        $start = self::listed()->get(self::PACKAGES[0].'_monthly');

        return $start && $start->price_rsd > 0 ? (int) $start->price_rsd : null;
    }

    /** „Prvih 30 firmi: 12 meseci besplatno. Posle toga paketi počinju od 1.490 RSD mesečno, bez automatske naplate.” */
    public static function offerText(): string
    {
        $from = self::packagesFrom();
        $price = $from !== null ? self::price($from) : null;

        if (! self::offerOpen()) {
            return $price ? 'Paketi za firme počinju od '.$price.' mesečno.' : '';
        }

        $free = self::foundingTotal();
        $lead = sprintf(self::plural($free, 'Prva %d firma', 'Prve %d firme', 'Prvih %d firmi'), $free).': 12 meseci besplatno.';

        return $price
            ? $lead.' Posle toga paketi počinju od '.$price.' mesečno, bez automatske naplate.'
            : $lead.' Posle toga nema automatske naplate.';
    }

    /** „Prvih 30 firmi besplatno prvu godinu” (kratko, za karticu). */
    public static function offerShort(): string
    {
        $free = self::foundingTotal();

        return sprintf(self::plural($free, 'Prva %d firma besplatno prvu godinu', 'Prve %d firme besplatno prvu godinu', 'Prvih %d firmi besplatno prvu godinu'), $free);
    }

    /**
     * Brojač besplatnih mesta za prikaz.
     *
     * @return array{text: string, note: string, percent: int}|null
     */
    public static function counter(): ?array
    {
        $total = self::foundingTotal();
        if ($total <= 0) {
            return null;
        }

        $left = self::foundingLeft();
        $taken = $total - $left;

        return [
            'text' => match (true) {
                $left === 0 => 'Sva besplatna mesta su popunjena',
                $left === $total => sprintf('Slobodno je svih %d besplatnih mesta', $total),
                default => sprintf('Slobodno još %d od %d besplatnih mesta', $left, $total),
            },
            'note' => sprintf('Zauzeto je %d od %d.', $taken, $total),
            'percent' => max(3, min(100, (int) round(100 * $taken / $total))),
        ];
    }

    /**
     * Uneti podaci za uplatu (prazni se izostavljaju).
     *
     * @return array<string, array{label: string, value: string}>
     */
    public static function paymentRows(): array
    {
        $rows = [];
        foreach (self::PAYMENT_KEYS as $key => $label) {
            $value = trim((string) Settings::get($key, ''));
            if ($value !== '') {
                $rows[$key] = ['label' => $label, 'value' => $value];
            }
        }

        return $rows;
    }

    /** Primalac i račun su obavezni da bi se podaci prikazali. */
    public static function paymentReady(): bool
    {
        $rows = self::paymentRows();

        return isset($rows['payment_recipient'], $rows['payment_account']);
    }
}
