<?php

namespace App\Support\Payments;

use App\Models\User;
use App\Support\Settings;

/**
 * Switches for the payment flow, editable in the admin (settings) with defaults in config/payments.php:
 * - payments.test_mode: the whole flow runs with fake receipts and TEST markings
 * - payments.private_payments_enabled: orders from private persons (off until the fiscal register works)
 * - payments.min_order_rsd: smallest order
 */
final class PaymentMode
{
    public const TEST_MODE = 'payments.test_mode';

    public const PRIVATE_PAYMENTS = 'payments.private_payments_enabled';

    public static function testMode(): bool
    {
        return (bool) Settings::get(self::TEST_MODE, (bool) config('payments.test_mode', true));
    }

    public const MIN_ORDER = 'payments.min_order_rsd';

    /**
     * Private persons pay only once the fiscal register works (admin switch); until then only firms and
     * entrepreneurs pay. Test mode does not change this, so the site shows what launch will show.
     */
    public static function privatePaymentsEnabled(): bool
    {
        return (bool) Settings::get(self::PRIVATE_PAYMENTS, (bool) config('payments.private_payments_enabled', false));
    }

    /** Whether this viewer can order now: firm accounts always, others once private payments are on. */
    public static function canOrder(?User $user): bool
    {
        return self::privatePaymentsEnabled() || ($user !== null && $user->isFirm());
    }

    /** Smallest order in RSD (price list: 149). */
    public static function minOrder(): int
    {
        return max(0, (int) Settings::get(self::MIN_ORDER, (int) config('payments.min_order_rsd', 149)));
    }
}
