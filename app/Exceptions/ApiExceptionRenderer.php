<?php

namespace App\Exceptions;

use App\Http\Middleware\EnsureNotBlocked;
use App\Models\User;
use App\Support\ApiError;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/** Every error on /api/* comes in the contract's Error shape, with a Serbian message. */
class ApiExceptionRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return static::render($e, $request);
        });
    }

    public static function render(Throwable $e, Request $request)
    {
        // GET parameters (search, catalog) and ApiError from the catalog go through ApiResponse.
        // Here only login (token_expired) and the request body on writes (422 with the first field's message);
        // everything else (GET parameters, 404, 405, 429...) is returned by App\Http\Api\ApiResponse.
        $handled = $e instanceof AuthenticationException
            || ($e instanceof ValidationException && ! $request->isMethodSafe());
        if (! $handled) {
            return null;
        }

        return match (true) {
            $e instanceof ValidationException => static::validation($e),
            $e instanceof AuthenticationException => static::unauthenticated($request),
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => ApiError::response('forbidden', 'Nemate dozvolu za ovu radnju.', 403),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ApiError::response('not_found', 'Ovo više ne postoji.', 404),
            $e instanceof ThrottleRequestsException => ApiError::response(
                'too_many_requests',
                'Previše pokušaja. Sačekajte malo pa probajte ponovo.',
                429,
                [],
                $e->getHeaders(),
            ),
            $e instanceof HttpExceptionInterface => ApiError::response(
                static::codeFor($e->getStatusCode()),
                static::messageFor($e->getStatusCode()),
                $e->getStatusCode(),
                [],
                $e->getHeaders(),
            ),
            default => config('app.debug') ? null : ApiError::response('server_error', static::messageFor(500), 500),
        };
    }

    private static function validation(ValidationException $e)
    {
        $fields = [];
        foreach ($e->errors() as $key => $messages) {
            $fields[$key] = (string) ($messages[0] ?? '');
        }

        return ApiError::response('validation_failed', (string) (reset($fields) ?: 'Proverite unete podatke.'), 422, $fields);
    }

    /** A token that expired or was revoked → token_expired (the app refreshes the token). */
    private static function unauthenticated(Request $request)
    {
        $token = $request->bearerToken();
        if ($token !== null && $token !== '') {
            $model = Sanctum::$personalAccessTokenModel::findToken($token);
            if ($model === null || ($model->expires_at !== null && $model->expires_at->isPast())) {
                return ApiError::response('token_expired', 'Prijava je istekla. Prijavite se ponovo.', 401);
            }
            if ($model->tokenable instanceof User && $model->tokenable->isBlocked()) {
                return ApiError::response('account_blocked', EnsureNotBlocked::MESSAGE, 401);
            }
        }

        return ApiError::response('unauthorized', 'Prijavite se da biste nastavili.', 401);
    }

    private static function codeFor(int $status): string
    {
        return match ($status) {
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            409 => 'conflict',
            413 => 'too_large',
            419 => 'session_expired',
            422 => 'validation_failed',
            429 => 'too_many_requests',
            503 => 'unavailable',
            default => $status >= 500 ? 'server_error' : 'error',
        };
    }

    private static function messageFor(int $status): string
    {
        return match ($status) {
            401 => 'Prijavite se da biste nastavili.',
            403 => 'Nemate dozvolu za ovu radnju.',
            404 => 'Ovo više ne postoji.',
            405 => 'Ova radnja nije dozvoljena.',
            409 => 'Ovo već postoji.',
            413 => 'Fajl je prevelik.',
            429 => 'Previše pokušaja. Sačekajte malo pa probajte ponovo.',
            503 => 'Sajt se ažurira. Pokušajte ponovo za nekoliko minuta.',
            default => 'Došlo je do greške na serveru. Pokušajte ponovo.',
        };
    }
}
