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
        foreach (['/prijava/' => 'Prijavi se', '/registracija/' => 'Napravi nalog', '/login-registracija/' => 'Prijava i registracija', '/zaboravljena-lozinka/' => 'Pošalji link'] as $url => $text) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<meta name="robots" content="noindex, follow">', false)
                ->assertSee($text)
                ->assertSee('css/nalozi.css', false);
        }
    }

    public function test_combined_page_has_both_forms(): void
    {
        $this->get('/login-registracija/')
            ->assertOk()
            ->assertSee('action="'.url_slash('prijava').'"', false)
            ->assertSee('action="'.url_slash('registracija').'"', false);
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

        $this->get('/moj-nalog/')->assertRedirect(url_slash('prijava'));
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
            ->assertRedirect(url_slash('prijava'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
