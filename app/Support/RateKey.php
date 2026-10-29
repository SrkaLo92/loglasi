<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Rate limit key for a client. Mobile networks put many phones behind one IP (CGNAT), so a signed-in
 * user or an app token counts on its own and only anonymous visitors share the IP.
 */
final class RateKey
{
    public static function client(Request $request): string
    {
        if ($id = $request->user()?->getAuthIdentifier()) {
            return 'u'.$id;
        }
        if ($token = $request->bearerToken()) {
            return 't'.sha1($token);
        }

        return 'ip'.$request->ip();
    }
}
