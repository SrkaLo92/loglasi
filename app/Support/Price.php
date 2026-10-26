<?php

namespace App\Support;

use App\Enums\Currency;

/** Price in the listing's currency (EUR or RSD) and the conversion to euros for sorting and filtering. */
class Price
{
    /** How many dinars 1 € is worth. */
    public static function rate(): float
    {
        $rate = (float) Settings::get('eur_rate');

        return $rate > 0 ? $rate : Settings::DEFAULTS['eur_rate'];
    }

    public static function toEur(float $amount, Currency $currency): float
    {
        return round($currency === Currency::RSD ? $amount / static::rate() : $amount, 2);
    }

    public static function fromEur(float $eur, Currency $currency): float
    {
        return round($currency === Currency::RSD ? $eur * static::rate() : $eur, 2);
    }

    /** „8.900 €”, „12.500 din”, „1.250,50 €”, „Dogovor”. */
    public static function label(?float $amount, Currency $currency): string
    {
        if ($amount === null) {
            return 'Dogovor';
        }

        $decimals = floor($amount) === $amount ? 0 : 2;

        return number_format($amount, $decimals, ',', '.').' '.$currency->label();
    }
}
