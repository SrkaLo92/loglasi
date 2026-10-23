<?php

namespace App\Support\Auth;

/**
 * Provera lozinki prenetih iz WordPress-a, isto kao wp_check_password() (WP 6.8+):
 * - "$wp$2y$...": bcrypt nad base64(HMAC-SHA384(lozinka, "wp-sha384")),
 * - "$P$..." / "$H$...": phpass (prenosivi MD5, 8192 kruga za "$P$B"),
 * - do 32 znaka: stari čist MD5,
 * - ostalo ("$2y$..." iz dodataka): password_verify.
 */
class WordPressPassword
{
    private const ITOA64 = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    /** WordPress ne proverava duže lozinke. */
    private const MAX_LENGTH = 4096;

    /** Da li heš dolazi iz WordPress-a (i nije običan bcrypt/argon). */
    public static function isWordPressHash(?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            return false;
        }

        return str_starts_with($hash, '$wp$')
            || str_starts_with($hash, '$P$')
            || str_starts_with($hash, '$H$')
            || (strlen($hash) === 32 && ctype_xdigit($hash));
    }

    public static function check(string $password, string $hash): bool
    {
        if ($hash === '') {
            return false;
        }

        if (strlen($hash) <= 32) {
            return hash_equals($hash, md5($password));
        }

        if (strlen($password) > self::MAX_LENGTH) {
            return false;
        }

        if (str_starts_with($hash, '$wp')) {
            return password_verify(self::preHash($password), substr($hash, 3));
        }

        if (str_starts_with($hash, '$P$') || str_starts_with($hash, '$H$')) {
            $computed = self::phpassCrypt($password, $hash);

            return $computed[0] !== '*' && hash_equals($hash, $computed);
        }

        return password_verify($password, $hash);
    }

    /** Heš u obliku WordPress 6.8 (koristi se za testove i proveru uvoza). */
    public static function hashWp68(string $password, int $cost = 10): string
    {
        return '$wp'.password_hash(self::preHash(trim($password)), PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    /** base64(HMAC-SHA384) kao u wp_hash_password(). */
    private static function preHash(string $password): string
    {
        return base64_encode(hash_hmac('sha384', $password, 'wp-sha384', true));
    }

    /** PasswordHash::crypt_private() iz phpass (wp-includes/class-phpass.php). */
    public static function phpassCrypt(string $password, string $setting): string
    {
        $output = str_starts_with($setting, '*0') ? '*1' : '*0';

        $id = substr($setting, 0, 3);
        if ($id !== '$P$' && $id !== '$H$') {
            return $output;
        }

        $countLog2 = strpos(self::ITOA64, $setting[3] ?? '');
        if ($countLog2 === false || $countLog2 < 7 || $countLog2 > 30) {
            return $output;
        }
        $count = 1 << $countLog2;

        $salt = substr($setting, 4, 8);
        if (strlen($salt) !== 8) {
            return $output;
        }

        $hash = md5($salt.$password, true);
        do {
            $hash = md5($hash.$password, true);
        } while (--$count);

        return substr($setting, 0, 12).self::encode64($hash, 16);
    }

    /** Novi phpass heš (za testove): "$P$B" + so od 8 znakova. */
    public static function phpassHash(string $password, ?string $salt = null): string
    {
        $salt ??= self::encode64(random_bytes(6), 6);

        return self::phpassCrypt($password, '$P$B'.$salt);
    }

    private static function encode64(string $input, int $count): string
    {
        $itoa = self::ITOA64;
        $output = '';
        $i = 0;
        do {
            $value = ord($input[$i++]);
            $output .= $itoa[$value & 0x3F];
            if ($i < $count) {
                $value |= ord($input[$i]) << 8;
            }
            $output .= $itoa[($value >> 6) & 0x3F];
            if ($i++ >= $count) {
                break;
            }
            if ($i < $count) {
                $value |= ord($input[$i]) << 16;
            }
            $output .= $itoa[($value >> 12) & 0x3F];
            if ($i++ >= $count) {
                break;
            }
            $output .= $itoa[($value >> 18) & 0x3F];
        } while ($i < $count);

        return $output;
    }
}
