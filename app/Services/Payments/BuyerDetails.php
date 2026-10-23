<?php

namespace App\Services\Payments;

use App\Enums\BuyerType;
use App\Exceptions\OrderException;
use App\Support\Payments\TaxId;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Who pays and the explicit consent given on the confirm step: the service starts as soon as
 * the payment arrives, and with that the buyer loses the 14-day right of withdrawal.
 */
final class BuyerDetails
{
    public readonly CarbonInterface $consentAt;

    public function __construct(
        public readonly BuyerType $type,
        public readonly ?string $name = null,
        public readonly ?string $pib = null,
        public readonly ?string $mb = null,
        public readonly ?string $consentIp = null,
        ?CarbonInterface $consentAt = null,
    ) {
        $this->consentAt = $consentAt ?? Carbon::now();
    }

    public static function private(?string $consentIp = null): self
    {
        return new self(BuyerType::Private, consentIp: $consentIp);
    }

    public static function firm(string $name, string $pib, string $mb, ?string $consentIp = null): self
    {
        return new self(BuyerType::Firm, trim($name), trim($pib), trim($mb), $consentIp);
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
            'consent_at' => $this->consentAt,
            'consent_ip' => $this->consentIp,
        ];
    }
}
