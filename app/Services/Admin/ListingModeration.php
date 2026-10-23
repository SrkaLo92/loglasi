<?php

namespace App\Services\Admin;

use App\Enums\ListingStatus;
use App\Mail\Admin\ListingStatusMail;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Support\Settings;
use DomainException;
use Illuminate\Support\Facades\Mail;

/** Listing moderation for the admin panel and the admin API. Every action is written to the audit log. */
class ListingModeration
{
    public const REASON_MAX = 255;

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

    /** Free manual featuring for N days (from now, or from the current end). */
    public function feature(Listing $listing, int $days): Listing
    {
        if ($days < 1 || $days > 365) {
            throw new DomainException('Broj dana mora biti od 1 do 365.');
        }

        $before = $listing->featured_until;
        $until = $before !== null && $before->isFuture() ? $before->copy()->addDays($days) : now()->addDays($days);
        $listing->forceFill(['featured_until' => $until])->save();

        AuditLog::record('listing.feature', $listing, [
            'days' => $days,
            'featured_until' => [$before?->toIso8601String(), $until->toIso8601String()],
            'free' => true,
        ]);

        return $listing;
    }

    public function unfeature(Listing $listing): Listing
    {
        $before = $listing->featured_until;
        $listing->forceFill(['featured_until' => null])->save();

        AuditLog::record('listing.unfeature', $listing, ['featured_until' => [$before?->toIso8601String(), null]]);

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
        $email = $listing->user?->email;

        if ($email === null || $listing->user->trashed()) {
            return;
        }

        Mail::to($email)->queue(new ListingStatusMail($listing, $approved, $reason));
    }
}
