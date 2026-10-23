<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Čeka uplatu',
            self::Paid => 'Plaćeno',
            self::Cancelled => 'Otkazano',
        };
    }
}
