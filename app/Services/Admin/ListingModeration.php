<?php

namespace App\Services\Admin;

use App\Enums\ListingStatus;
use App\Mail\Admin\ListingStatusMail;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Services\Notifications\InAppNotifications;
use App\Support\Settings;
use DomainException;
use Illuminate\Support\Facades\Mail;

/** Listing moderation for the admin panel and the admin API. Every action is written to the audit log. */
class ListingModeration
{
    public const REASON_MAX = 255;

    public const REASONS_SETTING = 'rejection_reasons';

    /** Ready-made rejection reasons; the admin edits them in Podešavanja → Pravila. */
    public const REASONS_DEFAULT = [
        ['naziv' => 'Oglas nije iz Loznice i okoline', 'tekst' => 'Vaš oglas nije odobren jer loglasi.rs objavljuje samo oglase iz Loznice i okoline.'],
    ];

    /** Publishes a listing and tells the owner. */
    public function approve(Listing $listing, bool $notify = true): Listing
    {
        $before = $listing->status;
        $days = max(1, (int) Settings::get('listing_duration_days'));

        $listing->forceFill([
            'status' => ListingStatus::Published,
            'rejection_reason' => null,
            'published_at' => $listing->published_at ?? now(),
            'bumped_at' => $listing->bumped_at ?? now(),
            'expires_at' => $listing->expires_at !== null && $listing->expires_at->isFuture()
                ? $listing->expires_at
                : now()->addDays($days),
        ])->save();

        AuditLog::record('listing.approve', $listing, ['status' => [$before?->value, ListingStatus::Published->value]]);

        if ($notify && $before !== ListingStatus::Published) {
            $this->notify($listing, true);
        }

        return $listing;
    }

    /** Rejects a listing with a reason shown to the owner. */
    public function reject(Listing $listing, string $reason, bool $notify = true): Listing
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Upišite razlog odbijanja.');
        }
        $reason = mb_substr($reason, 0, self::REASON_MAX);
        $before = $listing->status;

        $listing->forceFill([
            'status' => ListingStatus::Rejected,
            'rejection_reason' => $reason,
        ])->save();

        AuditLog::record('listing.reject', $listing, [
            'status' => [$before?->value, ListingStatus::Rejected->value],
            'reason' => $reason,
        ]);

        if ($notify) {
            $this->notify($listing, false, $reason);
        }

        return $listing;
    }

    /** Manual promotions (free), by the listing column the payments module also uses. */
    public const PROMOTIONS = [
        'featured_until' => 'Istaknut oglas',
        'priority_until' => 'Prioritetni oglas',
        'top_until' => 'TOP oglas',
        'premium_xl_until' => 'Premium XL kartica',
        'premium_home_until' => 'Premium XL + početna',
    ];

    /** Free manual promotion for N days (from now, or from the current end). */
    public function feature(Listing $listing, int $days, string $promotion = 'featured_until'): Listing
    {
        if ($days < 1 || $days > 365) {
            throw new DomainException('Broj dana mora biti od 1 do 365.');
        }
        if (! array_key_exists($promotion, self::PROMOTIONS)) {
            throw new DomainException('Nepoznata vrsta promocije.');
        }
        if ($listing->trashed() || $listing->status !== ListingStatus::Published) {
            throw new DomainException('Promoviše se samo objavljen oglas.');
        }

        $columns = $promotion === 'premium_home_until' ? ['premium_xl_until', 'premium_home_until'] : [$promotion];
        $changes = ['days' => $days, 'free' => true];
        foreach ($columns as $column) {
            $before = $listing->{$column};
            $until = $before !== null && $before->isFuture() ? $before->copy()->addDays($days) : now()->addDays($days);
            $listing->forceFill([$column => $until]);
            $changes[$column] = [$before?->toIso8601String(), $until->toIso8601String()];
        }
        $listing->save();

        AuditLog::record('listing.feature', $listing, $changes);

        return $listing;
    }

    /** Ends every running promotion. */
    public function unfeature(Listing $listing): Listing
    {
        $changes = [];
        foreach (array_keys(self::PROMOTIONS) as $column) {
            if ($listing->{$column}?->isFuture()) {
                $changes[$column] = [$listing->{$column}->toIso8601String(), null];
                $listing->forceFill([$column => null]);
            }
        }
        $listing->save();

        AuditLog::record('listing.unfeature', $listing, $changes);

        return $listing;
    }

    /** Ends a published listing now (the owner can renew it as after normal expiry). */
    public function expire(Listing $listing): Listing
    {
        if ($listing->status !== ListingStatus::Published) {
            throw new DomainException('Ističe samo objavljen oglas.');
        }

        $before = $listing->expires_at;
        $listing->forceFill(['status' => ListingStatus::Expired, 'expires_at' => now()])->save();

        AuditLog::record('listing.expire', $listing, [
            'status' => [ListingStatus::Published->value, ListingStatus::Expired->value],
            'expires_at' => [$before?->toIso8601String(), $listing->expires_at->toIso8601String()],
        ]);

        return $listing;
    }

    /** Moves the listing to the top of the list (as if just posted). */
    public function bump(Listing $listing): Listing
    {
        $before = $listing->bumped_at;
        $listing->forceFill(['bumped_at' => now()])->save();

        AuditLog::record('listing.bump', $listing, ['bumped_at' => [$before?->toIso8601String(), $listing->bumped_at->toIso8601String()]]);

        return $listing;
    }

    /** Soft delete: the old URL answers 410. */
    public function delete(Listing $listing): void
    {
        $listing->delete();

        AuditLog::record('listing.delete', $listing, ['title' => $listing->title]);
    }

    private function notify(Listing $listing, bool $approved, ?string $reason = null): void
    {
        app(InAppNotifications::class)->moderated($listing, $approved, $reason);
        $email = $listing->user?->email;

        if ($email === null || $listing->user->trashed()) {
            return;
        }

        Mail::to($email)->queue(new ListingStatusMail($listing, $approved, $reason));
    }

    /** @return list<array{naziv: string, tekst: string}> */
    public static function rejectionReasons(): array
    {
        $rows = Settings::get(self::REASONS_SETTING, self::REASONS_DEFAULT);

        return array_values(array_filter(array_map(
            fn ($row) => ['naziv' => trim((string) ($row['naziv'] ?? '')), 'tekst' => trim((string) ($row['tekst'] ?? ''))],
            is_array($rows) ? array_filter($rows, 'is_array') : [],
        ), fn ($row) => $row['naziv'] !== '' && $row['tekst'] !== ''));
    }
}
