<?php

namespace App\Support\Firms;

use App\Models\Firm;
use App\Models\Product;
use Carbon\CarbonInterface;

/**
 * The plan a firm is on right now: a Business package (start, pro, max; monthly or yearly) or the free
 * first year of the first 30 firms. A term is active while package_ends_at is in the future.
 * Definitions are in config('firms.packages') and config('firms.free_program'); the listing limit of a
 * package comes from its product row (admin-editable) when that row exists.
 */
class FirmPackage
{
    public const PACKAGES = ['start', 'pro', 'max'];

    public const PERIODS = ['monthly', 'yearly'];

    public const FREE = 'free';

    public static function isActive(?Firm $firm): bool
    {
        return $firm !== null
            && $firm->package_ends_at !== null
            && $firm->package_ends_at->isFuture()
            && ($firm->free_program || in_array($firm->package, self::PACKAGES, true));
    }

    /** 'start' | 'pro' | 'max' | 'free', or null without an active term. */
    public static function code(?Firm $firm): ?string
    {
        if (! static::isActive($firm)) {
            return null;
        }

        return $firm->free_program ? self::FREE : $firm->package;
    }

    /** @return array{label: string, listing_limit: ?int, credits: array{bumps: int, highlights: int, premium_xl: int}, advanced_stats: bool, products?: array}|null */
    public static function definition(?string $code): ?array
    {
        return match (true) {
            $code === self::FREE => config('firms.free_program'),
            in_array($code, self::PACKAGES, true) => config('firms.packages.'.$code),
            default => null,
        };
    }

    /** "Business Pro", "Prvih 30 firmi", or null. */
    public static function label(?Firm $firm): ?string
    {
        return static::definition(static::code($firm))['label'] ?? null;
    }

    /** Active listing limit of the current term; null = unlimited. Without a term: not applicable (caller uses the private limit). */
    public static function listingLimit(Firm $firm): ?int
    {
        $code = static::code($firm);
        $definition = static::definition($code);
        if ($code !== self::FREE && $definition !== null) {
            $product = $definition['products'][$firm->package_period ?? 'monthly'] ?? null;
            $row = $product ? Product::query()->where('code', $product)->first(['id', 'listing_limit']) : null;
            if ($row !== null) {
                return $row->listing_limit === null ? null : (int) $row->listing_limit;
            }
        }

        return $definition['listing_limit'] ?? null;
    }

    /** @return array{bumps: int, highlights: int, premium_xl: int} promotions included each month */
    public static function monthlyCredits(?Firm $firm): array
    {
        $credits = static::definition(static::code($firm))['credits'] ?? [];

        return [
            'bumps' => (int) ($credits['bumps'] ?? 0),
            'highlights' => (int) ($credits['highlights'] ?? 0),
            'premium_xl' => (int) ($credits['premium_xl'] ?? 0),
        ];
    }

    public static function hasAdvancedStats(?Firm $firm): bool
    {
        return (bool) (static::definition(static::code($firm))['advanced_stats'] ?? false);
    }

    public static function endsAt(?Firm $firm): ?CarbonInterface
    {
        return $firm?->package_ends_at;
    }

    /**
     * Starts or extends a term (for the payments module and the admin): a running term of the same
     * package is extended from its end, otherwise the new term starts now.
     */
    public static function grant(Firm $firm, string $package, string $period): void
    {
        abort_unless(in_array($package, self::PACKAGES, true) && in_array($period, self::PERIODS, true), 500, 'Unknown package.');

        $running = static::isActive($firm) && ! $firm->free_program && $firm->package === $package;
        $from = $running ? $firm->package_ends_at->copy() : now();
        $ends = $period === 'yearly' ? $from->copy()->addYear() : $from->copy()->addMonth();

        $firm->forceFill([
            'package' => $package,
            'package_period' => $period,
            'package_starts_at' => $running ? $firm->package_starts_at : now(),
            'package_ends_at' => $ends,
            'free_program' => false,
        ])->save();
    }

    /** The free first year of the first 30 firms (admin action); counts as one of the 30 places. */
    public static function grantFreeYear(Firm $firm): void
    {
        $firm->forceFill([
            'package' => null,
            'package_period' => null,
            'package_starts_at' => now(),
            'package_ends_at' => now()->addMonths((int) config('firms.free_program.months', 12)),
            'free_program' => true,
            'founding_free' => true,
        ])->save();
    }
}
