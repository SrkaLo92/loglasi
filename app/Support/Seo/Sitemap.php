<?php

namespace App\Support\Seo;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use App\Support\Seo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use XMLWriter;

/**
 * Sitemap: /sitemap.xml (indeks) i delovi: oglasi (po N), kategorije, mesta (kategorija + mesto od 3 oglasa),
 * firme, strane. XML se kešira; keš se briše na promenu i obnavlja komandom seo:sitemap (svakog sata).
 */
class Sitemap
{
    private const VERSION_KEY = 'seo.sitemap.version';

    private const NS = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    /** Sadržaj jednog fajla ili null ako ne postoji (404). */
    public function get(string $name): ?string
    {
        if (! $this->exists($name)) {
            return null;
        }

        return Cache::remember($this->key($name), (int) config('seo.sitemap.cache_ttl', 86400), fn () => $this->build($name));
    }

    /** Svi delovi koji trenutno postoje, npr. ['strane', 'kategorije', 'oglasi-1']. */
    public function parts(): array
    {
        return Cache::remember($this->key('parts'), (int) config('seo.sitemap.cache_ttl', 86400), function () {
            $parts = ['strane', 'kategorije'];

            if ($this->placeRows() !== []) {
                $parts[] = 'mesta';
            }

            if ($this->firmQuery()->exists()) {
                $parts[] = 'firme';
            }

            $pages = (int) ceil(Seo::activeListings()->count() / $this->perFile());

            for ($i = 1; $i <= $pages; $i++) {
                $parts[] = 'oglasi-'.$i;
            }

            return $parts;
        });
    }

    public function exists(string $name): bool
    {
        return $name === 'index' || in_array($name, $this->parts(), true);
    }

    /** Briše keš (nova verzija ključeva); sledeći zahtev pravi sve iznova. */
    public function flush(): void
    {
        Cache::forever(self::VERSION_KEY, (int) Cache::get(self::VERSION_KEY, 0) + 1);
    }

    /** Briše keš i odmah pravi sve delove (komanda seo:sitemap). Vraća broj URL-ova. */
    public function rebuild(): int
    {
        $this->flush();
        $count = 0;

        foreach (array_merge(['index'], $this->parts()) as $name) {
            $xml = (string) $this->get($name);

            if ($name !== 'index') {
                $count += substr_count($xml, '<url>');
            }
        }

        return $count;
    }

    private function key(string $name): string
    {
        return 'seo.sitemap.'.Cache::get(self::VERSION_KEY, 0).'.'.$name;
    }

    private function perFile(): int
    {
        return max(1, (int) config('seo.sitemap.per_file', 1000));
    }

    private function build(string $name): string
    {
        return match (true) {
            $name === 'index' => $this->index(),
            $name === 'strane' => $this->urlset($this->pageRows()),
            $name === 'kategorije' => $this->urlset($this->categoryRows()),
            $name === 'mesta' => $this->urlset($this->placeRows()),
            $name === 'firme' => $this->urlset($this->firmRows()),
            str_starts_with($name, 'oglasi-') => $this->urlset($this->listingRows((int) substr($name, 7))),
        };
    }

    private function index(): string
    {
        $base = config('seo.base_url');
        $lastmods = [
            'strane' => $this->latest(Seo::activeListings()),
            'kategorije' => $this->latest(Seo::activeListings()),
            'mesta' => $this->latest(Seo::activeListings()),
            'firme' => $this->latest(DB::table('firms')),
        ];

        $xml = $this->writer();
        $xml->startElement('sitemapindex');
        $xml->writeAttribute('xmlns', self::NS);

        foreach ($this->parts() as $part) {
            $xml->startElement('sitemap');
            $xml->writeElement('loc', $base.'/sitemap-'.$part.'.xml');
            $lastmod = $lastmods[$part] ?? $this->listingChunkLastmod((int) substr($part, 7));

            if ($lastmod) {
                $xml->writeElement('lastmod', $lastmod->toAtomString());
            }

            $xml->endElement();
        }

        $xml->endElement();

        return $xml->outputMemory();
    }

    /** @param iterable<array{loc: string, lastmod: ?Carbon}> $rows */
    private function urlset(iterable $rows): string
    {
        $xml = $this->writer();
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', self::NS);

        foreach ($rows as $row) {
            $xml->startElement('url');
            $xml->writeElement('loc', Url::canonical($row['loc']));

            if ($row['lastmod']) {
                $xml->writeElement('lastmod', $row['lastmod']->toAtomString());
            }

            $xml->endElement();
        }

        $xml->endElement();

        return $xml->outputMemory();
    }

    private function writer(): XMLWriter
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');

        return $xml;
    }

    private function latest($query): ?Carbon
    {
        $value = $query->max('updated_at');

        return $value ? Carbon::parse($value) : null;
    }

    private function listingChunkLastmod(int $page): ?Carbon
    {
        $value = DB::query()->fromSub(
            Seo::activeListings()->orderBy('id')->select('updated_at')
                ->skip(($page - 1) * $this->perFile())->take($this->perFile())->toBase(),
            'chunk'
        )->max('updated_at');

        return $value ? Carbon::parse($value) : null;
    }

    private function listingRows(int $page): array
    {
        return Seo::activeListings()->orderBy('id')
            ->skip(($page - 1) * $this->perFile())->take($this->perFile())
            ->get(['id', 'slug', 'updated_at'])
            ->map(fn ($l) => ['loc' => '/oglas/'.$l->slug.'/', 'lastmod' => $l->updated_at])
            ->all();
    }

    private function pageRows(): array
    {
        $latest = $this->latest(Seo::activeListings());

        return collect(config('seo.pages', []))
            ->map(fn ($path) => ['loc' => $path, 'lastmod' => in_array($path, ['/', '/oglasi/'], true) ? $latest : null])
            ->all();
    }

    /**
     * Broj aktivnih oglasa i poslednja izmena po kategoriji (i mestu), sa zbirom za nadređene kategorije.
     *
     * @return array<string, array{count: int, lastmod: ?string}> ključ "kategorija" ili "kategorija|mesto"
     */
    private function counts(bool $byLocation): array
    {
        $parents = Category::query()->where('is_active', true)->pluck('parent_id', 'id')->all();

        $rows = Seo::activeListings()->toBase()
            ->selectRaw('category_id, '.($byLocation ? 'location_id' : 'NULL as location_id').', COUNT(*) as total, MAX(updated_at) as lastmod')
            ->when($byLocation, fn ($q) => $q->whereNotNull('location_id')->groupBy('category_id', 'location_id'))
            ->when(! $byLocation, fn ($q) => $q->groupBy('category_id'))
            ->get();

        $out = [];

        foreach ($rows as $row) {
            $categoryId = (int) $row->category_id;
            $guard = 0;

            // Oglas se računa u svojoj kategoriji i u svim nadređenim.
            while ($categoryId && array_key_exists($categoryId, $parents) && $guard++ < 10) {
                $key = $categoryId.($byLocation ? '|'.$row->location_id : '');
                $out[$key]['count'] = ($out[$key]['count'] ?? 0) + (int) $row->total;
                $out[$key]['lastmod'] = max($out[$key]['lastmod'] ?? '', (string) $row->lastmod);
                $categoryId = (int) $parents[$categoryId];
            }
        }

        return $out;
    }

    private function categoryRows(): array
    {
        $counts = $this->counts(false);
        $slugs = Category::query()->where('is_active', true)->whereIn('id', array_keys($counts))
            ->orderBy('sort')->orderBy('name')->pluck('slug', 'id');

        return $slugs->map(fn ($slug, $id) => [
            'loc' => '/oglasi/'.$slug.'/',
            'lastmod' => $counts[$id]['lastmod'] ? Carbon::parse($counts[$id]['lastmod']) : null,
        ])->values()->all();
    }

    private function placeRows(): array
    {
        $min = Seo::placeMinListings();
        $counts = array_filter($this->counts(true), fn ($c) => $c['count'] >= $min);

        if ($counts === []) {
            return [];
        }

        $categoryIds = array_unique(array_map(fn ($k) => (int) explode('|', $k)[0], array_keys($counts)));
        $locationIds = array_unique(array_map(fn ($k) => (int) explode('|', $k)[1], array_keys($counts)));
        $categories = Category::query()->whereIn('id', $categoryIds)->pluck('slug', 'id');
        $locations = Location::query()->whereIn('id', $locationIds)->pluck('slug', 'id');
        $rows = [];

        foreach ($counts as $key => $count) {
            [$categoryId, $locationId] = array_map('intval', explode('|', $key));

            if (isset($categories[$categoryId], $locations[$locationId])) {
                $rows[] = [
                    'loc' => '/oglasi/'.$categories[$categoryId].'/'.$locations[$locationId].'/',
                    'lastmod' => $count['lastmod'] ? Carbon::parse($count['lastmod']) : null,
                ];
            }
        }

        usort($rows, fn ($a, $b) => strcmp($a['loc'], $b['loc']));

        return $rows;
    }

    /** Firms with an active listing, a filled profile or a paid package (same rule as Seo::firmIndexable). */
    private function firmQuery()
    {
        return User::query()
            ->where('account_type', AccountType::Firm)
            ->where('status', UserStatus::Active)
            ->whereHas('firm')
            ->where(fn ($q) => $q
                ->whereHas('firm', fn ($f) => $f->where('package_ends_at', '>', now())
                    ->orWhere(fn ($w) => $w->whereNotNull('description')->where('description', '!=', ''))
                    ->orWhereNotNull('logo_path'))
                ->orWhereExists(fn ($e) => $e->selectRaw('1')->from('listings')
                    ->whereColumn('listings.user_id', 'users.id')
                    ->where('listings.status', 'published')
                    ->whereNull('listings.deleted_at')
                    ->where(fn ($x) => $x->whereNull('listings.expires_at')->orWhere('listings.expires_at', '>', now()))));
    }

    private function firmRows(): array
    {
        return $this->firmQuery()->with('firm:id,user_id,updated_at')->orderBy('id')
            ->get(['id', 'slug', 'updated_at'])
            ->map(fn ($u) => ['loc' => '/korisnik/'.$u->slug.'/', 'lastmod' => max($u->updated_at, $u->firm?->updated_at)])
            ->all();
    }
}
