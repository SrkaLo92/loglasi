<?php

namespace App\Services\Payments;

use Carbon\CarbonInterface;

/**
 * LATER PHASE, not implemented: automatic confirmation of instant (IPS) payments from the bank.
 * An implementation reads the account statement or the bank's notification API; a job matches
 * each payment by model 97 + reference and amount to a pending order and calls
 * OrderFulfillment::markPaid() with PaymentConfirmation(paidAt: $payment->paidAt).
 */
interface InstantPaymentFeed
{
    /** @return iterable<IncomingPayment> payments credited to the account since $since */
    public function paymentsSince(CarbonInterface $since): iterable;
}
