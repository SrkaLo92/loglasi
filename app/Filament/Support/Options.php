<?php

namespace App\Filament\Support;

use App\Models\Category;
use App\Services\Catalog;

/** Select options shared by several resources. */
class Options
{
    /**
     * Every category as "Parent › Child", in tree order.
     *
     * @return array<int, string>
     */
    public static function categories(bool $activeOnly = false): array
    {
        $catalog = app(Catalog::class);
        $out = [];

        $walk = function (?int $parentId, string $prefix) use (&$walk, &$out, $catalog, $activeOnly) {
            foreach ($catalog->children($parentId, $activeOnly) as $category) {
                /** @var Category $category */
                $name = html_entity_decode($category->name, ENT_QUOTES, 'UTF-8');
                $label = $prefix === '' ? $name : $prefix.' › '.$name;
                $out[$category->id] = $category->is_active ? $label : $label.' (neaktivna)';
                if (count($out) < 5000) {
                    $walk((int) $category->id, $label);
                }
            }
        };
        $walk(null, '');

        return $out;
    }

    /** @return array<int, string> */
    public static function locations(): array
    {
        return app(Catalog::class)->locations()
            ->mapWithKeys(fn ($l) => [$l->id => html_entity_decode($l->name, ENT_QUOTES, 'UTF-8')])
            ->all();
    }

    /**
     * Enum cases as [value => label()].
     *
     * @param  class-string<\BackedEnum>  $enum
     * @return array<string, string>
     */
    public static function enum(string $enum): array
    {
        return collect($enum::cases())->mapWithKeys(fn ($case) => [$case->value => $case->label()])->all();
    }
}
