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
    /** Query parameter with the page to return to after login (a path on this site). */
    public const BACK = 'nazad';

    public static function url(?string $tab = null, ?string $back = null): string
    {
        $query = array_filter(['tab' => $tab, self::BACK => self::safePath($back)]);

        return url_slash(self::PATH).($query === [] ? '' : '?'.http_build_query($query));
    }

    /** The login page that brings the visitor back to the current page. */
    public static function urlBack(Request $request): string
    {
        return self::url(back: $request->getRequestUri());
    }

    /** Remembers ?nazad= as the page to open after login; only paths on this site count. */
    public static function rememberBack(Request $request): void
    {
        if (($path = self::safePath($request->query(self::BACK))) !== null) {
            $request->session()->put('url.intended', url($path));
        }
    }

    /** "/oglas/golf/" yes; "//evil.rs", "https://...", "javascript:" no. */
    private static function safePath(mixed $path): ?string
    {
        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\') || strlen($path) > 500) {
            return null;
        }

        return $path;
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
