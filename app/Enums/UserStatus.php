<?php

namespace App\Enums;

enum UserStatus: string
{
    case Active = 'active';
    case Blocked = 'blocked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktivan',
            self::Blocked => 'Blokiran',
        };
    }
}
