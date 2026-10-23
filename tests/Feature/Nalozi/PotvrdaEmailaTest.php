<?php

namespace Tests\Feature\Nalozi;

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PotvrdaEmailaTest extends TestCase
{
    private function link(User $user, ?string $hash = null): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => $hash ?? sha1($user->email),
        ]);
    }

    public function test_signed_link_verifies_email_without_login(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->link($user))->assertRedirect(url_slash('prijava'))->assertSessionHas('status');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_logged_in_user_goes_to_account(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get($this->link($user))->assertRedirect(url_slash('moj-nalog'));
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_wrong_hash_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get($this->link($user, sha1('drugi@example.com')))->assertForbidden();
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_unsigned_link_is_rejected(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get('/potvrda-emaila/'.$user->id.'/'.sha1($user->email))->assertForbidden();
    }

    public function test_verification_mail_is_in_serbian(): void
    {
        $user = User::factory()->unverified()->create();
        $mail = (new VerifyEmail)->toMail($user);

        $this->assertSame('Potvrdite email adresu na loglasi.rs', $mail->subject);
        $this->assertStringContainsString('/potvrda-emaila/'.$user->id.'/', $mail->actionUrl);
    }

    public function test_notice_and_resend(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/potvrda-emaila/')->assertOk()->assertSee($user->email);
        $this->actingAs($user)->post('/potvrda-emaila/ponovo/')->assertSessionHas('status');

        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_verified_user_skips_notice(): void
    {
        $this->actingAs(User::factory()->create())->get('/potvrda-emaila/')->assertRedirect(url_slash('moj-nalog'));
    }

    public function test_notice_requires_login(): void
    {
        $this->get('/potvrda-emaila/')->assertRedirect(url_slash('prijava'));
    }
}
