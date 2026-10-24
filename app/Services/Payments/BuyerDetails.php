<?php

namespace App\Services\Payments;

use App\Enums\BuyerType;
use App\Exceptions\OrderException;
use App\Support\Payments\TaxId;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Who pays, and what was accepted on the confirm step: the terms (everyone) and, for private
 * persons, the request that the service starts on payment (the withdrawal right ends once the
 * service is fully performed). Firms give name, PIB, MB, address and an e-mail for the invoice.
 */
final class BuyerDetails
{
    public readonly CarbonInterface $acceptedAt;

    public function __construct(
        public readonly BuyerType $type,
        public readonly ?string $name = null,
        public readonly ?string $pib = null,
        public readonly ?string $mb = null,
        public readonly ?string $address = null,
        public readonly ?string $email = null,
        public readonly ?string $ip = null,
        ?CarbonInterface $acceptedAt = null,
    ) {
        $this->acceptedAt = $acceptedAt ?? Carbon::now();
    }

    public static function private(?string $ip = null): self
    {
        return new self(BuyerType::Private, ip: $ip);
    }

    public static function firm(string $name, string $pib, string $mb, string $address, string $email, ?string $ip = null): self
    {
        return new self(BuyerType::Firm, trim($name), trim($pib), trim($mb), trim($address), trim($email), $ip);
    }

    /** @throws OrderException */
    public function validate(): void
    {
        if ($this->type !== BuyerType::Firm) {
            return;
        }
        if (trim((string) $this->name) === '') {
            throw new OrderException('Upišite naziv firme.', 'buyer_invalid');
        }
        if (! TaxId::isValidPib($this->pib)) {
            throw new OrderException('PIB nije ispravan.', 'buyer_invalid');
        }
        if (! TaxId::isValidMb($this->mb)) {
            throw new OrderException('Matični broj nije ispravan.', 'buyer_invalid');
        }
        if (trim((string) $this->address) === '') {
            throw new OrderException('Upišite adresu firme.', 'buyer_invalid');
        }
        if (filter_var((string) $this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new OrderException('Upišite ispravan mejl za račun.', 'buyer_invalid');
        }
    }

    /** @return array<string, mixed> order columns */
    public function attributes(): array
    {
        $firm = $this->type === BuyerType::Firm;

        return [
            'buyer_type' => $this->type,
            'buyer_name' => $firm ? $this->name : null,
            'buyer_pib' => $firm ? $this->pib : null,
            'buyer_mb' => $firm ? $this->mb : null,
            'buyer_address' => $firm ? $this->address : null,
            'buyer_email' => $firm ? mb_strtolower((string) $this->email) : null,
            'terms_accepted_at' => $this->acceptedAt,
            // Only private persons (consumers) give the immediate-start consent.
            'consent_at' => $firm ? null : $this->acceptedAt,
            'consent_ip' => $this->ip,
        ];
    }
}
