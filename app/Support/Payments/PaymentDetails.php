<?php

namespace App\Support\Payments;

use App\Models\Order;
use App\Support\Settings;

/**
 * Bank transfer instructions for one order. Recipient and account come from the same admin
 * settings the price list page shows (payment_recipient, payment_account; never from code).
 * Purpose: the service name (+ ", oglas ID"), "TEST " in front for a test order. Model and
 * reference are always the order's own (model 97 + control digits + order ID).
 */
final class PaymentDetails
{
    private function __construct(
        public readonly string $recipient,
        public readonly ?string $account,
        public readonly string $accountDisplay,
        public readonly string $purpose,
        public readonly int $amount,
        public readonly string $model,
        public readonly string $reference,
    ) {}

    public static function forOrder(Order $order): self
    {
        $account = trim((string) Settings::get('payment_account', ''));
        $normalized = BankAccount::normalize($account);
        $order->loadMissing('product:id,code,name');
        $purpose = trim((string) $order->product?->name);
        if ($purpose === '') {
            $purpose = trim((string) Settings::get('payment_purpose', '')) ?: 'Uplata za oglašavanje';
        }
        if ($order->listing_id !== null) {
            $purpose .= ', oglas '.$order->listing_id;
        }
        if ($order->is_test) {
            $purpose = 'TEST '.$purpose;
        }

        return new self(
            recipient: trim((string) Settings::get('payment_recipient', '')),
            account: $normalized,
            accountDisplay: $account !== '' ? $account : '',
            purpose: $purpose,
            amount: (int) $order->amount_rsd,
            model: PaymentReference::model(),
            reference: (string) $order->reference,
        );
    }

    /** Recipient and account are entered by the admin; nothing is ever made up. */
    public function ready(): bool
    {
        return $this->recipient !== '' && $this->accountDisplay !== '';
    }

    /** Payment code (SF): admin setting payment_code, default 221. */
    public static function paymentCode(): string
    {
        $code = preg_replace('/\D/', '', (string) Settings::get('payment_code', ''));

        return strlen($code) === 3 && in_array($code[0], ['1', '2'], true) ? $code : (string) config('payments.ips_payment_code', '221');
    }

    /** VAT note (admin setting payment_vat_note): empty until the tax status is confirmed, then shown under prices. */
    public static function vatNote(): string
    {
        return trim((string) Settings::get('payment_vat_note', ''));
    }

    /**
     * Legal page links for the confirm step and e-mails: admin settings payment_page_terms,
     * payment_page_withdrawal, payment_page_complaints (paths). Empty = no link is shown.
     */
    public static function legalLink(string $page): ?string
    {
        $path = trim((string) Settings::get('payment_page_'.$page, ''));

        return $path === '' ? null : (preg_match('#^https?://#i', $path) ? $path : url_slash($path));
    }

    /** "6.000,00 RSD" */
    public function amountLabel(): string
    {
        return number_format($this->amount, 2, ',', '.')."\u{00A0}RSD";
    }

    /** NBS IPS QR payload, or null when the account is missing or not a valid account number. */
    public function ipsPayload(): ?string
    {
        if (! $this->ready() || $this->account === null) {
            return null;
        }

        return IpsQr::payload(
            account: $this->account,
            recipient: $this->recipient,
            amount: $this->amount,
            purpose: $this->purpose,
            model: $this->model,
            reference: $this->reference,
        );
    }
}
