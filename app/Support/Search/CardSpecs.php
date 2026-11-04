<?php

namespace App\Support\Search;

use App\Models\Listing;
use App\Services\ListingFields;

/**
 * Facts on a listing card as plain text with their meaning, so search engines can quote them
 * („77 m² · 3 sobe · 4. sprat · Vlasnik” instead of „77 · 3 · 4”).
 */
final class CardSpecs
{
    /** Number fields without a unit, said with their noun: [one, few (2–4), many]. */
    private const NOUNS = [
        'sobe' => ['soba', 'sobe', 'soba'],
        'kupatila' => ['kupatilo', 'kupatila', 'kupatila'],
    ];

    /** Fields that make a listing real estate (it then says who sells: owner or agency). */
    private const REAL_ESTATE = ['velicina_nekretnine', 'povrsina_placa'];

    /** Shown as the place line, not as a fact. */
    public const NEIGHBOURHOOD = 'naselje';

    /**
     * @return array{specs: list<string>, neighbourhood: ?string}
     */
    public static function for(Listing $listing, int $max = 3): array
    {
        if (! $listing->relationLoaded('fieldValues')) {
            return ['specs' => [], 'neighbourhood' => null];
        }

        $specs = [];
        $neighbourhood = null;
        $realEstate = false;
        foreach (app(ListingFields::class)->values($listing) as $value) {
            if ($value['key'] === self::NEIGHBOURHOOD) {
                $neighbourhood = trim($value['display']) ?: null;

                continue;
            }
            $realEstate = $realEstate || in_array($value['key'], self::REAL_ESTATE, true) || preg_match('/\d\s?(m²|ar)$/u', $value['display']) === 1;
            if ($value['display'] === '' || $value['display'] === 'Da' || count($specs) >= $max) {
                continue;
            }
            $specs[] = self::text($value);
        }

        if ($realEstate) {
            $specs[] = $listing->user?->isFirm() ? 'Agencija' : 'Vlasnik';
        }

        return ['specs' => $specs, 'neighbourhood' => $neighbourhood];
    }

    /** @param array{key: string, label: string, display: string, type: string, value: string} $value */
    private static function text(array $value): string
    {
        if (isset(self::NOUNS[$value['key']]) && is_numeric($value['value'])) {
            return $value['display'].' '.self::noun((float) $value['value'], self::NOUNS[$value['key']]);
        }
        // A bare number without a unit says nothing alone: „Broj soba: 3”.
        if ($value['type'] === 'number' && preg_match('/^[\d.,]+$/', $value['display'])) {
            return $value['label'].': '.$value['display'];
        }

        return $value['display'];
    }

    /** @param array{0: string, 1: string, 2: string} $forms */
    private static function noun(float $n, array $forms): string
    {
        if (floor($n) !== $n) {
            return $forms[1]; // 2.5 sobe
        }
        $n = (int) $n;
        $last = $n % 10;
        $lastTwo = $n % 100;

        return match (true) {
            $last === 1 && $lastTwo !== 11 => $forms[0],
            $last >= 2 && $last <= 4 && ($lastTwo < 12 || $lastTwo > 14) => $forms[1],
            default => $forms[2],
        };
    }
}
