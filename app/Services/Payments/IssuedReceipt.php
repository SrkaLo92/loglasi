<?php

namespace App\Services\Payments;

use App\Enums\ReceiptType;
use Carbon\CarbonInterface;

/**
 * A document recorded for an order: type, number, when it was issued, an optional advance receipt
 * and, for a fiscal receipt from the V-PFR, the Tax Administration's check link (QR code on the receipt).
 */
final class IssuedReceipt
{
    public function __construct(
        public readonly ReceiptType $type,
        public readonly ?string $number,
        public readonly CarbonInterface $issuedAt,
        public readonly ?string $advanceNumber = null,
        public readonly ?CarbonInterface $advanceIssuedAt = null,
        public readonly ?string $verificationUrl = null,
    ) {}
}
