<?php

namespace App\Services;

use App\Models\Firm;
use App\Models\User;
use App\Support\Firms\FirmCredits;
use App\Support\Firms\FirmPackage;
use App\Support\ListingLimits;
use Carbon\CarbonInterface;

/**
 * What a firm account gets with an active package, in one place for every module.
 *
 *   $benefits = app(FirmBenefits::class);
 *   $benefits->hasPackage($user);         // User or Firm: Business Start/Pro/Max or the free first year
 *   $benefits->packageCode($user);        // 'start' | 'pro' | 'max' | 'free' | null
 *   $benefits->listingLimit($user);       // active listings, null = unlimited
 *   $benefits->monthlyCredits($user);     // included bumps / highlights / Premium XL left this month
 *   $benefits->all($user);                // every flag, e.g. for an API payload
 *
 * Rules (price list of 24 Oct 2026 and loglasi-core inc/profil, "Šta firma dobija, a privatno lice ne"):
 * - The "Firma" badge and the firm profile layout belong to every firm account (only the admin makes
 *   an account a firm), with or without a package.
 * - Every active package and the free first year give: logo, cover, the "Proverena firma" badge (once
 *   the admin has verified the firm), activity, profile view count, working hours and open-now status,
 *   the details column (about, contact, website, map, socials), WhatsApp/Viber buttons, the sticky call
 *   bar, a place in the /firme/ directory and statistics. Pro and Max add advanced statistics
 *   (90 days, per listing) and more included promotions; limits differ (Start 20, Pro 100, Max and
 *   the free year unlimited active listings).
 * - When the term ends the firm falls back to the private limit and a plain profile, but all its data
 *   stays saved and comes back with the next package. There is no automatic billing.
 */
class FirmBenefits
{
    /** Every flag that all() returns. */
    public const FLAGS = [
        'has_package', 'free_program', 'show_firm_badge', 'show_logo', 'show_cover', 'show_verified_badge',
        'show_activity', 'show_details', 'show_working_hours', 'show_map', 'show_socials', 'show_website',
        'show_messenger_buttons', 'show_sticky_call_bar', 'show_profile_views', 'show_stats', 'show_advanced_stats',
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

    public function hasPackage(User|Firm|null $subject): bool
    {
        $firm = $this->firmOf($subject);

        if (! FirmPackage::isActive($firm)) {
            return false;
        }

        // A firm row whose account was turned back into a private one gets nothing.
        $user = $firm->relationLoaded('user') ? $firm->getRelation('user') : null;

        return $user === null || $user->isFirm();
    }

    /** 'start' | 'pro' | 'max' | 'free' (first 30 firms) | null */
    public function packageCode(User|Firm|null $subject): ?string
    {
        return $this->hasPackage($subject) ? FirmPackage::code($this->firmOf($subject)) : null;
    }

    /** "Business Pro", "Prvih 30 firmi", null without a package. */
    public function packageLabel(User|Firm|null $subject): ?string
    {
        return $this->hasPackage($subject) ? FirmPackage::label($this->firmOf($subject)) : null;
    }

    /** Current term is the free first year of the first 30 firms. */
    public function isFreeProgram(User|Firm|null $subject): bool
    {
        return $this->packageCode($subject) === FirmPackage::FREE;
    }

    public function packageEndsAt(User|Firm|null $subject): ?CarbonInterface
    {
        return $this->firmOf($subject)?->package_ends_at;
    }

    /** @return array{bumps: int, highlights: int, premium_xl: int} included promotions left this month */
    public function monthlyCredits(User|Firm|null $subject): array
    {
        $firm = $this->firmOf($subject);

        return $firm && $this->hasPackage($firm) ? FirmCredits::remaining($firm) : ['bumps' => 0, 'highlights' => 0, 'premium_xl' => 0];
    }

    /** Whole days until the package ends (0 on the last day), null without a package, negative after it ended. */
    public function daysLeft(User|Firm|null $subject): ?int
    {
        $until = $this->packageEndsAt($subject);
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

        return $this->hasPackage($subject) && $left !== null && $left <= $days;
    }

    /** A term existed and has ended (data kept, private limit). */
    public function isExpired(User|Firm|null $subject): bool
    {
        $until = $this->packageEndsAt($subject);

        return $until !== null && ! $until->isFuture();
    }

    /** Active listings allowed; null = unlimited. Same rule as ListingLimits (no package = private limit). */
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
        return $this->hasPackage($subject);
    }

    public function canShowCover(User|Firm|null $subject): bool
    {
        return $this->hasPackage($subject);
    }

    public function canShowVerifiedBadge(User|Firm|null $subject): bool
    {
        return $this->hasPackage($subject) && $this->firmOf($subject)?->verified_at !== null;
    }

    public function canShowActivity(User|Firm|null $subject): bool
    {
        return $this->hasPackage($subject);
    }

    /** Left column of the firm profile: about, contact, working hours, map, socials. */
    public function canShowDetails(User|Firm|null $subject): bool
    {
        return $this->hasPackage($subject);
    }

    public function canShowWorkingHours(User|Firm|null $subject): bool
    {
        return $this->hasPackage($subject);
    }

    public function canShowMap(User|Firm|null $subject): bool
    {
        return $this->hasPackage($subject);
    }

    public function canShowSocials(User|Firm|null $subject): bool
    {
        return $this->hasPackage($subject);
    }

    public function canShowWebsite(User|Firm|null $subject): bool
    {
        return $this->hasPackage($subject);
    }

    public function canShowMessengerButtons(User|Firm|null $subject): bool
    {
        return $this->hasPackage($subject);
    }

    public function canShowStickyCallBar(User|Firm|null $subject): bool
    {
        return $this->hasPackage($subject);
    }

    /** Profile view count, shown only from config('firms.profile_views_min') up. */
    public function canShowProfileViews(User|Firm|null $subject): bool
    {
        return $this->hasPackage($subject)
            && (int) $this->firmOf($subject)?->getAttribute('profile_views') >= (int) config('firms.profile_views_min', 10);
    }

    public function canShowStats(User|Firm|null $subject): bool
    {
        return $this->hasPackage($subject);
    }

    /** 90 days and totals per listing: Business Pro and Max. */
    public function canShowAdvancedStats(User|Firm|null $subject): bool
    {
        return $this->hasPackage($subject) && FirmPackage::hasAdvancedStats($this->firmOf($subject));
    }

    /** /korisnik/{slug}/ of the firm is indexable (index, canonical, LocalBusiness). */
    public function isIndexable(User|Firm|null $subject): bool
    {
        return $this->hasPackage($subject);
    }

    public function isListedInDirectory(User|Firm|null $subject): bool
    {
        if (! $this->hasPackage($subject)) {
            return false;
        }
        $user = $subject instanceof User ? $subject : $this->firmOf($subject)?->user;

        return $user !== null && ! $user->isBlocked() && ! $user->trashed();
    }

    /** @return array<string, bool> */
    public function all(User|Firm|null $subject): array
    {
        return [
            'has_package' => $this->hasPackage($subject),
            'free_program' => $this->isFreeProgram($subject),
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
            'show_advanced_stats' => $this->canShowAdvancedStats($subject),
            'indexable' => $this->isIndexable($subject),
            'listed_in_directory' => $this->isListedInDirectory($subject),
        ];
    }

    /**
     * What a package gives, for the status box and the expiry mail (Serbian, user-facing).
     *
     * @return list<string>
     */
    public static function benefitLabels(): array
    {
        return [
            'Logo, naslovna slika i oznaka „Proverena firma”',
            'Delatnost, opis, radno vreme sa oznakom „Otvoreno”, mapa i mreže na profilu',
            'Dugmad WhatsApp i Viber i traka „Pozovi firmu” na telefonu',
            'Mesto u imeniku „Firme iz Loznice”',
            'Statistika pregleda, poziva, poruka i omiljenih po danu',
            'Do 20, 100 ili neograničeno aktivnih oglasa, po paketu',
            'Podizanja i isticanja uključena svakog meseca (Pro i Max)',
        ];
    }
}
