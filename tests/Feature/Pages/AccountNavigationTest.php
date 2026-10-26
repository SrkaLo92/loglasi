<?php

namespace Tests\Feature\Pages;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_account_page_has_the_same_navigation_with_the_current_page_marked(): void
    {
        $user = User::factory()->create();

        foreach (['moj-nalog' => 'Pregled', 'moj-nalog/oglasi' => 'Moji oglasi', 'moj-nalog/poruke' => 'Poruke', 'moj-nalog/obavestenja' => 'Obaveštenja', 'moj-nalog/narudzbine' => 'Narudžbine', 'moj-nalog/podesavanja' => 'Podešavanja naloga'] as $path => $label) {
            $html = $this->actingAs($user)->get('/'.$path.'/')->assertOk()
                ->assertSee('class="lg-acct lg-wrap"', false)
                ->assertDontSee('Imate nešto za prodaju?')
                ->getContent();
            $this->assertMatchesRegularExpression('#aria-current="page">\s*<svg[^>]*>.*?</svg>\s*<span class="lg-acct-nav__label">'.preg_quote($label, '#').'</span>#s', $html);
        }
    }

    public function test_public_pages_have_no_account_navigation_and_keep_the_footer_call_to_action(): void
    {
        $this->actingAs(User::factory()->create())->get('/kontakt/')->assertOk()
            ->assertDontSee('class="lg-acct', false)
            ->assertSee('Imate nešto za prodaju?')
            ->assertSee('Pravne informacije');
    }
}
