<?php

namespace App\Support\Push;

use App\Notifications\Messages\PushMessage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging HTTP v1 without the SDK.
 *
 * OAuth: a JWT (RS256, signed with openssl from the service-account private key) is
 * exchanged at the account's token_uri for an access token, cached until shortly before
 * it expires. Credentials: config('notifications.fcm.credentials') = path to the JSON.
 */
class FcmClient
{
    public const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const TOKEN_CACHE_KEY = 'notifications.fcm.access_token';

    /** Result of send(). */
    public const SENT = 'sent';

    public const INVALID_TOKEN = 'invalid_token';

    public const FAILED = 'failed';

    public const DISABLED = 'disabled';

    private ?array $credentials = null;

    public function enabled(): bool
    {
        return $this->credentials() !== null;
    }

    /** @return self::SENT|self::INVALID_TOKEN|self::FAILED|self::DISABLED */
    public function send(string $token, PushMessage $message): string
    {
        if (config('notifications.hold')) {
            Log::info('Push held: notifications are on hold.', ['title' => $message->title]);

            return self::DISABLED;
        }

        $credentials = $this->credentials();
        if ($credentials === null) {
            Log::debug('Push not sent: FCM is not configured.', ['title' => $message->title]);

            return self::DISABLED;
        }

        $response = $this->post($credentials, $token, $message);

        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey($credentials)); // revoked or rotated key: one retry with a new token
            $response = $this->post($credentials, $token, $message);
        }

        if ($response->successful()) {
            return self::SENT;
        }

        if ($this->isInvalidToken($response)) {
            return self::INVALID_TOKEN;
        }

        Log::warning('FCM send failed.', ['status' => $response->status(), 'error' => $response->json('error.status')]);

        return self::FAILED;
    }

    private function post(array $credentials, string $token, PushMessage $message): Response
    {
        $url = 'https://fcm.googleapis.com/v1/projects/'.rawurlencode($credentials['project_id']).'/messages:send';

        return Http::withToken($this->accessToken($credentials))
            ->timeout((int) config('notifications.fcm.timeout', 10))
            ->acceptJson()
            ->post($url, ['message' => [
                'token' => $token,
                'notification' => ['title' => $message->title, 'body' => $message->body],
                // title/body also in data: the app builds the tap target from data alone.
                'data' => (object) ($message->stringData() + ['title' => $message->title, 'body' => $message->body]),
                // The app's two Android channels: "poruke" for messages, "oglasi" for the rest.
                'android' => ['priority' => 'high', 'notification' => ['channel_id' => isset($message->data['conversation_id']) ? 'poruke' : 'oglasi']],
                'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
            ]]);
    }

    /** 404 NOT_FOUND / UNREGISTERED: the app was removed or the token rotated. */
    private function isInvalidToken(Response $response): bool
    {
        if ($response->status() === 404) {
            return true;
        }

        foreach ((array) $response->json('error.details', []) as $detail) {
            if (($detail['errorCode'] ?? null) === 'UNREGISTERED') {
                return true;
            }
        }

        return $response->json('error.status') === 'UNREGISTERED';
    }

    private function accessToken(array $credentials): string
    {
        return Cache::remember($this->tokenCacheKey($credentials), now()->addMinutes(50), function () use ($credentials) {
            $response = Http::asForm()->timeout((int) config('notifications.fcm.timeout', 10))->post($credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->jwt($credentials),
            ]);

            $token = $response->json('access_token');
            if (! $response->successful() || ! is_string($token)) {
                throw new RuntimeException('FCM OAuth token request failed with status '.$response->status().'.');
            }

            return $token;
        });
    }

    /** Signed JWT for the OAuth 2.0 JWT bearer grant. */
    public function jwt(array $credentials, ?int $now = null): string
    {
        $now ??= time();
        $encode = fn (array $part) => $this->base64url(json_encode($part, JSON_UNESCAPED_SLASHES));

        $unsigned = $encode(['alg' => 'RS256', 'typ' => 'JWT'])
            .'.'.$encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ]);

        $key = openssl_pkey_get_private($credentials['private_key']);
        if ($key === false || ! openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('FCM private key could not sign the token.');
        }

        return $unsigned.'.'.$this->base64url($signature);
    }

    /** @return array{client_email: string, private_key: string, token_uri: string, project_id: string}|null */
    private function credentials(): ?array
    {
        if ($this->credentials !== null) {
            return $this->credentials;
        }

        $path = (string) config('notifications.fcm.credentials');
        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        $projectId = (string) (config('notifications.fcm.project_id') ?: ($json['project_id'] ?? ''));
        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key']) || $projectId === '') {
            Log::warning('FCM credentials file is incomplete.', ['path' => $path]);

            return null;
        }

        return $this->credentials = [
            'client_email' => $json['client_email'],
            'private_key' => $json['private_key'],
            'token_uri' => $json['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'project_id' => $projectId,
        ];
    }

    private function tokenCacheKey(array $credentials): string
    {
        return self::TOKEN_CACHE_KEY.'.'.md5($credentials['client_email']);
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
