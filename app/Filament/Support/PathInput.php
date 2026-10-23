<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;

/** Site path without the domain ("/stari-slug/"); a pasted full URL is cut to its path and query. */
class PathInput
{
    public static function make(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->required()
            ->maxLength(255)
            ->dehydrateStateUsing(fn (?string $state) => static::normalize($state))
            ->rule('regex:~^(/|https?://)~i');
    }

    public static function normalize(?string $value): string
    {
        $value = trim((string) $value);
        if (preg_match('~^https?://~i', $value)) {
            $parts = parse_url($value) ?: [];
            $value = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return '/'.ltrim($value, '/');
    }
}
