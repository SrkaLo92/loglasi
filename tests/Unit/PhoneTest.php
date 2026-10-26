<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The same cases as loglasi-core/tests/telefon-test.php. */
class PhoneTest extends TestCase
{
    public static function normalizeCases(): array
    {
        return [
            ['0655415128', '+381655415128'],
            ['065/541-5128', '+381655415128'],
            ['+381 65 541 5128', '+381655415128'],
            ['381655415128', '+381655415128'],
            ['00381655415128', '+381655415128'],
            ['+3810655415128', '+381655415128'],
            ['(065) 541.51.28', '+381655415128'],
            ['tel:+381655415128', '+381655415128'],
            ['65 541 5128', '+381655415128'],
            ['015 123456', '+38115123456'],
            ['011/123-4567', '+381111234567'],
            ['0230 12345', '+38123012345'],
            ['+387 65 123 456', '+38765123456'],
            ['+387 065 123 456', '+38765123456'],
            ['+39 06 1234 5678', '+390612345678'],
            ['', ''],
            ['abc', ''],
            ['123', ''],
            ['0800 123 456', ''],
            ['+381 12', ''],
            ['06512345678901234', ''],
            // extra: labels and extensions are dropped
            ['<b>065 541 5128</b>', '+381655415128'],
            ['065 541 5128 lok. 12', '+381655415128'],
        ];
    }

    #[DataProvider('normalizeCases')]
    public function test_normalize(string $input, string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($input));
    }

    public function test_normalize_accepts_null(): void
    {
        $this->assertSame('', Phone::normalize(null));
    }

    public function test_display(): void
    {
        $this->assertSame('+381 65 541 5128', Phone::display('0655415128'));
        $this->assertSame('065 541 5128', Phone::display('0655415128', true));
        $this->assertSame('065 541 5128', Phone::national('+381655415128'));
        $this->assertSame('+381 15 123 456', Phone::display('015 123456'));
        $this->assertSame('+38765123456', Phone::display('+387 65 123 456'));
        $this->assertSame('', Phone::display('xx'));
    }

    public function test_mask(): void
    {
        $this->assertSame('+381 65 541 ••••', Phone::mask('0655415128'));
        $this->assertSame('065 541 ••••', Phone::mask('+3810655415128', 4, true));
        $this->assertSame('', Phone::mask('xx'));
    }

    public function test_links(): void
    {
        $links = Phone::links('065/541-5128');

        $this->assertSame('tel:+381655415128', $links['tel']);
        $this->assertSame('https://wa.me/381655415128', $links['whatsapp']);
        $this->assertSame('viber://chat?number=%2B381655415128', $links['viber']);
        $this->assertSame('sms:+381655415128', $links['sms']);
        $this->assertSame('+381655415128', $links['e164']);
        $this->assertSame([], Phone::links('xx'));
    }

    public function test_validity_helpers(): void
    {
        $this->assertTrue(Phone::isValid('065 541 5128'));
        $this->assertFalse(Phone::isValid('123'));
        $this->assertTrue(Phone::isSerbian('015 123456'));
        $this->assertFalse(Phone::isSerbian('+387 65 123 456'));
        $this->assertTrue(Phone::isMobile('065 541 5128'));
        $this->assertFalse(Phone::isMobile('015 123456'));
        $this->assertSame(['cc' => '381', 'area' => '230', 'number' => '12345'], Phone::parts('0230 12345'));
    }
}
