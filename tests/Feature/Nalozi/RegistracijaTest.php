<?php

namespace Tests\Feature\Nalozi;

use App\Enums\AccountType;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistracijaTest extends TestCase
{
    private function data(array $override = []): array
    {
        return array_merge([
            'name' => 'Jovana Đurić',
            'email' => 'Jovana@Example.com',
            'phone' => '065/541-5128',
            'password' => 'lozinka123',
            'password_confirmation' => 'lozinka123',
            'accept_terms' => '1',
        ], $override);
    }

    public function test_registration_creates_private_account_and_sends_verification(): void
    {
        Notification::fake();

        $this->post('/registracija/', $this->data())
            ->assertRedirect(url_slash('moj-nalog'))
            ->assertSessionHas('status');

        $user = User::where('email', 'jovana@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Jovana Đurić', $user->name);
        $this->assertSame('+381655415128', $user->phone);
        $this->assertNull($user->phone_verified_at);
        $this->assertNull($user->email_verified_at);
        $this->assertSame(AccountType::Private, $user->account_type);
        $this->assertSame(Role::User, $user->role);
        $this->assertSame('jovana-djuric', $user->slug);
        $this->assertSame('argon2id', password_get_info($user->password)['algoName']);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_cannot_register_as_firm_or_admin(): void
    {
        $this->post('/registracija/', $this->data(['account_type' => 'firm', 'role' => 'admin', 'status' => 'active']));

        $user = User::where('email', 'jovana@example.com')->firstOrFail();
        $this->assertSame(AccountType::Private, $user->account_type);
        $this->assertSame(Role::User, $user->role);
    }

    public function test_slug_is_unique(): void
    {
        User::factory()->create(['slug' => 'jovana-djuric']);

        $this->post('/registracija/', $this->data());

        $this->assertSame('jovana-djuric-2', User::where('email', 'jovana@example.com')->value('slug'));
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'jovana@example.com']);

        $this->post('/registracija/', $this->data())->assertSessionHasErrors('email');
        $this->assertSame(1, User::where('email', 'jovana@example.com')->count());
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->post('/registracija/', $this->data(['phone' => '123']))
            ->assertSessionHasErrors(['phone' => 'Unesite ispravan broj telefona, npr. 065 123 4567.']);
        $this->assertGuest();
    }

    public function test_phone_is_required_on_site(): void
    {
        $this->post('/registracija/', $this->data(['phone' => '']))->assertSessionHasErrors('phone');
    }

    public function test_terms_must_be_accepted(): void
    {
        $this->post('/registracija/', $this->data(['accept_terms' => null]))
            ->assertSessionHasErrors(['accept_terms' => 'Morate prihvatiti uslove korišćenja.']);
    }

    public function test_password_rules(): void
    {
        $this->post('/registracija/', $this->data(['password' => 'kratka', 'password_confirmation' => 'kratka']))
            ->assertSessionHasErrors(['password' => 'Polje lozinka mora imati najmanje 8 znakova.']);

        $this->post('/registracija/', $this->data(['password_confirmation' => 'drugacija']))
            ->assertSessionHasErrors(['password' => 'Lozinke se ne poklapaju.']);
    }

    public function test_invalid_email_and_long_name(): void
    {
        $this->post('/registracija/', $this->data(['email' => 'nije-email', 'name' => str_repeat('a', 81)]))
            ->assertSessionHasErrors(['email' => 'Unesite ispravnu email adresu.', 'name']);
    }

    public function test_html_in_name_is_removed(): void
    {
        $this->post('/registracija/', $this->data(['name' => '<b>Jovana</b>   Đurić']));

        $this->assertSame('Jovana Đurić', User::where('email', 'jovana@example.com')->value('name'));
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/registracija/', $this->data(['email' => "r{$i}@example.com"]));
            $this->post('/odjava/');
        }

        $this->post('/registracija/', $this->data(['email' => 'sesti@example.com']))
            ->assertSessionHasErrors(['email' => 'Previše pokušaja. Sačekajte malo pa probajte ponovo.']);
        $this->assertDatabaseMissing('users', ['email' => 'sesti@example.com']);
    }

    public function test_password_is_hashed(): void
    {
        $this->post('/registracija/', $this->data());

        $this->assertTrue(Hash::check('lozinka123', User::where('email', 'jovana@example.com')->value('password')));
    }
}
