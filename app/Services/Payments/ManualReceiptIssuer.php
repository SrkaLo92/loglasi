<?php

namespace App\Services\Payments;

use App\Enums\BuyerType;
use App\Enums\ReceiptType;
use App\Models\Order;
use Illuminate\Support\Carbon;

/** The admin issues receipts in the fiscal app and types the numbers in; this only records them. */
class ManualReceiptIssuer implements FiscalReceiptIssuer
{
    public function issueSale(Order $order, PaymentConfirmation $confirmation): ?IssuedReceipt
    {
        $number = self::clean($confirmation->receiptNumber);
        $advance = self::clean($confirmation->advanceReceiptNumber);
        if ($number === null && $advance === null) {
            return null;
        }

        $now = Carbon::now();
        if ($order->buyer_type === BuyerType::Firm) {
            return new IssuedReceipt(ReceiptType::Invoice, $number, $now);
        }

        $paidAt = $confirmation->paidAt ?? $now;

        return new IssuedReceipt(
            type: ReceiptType::FiscalSale,
            number: $number,
            issuedAt: $now,
            advanceNumber: $advance,
            advanceIssuedAt: $advance !== null ? Carbon::instance($paidAt) : null,
        );
    }

    public function issueRefund(Order $order, ?string $refundNumber): ?IssuedReceipt
    {
        $number = self::clean($refundNumber);
        if ($number === null) {
            return null;
        }

        return new IssuedReceipt(
            $order->buyer_type === BuyerType::Firm ? ReceiptType::InvoiceCredit : ReceiptType::FiscalRefund,
            $number,
            Carbon::now(),
        );
    }

    private static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_substr($value, 0, 64);
    }
}
