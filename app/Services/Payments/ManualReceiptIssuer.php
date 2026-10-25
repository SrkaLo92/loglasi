<?php

namespace App\Services\Payments;

use App\Enums\BuyerType;
use App\Enums\ReceiptType;
use App\Models\Order;
use Illuminate\Support\Carbon;

/**
 * The admin issues fiscal receipts in the fiscal app and types the numbers in; this only records them.
 * Firm invoices are numbered automatically when no number is typed in (FirmInvoices).
 */
class ManualReceiptIssuer implements FiscalReceiptIssuer
{
    public function issueSale(Order $order, PaymentConfirmation $confirmation): ?IssuedReceipt
    {
        if ($order->buyer_type === BuyerType::Firm) {
            return FirmInvoices::sale($confirmation->receiptNumber);
        }

        $number = FirmInvoices::clean($confirmation->receiptNumber);
        $advance = FirmInvoices::clean($confirmation->advanceReceiptNumber);
        if ($number === null && $advance === null) {
            return null;
        }

        $now = Carbon::now();

        $paidAt = $confirmation->paidAt ?? $now;

        return new IssuedReceipt(
            type: ReceiptType::FiscalSale,
            number: $number,
            issuedAt: $now,
            advanceNumber: $advance,
            advanceIssuedAt: $advance !== null ? Carbon::instance($paidAt) : null,
        );
    }

    public function issueRefund(Order $order, ?string $refundNumber, ?string $buyerId = null): ?IssuedReceipt
    {
        if ($order->buyer_type === BuyerType::Firm) {
            return FirmInvoices::creditNote($refundNumber);
        }

        $number = FirmInvoices::clean($refundNumber);

        return $number === null ? null : new IssuedReceipt(ReceiptType::FiscalRefund, $number, Carbon::now());
    }
}
