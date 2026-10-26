<?php

namespace App\Support\Pages;

use Illuminate\Support\HtmlString;

/**
 * Line SVG icons (24×24) for the header, navigation and content pages.
 * No icon font: each goes straight into the HTML, always decorative (aria-hidden).
 */
class SiteIcons
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
        'list' => '<rect x="4" y="4.5" width="16" height="15" rx="2"/><path d="M8 9h8M8 12.5h8M8 16h5"/>',
        'heart' => '<path d="M12 19.5s-7.5-4.4-7.5-9.7A4.3 4.3 0 0 1 12 7.3a4.3 4.3 0 0 1 7.5 2.5c0 5.3-7.5 9.7-7.5 9.7Z"/>',
        'star' => '<path d="m12 4 2.4 5 5.4.6-4 3.7 1.1 5.4L12 16l-4.9 2.7 1.1-5.4-4-3.7 5.4-.6Z"/>',
        'bell' => '<path d="M6.5 16.5V11a5.5 5.5 0 0 1 11 0v5.5l1.5 1.5H5Z"/><path d="M10 20.5h4"/>',
        'bookmark' => '<path d="M7 4.5h10v15l-5-3.5-5 3.5Z"/>',
        'receipt' => '<path d="M6 3.5h12v17l-2-1.3-2 1.3-2-1.3-2 1.3-2-1.3-2 1.3Z"/><path d="M9 8.5h6M9 12h6M9 15.5h3.5"/>',
        'settings' => '<circle cx="12" cy="12" r="3"/><path d="M12 3.5v2.2M12 18.3v2.2M20.5 12h-2.2M5.7 12H3.5M18 6l-1.6 1.6M7.6 16.4 6 18M18 18l-1.6-1.6M7.6 7.6 6 6"/>',
        'logout' => '<path d="M14.5 4.5h-8a1.5 1.5 0 0 0-1.5 1.5v12a1.5 1.5 0 0 0 1.5 1.5h8"/><path d="M10.5 12h10M17 8.5l3.5 3.5-3.5 3.5"/>',
        'shop' => '<path d="M4.5 9.5 6 4.5h12l1.5 5"/><path d="M4.5 9.5a2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0"/><path d="M5.5 11.5v8h13v-8M10 19.5v-4.5h4v4.5"/>',
        'camera' => '<path d="M4 8h3.5L9 5.5h6L16.5 8H20v11H4Z"/><circle cx="12" cy="13" r="3.3"/>',
        'eye' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.8"/>',
        'alert' => '<path d="M12 4 21 19.5H3Z"/><path d="M12 10v4.5M12 17h.01"/>',
        'lock' => '<rect x="5" y="10.5" width="14" height="9.5" rx="2"/><path d="M8 10.5V8a4 4 0 0 1 8 0v2.5"/>',
        'trash' => '<path d="M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13"/>',
        'chevron-r' => '<path d="m9 6 6 6-6 6"/>',
        'chevron-l' => '<path d="m15 6-6 6 6 6"/>',
        'external' => '<path d="M13.5 4.5h6v6M19.5 4.5 11 13"/><path d="M17.5 13.5v5a1 1 0 0 1-1 1h-11a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1h5"/>',
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
