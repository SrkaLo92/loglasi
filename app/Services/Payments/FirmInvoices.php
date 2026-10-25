<?php

namespace App\Services\Payments;

use App\Enums\ReceiptType;
use Illuminate\Support\Carbon;

/**
 * Documents for a firm buyer, the same for every issuer: an invoice (no SEF, outside the VAT system)
 * and a credit note for a refund. A number the admin typed in wins; otherwise the next number of the
 * year when fiscal.invoice_auto_number is on, else nothing is recorded yet.
 */
final class FirmInvoices
{
    public static function sale(?string $typedNumber): ?IssuedReceipt
    {
        $number = self::clean($typedNumber) ?? (self::autoNumber() ? DocumentNumbers::next(DocumentNumbers::INVOICE) : null);

        return $number === null ? null : new IssuedReceipt(ReceiptType::Invoice, $number, Carbon::now());
    }

    public static function creditNote(?string $typedNumber): ?IssuedReceipt
    {
        $number = self::clean($typedNumber) ?? (self::autoNumber() ? DocumentNumbers::next(DocumentNumbers::CREDIT_NOTE) : null);

        return $number === null ? null : new IssuedReceipt(ReceiptType::InvoiceCredit, $number, Carbon::now());
    }

    public static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 64);
    }

    private static function autoNumber(): bool
    {
        return (bool) config('fiscal.invoice_auto_number', true);
    }
}
