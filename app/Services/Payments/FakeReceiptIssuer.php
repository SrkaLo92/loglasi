<?php

namespace App\Services\Payments;

use App\Enums\BuyerType;
use App\Enums\ReceiptType;
use App\Models\Order;
use Illuminate\Support\Carbon;

/** Test mode: "issues" documents with fake numbers (TEST-0042, TEST-A-0042, TEST-R-0042). Nothing is fiscalised. */
class FakeReceiptIssuer implements FiscalReceiptIssuer
{
    public function issueSale(Order $order, PaymentConfirmation $confirmation): ?IssuedReceipt
    {
        $now = Carbon::now();
        $id = sprintf('%04d', $order->getKey());

        if ($order->buyer_type === BuyerType::Firm) {
            return new IssuedReceipt(ReceiptType::Invoice, 'TEST-'.$id, $now);
        }

        $paidAt = $confirmation->paidAt ? Carbon::instance($confirmation->paidAt) : $now;
        $late = $paidAt->copy()->startOfDay()->lt($now->copy()->startOfDay());

        return new IssuedReceipt(
            type: ReceiptType::FiscalSale,
            number: 'TEST-'.$id,
            issuedAt: $now,
            advanceNumber: $late ? 'TEST-A-'.$id : null,
            advanceIssuedAt: $late ? $paidAt : null,
        );
    }

    public function issueRefund(Order $order, ?string $refundNumber): ?IssuedReceipt
    {
        return new IssuedReceipt(
            $order->buyer_type === BuyerType::Firm ? ReceiptType::InvoiceCredit : ReceiptType::FiscalRefund,
            'TEST-R-'.sprintf('%04d', $order->getKey()),
            Carbon::now(),
        );
    }
}
