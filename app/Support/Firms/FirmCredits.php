<?php

namespace App\Support\Firms;

use App\Models\Firm;
use App\Models\FirmMonthlyCredit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Monthly included promotions of a firm package: Pro 10 bumps + 2 highlights, Max 20 bumps +
 * 5 highlights + 1 Premium XL, the free first year 5 bumps + 1 highlight. Granted per calendar month
 * (Europe/Belgrade), unused credits do not carry over. The row of a month is created on first use
 * from the package the firm has at that moment.
 *
 *   FirmCredits::remaining($firm);            // ['bumps' => 7, 'highlights' => 2, 'premium_xl' => 0]
 *   FirmCredits::consume($firm, 'bumps');     // true when a credit was used (payments/posting apply the bump)
 */
class FirmCredits
{
    public const TYPES = ['bumps', 'highlights', 'premium_xl'];

    /** @return array{bumps: int, highlights: int, premium_xl: int} */
    public static function remaining(Firm $firm): array
    {
        $row = static::current($firm);
        $out = [];
        foreach (self::TYPES as $type) {
            $out[$type] = $row ? max(0, (int) $row->{$type.'_granted'} - (int) $row->{$type.'_used'}) : 0;
        }

        return $out;
    }

    /** Uses one credit of the type; false when none is left (or the firm has no active package). */
    public static function consume(Firm $firm, string $type): bool
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown credit type {$type}.");
        }
        $row = static::current($firm);
        if ($row === null) {
            return false;
        }

        // Atomic: two requests cannot use the last credit twice.
        return DB::table('firm_monthly_credits')
            ->where('id', $row->id)
            ->whereColumn($type.'_used', '<', $type.'_granted')
            ->update([$type.'_used' => DB::raw($type.'_used + 1'), 'updated_at' => now()]) === 1;
    }

    /** This month's row, created from the current package; null without an active package. */
    public static function current(Firm $firm): ?FirmMonthlyCredit
    {
        if (! FirmPackage::isActive($firm) || ! $firm->exists) {
            return null;
        }
        $month = static::month()->toDateString();
        $credits = FirmPackage::monthlyCredits($firm);

        $row = FirmMonthlyCredit::query()->firstOrCreate(['firm_id' => $firm->id, 'month' => $month], [
            'bumps_granted' => $credits['bumps'],
            'highlights_granted' => $credits['highlights'],
            'premium_xl_granted' => $credits['premium_xl'],
        ]);

        // An upgrade during the month raises the allowance (never lowers it).
        $raise = [];
        foreach (self::TYPES as $type) {
            if ($credits[$type] > (int) $row->{$type.'_granted'}) {
                $raise[$type.'_granted'] = $credits[$type];
            }
        }
        if ($raise !== []) {
            $row->forceFill($raise)->save();
        }

        return $row;
    }

    public static function month(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfMonth();
    }
}
