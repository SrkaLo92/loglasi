<?php

namespace App\Services;

use App\Models\Firm;
use App\Models\User;
use App\Support\ListingLimits;
use Carbon\CarbonInterface;

/**
 * What a firm account gets with an active Biznis package, in one place for every module.
 *
 *   $benefits = app(FirmBenefits::class);
 *   $benefits->hasBiznis($user);          // User or Firm
 *   $benefits->canShowLogo($user);
 *   $benefits->listingLimit($user);       // null = unlimited
 *   $benefits->all($user);                // every flag, e.g. for an API payload
 *
 * Rules (ported from loglasi-core inc/profil, "Šta firma dobija, a privatno lice ne"):
 * - The "Firma" badge and the firm profile layout belong to every firm account (only the admin
 *   makes an account a firm), with or without Biznis.
 * - Cover image, logo, "Proverena firma", activity, profile view count, working hours and open-now
 *   status, the details column (about, contact, website, map, socials), WhatsApp/Viber buttons,
 *   the sticky call bar on mobile, indexable profile with LocalBusiness schema, a place in the
 *   /firme/ directory and the statistics page need an active Biznis package.
 * - When Biznis expires the firm falls back to private-user limits and a plain profile, but all its
 *   data stays saved and comes back as soon as the package is renewed.
 */
class FirmBenefits
{
    /** Every flag that all() returns. */
    public const FLAGS = [
        'has_biznis', 'is_founding', 'show_firm_badge', 'show_logo', 'show_cover', 'show_verified_badge',
        'show_activity', 'show_details', 'show_working_hours', 'show_map', 'show_socials', 'show_website',
        'show_messenger_buttons', 'show_sticky_call_bar', 'show_profile_views', 'show_stats',
        'indexable', 'listed_in_directory',
    ];

    public function firmOf(User|Firm|null $subject): ?Firm
    {
        if ($subject instanceof Firm) {
            return $subject;
        }
        if ($subject === null || ! $subject->isFirm()) {
            return null;
        }

        return $subject->relationLoaded('firm') ? $subject->getRelation('firm') : $subject->firm;
    }

    public function isFirmAccount(User|Firm|null $subject): bool
    {
        return $subject instanceof Firm || ($subject !== null && $subject->isFirm());
    }

    public function hasBiznis(User|Firm|null $subject): bool
    {
        $firm = $this->firmOf($subject);

        if ($firm === null || ! $firm->hasBiznis()) {
            return false;
        }

        // A firm row whose account was turned back into a private one gets nothing.
        $user = $firm->relationLoaded('user') ? $firm->getRelation('user') : null;

        return $user === null || $user->isFirm();
    }

    /** One of the first firms that got the package for free (badge "Osnivačka firma"). */
    public function isFounding(User|Firm|null $subject): bool
    {
        return (bool) $this->firmOf($subject)?->founding_free;
    }

    public function biznisUntil(User|Firm|null $subject): ?CarbonInterface
    {
        return $this->firmOf($subject)?->biznis_until;
    }

    /** Whole days until the package ends (0 on the last day), null without a package, negative after it ended. */
    public function daysLeft(User|Firm|null $subject): ?int
    {
        $until = $this->biznisUntil($subject);
        if ($until === null) {
            return null;
        }

        $today = now()->startOfDay();
        $end = $until->copy()->timezone(config('app.timezone'))->startOfDay();

        return (int) $today->diffInDays($end, false);
    }

    public function isExpiringSoon(User|Firm|null $subject, int $days = 14): bool
    {
        $left = $this->daysLeft($subject);

        return $this->hasBiznis($subject) && $left !== null && $left <= $days;
    }

    /** Biznis existed once and has ended (data kept, private-like limits). */
    public function isExpired(User|Firm|null $subject): bool
    {
        $until = $this->biznisUntil($subject);

        return $until !== null && ! $until->isFuture();
    }

    /** New listings per month; null = unlimited. Same rule as ListingLimits (expired Biznis = private limit). */
    public function listingLimit(User $user): ?int
    {
        return ListingLimits::monthlyLimit($user);
    }

    public function canShowFirmBadge(User|Firm|null $subject): bool
    {
        return $this->isFirmAccount($subject);
    }

    public function canShowLogo(User|Firm|null $subject): bool
    {
        return $this->hasBiznis($subject);
    }

    public function canShowCover(User|Firm|null $subject): bool
    {
        return $this->hasBiznis($subject);
    }

    public function canShowVerifiedBadge(User|Firm|null $subject): bool
    {
        return $this->hasBiznis($subject) && $this->firmOf($subject)?->verified_at !== null;
    }

    public function canShowActivity(User|Firm|null $subject): bool
    {
        return $this->hasBiznis($subject);
    }

    /** Left column of the firm profile: about, contact, working hours, map, socials. */
    public function canShowDetails(User|Firm|null $subject): bool
    {
        return $this->hasBiznis($subject);
    }

    public function canShowWorkingHours(User|Firm|null $subject): bool
    {
        return $this->hasBiznis($subject);
    }

    public function canShowMap(User|Firm|null $subject): bool
    {
        return $this->hasBiznis($subject);
    }

    public function canShowSocials(User|Firm|null $subject): bool
    {
        return $this->hasBiznis($subject);
    }

    public function canShowWebsite(User|Firm|null $subject): bool
    {
        return $this->hasBiznis($subject);
    }

    public function canShowMessengerButtons(User|Firm|null $subject): bool
    {
        return $this->hasBiznis($subject);
    }

    public function canShowStickyCallBar(User|Firm|null $subject): bool
    {
        return $this->hasBiznis($subject);
    }

    /** Profile view count, shown only from config('firms.profile_views_min') up. */
    public function canShowProfileViews(User|Firm|null $subject): bool
    {
        return $this->hasBiznis($subject)
            && (int) $this->firmOf($subject)?->getAttribute('profile_views') >= (int) config('firms.profile_views_min', 10);
    }

    public function canShowStats(User|Firm|null $subject): bool
    {
        return $this->hasBiznis($subject);
    }

    /** /korisnik/{slug}/ of the firm is indexable (index, canonical, LocalBusiness). */
    public function isIndexable(User|Firm|null $subject): bool
    {
        return $this->hasBiznis($subject);
    }

    public function isListedInDirectory(User|Firm|null $subject): bool
    {
        if (! $this->hasBiznis($subject)) {
            return false;
        }
        $user = $subject instanceof User ? $subject : $this->firmOf($subject)?->user;

        return $user !== null && ! $user->isBlocked() && ! $user->trashed();
    }

    /** @return array<string, bool> */
    public function all(User|Firm|null $subject): array
    {
        return [
            'has_biznis' => $this->hasBiznis($subject),
            'is_founding' => $this->isFounding($subject),
            'show_firm_badge' => $this->canShowFirmBadge($subject),
            'show_logo' => $this->canShowLogo($subject),
            'show_cover' => $this->canShowCover($subject),
            'show_verified_badge' => $this->canShowVerifiedBadge($subject),
            'show_activity' => $this->canShowActivity($subject),
            'show_details' => $this->canShowDetails($subject),
            'show_working_hours' => $this->canShowWorkingHours($subject),
            'show_map' => $this->canShowMap($subject),
            'show_socials' => $this->canShowSocials($subject),
            'show_website' => $this->canShowWebsite($subject),
            'show_messenger_buttons' => $this->canShowMessengerButtons($subject),
            'show_sticky_call_bar' => $this->canShowStickyCallBar($subject),
            'show_profile_views' => $this->canShowProfileViews($subject),
            'show_stats' => $this->canShowStats($subject),
            'indexable' => $this->isIndexable($subject),
            'listed_in_directory' => $this->isListedInDirectory($subject),
        ];
    }

    /**
     * What the package gives, for the status box and the expiry mail (Serbian, user-facing).
     *
     * @return list<string>
     */
    public static function benefitLabels(): array
    {
        return [
            'Logo, naslovna slika i oznaka „Proverena firma”',
            'Delatnost, opis, radno vreme sa oznakom „Otvoreno”, mapa i mreže na profilu',
            'Dugmad WhatsApp i Viber i traka „Pozovi firmu” na telefonu',
            'Mesto u imeniku „Firme iz Loznice” i profil koji Google prikazuje u pretrazi',
            'Statistika pregleda, poziva, poruka i omiljenih po danu',
            'Više oglasa mesečno nego privatno lice',
        ];
    }
}
