<?php

namespace App\Enums;

/** Who pays an order: a private person gets a fiscal receipt, a firm gets an invoice. */
enum BuyerType: string
{
    case Private = 'private';
    case Firm = 'firm';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Fizičko lice',
            self::Firm => 'Firma ili preduzetnik',
        };
    }
}
