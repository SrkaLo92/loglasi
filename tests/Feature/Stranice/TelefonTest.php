<?php

namespace Tests\Feature\Stranice;

use App\Support\Stranice\Cenovnik;
use App\Support\Stranice\Telefon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TelefonTest extends TestCase
{
    public static function numbers(): array
    {
        return [
            ['065 123 4567', '+381651234567', '+381 65 123 4567'],
            ['0651234567', '+381651234567', '+381 65 123 4567'],
            ['+381 65 123 4567', '+381651234567', '+381 65 123 4567'],
            ['00381651234567', '+381651234567', '+381 65 123 4567'],
            ['381641112222', '+381641112222', '+381 64 111 2222'],
            ['015 123 456', '+38115123456', '+381 15 123 456'],
            ['0230 12345', '+38123012345', '+381 230 123 45'],
            ['+387 65 123 456', '+38765123456', '+38765123456'],
            ['123', '', ''],
            ['', '', ''],
        ];
    }

    #[DataProvider('numbers')]
    public function test_normalize_and_display(string $raw, string $e164, string $display): void
    {
        $this->assertSame($e164, Telefon::normalize($raw));
        $this->assertSame($display, Telefon::display($raw));
    }

    public function test_serbian_plural_forms(): void
    {
        $f = fn (int $n) => Cenovnik::plural($n, 'mesto', 'mesta', 'mesta_m');
        $this->assertSame(['mesto', 'mesta', 'mesta_m', 'mesta_m', 'mesto', 'mesta', 'mesta_m'], array_map($f, [1, 3, 5, 11, 21, 23, 112]));
        $this->assertSame("6.000\u{00A0}RSD", Cenovnik::price(6000));
        $this->assertSame('Besplatno', Cenovnik::price(0));
    }
}
