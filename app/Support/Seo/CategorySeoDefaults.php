<?php

namespace App\Support\Seo;

use Illuminate\Support\Facades\DB;

/**
 * SEO texts kept from WordPress for the main categories (database/seeders/data/category-seo-*.php).
 * Filled only where a field is still empty, so texts edited in the admin stay.
 */
final class CategorySeoDefaults
{
    public const FILE = 'seeders/data/category-seo-2026-10-24.php';

    /** @return int updated fields */
    public static function apply(): int
    {
        $updated = 0;
        foreach (require database_path(self::FILE) as $slug => $fields) {
            foreach ($fields as $column => $value) {
                $updated += DB::table('categories')->where('slug', $slug)
                    ->where(fn ($q) => $q->whereNull($column)->orWhere($column, ''))
                    ->update([$column => $value]);
            }
        }

        return $updated;
    }
}
