<?php

namespace App\Support\Search;

use App\Models\Category;
use App\Services\Catalog;

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

    /**
     * @return array{popular: list<array>, themes: list<array{name: string, items: list<array>}>, all: list<array>}
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

        // Most wanted: the groups (or top categories without groups) with the most listings.
        $popular = collect($leaves)
            ->filter(fn ($c) => $c['count'] > 0)
            ->sortByDesc('count')
            ->take(self::POPULAR)
            ->values()
            ->all();

        return ['popular' => $popular, 'themes' => $themes, 'all' => $all];
    }
}
