<?php

namespace App\Http\Controllers\Nalozi\Api;

use App\Models\User;
use DateTimeInterface;

/** Šema Me iz ugovora. */
class MePresenter
{
    public static function me(User $user): array
    {
        $user->loadMissing('location', 'firm');
        $avatar = $user->avatarUrl();

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'phone_verified' => $user->isPhoneVerified(),
            'role' => $user->apiRole(),
            'location' => $user->location ? [
                'id' => $user->location->id,
                'name' => $user->location->name,
                'slug' => $user->location->slug,
            ] : null,
            'avatar' => $avatar ? [
                'id' => $user->id,
                'thumb' => $avatar,
                'medium' => $avatar,
                'large' => $avatar,
                'width' => 400,
                'height' => 400,
            ] : null,
            'firm_id' => $user->isFirm() ? $user->firm?->id : null,
            'member_since' => static::date($user->created_at),
            'notifications' => $user->notificationSettings(),
        ];
    }

    /** ISO 8601 u UTC: 2026-10-23T18:30:00Z. */
    public static function date(?DateTimeInterface $date): ?string
    {
        return $date ? $date->copy()->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }
}
