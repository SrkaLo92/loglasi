<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Otvorena',
            self::Resolved => 'Rešena',
            self::Dismissed => 'Odbačena',
        };
    }
}
