<?php

namespace App\Support\Catalog;

use App\Enums\FieldType;
use App\Services\ListingFields;

/** Whether a stored field value says anything: WordPress kept every empty number field as 0. */
final class FieldValue
{
    public static function meaningful(FieldType $type, mixed $value): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return false;
        }

        return match ($type) {
            FieldType::Number => is_numeric($value) && (float) $value != 0.0,
            FieldType::Year => is_numeric($value) && (int) $value >= ListingFields::YEAR_MIN,
            default => true,
        };
    }
}
