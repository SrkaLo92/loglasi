<?php

namespace App\Support\Info;

use App\Models\InfoPharmacyDuty;
use App\Models\InfoPost;
use App\Models\InfoServiceNotice;
use App\Models\Listing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * "Danas u Loznici": weather, pharmacy on duty, today's outages and the next event. Built once a
 * few minutes and shared by the portal and the classifieds home page, so neither queries for it.
 */
final class InfoToday
{
    private const KEY = 'info.today';

    public static function forget(): void
    {
        foreach (array_keys((array) config('info.cities')) as $city) {
            Cache::forget(self::KEY.'.'.$city);
        }
        Cache::forget(self::KEY.'.listings');
    }

    /** @return array{weather: ?array, pharmacy: ?array, power_today: int, power_next: ?string, water_today: int, next_event: ?array} */
    public static function get(): array
    {
        return Cache::remember(self::KEY.'.'.InfoCity::current(), 300, function () {
            $pharmacy = InfoPharmacyDuty::today();
            $power = InfoServiceNotice::query()->current()->where('kind', 'power')->orderBy('starts_at')->get();
            $today = $power->filter(fn (InfoServiceNotice $n) => $n->starts_at->isToday());
            $water = InfoServiceNotice::query()->current()->where('kind', 'water')
                ->whereDate('starts_at', today())->count();
            $event = InfoPost::query()->upcomingEvents()->orderBy('event_starts_at')->first();

            return [
                'weather' => Weather::current(),
                'pharmacy' => $pharmacy ? $pharmacy->only(['name', 'address', 'phone', 'hours']) : null,
                'power_today' => $today->count(),
                'power_next' => $power->first()?->whenLabel(),
                'water_today' => $water,
                'next_event' => $event ? [
                    'title' => $event->title,
                    'when' => $event->event_starts_at?->isToday() ? 'Danas '.$event->event_starts_at->format('H:i') : $event->event_starts_at?->translatedFormat('D j. n. H:i'),
                    'venue' => $event->event_venue ?: $event->place,
                    'url' => $event->url(),
                ] : null,
            ];
        });
    }

    /** Newest active listings for the "Novi oglasi iz Loznice" strip (ids cached, models loaded fresh). */
    public static function latestListings(int $count = 4): Collection
    {
        $ids = Cache::remember(self::KEY.'.listings', 300, fn () => Listing::query()->active()
            ->latest('published_at')->limit($count)->pluck('id')->all());

        return Listing::query()->whereIn('id', $ids)->with(['coverImage', 'location'])
            ->get()->sortBy(fn (Listing $l) => array_search($l->id, $ids, true))->values();
    }
}
