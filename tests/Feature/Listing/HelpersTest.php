<?php

namespace Tests\Feature\Listing;

use App\Models\Listing;
use App\Support\Listing\LoanCalculator;
use App\Support\Listing\Text;
use App\Support\Listing\WorkingHours;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class HelpersTest extends TestCase
{
    public function test_plural_and_relative_dates(): void
    {
        $this->assertSame('1 aktivan oglas', Text::activeListings(1));
        $this->assertSame('3 aktivna oglasa', Text::activeListings(3));
        $this->assertSame('11 aktivnih oglasa', Text::activeListings(11));
        $this->assertSame('21 aktivan oglas', Text::activeListings(21));

        $now = CarbonImmutable::parse('2026-10-23 14:00', Text::TIMEZONE);
        $this->assertSame('upravo', Text::relative($now->subSeconds(20), $now));
        $this->assertSame('pre 5 minuta', Text::relative($now->subMinutes(5), $now));
        $this->assertSame('pre 2 sata', Text::relative($now->subHours(2), $now));
        $this->assertSame('juče', Text::relative($now->subDay(), $now));
        $this->assertSame('pre 3 dana', Text::relative($now->subDays(3), $now));
        $this->assertSame('pre 2 nedelje', Text::relative($now->subDays(14), $now));
        $this->assertSame('pre 3 meseca', Text::relative($now->subDays(95), $now));
        $this->assertSame('pre 2 godine', Text::relative($now->subDays(800), $now));
    }

    public function test_initials(): void
    {
        $this->assertSame('AC', Text::wordInitials('Auto centar Drina'));
        $this->assertSame('ŽD', Text::initials('Žikica Drezgić'));
        $this->assertSame('Suncica Perisic', Text::displayName('suncica perisic'));
        $this->assertSame('Auto centar DRINA', Text::displayName('Auto centar DRINA'));
        $this->assertSame('đorđe ćirić', mb_strtolower(Text::displayName('đorđe ćirić')));
        $this->assertSame('Đorđe Ćirić', Text::displayName('đorđe ćirić'));
    }

    public function test_description_escapes_and_links_safely(): void
    {
        $html = (string) Text::description("<script>x</script>\n\nPogledaj https://primer.rs/a?b=1 i www.test.rs\njavascript:alert(1)");

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('<a href="https://primer.rs/a?b=1" rel="nofollow ugc noopener" target="_blank">', $html);
        $this->assertStringContainsString('href="https://www.test.rs"', $html);
        $this->assertStringNotContainsString('href="javascript', $html);
        $this->assertSame(2, substr_count($html, '<p>'));
    }

    public function test_working_hours_status_cases_from_the_wordpress_notes(): void
    {
        $hours = WorkingHours::days([1 => '08–17', 2 => '08-12, 13-17', 3 => '08–17', 4 => '8.30–17', 5 => '08–17', 6 => '08–14', 7 => 'Zatvoreno']);
        $schedule = WorkingHours::schedule($hours);
        // 2026-10-19 is a Monday.
        $at = fn (string $day, string $time) => WorkingHours::status($schedule, CarbonImmutable::parse('2026-10-'.$day.' '.$time, Text::TIMEZONE))['text'] ?? null;

        $this->assertSame('Zatvoreno, otvara se u 08:00', $at('19', '07:10'));
        $this->assertSame('Otvoreno do 17:00', $at('19', '10:00'));
        $this->assertSame('Zatvoreno, otvara se u 13:00', $at('20', '12:30'));
        $this->assertSame('Zatvoreno, otvara se u 08:30', $at('22', '08:00'));
        $this->assertSame('Zatvoreno, otvara se sutra u 08:00', $at('23', '18:25'));
        $this->assertSame('Zatvoreno, otvara se u ponedeljak u 08:00', $at('24', '15:00'));
        $this->assertSame('Zatvoreno, otvara se sutra u 08:00', $at('25', '11:00'));

        $vague = WorkingHours::schedule(WorkingHours::days([1 => 'po dogovoru']));
        $this->assertNull(WorkingHours::status($vague, CarbonImmutable::parse('2026-10-19 10:00', Text::TIMEZONE)));
    }

    public function test_loan_calculator(): void
    {
        $this->assertEqualsWithDelta(162.21, LoanCalculator::monthly(8000, 60, 8), 0.01);
        $this->assertSame(0.0, LoanCalculator::monthly(0, 60, 8));
        $this->assertSame('12.500 €', LoanCalculator::money(12500.4, '€'));
        $this->assertFalse(LoanCalculator::applies(Listing::factory()->make(['price' => 500, 'price_eur' => 500])));
        $this->assertTrue(LoanCalculator::applies(Listing::factory()->make(['price' => 8900, 'price_eur' => 8900])));
    }
}
