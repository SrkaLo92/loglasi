<?php

namespace Tests\Feature\Uvoz;

use App\Models\Listing;
use App\Models\User;

/** Keys verified on the real local WordPress database (2026-10-24). */
class RealListivoKeysTest extends UvozTestCase
{
    public function test_featured_expire_date_is_used(): void
    {
        $this->import()->assertSuccessful();

        $this->assertSame('2030-01-15 12:00:00', Listing::find(5001)->featured_until->format('Y-m-d H:i:s'));
    }

    public function test_country_code_with_flag_entity_is_parsed(): void
    {
        $this->import()->assertSuccessful();

        $this->assertSame('+381655415128', User::where('wp_id', 1)->value('phone'));
    }
}
