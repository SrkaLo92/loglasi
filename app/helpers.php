<?php

if (! function_exists('asset_v')) {
    /** Putanja do fajla iz public/ sa verzijom (vreme izmene), za dug keš u pregledaču. */
    function asset_v(string $path): string
    {
        $file = public_path($path);
        $version = is_file($file) ? filemtime($file) : null;

        return asset($path).($version ? '?v='.$version : '');
    }
}

if (! function_exists('url_slash')) {
    /** Apsolutni URL sa kosom crtom na kraju, kao na sadašnjem sajtu: /oglasi/automobili/ */
    function url_slash(string $path = '/'): string
    {
        $path = '/'.trim($path, '/');

        return rtrim(url($path), '/').'/';
    }
}
