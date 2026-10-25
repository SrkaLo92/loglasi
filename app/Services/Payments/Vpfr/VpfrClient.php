<?php

namespace App\Services\Payments\Vpfr;

use App\Support\Settings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * HTTP client for the V-PFR API of the Tax Administration's fiscal system (ESIR to PFR protocol, v3):
 * GET /api/v3/status and POST /api/v3/invoices, with the PAC header and the client certificate
 * (or the fiscal service's API key). Settings: config/fiscal.php and admin setting fiscal.vpfr_url.
 */
class VpfrClient
{
    public function configured(): bool
    {
        return $this->url() !== '' && ($this->option('pac') !== '' || $this->option('token') !== '');
    }

    /** @return array<string, mixed> the processor's status (time, GSC, tax rates, ...) */
    public function status(): array
    {
        return $this->send(fn (PendingRequest $http) => $http->get($this->url().'/api/v3/status'));
    }

    /**
     * Fiscalises one invoice. RequestId makes a retry of the same document safe (the processor
     * returns the receipt it already made instead of a second one).
     *
     * @param  array<string, mixed>  $invoice
     * @return array<string, mixed> invoiceNumber, sdcDateTime, verificationUrl, totalAmount, ...
     */
    public function invoice(array $invoice, string $requestId): array
    {
        $response = $this->send(fn (PendingRequest $http) => $http->withHeaders(['RequestId' => $requestId])
            ->post($this->url().'/api/v3/invoices', $invoice));

        if (! is_string($response['invoiceNumber'] ?? null) || $response['invoiceNumber'] === '') {
            throw new RuntimeException('V-PFR nije vratio broj računa.');
        }

        return $response;
    }

    /** @param  callable(PendingRequest): Response  $call */
    private function send(callable $call): array
    {
        if (! $this->configured()) {
            throw new RuntimeException('Fiskalna kasa nije podešena (adresa V-PFR-a i PAC ili ključ).');
        }

        $http = Http::acceptJson()->asJson()
            ->timeout((int) config('fiscal.vpfr.timeout', 20))
            ->withHeaders(array_filter([
                'Accept-Language' => 'sr-Latn-RS',
                'PAC' => $this->option('pac') ?: null,
            ]));
        if (($token = $this->option('token')) !== '') {
            $http = $http->withToken($token);
        }
        if (($cert = $this->option('cert')) !== '') {
            $http = $http->withOptions(['cert' => [$cert, $this->option('cert_password')]]);
        }

        $response = $call($http);
        if (! $response->successful()) {
            $message = $response->json('message') ?? $response->json('modelState') ?? $response->body();
            throw new RuntimeException('V-PFR je odbio zahtev ('.$response->status().'): '.mb_substr(is_string($message) ? $message : json_encode($message), 0, 300));
        }

        return (array) $response->json();
    }

    private function url(): string
    {
        return rtrim((string) (Settings::get('fiscal.vpfr_url') ?: config('fiscal.vpfr.url')), '/');
    }

    private function option(string $key): string
    {
        return trim((string) config('fiscal.vpfr.'.$key, ''));
    }
}
