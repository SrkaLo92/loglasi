<?php

namespace App\Enums;

/**
 * pending (waiting for the transfer) → paid (admin saw the money) → activated (the feature is on)
 * → refunded (money returned, refund receipt references the original). A pending order can be
 * cancelled (by the user, the admin, or automatically after the payment deadline).
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Activated = 'activated';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Čeka uplatu',
            self::Paid => 'Plaćeno',
            self::Activated => 'Aktivirano',
            self::Refunded => 'Refundirano',
            self::Cancelled => 'Otkazano',
        };
    }

    /** The money has arrived (whether or not the feature is on yet, or was refunded later). */
    public function isPaid(): bool
    {
        return in_array($this, [self::Paid, self::Activated, self::Refunded], true);
    }
}
