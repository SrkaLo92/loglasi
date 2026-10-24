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

        // WooCommerce ostaci.
        if (preg_match('#^/product/[^/]+/?$#', $path)) {
            return $this->hit(config('seo.targets.woo_product'), 301, 'woo-product');
        }

        if (preg_match('#^/(product-category|product-tag)/.+$#', $path) || preg_match('#^/shop/?$#', $path)) {
            return $this->hit(config('seo.targets.woo_shop'), 301, 'woo-shop');
        }

        if (preg_match('#^/(cart|checkout|my-account)(/.*)?$#', $path)) {
            return $this->hit(config('seo.targets.woo_account'), 301, 'woo-nalog');
        }

        // Feedovi koji više ne postoje: komentari i feed jednog oglasa ili kategorije.
        if (preg_match('#^/comments/feed/?$#', $path)) {
            return $this->hit('/feed/', 301, 'feed');
        }

        if (preg_match('#^/(oglas|oglasi)/([^/]+)/feed/?$#', $path, $m)) {
            return $this->hit('/'.$m[1].'/'.$m[2].'/', 301, 'feed');
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
            return $this->hit(config('seo.targets.marka_automobila'), 301, 'marka-automobila');
        }

        if ($keys === ['kategorija'] && is_string($clean['kategorija'])) {
            $slug = trim($clean['kategorija']);
            $exists = $slug !== '' && Category::query()->where('slug', $slug)->where('is_active', true)->exists();

            return $exists
                ? $this->hit('/oglasi/'.$slug.'/', 301, 'kategorija-param')
                : $this->hit(config('seo.targets.kategorija_unknown'), 301, 'kategorija-param');
        }

        return null;
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

    private function hit(string $to, int $code, string $rule, array $query = [], bool $raw = false): array
    {
        return ['to' => $to, 'code' => $code, 'rule' => $rule, 'query' => $query, 'raw' => $raw];
    }
}
