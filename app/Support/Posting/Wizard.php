<?php

namespace App\Support\Posting;

use App\Enums\ListingStatus;
use App\Models\Listing;
use App\Services\Catalog;

/** Steps of the "Postavi oglas" wizard: key => [URL segment, label]. */
class Wizard
{
    public const STEPS = [
        'category' => ['kategorija', 'Kategorija'],
        'basics' => ['osnovno', 'Osnovni podaci'],
        'details' => ['detalji', 'Detalji'],
        'images' => ['slike', 'Slike'],
        'contact' => ['kontakt', 'Kontakt'],
        'preview' => ['pregled', 'Pregled'],
    ];

    /** Steps for this listing; "details" only when the category has fields. */
    public static function steps(Listing $listing): array
    {
        $steps = self::STEPS;
        if (app(Catalog::class)->fieldsFor($listing->category_id)->isEmpty()) {
            unset($steps['details']);
        }

        return $steps;
    }

    public static function url(Listing $listing, string $step): string
    {
        return url_slash('postavi-oglas/'.$listing->id.'/'.self::STEPS[$step][0]);
    }

    public static function next(Listing $listing, string $step): string
    {
        $keys = array_keys(static::steps($listing));
        $index = array_search($step, $keys, true);

        return $keys[min(count($keys) - 1, $index === false ? 0 : $index + 1)];
    }

    public static function previous(Listing $listing, string $step): ?string
    {
        $keys = array_keys(static::steps($listing));
        $index = array_search($step, $keys, true);

        return $index ? $keys[$index - 1] : null;
    }

    /** Draft: posting a new listing; anything else: editing. */
    public static function isEditing(Listing $listing): bool
    {
        return $listing->status !== ListingStatus::Draft;
    }
}
