<?php

namespace Tests\Feature\Uvoz;

use App\Uvoz\Phone;
use App\Uvoz\Text;
use Tests\TestCase;

class PomocniciTest extends TestCase
{
    public function test_phone_is_normalized_like_loglasi_core(): void
    {
        foreach (['0655415128', '065/541-5128', '+381 65 541 5128', '381655415128', '00381655415128', '+3810655415128', 'tel:065 541 5128'] as $raw) {
            $this->assertSame('+381655415128', Phone::normalize($raw), $raw);
        }
        $this->assertSame('+38115123456', Phone::normalize('015 123456'));
        $this->assertSame('+38761123456', Phone::normalize('+387 061 123 456'));
        $this->assertSame('', Phone::normalize('12345'));
        $this->assertSame('', Phone::normalize(''));
        $this->assertSame('', Phone::normalize('0991234567'));
    }

    public function test_phone_is_joined_with_separate_country_code(): void
    {
        $this->assertSame('+381655415128', Phone::join('0655415128', '+381'));
        $this->assertSame('+381655415128', Phone::join('655415128', '381'));
        $this->assertSame('+381655415128', Phone::join('+381655415128', '+381'));
        $this->assertSame('+38761123456', Phone::join('61123456', '+387'));
    }

    public function test_amount_is_parsed_like_loglasi_cena(): void
    {
        $this->assertSame(8900.0, Text::amount('8.900'));
        $this->assertSame(8900.0, Text::amount('8 900'));
        $this->assertSame(12500.5, Text::amount('12500,50'));
        $this->assertSame(1500.0, Text::amount(serialize(['value' => '1500'])));
        $this->assertNull(Text::amount('0'));
        $this->assertNull(Text::amount(''));
        $this->assertNull(Text::amount('po dogovoru'));
    }

    public function test_id_lists_and_text(): void
    {
        $this->assertSame([1, 2, 3], Text::idList(serialize([1, '2', 3])));
        $this->assertSame([4, 5], Text::idList('4,5'));
        $this->assertSame([6, 7], Text::idList('[6,7]'));
        $this->assertSame([8], Text::idList(serialize([['id' => 8]])));
        $this->assertSame([], Text::idList(''));
        $this->assertSame([], Text::idList('O:8:"stdClass":0:{}'));

        $this->assertSame("Prvi red\n\nDrugi – red", Text::plain('<p>Prvi red</p><p>Drugi &ndash; red [gallery ids="1"]</p>'));
        $this->assertSame('A & B', Text::title('<b>A &amp; B</b>'));
    }
}
