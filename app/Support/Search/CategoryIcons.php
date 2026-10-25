<?php

namespace App\Support\Search;

use App\Models\Category;
use App\Services\Catalog;
use Illuminate\Support\HtmlString;

/** Line icons for categories, chosen by slug. Paths and rules are the same as in the mobile apps (CategoryIcons.kt / CategoryIcons.swift). */
class CategoryIcons
{
    /** viewBox 0 0 24 24, stroke. */
    private const ICONS = [
        'car' => '<path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><path d="M5 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M15 17a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M9 17h6"/>',
        'moto' => '<path d="M2 16a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M16 16a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M7.5 14h5l4-4H6M8 14l4-4"/><path d="M13 6h2l1.5 3 2 4"/>',
        'bike' => '<path d="M2.5 16a3.5 3.5 0 1 0 7 0a3.5 3.5 0 1 0 -7 0"/><path d="M14.5 16a3.5 3.5 0 1 0 7 0a3.5 3.5 0 1 0 -7 0"/><path d="M6 16l4-8h5l3 8M9 6h3M10 8l3 8"/>',
        'truck' => '<path d="M2 6h12v10H2zM14 10h4l3 3v3h-7z"/><path d="M4 18a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M15 18a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/>',
        'house' => '<path d="M3 11l9-7 9 7v9H3z"/><path d="M10 20v-6h4v6"/>',
        'building' => '<path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4M10 10h4M10 14h4M10 18h4"/>',
        'land' => '<path d="M12 14s-4-3.6-4-6.5a4 4 0 0 1 8 0c0 2.9-4 6.5-4 6.5z"/><path d="M10.7 7.5a1.3 1.3 0 1 0 2.6 0a1.3 1.3 0 1 0 -2.6 0"/><path d="M3 17l5-2 4 2 4-2 5 2v4H3z"/>',
        'phone' => '<path d="M9 2h6a2 2 0 0 1 2 2v16a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2v-16a2 2 0 0 1 2 -2z"/><path d="M11 18h2"/>',
        'laptop' => '<path d="M5.5 5h13a1.5 1.5 0 0 1 1.5 1.5v8a1.5 1.5 0 0 1 -1.5 1.5h-13a1.5 1.5 0 0 1 -1.5 -1.5v-8a1.5 1.5 0 0 1 1.5 -1.5z"/><path d="M2 19h20"/>',
        'tv' => '<path d="M4.5 5h15a1.5 1.5 0 0 1 1.5 1.5v9a1.5 1.5 0 0 1 -1.5 1.5h-15a1.5 1.5 0 0 1 -1.5 -1.5v-9a1.5 1.5 0 0 1 1.5 -1.5z"/><path d="M8 21h8M12 17v4"/>',
        'washer' => '<path d="M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2v-14a2 2 0 0 1 2 -2z"/><path d="M8 13a4 4 0 1 0 8 0a4 4 0 1 0 -8 0"/><path d="M8 6.5h2"/>',
        'sofa' => '<path d="M4 11V8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v3"/><path d="M2 11h20v6H2zM5 17v2M19 17v2"/>',
        'garden' => '<path d="M7 14h10l-1.5 7h-7z"/><path d="M12 14V9M12 9c0-3 2-5 5-5 0 3-2 5-5 5zM12 11c0-2.5-1.7-4-4-4 0 2.5 1.7 4 4 4z"/>',
        'shirt' => '<path d="M8 3L3 6l2 5 3-1v11h8V10l3 1 2-5-5-3a4 4 0 0 1-8 0z"/>',
        'sprout' => '<path d="M12 21V9M12 14c-3 0-5-2-5-5 3 0 5 2 5 5zM12 14c3 0 5-2 5-5-3 0-5 2-5 5zM12 9c-2-1.5-2-4.5 0-6 2 1.5 2 4.5 0 6z"/>',
        'job' => '<path d="M5 7h14a2 2 0 0 1 2 2v9a2 2 0 0 1 -2 2h-14a2 2 0 0 1 -2 -2v-9a2 2 0 0 1 2 -2z"/><path d="M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M3 13h18"/>',
        'wrench' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
        'music' => '<path d="M9 18V5l11-2v13"/><path d="M3 18a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M14 16a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/>',
        'paw' => '<path d="M5.2 10a1.8 1.8 0 1 0 3.6 0a1.8 1.8 0 1 0 -3.6 0"/><path d="M8.7 6.5a1.8 1.8 0 1 0 3.6 0a1.8 1.8 0 1 0 -3.6 0"/><path d="M12.7 6.5a1.8 1.8 0 1 0 3.6 0a1.8 1.8 0 1 0 -3.6 0"/><path d="M16.2 10a1.8 1.8 0 1 0 3.6 0a1.8 1.8 0 1 0 -3.6 0"/><path d="M12 12c-3 0-5.5 3.5-5.5 5.5 0 1.5 1.2 2.5 2.7 2.5 1 0 1.8-.5 2.8-.5s1.8.5 2.8.5c1.5 0 2.7-1 2.7-2.5 0-2-2.5-5.5-5.5-5.5z"/>',
        'kids' => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M9 10h.01M15 10h.01M9.5 15a3.5 3.5 0 0 0 5 0"/>',
        'sport' => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 3v18M3 12h18M5.6 5.6c3.5 3.5 3.5 9.3 0 12.8M18.4 5.6c-3.5 3.5-3.5 9.3 0 12.8"/>',
        'book' => '<path d="M4 19V5a2 2 0 0 1 2-2h14v14H6a2 2 0 0 0-2 2zm0 0a2 2 0 0 0 2 2h14"/>',
        'build' => '<path d="M5 3h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M3 9h18M3 15h18M12 9v6M8 3v6M16 3v6M8 15v6M16 15v6"/>',
        'tag' => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><path d="M6 7.5a1.5 1.5 0 1 0 3 0a1.5 1.5 0 1 0 -3 0"/>',
        'boat' => '<path d="M3 16l2 4h14l2-4z"/><path d="M12 3v13M12 4l6 9h-6"/>',
        'fish' => '<path d="M3 12c3-4 8-6 12-4 2 1 4 3 5 4-1 1-3 3-5 4-4 2-9 0-12-4z"/><path d="M3 12l-1-3v6zM15 11h.01"/>',
        'lamp' => '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-4 10.5c.6.6 1 1.4 1 2.5h6c0-1.1.4-1.9 1-2.5A6 6 0 0 0 12 3z"/>',
        'gamepad' => '<path d="M7 7h10a5 5 0 0 1 5 5v1a5 5 0 0 1 -5 5h-10a5 5 0 0 1 -5 -5v-1a5 5 0 0 1 5 -5z"/><path d="M7 11v3M5.5 12.5h3M15.5 12h.01M18 14h.01"/>',
        'camera' => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><path d="M8.5 13a3.5 3.5 0 1 0 7 0a3.5 3.5 0 1 0 -7 0"/>',
        'care' => '<path d="M9 3h6v4H9zM8 7h8v14H8z"/><path d="M8 12h8"/>',
        'grid' => '<path d="M4.5 3h4a1.5 1.5 0 0 1 1.5 1.5v4a1.5 1.5 0 0 1 -1.5 1.5h-4a1.5 1.5 0 0 1 -1.5 -1.5v-4a1.5 1.5 0 0 1 1.5 -1.5z"/><path d="M15.5 3h4a1.5 1.5 0 0 1 1.5 1.5v4a1.5 1.5 0 0 1 -1.5 1.5h-4a1.5 1.5 0 0 1 -1.5 -1.5v-4a1.5 1.5 0 0 1 1.5 -1.5z"/><path d="M4.5 14h4a1.5 1.5 0 0 1 1.5 1.5v4a1.5 1.5 0 0 1 -1.5 1.5h-4a1.5 1.5 0 0 1 -1.5 -1.5v-4a1.5 1.5 0 0 1 1.5 -1.5z"/><path d="M15.5 14h4a1.5 1.5 0 0 1 1.5 1.5v4a1.5 1.5 0 0 1 -1.5 1.5h-4a1.5 1.5 0 0 1 -1.5 -1.5v-4a1.5 1.5 0 0 1 1.5 -1.5z"/>',
        'flame' => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.07-2.14-.22-4.05 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.15.43-2.29 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
        'gear' => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><path d="M9 12a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/>',
        'info' => '<path d="M3 12a9 9 0 1 0 18 0a9 9 0 1 0 -18 0"/><path d="M12 16v-4M12 8h.01"/>',
        'gift' => '<path d="M4.5 8h15a1 1 0 0 1 1 1v2a1 1 0 0 1 -1 1h-15a1 1 0 0 1 -1 -1v-2a1 1 0 0 1 1 -1z"/><path d="M5 12v8h14v-8M12 8v12"/><path d="M12 8c-1.5-3-5-3.5-5-1.2C7 8 9 8 12 8Zm0 0c1.5-3 5-3.5 5-1.2C17 8 15 8 12 8Z"/>',
        'pin' => '<path d="M12 21s-7-6.5-7-12a7 7 0 0 1 14 0c0 5.5-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/>',
        'image' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>',
        'heart' => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
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

    /** Part of the slug => icon, first match wins. Keys of up to 2 letters only match where a slug word starts ("tv" is not in "stvari"), as in the apps. */
    private const RULES = [
        'plovn' => 'boat',
        'camac' => 'boat',
        'ribolov' => 'fish',
        'rasvet' => 'lamp',
        'konzol' => 'gamepad',
        'igric' => 'gamepad',
        'foto-aparat' => 'camera',
        'kamere' => 'camera',
        'nega-i-licna' => 'care',
        'higijen' => 'care',
        'elektro-i' => 'lamp',
        'kuca-i-basta' => 'garden',
        'aparat' => 'washer',
        'bela-tehn' => 'washer',
        'ljubim' => 'paw',
        'zivotinj' => 'paw',
        'biciklisticka-odec' => 'shirt',
        'bicikl' => 'bike',
        'motor' => 'moto',
        'moto' => 'moto',
        'skuter' => 'moto',
        'kamion' => 'truck',
        'kombi' => 'truck',
        'teretn' => 'truck',
        'auto-del' => 'gear',
        'rezervn' => 'gear',
        'auto' => 'car',
        'vozil' => 'car',
        'hecbek' => 'car',
        'karavan' => 'car',
        'limuzin' => 'car',
        'dzip' => 'car',
        'stan' => 'building',
        'plac' => 'land',
        'zemlj' => 'land',
        'parcel' => 'land',
        'kuc' => 'house',
        'nekretn' => 'house',
        'vikend' => 'house',
        'lokal' => 'building',
        'poslovni-prostor' => 'building',
        'bast' => 'garden',
        'cvec' => 'garden',
        'mobil' => 'phone',
        'telefon' => 'phone',
        'racunar' => 'laptop',
        'laptop' => 'laptop',
        'kompjut' => 'laptop',
        'elektron' => 'tv',
        'audio' => 'tv',
        'tv' => 'tv',
        'foto' => 'tv',
        'namest' => 'sofa',
        'odec' => 'shirt',
        'obuc' => 'shirt',
        'moda' => 'shirt',
        'garderob' => 'shirt',
        'sat' => 'shirt',
        'drva' => 'flame',
        'pelet' => 'flame',
        'poljopriv' => 'sprout',
        'stoc' => 'sprout',
        'stoka' => 'sprout',
        'mehaniz' => 'sprout',
        'voce' => 'sprout',
        'ogrev' => 'flame',
        'posao' => 'job',
        'poslov' => 'job',
        'uslug' => 'wrench',
        'majstor' => 'wrench',
        'servis' => 'wrench',
        'muzi' => 'music',
        'instrument' => 'music',
        'dec' => 'kids',
        'beb' => 'kids',
        'igrack' => 'kids',
        'sport' => 'sport',
        'fitnes' => 'sport',
        'knjig' => 'book',
        'gradj' => 'build',
        'alat' => 'wrench',
        'materijal' => 'build',
        'besplatn' => 'gift',
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
                if (self::matches($slug, $needle)) {
                    return $icon;
                }
            }
            $category = $category->parent_id ? $catalog->category((int) $category->parent_id) : null;
        }

        return 'tag';
    }

    private static function matches(string $slug, string $needle): bool
    {
        if (strlen($needle) > 2) {
            return str_contains($slug, $needle);
        }

        return str_starts_with($slug, $needle) || str_contains($slug, '-'.$needle);
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
