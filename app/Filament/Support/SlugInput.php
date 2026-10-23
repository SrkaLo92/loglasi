<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

/** Slug field filled from the name on create; lowercase letters, digits and dashes. */
class SlugInput
{
    public static function make(string $help): TextInput
    {
        return TextInput::make('slug')
            ->label('Adresa (slug)')
            ->helperText($help)
            ->required()
            ->maxLength(255)
            ->rule('regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
            ->unique(ignoreRecord: true);
    }

    /** For the name field: fills an empty slug while typing a new record. */
    public static function fillFrom(): \Closure
    {
        return function (?string $state, Get $get, Set $set, string $operation) {
            if ($operation === 'create' && blank($get('slug'))) {
                $set('slug', Str::slug(strtr((string) $state, ['đ' => 'dj', 'Đ' => 'Dj'])));
            }
        };
    }
}
