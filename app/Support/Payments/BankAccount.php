<?php

namespace App\Support\Payments;

/**
 * Serbian bank account number: 3-digit bank code, up to 13-digit account, 2 control digits.
 * "160-123456-15" and "160000000012345615" are the same account.
 */
final class BankAccount
{
    /** 18 digits, or null when the value is not a valid account number. */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{3})[\s-]+(\d{1,13})[\s-]+(\d{2})$/', $value, $m)) {
            $digits = $m[1].str_pad($m[2], 13, '0', STR_PAD_LEFT).$m[3];
        } else {
            $digits = preg_replace('/\D/', '', $value);
        }

        return strlen($digits) === 18 && Mod97::endsWithValidControl($digits) ? $digits : null;
    }

    /** "160-0000000123456-15" */
    public static function format(string $digits): string
    {
        return substr($digits, 0, 3).'-'.substr($digits, 3, 13).'-'.substr($digits, 16, 2);
    }
}
