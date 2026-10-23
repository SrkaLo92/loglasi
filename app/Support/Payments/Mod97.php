<?php

namespace App\Support\Payments;

use InvalidArgumentException;

/**
 * ISO 7064 MOD 97-10 on digit strings, as used in Serbia for payment model 97
 * ("poziv na broj") and for bank account control digits.
 */
final class Mod97
{
    /** Remainder of a (possibly very long) digit string divided by 97. */
    public static function remainder(string $digits): int
    {
        if ($digits === '' || ! ctype_digit($digits)) {
            throw new InvalidArgumentException('Only digits are allowed.');
        }

        $remainder = 0;
        foreach (str_split($digits, 7) as $chunk) {
            $remainder = (int) (($remainder.$chunk) % 97);
        }

        return $remainder;
    }

    /** Two control digits for $digits: 98 - (digits * 100 mod 97). */
    public static function controlDigits(string $digits): string
    {
        return str_pad((string) (98 - self::remainder($digits.'00')), 2, '0', STR_PAD_LEFT);
    }

    /** True when the last two digits of $number are the control digits of the rest. */
    public static function endsWithValidControl(string $number): bool
    {
        if (strlen($number) < 3 || ! ctype_digit($number)) {
            return false;
        }

        return self::controlDigits(substr($number, 0, -2)) === substr($number, -2);
    }
}
