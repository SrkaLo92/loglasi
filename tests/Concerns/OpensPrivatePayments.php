<?php

namespace Tests\Concerns;

use App\Support\Payments\PaymentMode;
use App\Support\Settings;

/**
 * For tests of the order flow itself: private persons may pay and there is no smallest order,
 * so single bumps and private buyers work as before the price list of 25 Oct 2026.
 */
trait OpensPrivatePayments
{
    protected function setUpOpensPrivatePayments(): void
    {
        Settings::set(PaymentMode::PRIVATE_PAYMENTS, true);
        Settings::set(PaymentMode::MIN_ORDER, 0);
    }
}
