<?php

namespace Tests\Unit;

use App\Support\Search\TimeAgo;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TimeAgoTest extends TestCase
{
    /** @return array<string, array{int, string}> */
    public static function ages(): array
    {
        return [
            'minutes' => [5 * 60, 'pre 5 minuta'],
            'one hour' => [3600, 'pre 1 sat'],
            'yesterday' => [86400, 'juče'],
            'days' => [3 * 86400, 'pre 3 dana'],
            'one week' => [7 * 86400, 'pre nedelju dana'],
            'two weeks' => [14 * 86400, 'pre 2 nedelje'],
            'one month' => [35 * 86400, 'pre mesec dana'],
            'months' => [70 * 86400, 'pre 2 meseca'],
            'one year' => [400 * 86400, 'pre godinu dana'],
            'years' => [800 * 86400, 'pre 2 godine'],
        ];
    }

    #[DataProvider('ages')]
    public function test_relative_time_reads_naturally(int $seconds, string $expected): void
    {
        $now = CarbonImmutable::parse('2026-10-27 12:00:00');

        $this->assertSame($expected, TimeAgo::format($now->subSeconds($seconds), $now));
    }
}
