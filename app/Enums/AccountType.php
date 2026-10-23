<?php

namespace App\Enums;

enum AccountType: string
{
    case Private = 'private';
    case Firm = 'firm';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Privatno lice',
            self::Firm => 'Firma',
        };
    }
}
