<?php

namespace App\Enums;

enum Role: string
{
    case User = 'user';
    case Moderator = 'moderator';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::User => 'Korisnik',
            self::Moderator => 'Moderator',
            self::Admin => 'Administrator',
        };
    }
}
