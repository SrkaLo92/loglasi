<?php

namespace App\Services\Payments;

use App\Models\Order;

/**
 * Issues the documents for a paid order. Private buyer: fiscal receipt "Promet prodaja" with the
 * payment method "prenos na račun", issued the day the payment is confirmed (advance receipt dated
 * the payment day first, when it is confirmed later). Firm buyer: plain invoice with the note
 * "Obveznik nije u sistemu PDV-a" (no SEF). A refund document references the original receipt.
 *
 * Implementations: FakeReceiptIssuer (test mode, "TEST-0001"), ManualReceiptIssuer (the admin
 * issues the receipt in Šljaker and types the number in). A future SljakerReceiptIssuer would call
 * Šljaker's API (V-PFR; only in their "Agencija" plan) and return the numbers. Outside test mode
 * the implementation is config('payments.receipt_issuer').
 */
interface FiscalReceiptIssuer
{
    /** Sale document for a payment that was just confirmed; null when nothing could be recorded yet. */
    public function issueSale(Order $order, PaymentConfirmation $confirmation): ?IssuedReceipt;

    /** Refund document referencing $order->receipt_number; null when nothing could be recorded yet. */
    public function issueRefund(Order $order, ?string $refundNumber): ?IssuedReceipt;
}
