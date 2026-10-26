<?php

namespace App\Http\Api;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * API error per the contract: {"code": "...", "message": "...", "fields": {...}}.
 * Throw it from a controller; Laravel turns it into a response itself (render()).
 */
class ApiError extends RuntimeException
{
    /**
     * @param  array<string, string>  $fields  errors per field
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

    /** 400 for invalid query parameters (GET). */
    public static function invalidParams(array $fields): self
    {
        return new self('invalid_param', 'Neispravni parametri.', 400, $fields);
    }

    /** 400 when a required query parameter is missing. */
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

    /** Client errors (4xx) are answers, not faults: they are not written to the log. */
    public function report(): bool
    {
        return $this->status < 500;
    }

    public function render(): JsonResponse
    {
        return ApiResponse::error($this->errorCode, $this->getMessage(), $this->status, $this->fields, $this->headers);
    }
}
