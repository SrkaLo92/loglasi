<?php

namespace App\Support\Import;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Downloads a photo from a URL a user gave (firm table import). Only public web servers:
 * http(s) on ports 80/443, every address of the host public, the connection pinned to the
 * checked address (no DNS rebinding), each redirect checked again, and the size capped while
 * downloading. So a table cannot make the server call itself or the hosting's internal network.
 */
class RemoteImageDownloader
{
    public const MAX_REDIRECTS = 3;

    /** @var callable(string): list<string> host => IPv4 addresses */
    private $resolve;

    public function __construct(?callable $resolve = null)
    {
        $this->resolve = $resolve ?? fn (string $host): array => gethostbynamel($host) ?: [];
    }

    /** Saves the file to $target; false when the URL is not allowed, the download fails or is too large. */
    public function download(string $url, string $target, int $maxBytes): bool
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $pin = $this->publicTarget($url);
            if ($pin === null) {
                return false;
            }

            try {
                $response = Http::timeout(20)->connectTimeout(5)->withoutRedirecting()->withOptions([
                    'sink' => $target,
                    'curl' => [CURLOPT_RESOLVE => [$pin]],
                    'progress' => function ($total, $done) use ($maxBytes) {
                        if ($total > $maxBytes || $done > $maxBytes) {
                            throw new RuntimeException('Image too large.');
                        }
                    },
                ])->get($url);
            } catch (\Throwable) {
                return false;
            }

            if ($response->redirect()) {
                $location = $response->header('Location');
                if ($location === '') {
                    return false;
                }
                $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));

                continue;
            }

            clearstatcache(true, $target);
            $size = is_file($target) ? (int) filesize($target) : 0;

            return $response->successful() && $size > 0 && $size <= $maxBytes;
        }

        return false;
    }

    /** "host:port:ip" for CURLOPT_RESOLVE when the URL points to a public web server, otherwise null. */
    public function publicTarget(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(trim((string) ($parts['host'] ?? ''), '[]'));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));
        if (! in_array($port, [80, 443], true)) {
            return null;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : ($this->resolve)($host);
        if ($ips === []) {
            return null;
        }
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return null;
            }
        }

        return $host.':'.$port.':'.$ips[0];
    }
}
