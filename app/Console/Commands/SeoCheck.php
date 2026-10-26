<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Location;
use App\Models\User;
use App\Support\Seo;
use App\Support\Seo\Url;
use App\Support\Seo\UrlTables;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Checks indexed URLs from a Google Search Console export (09-deploy-seo.md, section 9):
 * for each URL the expected status by pattern (200 / 301 to the target / 404 / 410), robots and canonical.
 *
 *   php artisan seo:check raw-indeksirane.tsv http://loglasirs.local --csv=rezultat.csv
 */
class SeoCheck extends Command
{
    protected $signature = 'seo:check
        {file : TSV export from Search Console, first column is the URL}
        {base_url : site to check, e.g. http://loglasirs.local or https://loglasi.rs}
        {--csv= : where to write the result (CSV)}
        {--pause=0.3 : pause between requests in seconds}
        {--no-cache : add ?nc= to bypass the cache}
        {--sitemap : also check every URL in /sitemap.xml (must be 200 and indexable)}
        {--gone : also check every address in the 410 table (must return 410 and not be in the sitemap)}
        {--errors-only : show only the failures in the table}';

    protected $description = 'Check URLs from a Search Console export: expected status, redirect, robots and canonical';

    /** Old name, kept so existing server scripts keep working. */
    protected $aliases = ['seo:provera'];

    private string $base;

    /** @var list<string> */
    private array $sitemapUrls = [];

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file) || ! is_readable($file)) {
            $this->error('Fajl ne postoji ili ne može da se pročita: '.$file);

            return self::INVALID;
        }

        $this->base = rtrim((string) $this->argument('base_url'), '/');

        if (! preg_match('#^https?://[^/]+$#i', $this->base)) {
            $this->error('base_url mora biti oblika https://domen (bez putanje).');

            return self::INVALID;
        }

        $rows = [];

        foreach ($this->readUrls($file) as $url) {
            $rows[] = $this->check($url, $this->expect($url));
        }

        if ($this->option('sitemap') || $this->option('gone')) {
            $this->sitemapUrls = $this->collectSitemap();
        }

        if ($this->option('sitemap')) {
            foreach ($this->sitemapUrls as $url) {
                $rows[] = $this->check($url, ['pattern' => 'sitemap', 'status' => 200, 'index' => true]);
            }
        }

        if ($this->option('gone')) {
            $inSitemap = array_flip(array_map(fn ($u) => UrlTables::key((string) parse_url($u, PHP_URL_PATH)), $this->sitemapUrls));

            foreach (array_keys(UrlTables::gone()) as $path) {
                $row = $this->check($path, ['pattern' => '410 lista', 'status' => 410]);

                if (isset($inSitemap[UrlTables::key($path)])) {
                    $row['ok'] = false;
                    $row['napomena'] = trim($row['napomena'].' u sitemapu');
                }

                $rows[] = $row;
            }
        }

        $this->report($rows);

        return collect($rows)->every(fn ($r) => $r['ok']) ? self::SUCCESS : self::FAILURE;
    }

    /** @return list<string> URLs from the first column, without the header and duplicates. */
    private function readUrls(string $file): array
    {
        $urls = [];

        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $first = trim((string) Str::before($line, "\t"), " \"\u{FEFF}");

            if (preg_match('#^(https?://|/)#i', $first)) {
                $urls[$first] = true;
            }
        }

        return array_keys($urls);
    }

    /** Path and query of the URL (the domain is replaced by the address being checked). */
    private function parts(string $url): array
    {
        $parts = parse_url($url);
        $query = [];
        parse_str($parts['query'] ?? '', $query);

        return [$parts['path'] ?? '/', $query, $parts['query'] ?? ''];
    }

    /**
     * Expected behaviour by the pattern from table 9.2.
     *
     * @return array{pattern: string, status: ?int, target?: ?string, index?: ?bool, xrobots?: bool, note?: string}
     */
    private function expect(string $url): array
    {
        [$path, $query] = $this->parts($url);
        $clean = Url::withoutTracking($query);
        $lower = mb_strtolower(rawurldecode($path));
        $norm = Url::normalizePath($lower);
        $targets = config('seo.targets');

        if (UrlTables::findGone($path) !== null) {
            return ['pattern' => '410 lista', 'status' => 410];
        }

        if ($row = UrlTables::findRedirect($path, $query)) {
            return ['pattern' => 'tabela preusmerenja', 'status' => $row['code'], 'target' => $row['to']];
        }

        if (preg_match('#^/wp-json/loglasi/v1(/.*)?$#', $lower, $m)) {
            return ['pattern' => 'stari API', 'status' => 308, 'target' => '/api/v1'.rtrim($m[1] ?? '', '/')];
        }

        if ($lower === '/sitemap_index.xml' || preg_match('#^/[a-z0-9_]+-sitemap\d*\.xml$#', $lower) || preg_match('#^/wp-sitemap(-[a-z0-9_-]+)?\.xml$#', $lower)) {
            return ['pattern' => 'stari sitemap', 'status' => 301, 'target' => '/sitemap.xml'];
        }

        if (preg_match('#^/wp-admin(/.*)?$#', $lower)) {
            return ['pattern' => 'wp-admin', 'status' => 301, 'target' => $targets['wp_admin']];
        }

        if ($lower === '/wp-login.php') {
            return ['pattern' => 'wp-login', 'status' => 301, 'target' => $targets['wp_login']];
        }

        if (preg_match('#^/product/[^/]+/?$#', $lower)) {
            return ['pattern' => '/product/', 'status' => 301, 'target' => $targets['woo_product']];
        }

        if (preg_match('#^/(product-category|product-tag)/.+$#', $lower) || preg_match('#^/shop/?$#', $lower)) {
            return ['pattern' => 'Woo prodavnica', 'status' => 301, 'target' => $targets['woo_shop']];
        }

        if (preg_match('#^/(cart|checkout|naplata)(/.*)?$#', $lower)) {
            return ['pattern' => 'Woo korpa', 'status' => 301, 'target' => $targets['woo_cart']];
        }

        if (preg_match('#^/my-account(/.*)?$#', $lower)) {
            return ['pattern' => 'Woo nalog', 'status' => 301, 'target' => $targets['woo_account']];
        }

        if (preg_match('#^/panel(/.*)?$#', $lower, $m)) {
            $tab = trim($m[1] ?? '', '/') ?: (is_string($clean['tab'] ?? null) ? mb_strtolower($clean['tab']) : '');

            return ['pattern' => '/panel/', 'status' => 301, 'target' => $targets['panel_tabs'][$tab] ?? $targets['panel']];
        }

        if (preg_match('#^/oglasi/([a-z0-9-]+(?:,[a-z0-9-]+)+)/?$#', $lower, $m)) {
            return ['pattern' => 'kategorija sa zarezom', 'status' => 301, 'target' => $this->categoryTarget($m[1])];
        }

        if (isset($clean['paginacija']) && str_starts_with($norm, '/oglasi/')) {
            return ['pattern' => '?paginacija=', 'status' => 301, 'target' => null];
        }

        if ($norm === '/stranica-404/') {
            return ['pattern' => '/stranica-404/', 'status' => 404];
        }

        if (preg_match('#(^|/)feed/$#', $norm) || preg_match('#^/(blog|listivo_template)(/|$)#', $norm)) {
            return ['pattern' => 'feed / blog', 'status' => 410];
        }

        // Kept on purpose (LegacyUrls): the old data deletion page with a typo and the theme's comparison page.
        if (in_array($norm, ['/birsanje-licnih-podataka/', '/brisanje-podataka/'], true)) {
            return ['pattern' => 'stara strana', 'status' => 301, 'target' => '/brisanje-licnih-podataka/'];
        }
        if ($norm === '/uporedite-oglase/') {
            return ['pattern' => 'stara strana', 'status' => 301, 'target' => '/oglasi/'];
        }

        if ($norm === '/oglasi/' && $clean !== []) {
            return $this->expectArchiveQuery($clean);
        }

        if (! str_ends_with($path, '/') && ! Url::hasExtension($path)) {
            return ['pattern' => 'bez kose crte', 'status' => 301, 'target' => $path.'/'];
        }

        if ($norm === '/' && $clean !== []) {
            if (isset($clean['attachment_id']) || isset($clean['p'])) {
                return ['pattern' => 'stari WP link', 'status' => 301, 'target' => null];
            }

            return ['pattern' => 'pretraga', 'status' => 200, 'index' => false];
        }

        if ($clean !== [] && array_diff(array_keys($clean), config('seo.page_params', [])) === []) {
            return ['pattern' => 'paginacija', 'status' => 200, 'index' => true];
        }

        if ($clean !== []) {
            return ['pattern' => 'filter', 'status' => 200, 'index' => false];
        }

        if (preg_match('#/page/\d+/$#', $norm)) {
            // Pages 2+ are indexable with a self canonical.
            return ['pattern' => 'paginacija', 'status' => 200, 'index' => true];
        }

        if ($norm === '/oglasi/') {
            return ['pattern' => '/oglasi/', 'status' => 200, 'index' => Seo::activeListings()->exists()];
        }

        if (preg_match('#^/oglas/([^/]+)/?$#', rawurldecode($path), $m)) {
            return $this->expectListing($m[1]);
        }

        if (preg_match('#^/oglasi/([^/]+)/(?:([^/]+)/)?$#', $norm, $m)) {
            return $this->expectCategory($m[1], $m[2] ?? null);
        }

        if (preg_match('#^/korisnik/([^/]+)/$#', $norm, $m)) {
            return $this->expectUser($m[1]);
        }

        if (in_array($norm, array_map(fn ($p) => Url::normalizePath($p), config('seo.pages', [])), true)) {
            return ['pattern' => 'strana', 'status' => 200, 'index' => true];
        }

        if (Str::is(config('seo.noindex_paths', []), trim($norm, '/'))) {
            return ['pattern' => 'strana naloga', 'status' => 200, 'index' => false];
        }

        return ['pattern' => 'ostalo', 'status' => 200, 'index' => null];
    }

    /** Same rule as LegacyUrls: the last slug of a comma list, if it is an active category. */
    private function categoryTarget(string $slugs): string
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', mb_strtolower($slugs)))));
        $slug = (string) end($parts);
        $exists = $slug !== '' && Category::query()->where('slug', $slug)->where('is_active', true)->exists();

        return $exists ? '/oglasi/'.$slug.'/' : config('seo.targets.kategorija_unknown');
    }

    private function expectArchiveQuery(array $clean): array
    {
        $keys = array_keys($clean);
        $pageKeys = config('seo.page_params', []);

        if (isset($clean['marka-automobila']) && array_diff($keys, ['marka-automobila', 'kategorija']) === []) {
            return ['pattern' => '?marka-automobila=', 'status' => 301, 'target' => config('seo.targets.marka_automobila')];
        }

        if ($keys === ['kategorija'] && is_string($clean['kategorija'])) {
            return ['pattern' => '?kategorija=', 'status' => 301, 'target' => $this->categoryTarget($clean['kategorija'])];
        }

        if (isset($clean['paginacija'])) {
            return ['pattern' => '?paginacija=', 'status' => 301, 'target' => null];
        }

        if (array_diff($keys, $pageKeys) === []) {
            return ['pattern' => 'paginacija', 'status' => 200, 'index' => true];
        }

        return ['pattern' => 'filter', 'status' => 200, 'index' => false];
    }

    private function expectListing(string $slug): array
    {
        $listing = Listing::withTrashed()->where('slug', $slug)->first();

        if ($listing === null) {
            return ['pattern' => '/oglas/ (nema ga)', 'status' => 410];
        }

        if ($listing->trashed()) {
            return ['pattern' => '/oglas/ (obrisan)', 'status' => 410];
        }

        if (Seo::isActive($listing)) {
            return ['pattern' => '/oglas/ (aktivan)', 'status' => 200, 'index' => true];
        }

        if ($listing->expires_at !== null && $listing->expires_at->isPast() || $listing->status->value === 'expired') {
            return ['pattern' => '/oglas/ (istekao)', 'status' => 200, 'index' => false];
        }

        return ['pattern' => '/oglas/ ('.$listing->status->label().')', 'status' => null, 'note' => 'oglas nije javan: ručna provera'];
    }

    private function expectCategory(string $categorySlug, ?string $locationSlug): array
    {
        $category = Category::query()->where('slug', rawurldecode($categorySlug))->where('is_active', true)->first();

        if ($category === null) {
            return ['pattern' => '/oglasi/{kategorija}/ (nema je)', 'status' => null, 'note' => 'kategorija ne postoji: ručna provera'];
        }

        $ids = Category::query()->where('parent_id', $category->id)->pluck('id')->push($category->id);

        if ($locationSlug === null) {
            return ['pattern' => '/oglasi/{kategorija}/', 'status' => 200,
                'index' => Seo::activeListings()->whereIn('category_id', $ids)->exists()];
        }

        $location = Location::query()->where('slug', rawurldecode($locationSlug))->first();

        if ($location === null) {
            return ['pattern' => '/oglasi/{kategorija}/{mesto}/ (nema ga)', 'status' => null, 'note' => 'mesto ne postoji: ručna provera'];
        }

        $count = Seo::activeListings()->whereIn('category_id', $ids)->where('location_id', $location->id)->count();

        return ['pattern' => '/oglasi/{kategorija}/{mesto}/', 'status' => 200, 'index' => $count >= Seo::placeMinListings()];
    }

    private function expectUser(string $slug): array
    {
        $user = User::withTrashed()->with('firm')->where('slug', rawurldecode($slug))->first();

        if ($user === null) {
            return ['pattern' => '/korisnik/ (nema ga)', 'status' => null, 'note' => 'nalog nije u novoj bazi ni na 410 listi: ručna provera'];
        }

        if ($user->trashed()) {
            return ['pattern' => '/korisnik/ (obrisan)', 'status' => 410];
        }

        $firm = Seo::firmIndexable($user);

        return ['pattern' => $firm ? '/korisnik/ (firma)' : '/korisnik/ (privatni)', 'status' => 200, 'index' => $firm];
    }

    /** Sends a request without following redirects and compares it with the expected result. */
    private function check(string $url, array $expected): array
    {
        [$path, , $rawQuery] = $this->parts($url);
        $target = $this->base.$path.($rawQuery !== '' ? '?'.$rawQuery : '');

        if ($this->option('no-cache')) {
            $target .= (str_contains($target, '?') ? '&' : '?').'nc='.time();
        }

        $row = [
            'url' => $url, 'obrazac' => $expected['pattern'], 'ocekivano' => $this->describe($expected),
            'dobijeno' => '', 'odrediste' => '', 'robots' => '', 'canonical' => '', 'ok' => false,
            'napomena' => $expected['note'] ?? '',
        ];

        $response = $this->fetch($target);
        $this->pause();

        if ($response === null) {
            $row['dobijeno'] = 'greška veze';

            return $row;
        }

        $status = $response->status();
        $row['dobijeno'] = (string) $status;
        $problems = [];

        if ($expected['status'] === null) {
            $problems[] = 'ručna provera';
        } elseif ($status !== $expected['status']) {
            $problems[] = 'status';
        }

        if ($status >= 300 && $status < 400) {
            $location = $this->absolute((string) $response->header('Location'), $target);
            $row['odrediste'] = $location;

            if (array_key_exists('target', $expected) && $expected['target'] !== null
                && ! $this->samePath($location, $expected['target'])) {
                $problems[] = 'odredište';
            }

            $next = $this->fetch($location);
            $this->pause();

            if ($next === null || $next->status() !== 200) {
                $problems[] = $next && $next->status() >= 300 && $next->status() < 400 ? 'lanac preusmerenja' : 'odredište nije 200';
            }
        }

        if ($status === 200) {
            [$robots, $canonical] = $this->readHead($response);
            $row['robots'] = $robots;
            $row['canonical'] = $canonical;
            $noindex = str_contains(strtolower($robots), 'noindex');

            if (($expected['xrobots'] ?? false) && ! str_contains(strtolower((string) $response->header('X-Robots-Tag')), 'noindex')) {
                $problems[] = 'nema X-Robots-Tag';
            }

            if (($expected['index'] ?? null) === true) {
                if ($noindex) {
                    $problems[] = 'noindex';
                }

                $base = config('seo.base_url');

                if (! str_starts_with($canonical, $base.'/') || ! $this->samePath($canonical, $path)) {
                    $problems[] = 'canonical';
                }
            } elseif (($expected['index'] ?? null) === false && ! $noindex) {
                $problems[] = 'nema noindex';
            }
        }

        if ($status >= 500) {
            $problems[] = '5xx';
        }

        $row['ok'] = $problems === [];
        $row['napomena'] = trim($row['napomena'].' '.implode(', ', array_unique($problems)));

        return $row;
    }

    private function fetch(string $url): ?Response
    {
        try {
            return Http::withoutRedirecting()
                ->withUserAgent('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')
                ->timeout(20)
                ->get($url);
        } catch (\Throwable) {
            return null;
        }
    }

    /** robots (meta + X-Robots-Tag) and canonical from the HTML. */
    private function readHead(Response $response): array
    {
        $html = (string) $response->body();
        $robots = [];
        $canonical = '';

        if ($header = $response->header('X-Robots-Tag')) {
            $robots[] = $header;
        }

        preg_match_all('#<(meta|link)\b([^>]*)>#i', $html, $tags, PREG_SET_ORDER);

        foreach ($tags as $tag) {
            preg_match_all('#([a-z-]+)\s*=\s*("([^"]*)"|\'([^\']*)\')#i', $tag[2], $attrs, PREG_SET_ORDER);
            $a = [];

            foreach ($attrs as $attr) {
                $a[strtolower($attr[1])] = html_entity_decode($attr[3] !== '' ? $attr[3] : ($attr[4] ?? ''));
            }

            if (strtolower($tag[1]) === 'meta' && in_array(strtolower($a['name'] ?? ''), ['robots', 'googlebot'], true)) {
                $robots[] = $a['content'] ?? '';
            }

            if (strtolower($tag[1]) === 'link' && strtolower($a['rel'] ?? '') === 'canonical' && $canonical === '') {
                $canonical = $a['href'] ?? '';
            }
        }

        return [implode('; ', $robots), $canonical];
    }

    private function collectSitemap(): array
    {
        $urls = [];
        $index = $this->fetch($this->base.'/sitemap.xml');

        if ($index === null || $index->status() !== 200) {
            $this->warn('Sitemap nije dostupan: '.$this->base.'/sitemap.xml');

            return [];
        }

        $locs = fn (string $xml) => preg_match_all('#<loc>\s*([^<]+?)\s*</loc>#', $xml, $m) ? array_map('html_entity_decode', $m[1]) : [];

        foreach ($locs($index->body()) as $part) {
            $response = $this->fetch($this->base.parse_url($part, PHP_URL_PATH));
            $this->pause();

            if ($response?->status() === 200) {
                array_push($urls, ...$locs($response->body()));
            }
        }

        return array_values(array_unique($urls));
    }

    private function samePath(string $url, string $expected): bool
    {
        $a = parse_url($url);
        $b = parse_url($expected);
        $norm = fn ($p) => rtrim(mb_strtolower(rawurldecode($p ?? '/')), '/');

        return $norm($a['path'] ?? '/') === $norm($b['path'] ?? '/')
            && (! isset($b['query']) || ($a['query'] ?? '') === $b['query']);
    }

    private function absolute(string $location, string $from): string
    {
        if ($location === '' || preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($from);

        return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').'/'.ltrim($location, '/');
    }

    private function describe(array $expected): string
    {
        if ($expected['status'] === null) {
            return '?';
        }

        $text = (string) $expected['status'];

        if (! empty($expected['target'])) {
            $text .= ' → '.$expected['target'];
        }

        if (($expected['index'] ?? null) === true) {
            $text .= ' index';
        } elseif (($expected['index'] ?? null) === false) {
            $text .= ' noindex';
        }

        if ($expected['xrobots'] ?? false) {
            $text .= ' X-Robots-Tag: noindex';
        }

        return $text;
    }

    private function pause(): void
    {
        $seconds = (float) $this->option('pause');

        if ($seconds > 0) {
            usleep((int) ($seconds * 1_000_000));
        }
    }

    private function report(array $rows): void
    {
        $shown = $this->option('errors-only') ? array_filter($rows, fn ($r) => ! $r['ok']) : $rows;

        $this->table(
            ['URL', 'Obrazac', 'Očekivano', 'Dobijeno', 'Odredište', 'Rezultat', 'Napomena'],
            array_map(fn ($r) => [
                Str::limit($r['url'], 70), $r['obrazac'], $r['ocekivano'], $r['dobijeno'],
                Str::limit($r['odrediste'], 50), $r['ok'] ? 'OK' : 'GREŠKA', $r['napomena'],
            ], $shown),
        );

        if ($csv = $this->option('csv')) {
            $handle = fopen($csv, 'w');
            fputcsv($handle, ['URL', 'obrazac', 'očekivano', 'dobijeno', 'odredište', 'robots', 'canonical', 'rezultat', 'napomena'], escape: '');

            foreach ($rows as $r) {
                fputcsv($handle, [$r['url'], $r['obrazac'], $r['ocekivano'], $r['dobijeno'], $r['odrediste'], $r['robots'], $r['canonical'], $r['ok'] ? 'OK' : 'GREŠKA', $r['napomena']], escape: '');
            }

            fclose($handle);
            $this->line('CSV: '.$csv);
        }

        $failed = count(array_filter($rows, fn ($r) => ! $r['ok']));
        $fiveXx = count(array_filter($rows, fn ($r) => str_starts_with($r['dobijeno'], '5')));
        $chains = count(array_filter($rows, fn ($r) => str_contains($r['napomena'], 'lanac')));
        $notFound = count(array_filter($rows, fn ($r) => $r['dobijeno'] === '404' && ! $r['ok']));

        $this->newLine();
        $this->line(sprintf('Ukupno: %d, OK: %d, GREŠKA: %d (5xx: %d, neočekivan 404: %d, lanci: %d)',
            count($rows), count($rows) - $failed, $failed, $fiveXx, $notFound, $chains));

        $failed === 0 ? $this->info('Prolaz: sve adrese se ponašaju kako treba.') : $this->error('Ima grešaka, vidi tabelu.');
    }
}
