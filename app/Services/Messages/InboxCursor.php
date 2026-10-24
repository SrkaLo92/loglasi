<?php

namespace App\Services\Messages;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/** Opaque keyset cursor "timestamp.id" (base64url) for inbox and favourites lists. */
class InboxCursor
{
    public static function encode(DateTimeInterface $at, int $id): string
    {
        return rtrim(strtr(base64_encode($at->getTimestamp().'.'.$id), '+/', '-_'), '=');
    }

    /** @return array{at: CarbonImmutable, id: int}|null  null for a missing or malformed cursor (first page) */
    public static function decode(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '' || strlen($cursor) > 64) {
            return null;
        }

        $raw = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($raw === false || ! preg_match('/^(\d{1,12})\.(\d{1,20})$/', $raw, $m)) {
            return null;
        }

        return ['at' => CarbonImmutable::createFromTimestamp((int) $m[1], config('app.timezone')), 'id' => (int) $m[2]];
    }
}
