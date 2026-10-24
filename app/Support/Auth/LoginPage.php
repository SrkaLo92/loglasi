<?php

namespace App\Support\Auth;

use Illuminate\Http\Request;

/**
 * The one login page: /login-registracija/ with two tabs, the address of the old WordPress site
 * (?tab=prijava, ?tab=registracija). GET /prijava/ and /registracija/ are 301s to it;
 * the forms still post to /prijava/ and /registracija/.
 */
final class LoginPage
{
    public const PATH = 'login-registracija';

    public const LOGIN = 'prijava';

    public const REGISTER = 'registracija';

    /** Hidden field in each form, so a failed submit comes back to the same tab. */
    public const FIELD = '_tab';

    /** Without a tab the page opens on login. */
    public static function url(?string $tab = null): string
    {
        return url_slash(self::PATH).($tab === null ? '' : '?tab='.$tab);
    }

    /** Tab to show: the form that was just submitted, then ?tab=, then login. */
    public static function tab(Request $request): string
    {
        foreach ([$request->session()->getOldInput(self::FIELD), $request->query('tab')] as $tab) {
            if (in_array($tab, [self::LOGIN, self::REGISTER], true)) {
                return $tab;
            }
        }

        return self::LOGIN;
    }
}
