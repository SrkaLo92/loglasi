<?php

namespace App\Services\Payments\Vpfr;

use App\Enums\BuyerType;
use App\Enums\ReceiptType;
use App\Models\Order;
use App\Services\Payments\FirmInvoices;
use App\Services\Payments\FiscalReceiptIssuer;
use App\Services\Payments\IssuedReceipt;
use App\Services\Payments\PaymentConfirmation;
use App\Support\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fiscal receipts issued automatically through the V-PFR. Private buyer: "Promet prodaja" with the
 * payment "prenos na račun" (WireTransfer) on the day the payment is confirmed; a refund is
 * "Promet refundacija" referencing the original receipt and carrying the buyer id the admin enters
 * (required by the Tax Administration; not stored). Firm buyer: our own invoice (FirmInvoices),
 * not a fiscal receipt.
 *
 * A number typed in by the admin is recorded as is (receipt issued by hand while the V-PFR was down).
 * When the V-PFR fails the error is logged and nothing is recorded: the order stays paid without a
 * receipt and `fiscal:issue` tries again (same RequestId, so no second receipt).
 */
class VpfrReceiptIssuer implements FiscalReceiptIssuer
{
    public function __construct(private readonly VpfrClient $client) {}

    public function issueSale(Order $order, PaymentConfirmation $confirmation): ?IssuedReceipt
    {
        if ($order->buyer_type === BuyerType::Firm) {
            return FirmInvoices::sale($confirmation->receiptNumber);
        }
        if (($typed = FirmInvoices::clean($confirmation->receiptNumber)) !== null) {
            return new IssuedReceipt(ReceiptType::FiscalSale, $typed, Carbon::now());
        }

        return $this->fiscalise($order, ReceiptType::FiscalSale, (int) $order->amount_rsd);
    }

    public function issueRefund(Order $order, ?string $refundNumber, ?string $buyerId = null): ?IssuedReceipt
    {
        if ($order->buyer_type === BuyerType::Firm) {
            return FirmInvoices::creditNote($refundNumber);
        }
        if (($typed = FirmInvoices::clean($refundNumber)) !== null) {
            return new IssuedReceipt(ReceiptType::FiscalRefund, $typed, Carbon::now());
        }
        if ($order->receipt_number === null || $order->receipt_type !== ReceiptType::FiscalSale) {
            return null; // nothing to reference: the sale was never fiscalised
        }
        if (! self::validBuyerId($buyerId)) {
            Log::warning('Refundacija nije fiskalizovana: nedostaje ID kupca', ['order' => $order->getKey()]);

            return null; // the admin issues it by hand and types the number in
        }

        return $this->fiscalise($order, ReceiptType::FiscalRefund, (int) ($order->refund_amount_rsd ?? $order->amount_rsd), $buyerId);
    }

    /** The request body for one receipt (ESIR to PFR protocol, POST /api/v3/invoices). */
    public static function payload(Order $order, ReceiptType $type, int $amount, ?string $buyerId = null): array
    {
        $refund = $type === ReceiptType::FiscalRefund;
        $item = [
            'name' => mb_substr(trim(($order->product?->name ?? 'Usluga oglašavanja').' ('.$order->reference.')'), 0, 128),
            'quantity' => 1,
            'unitPrice' => $amount,
            'labels' => [self::taxLabel()],
            'totalAmount' => $amount,
        ];

        return array_filter([
            'invoiceType' => 'Normal',
            'transactionType' => $refund ? 'Refund' : 'Sale',
            'cashier' => (string) (Settings::get('fiscal.cashier') ?: config('fiscal.vpfr.cashier', 'loglasi.rs')),
            'payment' => [['amount' => $amount, 'paymentType' => 'WireTransfer']],
            'items' => [$item],
            'buyerId' => $refund ? $buyerId : null,
            'referentDocumentNumber' => $refund ? $order->receipt_number : null,
            'referentDocumentDT' => $refund ? $order->receipt_issued_at?->toIso8601String() : null,
        ], fn ($value) => $value !== null);
    }

    /** RequestId for a document: the same order and type always gives the same id. */
    public static function requestId(Order $order, ReceiptType $type): string
    {
        return 'loglasi-'.$order->getKey().'-'.($type === ReceiptType::FiscalRefund ? 'refund' : 'sale');
    }

    public static function taxLabel(): string
    {
        return (string) (Settings::get('fiscal.tax_label') ?: config('fiscal.vpfr.tax_label', 'А'));
    }

    /** Buyer id on a fiscal receipt: two-digit type, colon, number (20:123456789, 23:..., 10:PIB). */
    public static function validBuyerId(?string $buyerId): bool
    {
        return $buyerId !== null && preg_match('/^\d{2}:[0-9A-Za-z]{1,20}$/', $buyerId) === 1;
    }

    private function fiscalise(Order $order, ReceiptType $type, int $amount, ?string $buyerId = null): ?IssuedReceipt
    {
        try {
            $response = $this->client->invoice(self::payload($order, $type, $amount, $buyerId), self::requestId($order, $type));
        } catch (Throwable $e) {
            Log::warning('Fiskalni račun nije izdat', ['order' => $order->getKey(), 'type' => $type->value, 'error' => $e->getMessage()]);

            return null;
        }

        $issuedAt = is_string($response['sdcDateTime'] ?? null) ? Carbon::parse($response['sdcDateTime']) : Carbon::now();
        $url = is_string($response['verificationUrl'] ?? null) && str_starts_with($response['verificationUrl'], 'https://')
            ? mb_substr($response['verificationUrl'], 0, 512) : null;

        return new IssuedReceipt($type, mb_substr((string) $response['invoiceNumber'], 0, 64), $issuedAt, verificationUrl: $url);
    }
}
