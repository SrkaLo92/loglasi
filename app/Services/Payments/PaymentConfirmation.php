<?php

namespace App\Services\Payments;

use Carbon\CarbonInterface;

/**
 * What the admin enters when marking an order paid: the day the money arrived and, with manual
 * fiscalisation, the receipt (and advance receipt) numbers issued in the fiscal app.
 */
final class PaymentConfirmation
{
    public function __construct(
        public readonly ?CarbonInterface $paidAt = null,
        public readonly ?string $receiptNumber = null,
        public readonly ?string $advanceReceiptNumber = null,
    ) {}
}
