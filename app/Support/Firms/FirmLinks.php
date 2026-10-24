<?php

namespace App\Support\Firms;

/**
 * Website and social links typed by a firm: "vulkanizer.rs" → "https://vulkanizer.rs",
 * "@vulkanizer" → "https://www.facebook.com/vulkanizer". Only http(s) links are accepted;
 * a Facebook / Instagram link must point to that network.
 */
class FirmLinks
{
    private const NETWORKS = [
        'facebook' => ['base' => 'https://www.facebook.com/', 'hosts' => ['facebook.com', 'fb.com', 'fb.me']],
        'instagram' => ['base' => 'https://www.instagram.com/', 'hosts' => ['instagram.com', 'instagr.am']],
    ];

    /** Normalized URL, '' for empty input, null when the value is not a valid link. */
    public static function website(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
            $value = 'https://'.$value;
        }

        return static::validUrl($value) ? $value : null;
    }

    public static function social(string $network, ?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $config = self::NETWORKS[$network];

        // "@name" is always a handle; a bare word only when it does not look like a domain.
        if (preg_match('/^(@?)([A-Za-z0-9._-]{2,80})$/', $value, $m) && ! str_contains($m[2], '..')
            && ($m[1] === '@' || ! preg_match('/\.[a-z]{2,}$/i', $m[2]))) {
            return $config['base'].$m[2];
        }

        $url = preg_match('#^[a-z][a-z0-9+.-]*://#i', $value) ? $value : 'https://'.$value;
        if (! static::validUrl($url)) {
            return null;
        }
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach ($config['hosts'] as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return $url;
            }
        }

        return null;
    }

    private static function validUrl(string $url): bool
    {
        if (mb_strlen($url) > 255 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = (string) parse_url($url, PHP_URL_HOST);

        return in_array($scheme, ['http', 'https'], true) && str_contains($host, '.') && ! str_contains($url, ' ');
    }
}
