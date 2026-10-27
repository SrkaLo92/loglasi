<?php

namespace App\Support\Accounts;

/** Names shown on public profiles and listings must not carry an e-mail address or a phone number. */
class PublicName
{
    public const FALLBACK = 'Korisnik';

    private const EMAIL = '/[^\s@,;<>()]+@[^\s@,;<>()]+\.[\p{L}]{2,}/u';

    // Seven or more digits, optionally split by spaces, dots, dashes, slashes or brackets; "+381" counts too.
    private const PHONE = '/\+?\(?\d(?:[\s.\/\-()]*\d){6,}/u';

    public static function hasContact(string $name): bool
    {
        return preg_match(self::EMAIL, $name) === 1 || preg_match(self::PHONE, $name) === 1;
    }

    /** The name without e-mail addresses and phone numbers; FALLBACK when too little is left. */
    public static function clean(string $name): string
    {
        $clean = preg_replace([self::EMAIL, self::PHONE], ' ', $name) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '', " \t\n\r\0\x0B,;:-|/");

        return mb_strlen($clean) >= 2 ? $clean : self::FALLBACK;
    }
}
