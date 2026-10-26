<?php

namespace Tests\Feature\Accounts;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Auth\ApiTokens;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    public function test_reset_link_is_sent_with_site_url(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->from('/zaboravljena-lozinka/')->post('/zaboravljena-lozinka/', ['email' => 'Ana@example.com'])
            ->assertRedirect('/zaboravljena-lozinka/')
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
            $mail = $n->toMail($user);
            $this->assertSame('Nova lozinka za loglasi.rs', $mail->subject);
            $this->assertStringContainsString('/nova-lozinka/'.$n->token.'/?email=ana%40example.com', $mail->actionUrl);

            return true;
        });
    }

    public function test_unknown_email_gets_same_answer_and_no_mail(): void
    {
        Notification::fake();

        $this->post('/zaboravljena-lozinka/', ['email' => 'nema@example.com'])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    public function test_blocked_user_gets_no_mail(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'blok@example.com']);
        $user->forceFill(['status' => UserStatus::Blocked])->save();

        $this->post('/zaboravljena-lozinka/', ['email' => 'blok@example.com'])->assertSessionHas('status');

        Notification::assertNothingSent();
    }

    public function test_reset_form_renders(): void
    {
        $this->get('/nova-lozinka/abc/?email=ana@example.com')
            ->assertOk()
            ->assertSee('Nova lozinka')
            ->assertSee('value="ana@example.com"', false)
            ->assertSee('value="abc"', false);
    }

    public function test_password_is_reset_and_devices_logged_out(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        ApiTokens::issue($user, 'Pixel 8');
        $token = Password::broker()->createToken($user);

        $this->post('/nova-lozinka/', [
            'token' => $token,
            'email' => 'ana@example.com',
            'password' => 'novalozinka1',
            'password_confirmation' => 'novalozinka1',
        ])->assertRedirect(url_slash('login-registracija'))->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('novalozinka1', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame(0, $user->refreshTokens()->whereNull('revoked_at')->count());
    }

    public function test_invalid_token_is_rejected(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->post('/nova-lozinka/', [
            'token' => 'pogresan',
            'email' => 'ana@example.com',
            'password' => 'novalozinka1',
            'password_confirmation' => 'novalozinka1',
        ])->assertSessionHasErrors(['email' => 'Link za novu lozinku nije ispravan ili je istekao. Zatražite novi.']);
    }

    public function test_new_password_is_validated(): void
    {
        $this->post('/nova-lozinka/', ['token' => 'x', 'email' => 'ana@example.com', 'password' => 'kratka', 'password_confirmation' => 'kratka'])
            ->assertSessionHasErrors('password');
    }

    public function test_reset_request_is_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/zaboravljena-lozinka/', ['email' => 'ana@example.com']);
        }

        $this->post('/zaboravljena-lozinka/', ['email' => 'ana@example.com'])
            ->assertSessionHasErrors(['email' => 'Previše pokušaja. Sačekajte malo pa probajte ponovo.']);
    }
}
