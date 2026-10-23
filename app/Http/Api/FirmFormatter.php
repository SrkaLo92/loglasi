<?php

namespace App\Http\Api;

use App\Models\Firm;
use App\Models\User;
use App\Services\FirmBenefits;
use App\Support\Firms\FirmImages;
use App\Support\Firms\WorkingHours;
use App\Support\Seo;
use DateTimeInterface;

/** Firm schema of the mobile contract (GET/PATCH /me/firm). */
class FirmFormatter
{
    public static function firm(Firm $firm, User $user): array
    {
        $benefits = app(FirmBenefits::class);
        $logo = FirmImages::url($firm->logo_path);

        return [
            'id' => $firm->exists ? $firm->id : null,
            'name' => $firm->name ?: $user->name,
            'about' => (string) $firm->description,
            'logo' => $logo ? [
                'id' => $firm->id,
                'thumb' => $logo,
                'medium' => $logo,
                'large' => $logo,
            ] : null,
            'address' => (string) $firm->address,
            'website' => (string) $firm->website,
            'working_hours' => WorkingHours::toText($firm->working_hours),
            'phone_verified' => $user->isPhoneVerified(),
            'package' => $benefits->hasBiznis($firm) ? 'Biznis' : null,
            'package_expires_at' => static::date($firm->biznis_until),
            'active_listings' => Seo::activeListings()->where('user_id', $user->id)->count(),
            'member_since' => static::date($user->created_at),
        ];
    }

    private static function date(?DateTimeInterface $date): ?string
    {
        return $date ? $date->copy()->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }
}
