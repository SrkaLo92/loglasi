<?php

namespace App\Support\Firms;

use App\Enums\AccountType;
use App\Enums\ListingStatus;
use App\Enums\UserStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Firme iz Loznice" (/firme/): firm accounts with an active package (Business Start/Pro/Max or the free first year).
 *
 * The whole directory is one cached array (there are hundreds of firms at most), built with two
 * indexed queries: firms joined to users (active firm accounts, package_ends_at in the future) and one
 * grouped count of active listings for those users. Filters (place, text) and paging run on the
 * cached array. The cache is cleared when a firm or a firm account changes (FirmsServiceProvider)
 * and expires after config('firms.directory.cache_ttl'), which also refreshes listing counts and
 * drops firms whose package ended in the meantime.
 */
class FirmDirectory
{
    public const CACHE_KEY = 'firms.directory.v1';

    /**
     * Sorted: the first 30 firms first, then by active listings, then by name.
     *
     * @return list<array{id: int, user_id: int, slug: string, name: string, activity: ?string, description: ?string, logo: ?string, location_id: ?int, location: ?string, location_slug: ?string, founding: bool, verified: bool, listings: int, search: string}>
     */
    public static function all(): array
    {
        return Cache::remember(self::CACHE_KEY, (int) config('firms.directory.cache_ttl', 600), static fn () => static::build());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{items: LengthAwarePaginator, places: list<array{slug: string, name: string, count: int}>, total: int}
     */
    public static function search(?string $place, ?string $keyword, int $page, string $path): array
    {
        $all = static::all();
        $rows = $all;

        if ($place !== null && $place !== '') {
            $rows = array_values(array_filter($rows, fn ($row) => $row['location_slug'] === $place));
        }

        $words = preg_split('/\s+/u', static::fold((string) $keyword), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words !== []) {
            $rows = array_values(array_filter($rows, function ($row) use ($words) {
                foreach ($words as $word) {
                    if (! str_contains($row['search'], $word)) {
                        return false;
                    }
                }

                return true;
            }));
        }

        $perPage = (int) config('firms.directory.per_page', 24);
        $items = new LengthAwarePaginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
            ['path' => $path, 'pageName' => 'page'],
        );

        return ['items' => $items, 'places' => static::places($all), 'total' => count($all)];
    }

    /** Lowercase without diacritics, so "cacak" finds "Čačak" and "djordje" finds "Đorđe". */
    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text));

        return strtr($text, ['č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'dj', 'dž' => 'dz']);
    }

    /** @return list<array{slug: string, name: string, count: int}> places that have at least one listed firm */
    private static function places(array $rows): array
    {
        $places = [];
        foreach ($rows as $row) {
            if ($row['location_slug'] === null) {
                continue;
            }
            $places[$row['location_slug']] ??= ['slug' => $row['location_slug'], 'name' => $row['location'], 'count' => 0];
            $places[$row['location_slug']]['count']++;
        }
        usort($places, fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        return array_values($places);
    }

    private static function build(): array
    {
        $firms = DB::table('firms as f')
            ->join('users as u', 'u.id', '=', 'f.user_id')
            ->leftJoin('locations as l', 'l.id', '=', 'f.location_id')
            ->where('u.account_type', AccountType::Firm->value)
            ->where('u.status', UserStatus::Active->value)
            ->whereNull('u.deleted_at')
            ->where('f.package_ends_at', '>', now())
            ->get([
                'f.id', 'f.user_id', 'f.name', 'f.activity', 'f.description', 'f.logo_path', 'f.founding_free', 'f.verified_at',
                'f.location_id', 'u.slug', 'u.name as user_name', 'l.name as location', 'l.slug as location_slug',
            ]);

        $counts = $firms->isEmpty() ? collect() : DB::table('listings')
            ->whereIn('user_id', $firms->pluck('user_id'))
            ->where('status', ListingStatus::Published->value)
            ->whereNull('deleted_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'user_id');

        $rows = $firms->map(function ($f) use ($counts) {
            $name = trim((string) $f->name) !== '' ? $f->name : $f->user_name;
            $description = $f->description ? Str::limit(trim((string) preg_replace('/\s+/u', ' ', strip_tags($f->description))), 160) : null;

            return [
                'id' => (int) $f->id,
                'user_id' => (int) $f->user_id,
                'slug' => $f->slug,
                'name' => $name,
                'activity' => $f->activity ?: null,
                'description' => $description,
                'logo' => FirmImages::url($f->logo_path),
                'location_id' => $f->location_id ? (int) $f->location_id : null,
                'location' => $f->location,
                'location_slug' => $f->location_slug,
                'founding' => (bool) $f->founding_free,
                'verified' => $f->verified_at !== null,
                'listings' => (int) ($counts[$f->user_id] ?? 0),
                'search' => static::fold($name.' '.$f->activity.' '.$f->description.' '.$f->location),
            ];
        })->all();

        usort($rows, fn ($a, $b) => [$b['founding'], $b['listings'], static::fold($a['name'])] <=> [$a['founding'], $a['listings'], static::fold($b['name'])]);

        return $rows;
    }
}
