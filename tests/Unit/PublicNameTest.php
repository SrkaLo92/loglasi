<?php

namespace Tests\Unit;

use App\Support\Accounts\PublicName;
use PHPUnit\Framework\TestCase;

class PublicNameTest extends TestCase
{
    public function test_finds_email_and_phone(): void
    {
        $this->assertTrue(PublicName::hasContact('Flat.sale.vracar@Gmail.com +381654635963'));
        $this->assertTrue(PublicName::hasContact('Marko 065/123-4567'));
        $this->assertTrue(PublicName::hasContact('marko@primer.rs'));
        $this->assertFalse(PublicName::hasContact('Auto servis 2000'));
        $this->assertFalse(PublicName::hasContact('Jovana Đurić'));
    }

    public function test_clean_removes_contact_and_falls_back(): void
    {
        $this->assertSame(PublicName::FALLBACK, PublicName::clean('Flat.sale.vracar@Gmail.com +381654635963'));
        $this->assertSame('Marko Petrović', PublicName::clean('Marko Petrović, 065 123 4567'));
        $this->assertSame('Pera', PublicName::clean('Pera - pera@gmail.com'));
    }
}
