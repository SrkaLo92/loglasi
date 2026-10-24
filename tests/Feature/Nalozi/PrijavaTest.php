<?php

namespace Tests\Feature\Nalozi;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PrijavaTest extends TestCase
{
    public function test_pages_render_in_serbian_with_noindex(): void
    {
        foreach (['/login-registracija/' => 'Prijava i registracija', '/zaboravljena-lozinka/' => 'Pošalji link'] as $url => $text) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<meta name="robots" content="noindex, follow">', false)
                ->assertSee($text)
                ->assertSee('css/nalozi.css', false);
        }
    }

    public function test_one_card_with_both_forms_and_accessible_tabs(): void
    {
        $this->get('/login-registracija/')
            ->assertOk()
            ->assertSee('role="tablist"', false)
            ->assertSee('id="lgn-tab-prijava" href="'.url_slash('login-registracija').'?tab=prijava" aria-controls="lgn-panel-prijava"', false)
            ->assertSee('aria-selected="true" tabindex="0" data-lgn-tab="prijava">Prijava</a>', false)
            ->assertSee('aria-selected="false" tabindex="-1" data-lgn-tab="registracija">Novi nalog</a>', false)
            ->assertSee('id="lgn-panel-prijava" aria-labelledby="lgn-tab-prijava" >', false)
            ->assertSee('id="lgn-panel-registracija" aria-labelledby="lgn-tab-registracija"  hidden', false)
            ->assertSee('action="'.url_slash('prijava').'"', false)
            ->assertSee('action="'.url_slash('registracija').'"', false)
            ->assertSee('js/nalozi.js', false);
    }

    public function test_tab_is_chosen_by_query_string(): void
    {
        $this->get('/login-registracija/?tab=registracija')
            ->assertOk()
            ->assertSee('aria-selected="true" tabindex="0" data-lgn-tab="registracija">Novi nalog</a>', false)
            ->assertSee('id="lgn-panel-prijava" aria-labelledby="lgn-tab-prijava"  hidden', false);

        $this->get('/login-registracija/?tab=nesto')
            ->assertOk()
            ->assertSee('aria-selected="true" tabindex="0" data-lgn-tab="prijava">Prijava</a>', false);
    }

    public function test_failed_registration_comes_back_on_its_tab(): void
    {
        $this->from('/login-registracija/')->post('/registracija/', ['_tab' => 'registracija', 'email' => 'x'])
            ->assertRedirect('/login-registracija/');

        $this->get('/login-registracija/')
            ->assertSee('aria-selected="true" tabindex="0" data-lgn-tab="registracija">Novi nalog</a>', false);
    }

    public function test_old_login_and_register_urls_redirect_to_the_tabs(): void
    {
        $this->get('/prijava/')->assertStatus(301)->assertRedirect(url_slash('login-registracija').'?tab=prijava');
        $this->get('/registracija/')->assertStatus(301)->assertRedirect(url_slash('login-registracija').'?tab=registracija');
    }

    public function test_redirect_from_old_url_keeps_the_flash_message(): void
    {
        $this->withSession(['status' => 'Poruka za prijavu', '_flash' => ['old' => [], 'new' => ['status']]])
            ->get('/prijava/')->assertStatus(301);

        $this->get('/login-registracija/?tab=prijava')->assertSee('Poruka za prijavu');
    }

    public function test_internal_links_use_the_single_login_page(): void
    {
        $this->get('/zaboravljena-lozinka/')->assertSee('href="'.url_slash('login-registracija').'"', false);
        $this->get('/moj-nalog/')->assertRedirect(url_slash('login-registracija'));
    }

    public function test_user_logs_in_with_email_and_password(): void
    {
        $user = User::factory()->create(['email' => 'marko@example.com']);

        $this->post('/prijava/', ['email' => ' Marko@Example.com ', 'password' => 'lozinka123'])
            ->assertRedirect(url_slash('moj-nalog'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_remember_me_sets_cookie(): void
    {
        User::factory()->create(['email' => 'marko@example.com']);

        $response = $this->post('/prijava/', ['email' => 'marko@example.com', 'password' => 'lozinka123', 'remember' => '1']);

        $cookies = collect($response->headers->getCookies())->map->getName();
        $this->assertTrue($cookies->contains(fn ($name) => str_starts_with($name, 'remember_web_')));
    }

    public function test_intended_url_is_kept(): void
    {
        User::factory()->create(['email' => 'marko@example.com']);

        $this->get('/moj-nalog/')->assertRedirect(url_slash('login-registracija'));
        $this->post('/prijava/', ['email' => 'marko@example.com', 'password' => 'lozinka123'])
            ->assertRedirect(url('/moj-nalog'));
    }

    public function test_wrong_password_shows_error(): void
    {
        User::factory()->create(['email' => 'marko@example.com']);

        $this->from('/prijava/')->post('/prijava/', ['email' => 'marko@example.com', 'password' => 'pogresna'])
            ->assertRedirect('/prijava/')
            ->assertSessionHasErrors(['email' => 'Pogrešan email ili lozinka.']);

        $this->assertGuest();
    }

    public function test_unknown_email_shows_same_error(): void
    {
        $this->post('/prijava/', ['email' => 'nema@example.com', 'password' => 'lozinka123'])
            ->assertSessionHasErrors(['email' => 'Pogrešan email ili lozinka.']);
    }

    public function test_validation_errors_in_serbian(): void
    {
        $this->post('/prijava/', ['email' => '', 'password' => ''])
            ->assertSessionHasErrors(['email' => 'Polje email je obavezno.', 'password' => 'Polje lozinka je obavezno.']);
    }

    public function test_blocked_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['email' => 'blok@example.com']);
        $user->forceFill(['status' => UserStatus::Blocked])->save();

        $this->post('/prijava/', ['email' => 'blok@example.com', 'password' => 'lozinka123'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_deleted_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['email' => 'obrisan@example.com']);
        $user->delete();

        $this->post('/prijava/', ['email' => 'obrisan@example.com', 'password' => 'lozinka123'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'marko@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/prijava/', ['email' => 'marko@example.com', 'password' => 'pogresna']);
        }

        $this->post('/prijava/', ['email' => 'marko@example.com', 'password' => 'lozinka123'])
            ->assertSessionHasErrors(['email' => 'Previše pokušaja. Sačekajte malo pa probajte ponovo.']);
        $this->assertGuest();

        RateLimiter::clear('login:marko@example.com|127.0.0.1');
    }

    public function test_logged_in_user_is_redirected_from_login(): void
    {
        $this->actingAs(User::factory()->create())->get('/prijava/')->assertRedirect(url_slash('moj-nalog'));
    }

    public function test_logout(): void
    {
        $this->actingAs(User::factory()->create())->post('/odjava/')->assertRedirect(url_slash('/'));
        $this->assertGuest();
    }

    public function test_logout_requires_post(): void
    {
        $this->actingAs(User::factory()->create())->get('/odjava/')->assertStatus(405);
    }

    public function test_blocked_user_with_session_is_logged_out(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['status' => UserStatus::Blocked])->save();

        $this->actingAs($user)->get('/moj-nalog/')
            ->assertRedirect(url_slash('login-registracija'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
