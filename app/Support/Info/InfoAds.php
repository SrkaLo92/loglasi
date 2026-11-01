<?php

namespace App\Support\Info;

use App\Models\InfoAd;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;

/** Running ads per slot (cached a few minutes) and the advertising price list. */
final class InfoAds
{
    private const KEY = 'info.ads';

    public static function forget(): void
    {
        Cache::forget(self::KEY);
    }

    /** The ad running in a slot now; for category_top, the one for that category. */
    public static function for(string $slot, ?string $category = null): ?InfoAd
    {
        $ids = Cache::remember(self::KEY, 300, fn () => InfoAd::query()->running()->orderByDesc('starts_on')
            ->get(['id', 'slot', 'category'])->map(fn (InfoAd $ad) => [$ad->id, $ad->slot, $ad->category])->all());

        foreach ($ids as [$id, $adSlot, $adCategory]) {
            if ($adSlot === $slot && ($slot !== 'category_top' || $adCategory === $category)) {
                return InfoAd::query()->with('business')->find($id);
            }
        }

        return null;
    }

    public static function price(string $key): int
    {
        return (int) Settings::get('info_price_'.$key, config('info.pricing.'.$key, 0));
    }

    public static function format(int $amount): string
    {
        return number_format($amount, 0, ',', '.').' din';
    }
}
