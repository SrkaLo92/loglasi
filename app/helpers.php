<?php

if (! function_exists('asset_v')) {
    /** Path to a file in public/ with a version (modification time), for a long browser cache. */
    function asset_v(string $path): string
    {
        $file = public_path($path);
        $version = is_file($file) ? filemtime($file) : null;

        return asset($path).($version ? '?v='.$version : '');
    }
}

if (! function_exists('url_slash')) {
    /** Absolute URL with a trailing slash, as on the old site: /oglasi/automobili/ */
    function url_slash(string $path = '/'): string
    {
        $path = '/'.trim($path, '/');

        return rtrim(url($path), '/').'/';
    }
}
