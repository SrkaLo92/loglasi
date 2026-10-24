<?php

namespace App\Support\Payments;

use App\Support\Settings;

/**
 * Switches for the payment flow, editable in the admin (settings) with defaults in config/payments.php:
 * - payments.test_mode: the whole flow runs with fake receipts and TEST markings
 * - payments.private_payments_enabled: real (non-test) orders from private persons
 */
final class PaymentMode
{
    public const TEST_MODE = 'payments.test_mode';

    public const PRIVATE_PAYMENTS = 'payments.private_payments_enabled';

    public static function testMode(): bool
    {
        return (bool) Settings::get(self::TEST_MODE, (bool) config('payments.test_mode', true));
    }

    /** Private persons can order in test mode, and for real only once the fiscal register works. */
    public static function privatePaymentsEnabled(): bool
    {
        return self::testMode() || (bool) Settings::get(self::PRIVATE_PAYMENTS, (bool) config('payments.private_payments_enabled', false));
    }
}
