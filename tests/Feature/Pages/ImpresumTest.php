<?php

namespace Tests\Feature\Pages;

use App\Support\Settings;
use Tests\TestCase;

class ImpresumTest extends TestCase
{
    public function test_footer_terms_and_privacy_name_the_business(): void
    {
        Settings::set('contact_email', 'office@loglasi.rs');

        $this->get('/')->assertOk()
            ->assertSee('Sajtom upravlja SRĐAN MARINKOVIĆ PR VEB PORTALI ALPHASOFT DEV KLUPCI, Marka Kulića 57G, Klupci, 15300 Loznica.')
            ->assertSee('MB 68713919, PIB 115879910.')
            ->assertSee('Registrovan u Agenciji za privredne registre (APR).')
            ->assertSee('mailto:office@loglasi.rs', false)
            ->assertSee('"legalName":"SRĐAN MARINKOVIĆ PR VEB PORTALI ALPHASOFT DEV KLUPCI"', false)
            ->assertSee('"taxID":"115879910"', false);

        $this->get('/uslovi-koriscenja/')->assertOk()->assertSee('Pružalac usluge')->assertSee('68713919');
        $this->get('/politika-privatnosti/')->assertOk()->assertSee('Rukovalac podacima')
            ->assertSee('Rukovalac podacima je SRĐAN MARINKOVIĆ PR VEB PORTALI ALPHASOFT DEV KLUPCI, Marka Kulića 57G');
    }

    public function test_admin_settings_change_it(): void
    {
        Settings::set('company_name', 'NOVA RADNJA PR');

        $this->get('/uslovi-koriscenja/')->assertOk()->assertSee('NOVA RADNJA PR')->assertDontSee('ALPHASOFT DEV KLUPCI');
    }
}
