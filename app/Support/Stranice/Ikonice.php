<?php

namespace App\Support\Stranice;

use Illuminate\Support\HtmlString;

/**
 * Linijske SVG ikonice (24×24) za zaglavlje, navigaciju i sadržajne strane.
 * Bez fonta sa ikonicama: svaka ide direktno u HTML, uvek dekorativna (aria-hidden).
 */
class Ikonice
{
    private const PATHS = [
        'home' => '<path d="M3.5 10.5 12 4l8.5 6.5"/><path d="M6 9v10.5h4.5V14h3v5.5H18V9"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.5 4.5"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'chat' => '<path d="M4 5.5h16v10H9l-4.5 3.5V5.5Z"/><path d="M8.5 9.5h7M8.5 12.5h4"/>',
        'user' => '<circle cx="12" cy="8.5" r="3.8"/><path d="M4.5 20c.9-3.6 3.8-5.5 7.5-5.5s6.6 1.9 7.5 5.5"/>',
        'filter' => '<path d="M4 6h16M7 12h10M10 18h4"/>',
        'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
        'minus' => '<path d="M6 12h12"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'chevron' => '<path d="m6 9 6 6 6-6"/>',
        'image' => '<rect x="3.5" y="5" width="17" height="14" rx="2"/><circle cx="9" cy="10" r="1.8"/><path d="m4 17 5-4.5 3.5 3 3-2.5 4.5 4"/>',
        'badge' => '<path d="M12 3.5 14.2 5l2.7-.2.9 2.5 2.2 1.6-.8 2.6.8 2.6-2.2 1.6-.9 2.5-2.7-.2L12 20.5 9.8 19l-2.7.2-.9-2.5L4 15.1l.8-2.6L4 9.9l2.2-1.6.9-2.5 2.7.2Z"/><path d="m8.8 12.2 2.2 2.2 4.2-4.4"/>',
        'gift' => '<rect x="3.5" y="8" width="17" height="4" rx="1"/><path d="M5 12v8h14v-8M12 8v12M12 8c-1.5-3-5-3.5-5-1.2C7 8 9.5 8 12 8Zm0 0c1.5-3 5-3.5 5-1.2C17 8 14.5 8 12 8Z"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'pin' => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21Z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'grid' => '<rect x="4" y="4" width="7" height="7" rx="1.5"/><rect x="13" y="4" width="7" height="7" rx="1.5"/><rect x="4" y="13" width="7" height="7" rx="1.5"/><rect x="13" y="13" width="7" height="7" rx="1.5"/>',
        'phone' => '<path d="M6.6 3.8 9 3.5l1.6 4-1.9 1.3a10.5 10.5 0 0 0 5.5 5.5l1.3-1.9 4 1.6-.3 2.4A2.5 2.5 0 0 1 16.7 18.5 13.2 13.2 0 0 1 4.5 7.3 2.5 2.5 0 0 1 6.6 3.8Z"/>',
        'chart' => '<path d="M4.5 19.5h15"/><path d="M7.5 16v-4M12 16V8M16.5 16v-6.5"/>',
        'mail' => '<rect x="3.5" y="5.5" width="17" height="13" rx="2"/><path d="m4.5 7 7.5 6 7.5-6"/>',
        'copy' => '<rect x="8.5" y="8.5" width="11" height="11" rx="2"/><path d="M15.5 8.5V6a1.5 1.5 0 0 0-1.5-1.5H6A1.5 1.5 0 0 0 4.5 6v8A1.5 1.5 0 0 0 6 15.5h2.5"/>',
        'bank' => '<path d="M3.5 9.5 12 4.5l8.5 5"/><path d="M5.5 10v7M10 10v7M14 10v7M18.5 10v7M3.5 19.5h17"/>',
        'whatsapp' => '<path d="M4.5 19.5 5.6 16A7.8 7.8 0 1 1 8.4 18.6Z"/><path d="M9.3 8.7c.2-.4.5-.4.8-.4l.6 1.4-.5.8c.5 1 1.3 1.8 2.3 2.3l.8-.5 1.4.6c0 .4 0 .7-.4.9-1.4.9-5.6-2.8-5-5.1Z"/>',
        'viber' => '<path d="M6.5 4.5h11a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-6l-4 3v-3h-1a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2Z"/><path d="M10 8.5c.8 2.3 2.2 3.7 4.5 4.5"/>',
        'info' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 11v5M12 8h.01"/>',
    ];

    public static function svg(string $name, int $size = 24, ?string $stroke = null): HtmlString
    {
        if (! isset(self::PATHS[$name])) {
            return new HtmlString('');
        }

        $stroke ??= $name === 'plus' ? '2.4' : '1.8';

        return new HtmlString(sprintf(
            '<svg class="lg-icon lg-icon--%1$s" width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="%3$s" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%4$s</svg>',
            $name,
            $size,
            $stroke,
            self::PATHS[$name],
        ));
    }
}
