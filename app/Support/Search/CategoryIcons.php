<?php

namespace App\Support\Search;

use App\Models\Category;
use App\Services\Catalog;
use Illuminate\Support\HtmlString;

/** Line icons for categories, chosen by slug (ported from the home page of loglasi-core). */
class CategoryIcons
{
    /** viewBox 0 0 24 24, stroke. */
    private const ICONS = [
        'car' => '<path d="M3 15l2-6h14l2 6v3H3z"/><circle cx="7.5" cy="18" r="1.5"/><circle cx="16.5" cy="18" r="1.5"/>',
        'moto' => '<circle cx="5.5" cy="16.5" r="3.5"/><circle cx="18.5" cy="16.5" r="3.5"/><path d="M5.5 16.5h6l3-6h2.5l1.5 6M14.5 10.5L13 7h-3"/>',
        'bike' => '<circle cx="6" cy="16" r="3.5"/><circle cx="18" cy="16" r="3.5"/><path d="M6 16l4-8h5l3 8M9 6h3M10 8l3 8"/>',
        'truck' => '<path d="M2 6h12v10H2zM14 10h4l3 3v3h-7z"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'house' => '<path d="M3 11l9-7 9 7v9H3z"/><path d="M10 20v-6h4v6"/>',
        'building' => '<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h2M13 7h2M9 11h2M13 11h2M9 15h2M13 15h2"/>',
        'land' => '<path d="M12 14s-4-3.6-4-6.5a4 4 0 0 1 8 0c0 2.9-4 6.5-4 6.5z"/><circle cx="12" cy="7.5" r="1.3"/><path d="M3 17l5-2 4 2 4-2 5 2v4H3z"/>',
        'phone' => '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>',
        'laptop' => '<rect x="4" y="5" width="16" height="11" rx="1.5"/><path d="M2 19h20"/>',
        'tv' => '<rect x="3" y="5" width="18" height="12" rx="1.5"/><path d="M8 21h8M12 17v4"/>',
        'washer' => '<rect x="5" y="3" width="14" height="18" rx="2"/><circle cx="12" cy="13" r="4"/><path d="M8 6.5h2"/>',
        'sofa' => '<path d="M4 11V8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3"/><path d="M2 11h20v6H2zM5 17v2M19 17v2"/>',
        'garden' => '<path d="M7 14h10l-1.5 7h-7z"/><path d="M12 14V9M12 9c0-3 2-5 5-5 0 3-2 5-5 5zM12 11c0-2.5-1.7-4-4-4 0 2.5 1.7 4 4 4z"/>',
        'shirt' => '<path d="M8 3L3 6l2 5 3-1v11h8V10l3 1 2-5-5-3a4 4 0 0 1-8 0z"/>',
        'sprout' => '<path d="M12 21V9M12 14c-3 0-5-2-5-5 3 0 5 2 5 5zM12 14c3 0 5-2 5-5-3 0-5 2-5 5zM12 9c-2-1.5-2-4.5 0-6 2 1.5 2 4.5 0 6z"/>',
        'job' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M3 13h18"/>',
        'wrench' => '<path d="M14.5 5.5a4 4 0 0 0 4.9 4.9L11 18.8a2 2 0 0 1-2.8-2.8l8.4-8.4a4 4 0 0 0-2.1-2.1z"/>',
        'music' => '<path d="M9 18V5l11-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>',
        'paw' => '<circle cx="7" cy="10" r="1.8"/><circle cx="10.5" cy="6.5" r="1.8"/><circle cx="14.5" cy="6.5" r="1.8"/><circle cx="18" cy="10" r="1.8"/><path d="M12 12c-3 0-5.5 3.5-5.5 5.5 0 1.5 1.2 2.5 2.7 2.5 1 0 1.8-.5 2.8-.5s1.8.5 2.8.5c1.5 0 2.7-1 2.7-2.5 0-2-2.5-5.5-5.5-5.5z"/>',
        'kids' => '<circle cx="12" cy="12" r="9"/><path d="M9 10h.01M15 10h.01M9.5 15a3.5 3.5 0 0 0 5 0"/>',
        'sport' => '<circle cx="12" cy="12" r="9"/><path d="M12 3v18M3 12h18M5.6 5.6c3.5 3.5 3.5 9.3 0 12.8M18.4 5.6c-3.5 3.5-3.5 9.3 0 12.8"/>',
        'book' => '<path d="M4 19V5a2 2 0 0 1 2-2h14v14H6a2 2 0 0 0-2 2zm0 0a2 2 0 0 0 2 2h14"/>',
        'build' => '<path d="M3 18h18M5 18v-3a7 7 0 0 1 14 0v3M10 9V5h4v4"/>',
        'tag' => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'pin' => '<path d="M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>',
        'heart' => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
        'gift' => '<rect x="3.5" y="8" width="17" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12"/><path d="M12 8c-1.5-3-5-3.5-5-1.2C7 8 9 8 12 8Zm0 0c1.5-3 5-3.5 5-1.2C17 8 15 8 12 8Z"/>',
        'chat' => '<path d="M4 5.5h16v10H9l-4.5 3.5V5.5Z"/><path d="M8.5 9.5h7M8.5 12.5h4"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'close' => '<path d="M6 6l12 12M18 6 6 18"/>',
        'filter' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
        'sort' => '<path d="M7 4v16M3 16l4 4 4-4M17 20V4M13 8l4-4 4 4"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'shield' => '<path d="M12 3 5 6v5c0 4.5 3 8.3 7 10 4-1.7 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-4"/>',
        'support' => '<path d="M4 14v-2a8 8 0 0 1 16 0v2"/><rect x="3" y="13" width="4" height="6" rx="1.5"/><rect x="17" y="13" width="4" height="6" rx="1.5"/><path d="M19 19c0 1.5-2 2.5-5 2.5"/>',
        'click' => '<path d="M9 3v3M4.5 5l2 2M3 10h3"/><path d="m10 10 10 4-4 1.5L14.5 20z"/>',
    ];

    /** Part of the slug => icon, first match wins. */
    private const RULES = [
        'kuca-i-basta' => 'garden', 'aparat' => 'washer', 'bela-tehn' => 'washer', 'ljubim' => 'paw', 'zivotinj' => 'paw', 'bicikl' => 'bike', 'motor' => 'moto', 'moto' => 'moto', 'skuter' => 'moto',
        'kamion' => 'truck', 'kombi' => 'truck', 'teretn' => 'truck', 'auto' => 'car', 'vozil' => 'car',
        'hecbek' => 'car', 'karavan' => 'car', 'limuzin' => 'car', 'dzip' => 'car',
        'stan' => 'building', 'plac' => 'land', 'zemlj' => 'land', 'parcel' => 'land', 'kuc' => 'house',
        'nekretn' => 'house', 'vikend' => 'house', 'lokal' => 'building', 'poslovni-prostor' => 'building',
        'bast' => 'garden', 'cvec' => 'garden', 'mobil' => 'phone', 'telefon' => 'phone', 'racunar' => 'laptop',
        'laptop' => 'laptop', 'kompjut' => 'laptop',
        'elektron' => 'tv', 'audio' => 'tv', 'tv' => 'tv', 'konzol' => 'tv', 'foto' => 'tv', 'namest' => 'sofa',
        'odec' => 'shirt', 'obuc' => 'shirt', 'moda' => 'shirt', 'garderob' => 'shirt', 'sat' => 'shirt',
        'poljopriv' => 'sprout', 'stoc' => 'sprout', 'stoka' => 'sprout', 'mehaniz' => 'sprout', 'voce' => 'sprout',
        'ogrev' => 'sprout', 'posao' => 'job', 'poslov' => 'job', 'uslug' => 'wrench', 'majstor' => 'wrench',
        'servis' => 'wrench', 'muzi' => 'music', 'instrument' => 'music',
        'dec' => 'kids', 'beb' => 'kids', 'igrack' => 'kids', 'sport' => 'sport', 'fitnes' => 'sport',
        'knjig' => 'book', 'gradj' => 'build', 'alat' => 'build', 'materijal' => 'build', 'besplatn' => 'gift',
    ];

    public static function nameFor(?Category $category): string
    {
        if ($category === null) {
            return 'image';
        }

        $catalog = app(Catalog::class);
        $guard = 0;

        while ($category !== null && $guard++ < 10) {
            $slug = (string) $category->slug;
            foreach (self::RULES as $needle => $icon) {
                if (str_contains($slug, $needle)) {
                    return $icon;
                }
            }
            $category = $category->parent_id ? $catalog->category((int) $category->parent_id) : null;
        }

        return 'tag';
    }

    /** Inline SVG, decorative (aria-hidden). Paths are constants, never user input. */
    public static function svg(string $name, int $size = 24, string $stroke = '1.8'): HtmlString
    {
        $body = self::ICONS[$name] ?? self::ICONS['tag'];

        return new HtmlString(sprintf(
            '<svg class="lc-icon" width="%1$d" height="%1$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%2$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%3$s</svg>',
            $size,
            htmlspecialchars($stroke, ENT_QUOTES),
            $body,
        ));
    }

    public static function forCategory(?Category $category, int $size = 24): HtmlString
    {
        return self::svg(self::nameFor($category), $size);
    }
}
