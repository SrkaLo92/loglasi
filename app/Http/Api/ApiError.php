<?php

namespace App\Http\Api;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Greška API-ja po ugovoru: {"code": "...", "message": "...", "fields": {...}}.
 * Baciti iz kontrolera; Laravel je sam pretvara u odgovor (render()).
 */
class ApiError extends RuntimeException
{
    /**
     * @param  array<string, string>  $fields  greške po poljima
     * @param  array<string, string>  $headers
     */
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 400,
        public readonly array $fields = [],
        public readonly array $headers = [],
    ) {
        parent::__construct($message);
    }

    /** 400 za neispravne parametre upita (GET). */
    public static function invalidParams(array $fields): self
    {
        return new self('invalid_param', 'Neispravni parametri.', 400, $fields);
    }

    /** 400 kad nedostaje obavezan parametar upita. */
    public static function missingParams(string ...$params): self
    {
        return new self('missing_param', 'Nedostaje obavezan parametar.', 400, array_fill_keys($params, 'Obavezno polje.'));
    }

    public static function notFound(string $message = 'Tražena adresa ne postoji.'): self
    {
        return new self('not_found', $message, 404);
    }

    public static function forbidden(string $message = 'Nemate pravo pristupa.'): self
    {
        return new self('forbidden', $message, 403);
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->errorCode, $this->getMessage(), $this->status, $this->fields, $this->headers);
    }
}
