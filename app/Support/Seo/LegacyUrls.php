<?php

namespace App\Support\Seo;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Http\Request;

/**
 * Pravila za stare adrese sa WordPress/Listivo/WooCommerce/Yoast sajta (09-deploy-seo.md, odeljak 1).
 * Vraća odredište ili null kad pravilo ne važi.
 */
class LegacyUrls
{
    /**
     * @return array{to: string, code: int, rule: string, query: array<string, mixed>}|null
     */
    public function resolve(Request $request): ?array
    {
        $raw = '/'.ltrim($request->getPathInfo(), '/');
        $path = mb_strtolower(rawurldecode($raw));
        $query = $request->query->all();
        $clean = Url::withoutTracking($query);

        // REST API stare verzije: isti metod i telo (308), upit ostaje ceo.
        if (preg_match('#^/wp-json/loglasi/v1(/.*)?$#i', $raw, $m)) {
            return $this->hit('/api/v1'.rtrim($m[1] ?? '', '/'), 308, 'wp-json', $query, raw: true);
        }

        // Stari sitemapovi (Yoast i WordPress core) -> jedan novi indeks.
        if ($path === '/sitemap_index.xml'
            || preg_match('#^/[a-z0-9_]+-sitemap\d*\.xml$#', $path)
            || preg_match('#^/wp-sitemap(-[a-z0-9_-]+)?\.xml$#', $path)) {
            return $this->hit('/sitemap.xml', 301, 'stari-sitemap');
        }

        if (preg_match('#^/wp-admin(/.*)?$#', $path)) {
            return $this->hit(config('seo.targets.wp_admin'), 301, 'wp-admin');
        }

        if ($path === '/wp-login.php') {
            return $this->hit(config('seo.targets.wp_login'), 301, 'wp-login');
        }

        // Old data deletion page (its slug had a typo) and the earlier short address.
        if (in_array(rtrim($path, '/'), ['/birsanje-licnih-podataka', '/brisanje-podataka'], true)) {
            return $this->hit('/brisanje-licnih-podataka/', 301, 'brisanje-podataka');
        }

        // The theme's listing comparison page does not exist any more.
        if (rtrim($path, '/') === '/uporedite-oglase') {
            return $this->hit('/oglasi/', 301, 'uporedite-oglase');
        }

        // Old account panel of the WordPress theme -> our account pages.
        if (preg_match('#^/panel(/.*)?$#', $path, $m)) {
            return $this->panel(trim($m[1] ?? '', '/'), $clean);
        }

        // Subcategory in the old WordPress form, with a comma: /oglasi/{parent},{child}/
        if (preg_match('#^/oglasi/([a-z0-9-]+(?:,[a-z0-9-]+)+)/?$#', $path, $m)) {
            return $this->categoryTarget($m[1], 'kategorija-zarez');
        }

        // Page as a parameter (old ?paginacija=N, WordPress ?paged=N and ?page=N) -> /.../page/N/, one address per page.
        foreach (['paginacija', 'paged', 'page'] as $key) {
            if (isset($clean[$key]) && preg_match('#^/oglasi(/[a-z0-9-]+){0,2}/?$#', $path)) {
                $page = is_scalar($clean[$key]) && ctype_digit((string) $clean[$key]) ? (int) $clean[$key] : 1;
                $rest = array_diff_key($clean, ['paginacija' => true, 'paged' => true, 'page' => true]);
                $base = rtrim($path, '/').'/';

                return $this->hit($page > 1 ? $base.'page/'.$page.'/' : $base, 301, 'paginacija', $rest);
            }
        }

        // WooCommerce ostaci.
        if (preg_match('#^/product/[^/]+/?$#', $path)) {
            return $this->hit(config('seo.targets.woo_product'), 301, 'woo-product');
        }

        if (preg_match('#^/(product-category|product-tag)/.+$#', $path) || preg_match('#^/shop/?$#', $path)) {
            return $this->hit(config('seo.targets.woo_shop'), 301, 'woo-shop');
        }

        if (preg_match('#^/(cart|checkout|naplata)(/.*)?$#', $path)) {
            return $this->hit(config('seo.targets.woo_cart'), 301, 'woo-korpa');
        }

        if (preg_match('#^/my-account(/.*)?$#', $path)) {
            return $this->hit(config('seo.targets.woo_account'), 301, 'woo-nalog');
        }

        if (rtrim($path, '/') === '/oglasi') {
            return $this->archiveQuery($clean);
        }

        if ($path === '/') {
            return $this->rootQuery($clean);
        }

        return null;
    }

    /** /oglasi/?kategorija=x i /oglasi/?marka-automobila=y */
    private function archiveQuery(array $clean): ?array
    {
        $keys = array_keys($clean);

        if (isset($clean['marka-automobila']) && array_diff($keys, ['marka-automobila', 'kategorija']) === []) {
            $make = is_string($clean['marka-automobila']) ? static::make($clean['marka-automobila']) : null;

            // The make stays as a filter (noindex list), so the visitor sees the same cars as before.
            return $this->hit(config('seo.targets.marka_automobila'), 301, 'marka-automobila', $make ? ['f' => ['marka_automobila' => $make]] : []);
        }

        if ($keys === ['kategorija'] && is_string($clean['kategorija'])) {
            return $this->categoryTarget($clean['kategorija'], 'kategorija-param');
        }

        return null;
    }

    /** Last slug of a comma list (the subcategory), if it is an active category; otherwise /oglasi/. */
    private function categoryTarget(string $slugs, string $rule): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', mb_strtolower($slugs)))));
        $slug = (string) end($parts);
        $exists = $slug !== '' && Category::query()->where('slug', $slug)->where('is_active', true)->exists();

        return $this->hit($exists ? '/oglasi/'.$slug.'/' : config('seo.targets.kategorija_unknown'), 301, $rule);
    }

    /** /panel/ and its tabs (?tab=create, ?tab=messages, ...). */
    private function panel(string $sub, array $clean): array
    {
        $tab = $sub !== '' ? $sub : (is_string($clean['tab'] ?? null) ? mb_strtolower($clean['tab']) : '');
        $targets = config('seo.targets.panel_tabs', []);

        return $this->hit($targets[$tab] ?? config('seo.targets.panel'), 301, 'panel');
    }

    /** /?attachment_id=N (strana priloga) i /?p=N (kratki WordPress link oglasa). */
    private function rootQuery(array $clean): ?array
    {
        if (isset($clean['attachment_id']) && ctype_digit((string) $clean['attachment_id'])) {
            $image = ListingImage::query()->where('wp_attachment_id', (int) $clean['attachment_id'])
                ->whereNotNull('listing_id')->with('listing:id,slug')->first();

            if ($image?->listing) {
                return $this->hit('/oglas/'.$image->listing->slug.'/', 301, 'wp-prilog');
            }
        }

        if (isset($clean['p']) && ctype_digit((string) $clean['p'])
            && array_diff(array_keys($clean), ['p', 'post_type']) === []) {
            $slug = Listing::query()->where('wp_id', (int) $clean['p'])->value('slug');

            if ($slug) {
                return $this->hit('/oglas/'.$slug.'/', 301, 'wp-kratki-link');
            }
        }

        return null;
    }

    /** Our make value for an old WordPress make slug (catalog data, legacy_makes), or null. */
    private static function make(string $slug): ?string
    {
        static $makes = null;
        $makes ??= (require database_path('seeders/data/catalog/cars.php'))['legacy_makes'] ?? [];

        return $makes[mb_strtolower(trim($slug))] ?? null;
    }

    private function hit(string $to, int $code, string $rule, array $query = [], bool $raw = false): array
    {
        return ['to' => $to, 'code' => $code, 'rule' => $rule, 'query' => $query, 'raw' => $raw];
    }
}
