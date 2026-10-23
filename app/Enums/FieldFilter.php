<?php

namespace App\Enums;

enum FieldFilter: string
{
    case None = 'none';
    case Select = 'select';
    case Multi = 'multi';
    case Range = 'range';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Bez filtera',
            self::Select => 'Jedan izbor',
            self::Multi => 'Više izbora',
            self::Range => 'Od-do',
        };
    }
}
