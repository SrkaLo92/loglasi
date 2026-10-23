<?php

namespace App\Support\Payments;

use App\Models\Order;
use App\Support\Settings;

/**
 * Bank transfer instructions for one order. Recipient, account and purpose come from the
 * same admin settings the price list page shows (payment_recipient, payment_account,
 * payment_purpose); model and reference are always the order's own (model 97).
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
        $purpose = trim((string) Settings::get('payment_purpose', ''));

        return new self(
            recipient: trim((string) Settings::get('payment_recipient', '')),
            account: $normalized,
            accountDisplay: $account !== '' ? $account : '',
            purpose: $purpose !== '' ? $purpose : (string) config('payments.default_purpose'),
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
