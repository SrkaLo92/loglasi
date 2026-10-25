<?php

use App\Services\Payments\ManualReceiptIssuer;
use App\Services\Payments\Vpfr\VpfrReceiptIssuer;

// Fiscal receipts (Zakon o fiskalizaciji). Private buyers get a fiscal receipt "Promet prodaja" with the
// payment "prenos na račun"; firms get an invoice (outside the VAT system, no SEF). See docs/modules/payments.md.
return [
    // Who issues receipts outside test mode (admin setting fiscal.driver overrides it):
    // manual = the admin issues them in the fiscal app and types the number in,
    // vpfr   = automatically through the V-PFR (virtual fiscal processor) API of the fiscal service.
    'driver' => env('FISCAL_DRIVER', 'manual'),

    'drivers' => [
        'manual' => ManualReceiptIssuer::class,
        'vpfr' => VpfrReceiptIssuer::class,
    ],

    'vpfr' => [
        // Base URL of the V-PFR (or of the fiscal service's API that speaks the same protocol), without /api/v3.
        'url' => env('FISCAL_VPFR_URL', ''),
        // PAC from the security element (V-PFR), and the client certificate (.pfx/.pem) with its password.
        'pac' => env('FISCAL_VPFR_PAC', ''),
        'cert' => env('FISCAL_VPFR_CERT', ''),
        'cert_password' => env('FISCAL_VPFR_CERT_PASSWORD', ''),
        // Some fiscal services put the V-PFR behind their own API key instead of a certificate.
        'token' => env('FISCAL_VPFR_TOKEN', ''),
        'timeout' => 20,
        // Tax label for a seller outside the VAT system (check with the accountant; admin setting fiscal.tax_label).
        'tax_label' => 'А',
        // Name of the cashier on the receipt.
        'cashier' => 'loglasi.rs',
    ],

    // Invoices for firms: "{number}/{year}", numbered per calendar year.
    'invoice_auto_number' => true,
];
