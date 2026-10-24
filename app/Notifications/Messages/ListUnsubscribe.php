<?php

namespace App\Notifications\Messages;

use Symfony\Component\Mime\Email;

/** List-Unsubscribe headers for one-click unsubscribe (RFC 2369 + RFC 8058). */
class ListUnsubscribe
{
    /** @return array<string, string> */
    public static function headers(string $url): array
    {
        return [
            'List-Unsubscribe' => '<'.$url.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];
    }

    public static function apply(Email $email, string $url): void
    {
        foreach (self::headers($url) as $name => $value) {
            $email->getHeaders()->remove($name);
            $email->getHeaders()->addTextHeader($name, $value);
        }
    }
}
