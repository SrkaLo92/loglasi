<?php

namespace App\Support\Search;

use App\Models\Category;
use App\Services\Catalog;
use App\Support\Home\HomeSettings;

/**
 * "Sve kategorije" (as on kupujemprodajem, Leboncoin and Willhaben): a search over every category, the most
 * wanted ones, then the top categories under a few themes, as tiles with an icon and the number of listings.
 */
class CategorySheet
{
    /** Themes in the order shown; top categories are placed by slug, the rest go to "Ostalo". */
    public const THEMES = [
        'Vozila' => ['automobili', 'motori', 'kamioni', 'bicikli', 'bicikli-delovi-i-oprema', 'plovni-objekti'],
        'Nekretnine' => ['nekretnine'],
        'Elektronika' => ['mobilni-telefoni', 'kompjuteri-laptop-i-tablet', 'kompjuteri-desktop', 'tv-i-video', 'foto-aparati-i-kamere', 'konzole-i-igrice', 'elektronika-i-komponente', 'bela-tehnika-i-kucni-aparati'],
        'Kuća i bašta' => ['namestaj', 'dvoriste-i-basta', 'alati-i-orudja', 'gradjevinarstvo', 'elektro-i-rasveta'],
        'Porodica i moda' => ['moda', 'mama-i-beba', 'igracke-i-igre', 'nega-i-licna-higijena', 'knjige'],
        'Sport i hobi' => ['fitnes-i-vezbanje', 'lov-i-ribolov', 'muzicki-instrumenti'],
        'Životinje i poljoprivreda' => ['kucni-ljubimci', 'kucni-ljubimci-oprema', 'poljoprivreda', 'poljoprivreda-domace-zivotinje'],
        'Poslovi i usluge' => ['poslovi', 'usluge'],
    ];

    public const POPULAR = 8;

    /** Big tiles at the top of the home page (site and apps); a sixth tile opens all categories. */
    public const MAIN = 5;

    /**
     * Colour of each main tile (site and apps use the same names). Besplatne stvari is red and has a tagline:
     * giving things away is part of what the site stands for.
     */
    public const COLORS = ['automobili' => 'blue', 'nekretnine' => 'orange', 'besplatne-stvari' => 'red', 'poslovi' => 'violet', 'usluge' => 'teal'];

    public const TAGLINES = ['besplatne-stvari' => 'Poklonite nekome'];

    /** Popularno: chosen in the admin, not by count (a few big sellers would decide it otherwise). */
    public const POPULAR_MAX = 12;

    /**
     * @return array{main: list<array>, popular: list<array>, themes: list<array{name: string, items: list<array>}>, all: list<array>}
     */
    public static function get(): array
    {
        return once(fn () => self::build());
    }

    private static function build(): array
    {
        $catalog = app(Catalog::class);
        $counts = $catalog->counts();
        $tile = fn (Category $c, ?Category $parent = null) => [
            'id' => $c->id,
            'slug' => $c->slug,
            'name' => $c->name,
            'path' => $parent ? $parent->name : null,
            'url' => $c->url(),
            'icon' => CategoryIcons::nameFor($c),
            'image' => $c->iconUrl(),
            'count' => $counts[$c->id] ?? 0,
        ];

        $top = $catalog->children(null);
        $bySlug = $top->keyBy('slug');
        $themes = [];
        $placed = [];
        foreach (self::THEMES as $name => $slugs) {
            $items = [];
            foreach ($slugs as $slug) {
                if ($category = $bySlug->get($slug)) {
                    $items[] = $tile($category);
                    $placed[$slug] = true;
                }
            }
            // A theme with one category (Nekretnine) shows its groups instead of a lonely tile.
            if (count($items) === 1 && ($only = $bySlug->get($slugs[0]) ?? $top->firstWhere('name', $items[0]['name'])) && ($children = $catalog->children($only))->isNotEmpty()) {
                $items = $children->map(fn (Category $c) => $tile($c, $only))->values()->all();
            }
            if ($items !== []) {
                $themes[] = ['name' => $name, 'items' => $items];
            }
        }
        $rest = $top->reject(fn (Category $c) => isset($placed[$c->slug]))->map(fn (Category $c) => $tile($c))->values()->all();
        if ($rest !== []) {
            $themes[] = ['name' => 'Ostalo', 'items' => $rest];
        }

        // Everything, for the search box: top categories and their groups ("Nekretnine > Stanovi | prodaja").
        $all = [];
        $leaves = [];
        foreach ($top as $category) {
            $all[] = $tile($category);
            $children = $catalog->children($category);
            if ($children->isEmpty()) {
                $leaves[] = $tile($category);
            }
            foreach ($children as $child) {
                $all[] = $leaves[] = $tile($child, $category);
            }
        }

        // Popularno: the admin list (categories or groups), in its order; by count only when it is empty.
        $chosen = [];
        foreach (array_slice(HomeSettings::list('home.popular_categories'), 0, self::POPULAR_MAX) as $slug) {
            $category = $catalog->categoryBySlug($slug);
            if ($category !== null && $catalog->isVisible($category)) {
                $parent = $category->parent_id ? $catalog->category((int) $category->parent_id) : null;
                $chosen[] = $tile($category, $parent);
            }
        }

        $popular = $chosen !== [] ? $chosen : collect($leaves)
            ->filter(fn ($c) => $c['count'] > 0)
            ->sortByDesc('count')
            ->take(self::POPULAR)
            ->values()
            ->all();

        $main = [];
        foreach (HomeSettings::list('home.main_categories') as $slug) {
            $category = $catalog->categoryBySlug($slug);
            if ($category !== null && $catalog->isVisible($category) && count($main) < self::MAIN) {
                $main[] = $tile($category) + [
                    'color' => self::COLORS[$category->slug] ?? 'green',
                    'accent' => (self::COLORS[$category->slug] ?? null) === 'red' ? 'red' : null,
                    'tagline' => self::TAGLINES[$category->slug] ?? null,
                ];
            }
        }

        return ['main' => $main, 'popular' => $popular, 'themes' => $themes, 'all' => $all];
    }
}
