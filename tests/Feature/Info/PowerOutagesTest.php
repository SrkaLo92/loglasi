<?php

namespace Tests\Feature\Info;

use App\Support\Info\PowerOutages;
use Tests\TestCase;

class PowerOutagesTest extends TestCase
{
    public function test_parses_rows_for_our_municipalities(): void
    {
        $html = <<<'HTML'
<html><head><meta charset="utf-8"></head><body>
<p>Планирана искључења за датум: 05.11.2026</p>
<table>
<tr><th>Огранак</th><th>Општина</th><th>Време</th><th>Улице</th></tr>
<tr><td>ЕД Лозница</td><td>ЛОЗНИЦА</td><td>08:30 - 13:00</td><td>Лешница: Цер, Шабачка</td></tr>
<tr><td>ЕД Шабац</td><td>ШАБАЦ</td><td>09:00 - 12:00</td><td>Мајур</td></tr>
<tr><td>ЕД Лозница</td><td>Мали Зворник</td><td>10:00-11:30</td><td>Радаљ</td></tr>
</table></body></html>
HTML;

        $rows = PowerOutages::parse($html, ['Loznica', 'Mali Zvornik']);

        $this->assertCount(2, $rows);
        $this->assertSame('Loznica', $rows[0]['municipality']);
        $this->assertSame('2026-11-05 08:30', $rows[0]['starts_at']->format('Y-m-d H:i'));
        $this->assertSame('2026-11-05 13:00', $rows[0]['ends_at']->format('Y-m-d H:i'));
        $this->assertStringContainsString('Lešnica', $rows[0]['area']);
        $this->assertSame('Mali Zvornik', $rows[1]['municipality']);
    }

    public function test_empty_or_dateless_page_gives_nothing(): void
    {
        $this->assertSame([], PowerOutages::parse('', ['Loznica']));
        $this->assertSame([], PowerOutages::parse('<table><tr><td>a</td><td>Loznica</td><td>08:00-09:00</td><td>x</td></tr></table>', ['Loznica']));
    }
}
