<?php

namespace Tests\Feature;

use Tests\TestCase;

class PocetnaTest extends TestCase
{
    public function test_home_page_renders_in_serbian(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="sr-Latn">', false)
            ->assertSee('Besplatni oglasi Loznica');
    }
}
