<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/** Greška API-ja po ugovoru: {"code": "...", "message": "...", "fields": {...}}. */
class ApiError
{
    /** @param  array<string, string>  $fields */
    public static function response(string $code, string $message, int $status, array $fields = [], array $headers = []): JsonResponse
    {
        $body = ['code' => $code, 'message' => $message];
        if ($fields !== []) {
            $body['fields'] = $fields;
        }

        return new JsonResponse($body, $status, $headers);
    }
}
