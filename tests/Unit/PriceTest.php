<?php

namespace Tests\Unit;

use App\Enums\Currency;
use App\Support\Price;
use App\Support\Settings;
use Tests\TestCase;

class PriceTest extends TestCase
{
    public function test_label_formats_like_the_current_site(): void
    {
        $this->assertSame('8.900 €', Price::label(8900, Currency::EUR));
        $this->assertSame('12.500 din', Price::label(12500, Currency::RSD));
        $this->assertSame('1.250,50 €', Price::label(1250.5, Currency::EUR));
        $this->assertSame('Dogovor', Price::label(null, Currency::EUR));
    }

    public function test_rsd_is_converted_with_the_admin_rate(): void
    {
        Settings::set('eur_rate', 117.0);

        $this->assertSame(100.0, Price::toEur(11700, Currency::RSD));
        $this->assertSame(50.0, Price::toEur(50, Currency::EUR));
    }
}
