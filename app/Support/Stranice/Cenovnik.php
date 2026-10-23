<?php

namespace App\Support\Stranice;

use App\Models\Firm;
use App\Models\Product;
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
        'cena_biznis' => Product::BIZNIS_12M,
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
        return max(0, (int) Settings::get('private_monthly_limit'));
    }

    /** „do 10 novih oglasa” */
    public static function freeListingsText(): string
    {
        $n = self::freeListings();

        return 'do '.$n.' '.self::plural($n, 'novog oglasa', 'nova oglasa', 'novih oglasa');
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

    /** „Prvih 30 firmi dobija Biznis paket besplatno prvu godinu, a posle toga 6.000 RSD godišnje.” */
    public static function offerText(): string
    {
        $biznis = self::product(Product::BIZNIS_12M);
        $price = $biznis ? self::price($biznis->price_rsd) : null;

        if (! self::offerOpen()) {
            return $price ? 'Biznis paket košta '.$price.' godišnje.' : '';
        }

        $free = self::foundingTotal();
        $lead = sprintf(self::plural($free, 'Prva %d firma dobija', 'Prve %d firme dobijaju', 'Prvih %d firmi dobija'), $free);

        return $price
            ? $lead.' Biznis paket besplatno prvu godinu, a posle toga '.$price.' godišnje.'
            : $lead.' Biznis paket besplatno prvu godinu.';
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
            'text' => $left === 0
                ? 'Sva besplatna mesta su popunjena'
                : sprintf('Još %d %s od %d', $left, self::plural($left, 'besplatno mesto', 'besplatna mesta', 'besplatnih mesta'), $total),
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
