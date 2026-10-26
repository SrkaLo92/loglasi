<?php

namespace App\WordPressImport\Steps;

use App\WordPressImport\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Mesta (taksonomija listivo_14144). Nova šema je ravna lista; naselja ostaju kao zasebna mesta. */
class LocationImport extends Step
{
    public function run(): void
    {
        $terms = $this->wp->terms(config('wordpress_import.tax_location'));
        $meta = $this->wp->termMeta(array_keys($terms));
        $orderKey = (string) config('wordpress_import.termmeta.order');

        foreach ($terms as $term) {
            $this->report->wp('locations');
            $slug = rawurldecode((string) $term->slug);

            if (DB::table('locations')->where('slug', $slug)->where(fn ($q) => $q->whereNull('wp_term_id')->orWhere('wp_term_id', '!=', $term->term_id))->exists()) {
                $this->report->error('locations', "#{$term->term_id} {$slug}: slug već postoji kod drugog mesta");

                continue;
            }

            $values = [
                'name' => Str::limit(Text::title((string) $term->name), 250, ''),
                'slug' => $slug,
                'sort' => $orderKey !== '' && is_numeric($meta[$term->term_id][$orderKey] ?? null) ? max(0, (int) $meta[$term->term_id][$orderKey]) : 0,
                'updated_at' => now(),
            ];

            $updated = DB::table('locations')->where('wp_term_id', $term->term_id)->update($values);
            if (! $updated && ! DB::table('locations')->where('wp_term_id', $term->term_id)->exists()) {
                DB::table('locations')->insert($values + ['wp_term_id' => $term->term_id, 'created_at' => now()]);
            }
            $this->report->imported('locations');
        }

        $this->ctx->forget('locations');
    }
}
