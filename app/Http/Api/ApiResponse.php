<?php

namespace App\Http\Api;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;

/**
 * Shared behaviour of API responses (the same as Loglasi_API_Http in WordPress):
 * JSON without \u escapes, Cache-Control, weak ETag and 304, the contract's error shape.
 * Guide: docs/moduli/katalog.md, section "API pomoćnici".
 */
class ApiResponse
{
    public const JSON_FLAGS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /** Default cache for public GET responses. */
    public const CACHE_DEFAULT = 'public, max-age=60';

    /** Catalog and other data that rarely changes. */
    public const CACHE_CATALOG = 'public, max-age=300';

    /** Responses for a logged-in user. */
    public const CACHE_PRIVATE = 'private, no-cache';

    /**
     * Successful response. For GET/HEAD it adds Cache-Control and ETag and returns 304 without a body
     * when If-None-Match matches.
     *
     * @param  array<string, string>  $headers
     */
    public static function json(Request $request, mixed $data, ?string $cacheControl = self::CACHE_DEFAULT, int $status = 200, array $headers = []): Response|JsonResponse
    {
        $response = new JsonResponse($data, $status, $headers, self::JSON_FLAGS);

        if (! $request->isMethodCacheable()) {
            return $response;
        }

        if (! array_key_exists('Cache-Control', $headers)) {
            // A signed-in answer (is_mine, is_favorite...) must never be stored by a shared cache.
            $signedIn = $request->bearerToken() !== null || $request->user() !== null;
            $response->headers->set('Cache-Control', $signedIn ? self::CACHE_PRIVATE : ($cacheControl ?? self::CACHE_DEFAULT));
        }

        if ($status !== 200) {
            return $response;
        }

        $etag = $response->headers->get('ETag') ?: self::etag($response->getContent());
        $response->headers->set('ETag', $etag);

        if (self::matches((string) $request->headers->get('If-None-Match'), $etag)) {
            return new Response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => $response->headers->get('Cache-Control'),
            ]);
        }

        return $response;
    }

    /**
     * Error per the contract. `fields` is sent only when it is not empty.
     *
     * @param  array<string, string>  $fields
     * @param  array<string, string>  $headers
     */
    public static function error(string $code, string $message, int $status, array $fields = [], array $headers = []): JsonResponse
    {
        $body = ['code' => $code, 'message' => $message];
        if ($fields !== []) {
            $body['fields'] = array_map('strval', $fields);
        }

        $response = new JsonResponse($body, $status, $headers, self::JSON_FLAGS);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /** Weak ETag over the response body. */
    public static function etag(string $content): string
    {
        return 'W/"'.md5($content).'"';
    }

    /** If-None-Match against the ETag: several values, weak comparison, "*". */
    public static function matches(string $header, string $etag): bool
    {
        $header = trim($header);
        if ($header === '') {
            return false;
        }
        if ($header === '*') {
            return true;
        }

        $strip = static fn (string $tag): string => trim((string) preg_replace('~^W/~', '', trim($tag)));
        $want = $strip($etag);

        foreach (explode(',', $header) as $candidate) {
            if ($strip($candidate) === $want) {
                return true;
            }
        }

        return false;
    }

    /** Turns on the error shape for every route under /api (called from bootstrap/app.php). */
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (Throwable $e, Request $request) {
            return $request->is('api/*') ? self::fromException($e, $request) : null;
        });
    }

    /** Turns an exception into an error per the contract. */
    public static function fromException(Throwable $e, Request $request): ?JsonResponse
    {
        if ($e instanceof HttpResponseException) {
            return null;
        }

        if ($e instanceof ApiError) {
            return $e->render();
        }

        if ($e instanceof ValidationException) {
            $fields = array_map(fn (array $messages) => (string) reset($messages), $e->errors());

            // Search parameters (GET) give 400, the request body on writes 422, as in the contract.
            return $request->isMethodSafe()
                ? self::error('invalid_param', 'Neispravni parametri.', 400, $fields)
                : self::error('validation_failed', 'Proverite unete podatke.', 422, $fields);
        }

        if ($e instanceof AuthenticationException || $e instanceof UnauthorizedHttpException) {
            return self::error('unauthorized', 'Potrebna je prijava.', 401);
        }

        if ($e instanceof AuthorizationException || $e instanceof AccessDeniedHttpException
            || ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 403)) {
            return self::error('forbidden', 'Nemate pravo pristupa.', 403);
        }

        if ($e instanceof ThrottleRequestsException) {
            return self::error('too_many_requests', 'Previše pokušaja. Pokušajte ponovo malo kasnije.', 429, [], self::passHeaders($e, ['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset']));
        }

        if ($e instanceof NotFoundHttpException) {
            return self::error('not_found', 'Tražena adresa ne postoji.', 404);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return self::error('method_not_allowed', 'Ova metoda nije dozvoljena za ovu adresu.', 405, [], self::passHeaders($e, ['Allow']));
        }

        if ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();

            return $status >= 500
                ? self::error('server_error', 'Došlo je do greške. Pokušajte ponovo.', $status)
                : self::error('error', 'Zahtev nije moguće obraditi.', $status);
        }

        return self::error('server_error', 'Došlo je do greške. Pokušajte ponovo.', 500);
    }

    /**
     * @param  list<string>  $names
     * @return array<string, string>
     */
    private static function passHeaders(HttpExceptionInterface $e, array $names): array
    {
        $out = [];
        foreach ($e->getHeaders() as $name => $value) {
            foreach ($names as $wanted) {
                if (strcasecmp($name, $wanted) === 0) {
                    $out[$wanted] = (string) (is_array($value) ? reset($value) : $value);
                }
            }
        }

        return $out;
    }
}
