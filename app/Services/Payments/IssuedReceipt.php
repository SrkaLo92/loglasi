<?php

namespace App\Services\Payments;

use App\Enums\ReceiptType;
use Carbon\CarbonInterface;

/** A document recorded for an order: type, number, when it was issued, and an optional advance receipt. */
final class IssuedReceipt
{
    public function __construct(
        public readonly ReceiptType $type,
        public readonly ?string $number,
        public readonly CarbonInterface $issuedAt,
        public readonly ?string $advanceNumber = null,
        public readonly ?CarbonInterface $advanceIssuedAt = null,
    ) {}
}
