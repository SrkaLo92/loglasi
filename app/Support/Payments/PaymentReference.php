<?php

namespace App\Support\Payments;

/**
 * "Poziv na broj" for model 97: two control digits followed by the base number,
 * built from the order ID: order 42 → base "000042" → reference "69000042" (model 97).
 */
final class PaymentReference
{
    public static function model(): string
    {
        return (string) config('payments.reference_model', '97');
    }

    /** Reference (control digits + base) for a numeric base. */
    public static function fromBase(string $base): string
    {
        return Mod97::controlDigits($base).$base;
    }

    /** Reference for an order: control digits + order ID padded to payments.reference_digits. */
    public static function forOrderId(int $id): string
    {
        return self::fromBase(str_pad((string) $id, max(1, (int) config('payments.reference_digits', 6)), '0', STR_PAD_LEFT));
    }

    /** Model 97 check: moving the control digits to the end gives a number ≡ 1 (mod 97). */
    public static function isValid(string $reference): bool
    {
        if (strlen($reference) < 3 || strlen($reference) > 22 || ! ctype_digit($reference)) {
            return false;
        }

        return Mod97::remainder(substr($reference, 2).substr($reference, 0, 2)) === 1;
    }
}
