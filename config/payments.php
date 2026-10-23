<?php

use App\Services\Payments\ManualReceiptIssuer;

// Paid features (bump, featured listing, Biznis package), paid by bank transfer.
// Payment details (recipient, account, purpose) are admin settings: payment_* keys.

return [
    // Pending orders older than this are cancelled by `orders:cancel-stale` (daily).
    'pending_days' => 7,

    // TEST MODE, on until the fiscal register works: the whole flow runs (order, pro-forma,
    // IPS QR with purpose "TEST ...", mark paid, activation, refund), receipts are fake
    // (TEST-0001) and e-mails are marked TEST. Admin setting "payments.test_mode" overrides it.
    'test_mode' => true,

    // Real payments by private persons need fiscal receipts: off until the register works.
    // Admin setting "payments.private_payments_enabled" overrides it. Ignored in test mode.
    'private_payments_enabled' => false,

    // A listing can be bumped at most once in this many hours.
    'bump_cooldown_hours' => 24,

    // Maximum number of unpaid orders per user (protects against spam).
    'max_pending_per_user' => 10,

    // Model for "poziv na broj": control digits (ISO 7064 MOD 97-10) + the order ID.
    'reference_model' => '97',

    // The order ID in a reference is padded with zeros to this many digits.
    'reference_digits' => 6,

    // NBS IPS QR: payment code (SF). 221 = trade in goods and services, final consumption.
    'ips_payment_code' => '221',

    // Who records receipts and invoices outside test mode (App\Services\Payments\FiscalReceiptIssuer).
    // Manual: the admin issues them in the fiscal app (Šljaker, V-PFR) and types the number in.
    // Test mode always uses FakeReceiptIssuer.
    'receipt_issuer' => ManualReceiptIssuer::class,

    // Note under every price while the seller is outside the VAT system (legal text).
    'vat_note' => 'Cena je konačna. PDV nije obračunat jer pružalac usluge nije u sistemu PDV-a.',

    // Invoice note for firm buyers (no SEF while outside the VAT system).
    'invoice_note' => 'Obveznik nije u sistemu PDV-a.',

    // Legal pages linked from the confirm step and e-mails (texts: Stranice module).
    'links' => [
        'terms' => '/uslovi-koriscenja/',
        'withdrawal' => '/pravo-na-odustanak/',
        'complaints' => '/reklamacije-i-povracaj/',
    ],

    // Fallback purpose when the payment_purpose setting is empty.
    'default_purpose' => 'Uplata za oglašavanje',
];
