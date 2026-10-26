<?php

namespace App\WordPressImport\Steps;

use App\Support\Seo\CategorySeoDefaults;
use App\WordPressImport\Text;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Kategorije (taksonomija listivo_14): stablo, slug, redosled, opis, ikonica. */
class CategoryImport extends Step
{
    public function run(): void
    {
        $terms = $this->wp->terms(config('wordpress_import.tax_category'));
        $meta = $this->wp->termMeta(array_keys($terms));
        $orderKey = (string) config('wordpress_import.termmeta.order');
        $iconKey = (string) config('wordpress_import.termmeta.category_icon');

        $map = [];
        foreach ($terms as $term) {
            $this->report->wp('categories');
            $slug = rawurldecode((string) $term->slug);
            $label = "#{$term->term_id} {$slug}";

            if (DB::table('categories')->where('slug', $slug)->where(fn ($q) => $q->whereNull('wp_term_id')->orWhere('wp_term_id', '!=', $term->term_id))->exists()) {
                $this->report->error('categories', "{$label}: slug već postoji kod druge kategorije");

                continue;
            }

            $parentId = null;
            if ($term->parent > 0) {
                $parentId = $map[$term->parent] ?? null;
                if ($parentId === null) {
                    $this->report->note('categories', "{$label}: roditelj #{$term->parent} nije pronađen, kategorija ide na prvi nivo");
                }
            }

            $values = [
                'name' => Str::limit(Text::title((string) $term->name), 250, ''),
                'slug' => $slug,
                'description' => trim((string) $term->description) !== '' ? Text::plain((string) $term->description) : null,
                'parent_id' => $parentId,
                'sort' => $orderKey !== '' && is_numeric($meta[$term->term_id][$orderKey] ?? null) ? max(0, (int) $meta[$term->term_id][$orderKey]) : 0,
                'updated_at' => now(),
            ];

            if ($iconKey !== '' && ($icon = $this->icon($meta[$term->term_id][$iconKey] ?? null, $slug)) !== null) {
                $values['icon_path'] = $icon;
            }

            $existing = DB::table('categories')->where('wp_term_id', $term->term_id)->value('id');
            if ($existing) {
                DB::table('categories')->where('id', $existing)->update($values);
                $map[$term->term_id] = (int) $existing;
            } else {
                $map[$term->term_id] = (int) DB::table('categories')->insertGetId($values + [
                    'wp_term_id' => $term->term_id,
                    'is_active' => true,
                    'created_at' => now(),
                ]);
            }
            $this->report->imported('categories');
        }

        $this->ctx->forget('categories');

        CategorySeoDefaults::apply(); // the migration ran before the categories existed
    }

    /** Ikonica: ID priloga (kopira se) ili URL (beleži se kao putanja). */
    private function icon(mixed $raw, string $slug): ?string
    {
        if (is_numeric($raw) && (int) $raw > 0) {
            return $this->ctx->copyAttachment((int) $raw, 'kategorije/'.$slug, 'categories', 'ikonica '.$slug);
        }
        if (is_string($raw) && filter_var($raw, FILTER_VALIDATE_URL)) {
            return $raw;
        }

        return null;
    }
}
