<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Published = 'published';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Sold = 'sold';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Nacrt',
            self::Pending => 'Čeka odobrenje',
            self::Published => 'Aktivan',
            self::Rejected => 'Odbijen',
            self::Expired => 'Istekao',
            self::Sold => 'Prodat',
        };
    }
}
