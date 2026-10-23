<?php

namespace App\Support\Listing;

use App\Enums\Currency;
use App\Models\Listing;

/**
 * "Kreditni kalkulator (informativno)": annuity payment for the listing price.
 * The server renders the default example, public/js/listing.js recalculates on input.
 */
class LoanCalculator
{
    public const DOWN_PERCENT = 20;

    public const MONTHS = 60;

    public const RATE = 8.0;

    /** Shown only for listings worth financing (at least ~1.000 €). */
    public const MIN_EUR = 1000;

    public static function applies(Listing $listing): bool
    {
        return $listing->price !== null && (float) $listing->price_eur >= self::MIN_EUR;
    }

    /** Monthly annuity payment. */
    public static function monthly(float $principal, int $months, float $annualRate): float
    {
        if ($principal <= 0 || $months <= 0) {
            return 0.0;
        }
        $r = $annualRate / 100 / 12;
        if ($r <= 0) {
            return $principal / $months;
        }

        return $principal * $r / (1 - (1 + $r) ** -$months);
    }

    /** @return array{price: float, down: float, months: int, rate: float, monthly: float, total: float, currency: string, symbol: string} */
    public static function example(Listing $listing): array
    {
        $price = (float) $listing->price;
        $down = round($price * self::DOWN_PERCENT / 100);
        $monthly = static::monthly($price - $down, self::MONTHS, self::RATE);
        $currency = $listing->currency ?? Currency::EUR;

        return [
            'price' => $price,
            'down' => $down,
            'months' => self::MONTHS,
            'rate' => self::RATE,
            'monthly' => $monthly,
            'total' => $down + $monthly * self::MONTHS,
            'currency' => $currency->value,
            'symbol' => $currency->label(),
        ];
    }

    public static function money(float $amount, string $symbol): string
    {
        return number_format(round($amount), 0, ',', '.').' '.$symbol;
    }
}
