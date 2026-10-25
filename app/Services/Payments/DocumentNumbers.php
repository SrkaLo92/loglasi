<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\DB;

/**
 * Running numbers for documents we number ourselves (firm invoices, credit notes), one series per
 * calendar year: "1/2026", "2/2026", ... and "KO-1/2026" for credit notes. The counter row is locked,
 * so two payments confirmed at the same moment never get the same number.
 */
final class DocumentNumbers
{
    public const INVOICE = 'invoice';

    public const CREDIT_NOTE = 'credit';

    private const PREFIX = [self::INVOICE => '', self::CREDIT_NOTE => 'KO-'];

    public static function next(string $kind, ?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $series = $kind.'-'.$year;

        $number = DB::transaction(function () use ($series) {
            DB::table('document_counters')->insertOrIgnore(['series' => $series, 'last_number' => 0, 'created_at' => now(), 'updated_at' => now()]);
            $last = (int) DB::table('document_counters')->where('series', $series)->lockForUpdate()->value('last_number');
            DB::table('document_counters')->where('series', $series)->update(['last_number' => $last + 1, 'updated_at' => now()]);

            return $last + 1;
        });

        return (self::PREFIX[$kind] ?? '').$number.'/'.$year;
    }
}
