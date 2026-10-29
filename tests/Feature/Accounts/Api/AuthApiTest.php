<?php

namespace Tests\Feature\Accounts\Api;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\PersonalAccessToken;
use App\Models\RefreshToken;
use App\Models\User;
use App\Support\Auth\ApiTokens;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    private function login(string $email = 'ana@example.com', array $extra = []): array
    {
        return $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'lozinka123'] + $extra)
            ->assertOk()
            ->json();
    }

    // Registracija

    public function test_register_returns_tokens_and_me(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana Anić',
            'email' => 'Ana@Example.com',
            'phone' => '+381 65 541 5128',
            'password' => 'lozinka123',
            'accept_terms' => true,
        ])->assertCreated();

        $response->assertJsonStructure(['access_token', 'refresh_token', 'expires_in', 'user' => ['id', 'name', 'email', 'role']])
            ->assertJsonPath('expires_in', 900)
            ->assertJsonPath('user.email', 'ana@example.com')
            ->assertJsonPath('user.phone', '+381655415128')
            ->assertJsonPath('user.phone_verified', false)
            ->assertJsonPath('user.role', 'private')
            ->assertJsonPath('user.firm_id', null);

        $user = User::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame(AccountType::Private, $user->account_type);
        Notification::assertSentTo($user, VerifyEmail::class);

        $this->withToken($response->json('access_token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('id', $user->id);
        $this->assertSame(64, strlen($response->json('refresh_token')));
        $this->assertDatabaseHas('refresh_tokens', ['user_id' => $user->id, 'token_hash' => hash('sha256', $response->json('refresh_token'))]);
        $this->assertDatabaseMissing('refresh_tokens', ['token_hash' => $response->json('refresh_token')]);
    }

    public function test_register_phone_is_optional(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Bez Telefona', 'email' => 'bez@example.com', 'password' => 'lozinka123', 'accept_terms' => true,
        ])->assertCreated()->assertJsonPath('user.phone', null);
    }

    public function test_register_conflict(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana', 'email' => 'ANA@example.com', 'password' => 'lozinka123', 'accept_terms' => true,
        ])->assertStatus(409)->assertExactJson([
            'code' => 'email_taken',
            'message' => 'Nalog sa ovom email adresom već postoji.',
            'fields' => ['email' => 'Nalog sa ovom email adresom već postoji.'],
        ]);
    }

    public function test_register_validation_error_shape(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => '', 'email' => 'nije-email', 'phone' => '123', 'password' => 'kratka', 'accept_terms' => false,
        ])->assertUnprocessable();

        $response->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('fields.name', 'Polje ime je obavezno.')
            ->assertJsonPath('fields.email', 'Unesite ispravnu email adresu.')
            ->assertJsonPath('fields.phone', 'Unesite ispravan broj telefona, npr. 065 123 4567.')
            ->assertJsonPath('fields.password', 'Polje lozinka mora imati najmanje 8 znakova.')
            ->assertJsonPath('fields.accept_terms', 'Morate prihvatiti uslove korišćenja.');
        $this->assertIsString($response->json('message'));
        $this->assertSame(['code', 'message', 'fields'], array_keys($response->json()));
    }

    public function test_register_ignores_role_fields(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'lozinka123', 'accept_terms' => true,
            'account_type' => 'firm', 'role' => 'admin',
        ])->assertCreated()->assertJsonPath('user.role', 'private');
    }

    public function test_register_rate_limit(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/auth/register', ['name' => 'A', 'email' => "x{$i}@example.com", 'password' => 'lozinka123', 'accept_terms' => true]);
        }

        $this->postJson('/api/v1/auth/register', ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'lozinka123', 'accept_terms' => true])
            ->assertStatus(429)
            ->assertJson(['code' => 'too_many_requests', 'message' => 'Previše pokušaja. Sačekajte malo pa probajte ponovo.'])
            ->assertHeader('Retry-After');
    }

    // Prijava

    public function test_login(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $data = $this->login('ana@example.com', ['device_name' => 'Pixel 8']);

        $this->assertSame($user->id, $data['user']['id']);
        $this->assertSame(900, $data['expires_in']);
        $this->assertDatabaseHas('refresh_tokens', ['user_id' => $user->id, 'device_name' => 'Pixel 8']);
        $this->assertDatabaseHas('personal_access_tokens', ['tokenable_id' => $user->id, 'name' => 'Pixel 8']);
        $this->assertNotNull(PersonalAccessToken::first()->expires_at);
    }

    public function test_login_wrong_password(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'pogresna'])
            ->assertUnauthorized()
            ->assertExactJson(['code' => 'invalid_credentials', 'message' => 'Pogrešan email ili lozinka.']);
    }

    public function test_login_blocked(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $user->forceFill(['status' => UserStatus::Blocked])->save();

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'lozinka123'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'account_blocked');
        $this->assertSame(0, RefreshToken::count());
    }

    public function test_login_missing_fields(): void
    {
        $this->postJson('/api/v1/auth/login', [])->assertUnprocessable()->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['fields' => ['email', 'password']]);
    }

    public function test_login_rate_limit(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'x']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'ana@example.com', 'password' => 'lozinka123'])
            ->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }

    // Tokeni

    public function test_access_token_expires_after_15_minutes(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $data = $this->login();

        $this->travel(14)->minutes();
        $this->withToken($data['access_token'])->getJson('/api/v1/me')->assertOk();

        $this->travel(2)->minutes();
        $this->app['auth']->forgetGuards();
        $this->withToken($data['access_token'])->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertExactJson(['code' => 'token_expired', 'message' => 'Prijava je istekla. Prijavite se ponovo.']);
    }

    public function test_missing_and_invalid_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized()
            ->assertExactJson(['code' => 'unauthorized', 'message' => 'Prijavite se da biste nastavili.']);

        $this->withToken('123|nepostoji')->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'token_expired');
    }

    public function test_refresh_rotates_tokens(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $first = $this->login('ana@example.com', ['device_name' => 'Pixel']);

        $second = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $first['refresh_token']])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('expires_in', 900)
            ->json();

        $this->assertNotSame($first['refresh_token'], $second['refresh_token']);
        $this->assertNotSame($first['access_token'], $second['access_token']);

        // The old access token is deleted, the new one works; the device stays the same.
        $this->app['auth']->forgetGuards();
        $this->withToken($first['access_token'])->getJson('/api/v1/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($second['access_token'])->getJson('/api/v1/me')->assertOk();
        $this->assertDatabaseHas('refresh_tokens', ['token_hash' => hash('sha256', $second['refresh_token']), 'device_name' => 'Pixel']);

        // The old refresh token is no longer valid.
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $first['refresh_token']])
            ->assertUnauthorized()
            ->assertExactJson(['code' => 'invalid_refresh_token', 'message' => 'Prijava je istekla. Prijavite se ponovo.']);
    }

    public function test_reusing_rotated_refresh_token_revokes_the_chain(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $first = $this->login();
        $second = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $first['refresh_token']])->json();
        $third = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $second['refresh_token']])->json();

        // Someone stole and used the first token: the whole chain is revoked.
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $first['refresh_token']])->assertUnauthorized();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $third['refresh_token']])->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($third['access_token'])->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_reuse_does_not_touch_other_devices(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $phone = $this->login();
        $tablet = $this->login();
        $next = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $phone['refresh_token']])->json();
        $this->app['auth']->forgetGuards();
        $this->withToken($next['access_token'])->getJson('/api/v1/me')->assertOk(); // the new pair is in use
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $phone['refresh_token']])->assertUnauthorized();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tablet['refresh_token']])->assertOk();
    }

    public function test_retried_refresh_after_a_lost_answer_keeps_the_user_signed_in(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $first = $this->login();

        // The answer to this refresh never reached the phone (firewall, network cut).
        $lost = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $first['refresh_token']])->assertOk()->json();
        $this->travel(40)->minutes();

        // The phone retries with the old token and gets a working pair.
        $retry = $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $first['refresh_token']])->assertOk()->json();
        $this->app['auth']->forgetGuards();
        $this->withToken($retry['access_token'])->getJson('/api/v1/me')->assertOk();
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $retry['refresh_token']])->assertOk();

        // The pair that never arrived is dead.
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $lost['refresh_token']])->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($lost['access_token'])->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_retry_window_is_limited(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $first = $this->login();
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $first['refresh_token']])->assertOk();

        $this->travel(ApiTokens::RETRY_HOURS + 1)->hours();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $first['refresh_token']])->assertUnauthorized();
    }

    public function test_refresh_limit_counts_per_token_not_per_shared_ip(): void
    {
        $phones = array_map(function (int $i) {
            User::factory()->create(['email' => "korisnik{$i}@example.com"]);

            return $this->login("korisnik{$i}@example.com");
        }, range(1, 12));

        // Twelve phones behind one mobile-network IP all refresh at once.
        foreach ($phones as $phone) {
            $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $phone['refresh_token']])->assertOk();
        }
    }

    public function test_refresh_token_expires_after_30_days(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $data = $this->login();

        $this->travel(31)->days();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $data['refresh_token']])->assertUnauthorized();
    }

    public function test_refresh_for_blocked_user_fails(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $data = $this->login();
        $user->forceFill(['status' => UserStatus::Blocked])->save();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $data['refresh_token']])->assertUnauthorized();
        $this->withToken($data['access_token'])->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'account_blocked');
    }

    public function test_refresh_validation_and_unknown_token(): void
    {
        $this->postJson('/api/v1/auth/refresh', [])->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => 'izmisljen'])->assertUnauthorized()->assertJsonPath('code', 'invalid_refresh_token');
    }

    // Odjava

    public function test_logout_this_device(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $phone = $this->login();
        $tablet = $this->login();

        $this->withToken($phone['access_token'])->postJson('/api/v1/auth/logout', ['refresh_token' => $phone['refresh_token']])
            ->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->withToken($phone['access_token'])->getJson('/api/v1/me')->assertUnauthorized();
        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $phone['refresh_token']])->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($tablet['access_token'])->getJson('/api/v1/me')->assertOk();
    }

    public function test_logout_without_body_revokes_linked_refresh_token(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $data = $this->login();

        $this->withToken($data['access_token'])->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $data['refresh_token']])->assertUnauthorized();
    }

    public function test_logout_all_devices(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        $phone = $this->login();
        $tablet = $this->login();

        $this->withToken($phone['access_token'])->postJson('/api/v1/auth/logout', ['all_devices' => true])->assertNoContent();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $tablet['refresh_token']])->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($tablet['access_token'])->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_logout_cannot_revoke_other_users_token(): void
    {
        User::factory()->create(['email' => 'ana@example.com']);
        User::factory()->create(['email' => 'bob@example.com']);
        $ana = $this->login('ana@example.com');
        $bob = $this->login('bob@example.com');

        $this->withToken($ana['access_token'])->postJson('/api/v1/auth/logout', ['refresh_token' => $bob['refresh_token']])->assertNoContent();

        $this->postJson('/api/v1/auth/refresh', ['refresh_token' => $bob['refresh_token']])->assertOk();
    }

    public function test_logout_requires_token(): void
    {
        $this->postJson('/api/v1/auth/logout')->assertUnauthorized()->assertJsonPath('code', 'unauthorized');
    }

    // New password

    public function test_password_reset_always_204(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->postJson('/api/v1/auth/password-reset', ['email' => 'ana@example.com'])->assertNoContent();
        $this->postJson('/api/v1/auth/password-reset', ['email' => 'nema@example.com'])->assertNoContent();

        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_password_reset_validation_and_rate_limit(): void
    {
        $this->postJson('/api/v1/auth/password-reset', ['email' => 'x'])->assertUnprocessable()->assertJsonPath('fields.email', 'Unesite ispravnu email adresu.');

        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/auth/password-reset', ['email' => 'ana@example.com']);
        }
        $this->postJson('/api/v1/auth/password-reset', ['email' => 'ana@example.com'])->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }

    public function test_unknown_api_route_uses_error_shape(): void
    {
        $this->getJson('/api/v1/nepostoji')->assertNotFound()->assertExactJson(['code' => 'not_found', 'message' => 'Tražena adresa ne postoji.']);
    }
}
