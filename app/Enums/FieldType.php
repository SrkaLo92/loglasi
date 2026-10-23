<?php

namespace App\Enums;

enum FieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Checkbox = 'checkbox';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Tekst',
            self::Number => 'Broj',
            self::Select => 'Izbor',
            self::Multiselect => 'Više izbora',
            self::Checkbox => 'Da/ne',
        };
    }
}
