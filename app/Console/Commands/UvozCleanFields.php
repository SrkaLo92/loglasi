<?php

namespace App\Console\Commands;

use App\Enums\FieldType;
use App\Services\Catalog;
use App\Services\ListingFields;
use App\Services\SearchIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repairs field data of an import made before empty numbers were skipped: WordPress kept every
 * empty number field as 0, so listings showed "Sobe 0" and categories got fields of other categories.
 */
class UvozCleanFields extends Command
{
    protected $signature = 'uvoz:ocisti-polja {--dry-run : Only count, change nothing}';

    protected $description = 'Remove empty (0) field values from imported listings and field links no listing uses';

    public function handle(Catalog $catalog, SearchIndex $index): int
    {
        $dry = (bool) $this->option('dry-run');

        $empty = DB::table('listing_field_values as v')
            ->join('fields as f', 'f.id', '=', 'v.field_id')
            ->where(function ($q) {
                $q->where(fn ($q) => $q->where('f.type', FieldType::Number->value)->where(fn ($q) => $q->whereNull('v.value_num')->orWhere('v.value_num', 0)))
                    ->orWhere(fn ($q) => $q->where('f.type', FieldType::Year->value)->whereRaw('CAST(v.value AS SIGNED) < ?', [ListingFields::YEAR_MIN]))
                    ->orWhereRaw("TRIM(COALESCE(v.value, '')) = ''");
            });
        $listingIds = (clone $empty)->distinct()->pluck('v.listing_id')->all();
        $emptyIds = (clone $empty)->pluck('v.id')->all();
        $emptyCount = $dry ? count($emptyIds) : DB::table('listing_field_values')->whereIn('id', $emptyIds)->delete();

        // A field stays on a category when a listing in it or in a subcategory has a value for it.
        $parents = DB::table('categories')->pluck('parent_id', 'id')->all();
        $keep = [];
        $used = DB::table('listing_field_values as v')->join('listings as l', 'l.id', '=', 'v.listing_id')
            ->when($dry && $emptyIds, fn ($q) => $q->whereNotIn('v.id', $emptyIds)) // a dry run counts as if they were gone
            ->distinct()->get(['l.category_id', 'v.field_id']);
        foreach ($used as $row) {
            for ($id = (int) $row->category_id, $guard = 0; $id && $guard < 20; $id = (int) ($parents[$id] ?? 0), $guard++) {
                $keep[$id.':'.$row->field_id] = true;
            }
        }
        $unused = DB::table('category_field')->get(['category_id', 'field_id'])
            ->reject(fn ($link) => isset($keep[$link->category_id.':'.$link->field_id]));

        if (! $dry) {
            foreach ($unused as $link) {
                DB::table('category_field')->where('category_id', $link->category_id)->where('field_id', $link->field_id)->delete();
            }
            $catalog->flush();
            $index->syncIds($listingIds);
        }

        $this->info(sprintf('%s %d empty field values (%d listings) and %d unused category fields.', $dry ? 'Would remove' : 'Removed', $emptyCount, count($listingIds), $unused->count()));

        return self::SUCCESS;
    }
}
