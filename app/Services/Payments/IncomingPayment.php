<?php

namespace App\Services\Payments;

use Carbon\CarbonInterface;

/** One incoming payment reported by the bank (later phase, needs a bank contract). */
final class IncomingPayment
{
    public function __construct(
        public readonly string $bankTransactionId,
        public readonly int $amountRsd,
        public readonly CarbonInterface $paidAt,
        public readonly ?string $model = null,
        public readonly ?string $reference = null,
        public readonly ?string $payerName = null,
        public readonly ?string $payerAccount = null,
    ) {}
}
