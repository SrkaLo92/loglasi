<?php

namespace App\Enums;

enum Currency: string
{
    case EUR = 'EUR';
    case RSD = 'RSD';

    public function label(): string
    {
        return match ($this) {
            self::EUR => '€',
            self::RSD => 'din',
        };
    }
}
