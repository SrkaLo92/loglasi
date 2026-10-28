<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Pages whose address carries a secret (password reset token, signed links from mails).
 * They get no analytics tag and Referrer-Policy: no-referrer, so the secret never leaves the site.
 */
class SecretUrl
{
    public const ROUTES = ['password.reset', 'verification.verify', 'notifications.unsubscribe'];

    public static function is(?Request $request = null): bool
    {
        $request ??= request();

        return $request->routeIs(...self::ROUTES) || $request->query->has('signature');
    }
}
