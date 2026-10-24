<?php

namespace Tests\Feature\Notifications;

use App\Models\Device;
use App\Models\User;
use App\Notifications\Channels\PushChannel;
use App\Notifications\Messages\PushMessage;
use App\Support\Push\FcmClient;
use Illuminate\Http\Client\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PushTest extends TestCase
{
    use FakesFcm;

    private function token(string $suffix = 'a'): string
    {
        return 'fcm-token-'.str_repeat($suffix, 30).':APA91b';
    }

    private function pushNotification(): Notification
    {
        return new class extends Notification
        {
            public function via($n): array
            {
                return [PushChannel::class];
            }

            public function toPush($n): PushMessage
            {
                return new PushMessage('Naslov', 'Tekst', ['type' => 'message', 'conversation_id' => 7, 'flag' => true]);
            }
        };
    }

    // ---------------------------------------------------------------- devices API

    public function test_devices_need_a_token(): void
    {
        $this->postJson('/api/v1/devices', ['token' => $this->token(), 'platform' => 'android'])
            ->assertUnauthorized()->assertJsonPath('code', 'unauthorized');
        $this->deleteJson('/api/v1/devices/'.$this->token())->assertUnauthorized();
    }

    public function test_register_device(): void
    {
        $user = User::factory()->create();
        $api = $this->withToken($user->createToken('app')->plainTextToken);

        $api->postJson('/api/v1/devices', ['token' => $this->token(), 'platform' => 'android', 'app_version' => '1.0.3'])->assertNoContent();
        $api->postJson('/api/v1/devices', ['token' => $this->token(), 'platform' => 'android', 'app_version' => '1.0.4'])->assertNoContent();

        $device = Device::sole();
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame('1.0.4', $device->app_version);
        $this->assertNotNull($device->last_seen_at);
    }

    public function test_token_moves_to_the_account_that_signed_in_last(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->withToken($first->createToken('app')->plainTextToken)->postJson('/api/v1/devices', ['token' => $this->token(), 'platform' => 'ios'])->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($second->createToken('app')->plainTextToken)->postJson('/api/v1/devices', ['token' => $this->token(), 'platform' => 'ios'])->assertNoContent();

        $this->assertSame($second->id, Device::sole()->user_id);
    }

    public function test_device_validation(): void
    {
        $api = $this->withToken(User::factory()->create()->createToken('app')->plainTextToken);

        $api->postJson('/api/v1/devices', [])->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['fields' => ['token', 'platform']]);
        $api->postJson('/api/v1/devices', ['token' => $this->token(), 'platform' => 'windows'])->assertUnprocessable()->assertJsonStructure(['fields' => ['platform']]);
        $api->postJson('/api/v1/devices', ['token' => 'kratak', 'platform' => 'android'])->assertUnprocessable()->assertJsonPath('fields.token', 'Neispravan token uređaja.');
        $api->postJson('/api/v1/devices', ['token' => str_repeat('a', 30).'<script>', 'platform' => 'android'])->assertUnprocessable();
        $this->assertSame(0, Device::count());
    }

    public function test_old_devices_are_dropped_over_the_limit(): void
    {
        config(['notifications.devices.max_per_user' => 2]);
        $user = User::factory()->create();
        $api = $this->withToken($user->createToken('app')->plainTextToken);

        foreach (['a', 'b', 'c'] as $i => $s) {
            $this->travel($i)->minutes();
            $api->postJson('/api/v1/devices', ['token' => $this->token($s), 'platform' => 'android'])->assertNoContent();
        }

        $this->assertEqualsCanonicalizing([$this->token('b'), $this->token('c')], Device::pluck('token')->all());
    }

    public function test_delete_device_only_removes_own_token(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Device::create(['user_id' => $user->id, 'token' => $this->token('a'), 'platform' => 'android']);
        Device::create(['user_id' => $other->id, 'token' => $this->token('b'), 'platform' => 'android']);
        $api = $this->withToken($user->createToken('app')->plainTextToken);

        $api->deleteJson('/api/v1/devices/'.$this->token('b'))->assertNoContent();
        $api->deleteJson('/api/v1/devices/'.$this->token('z'))->assertNoContent();
        $this->assertSame(2, Device::count());

        $api->deleteJson('/api/v1/devices/'.$this->token('a'))->assertNoContent();
        $this->assertSame([$this->token('b')], Device::pluck('token')->all());
    }

    public function test_device_api_is_rate_limited(): void
    {
        $api = $this->withToken(User::factory()->create()->createToken('app')->plainTextToken);
        for ($i = 0; $i < 30; $i++) {
            $api->deleteJson('/api/v1/devices/'.$this->token())->assertNoContent();
        }
        $api->deleteJson('/api/v1/devices/'.$this->token())->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }

    // ---------------------------------------------------------------- channel

    public function test_without_configuration_push_is_a_logged_no_op(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        Log::spy();
        config(['notifications.fcm.credentials' => null]);

        $user = User::factory()->create();
        Device::create(['user_id' => $user->id, 'token' => $this->token(), 'platform' => 'android']);
        $user->notify($this->pushNotification());

        Http::assertNothingSent();
        Log::shouldHaveReceived('debug')->withArgs(fn ($message) => str_contains($message, 'FCM is not configured'));
        $this->assertSame(1, Device::count());
    }

    public function test_user_without_devices_sends_nothing(): void
    {
        $this->configureFcm();
        User::factory()->create()->notify($this->pushNotification());
        Http::assertNothingSent();
    }

    public function test_push_is_sent_with_oauth_token_from_signed_jwt(): void
    {
        $this->configureFcm();
        $user = User::factory()->create();
        Device::create(['user_id' => $user->id, 'token' => $this->token('a'), 'platform' => 'android']);
        Device::create(['user_id' => $user->id, 'token' => $this->token('b'), 'platform' => 'ios']);

        $user->notify($this->pushNotification());
        $user->notify($this->pushNotification());

        $tokenRequests = Http::recorded(fn (Request $r) => str_contains($r->url(), 'oauth2.googleapis.com'));
        $this->assertCount(1, $tokenRequests, 'the access token is cached');

        [$request] = $tokenRequests->first();
        $this->assertSame('urn:ietf:params:oauth:grant-type:jwt-bearer', $request['grant_type']);
        [$header, $claims, $signature] = explode('.', $request['assertion']);
        $decode = fn ($part) => base64_decode(strtr($part, '-_', '+/'));
        $this->assertSame(['alg' => 'RS256', 'typ' => 'JWT'], json_decode($decode($header), true));
        $claims = json_decode($decode($claims), true);
        $this->assertSame('push@loglasi-test.iam.gserviceaccount.com', $claims['iss']);
        $this->assertSame(FcmClient::SCOPE, $claims['scope']);
        $this->assertSame('https://oauth2.googleapis.com/token', $claims['aud']);
        $this->assertSame(3600, $claims['exp'] - $claims['iat']);
        $this->assertSame(1, openssl_verify($header.'.'.explode('.', $request['assertion'])[1], $decode($signature), $this->fcmPublicKey, OPENSSL_ALGO_SHA256));

        $sends = Http::recorded(fn (Request $r) => str_contains($r->url(), 'fcm.googleapis.com'));
        $this->assertCount(4, $sends);
        [$send] = $sends->first();
        $this->assertSame('https://fcm.googleapis.com/v1/projects/loglasi-test/messages:send', $send->url());
        $this->assertSame('Bearer ya29.test', $send->header('Authorization')[0]);
        $this->assertSame($this->token('a'), $send['message']['token']);
        $this->assertSame(['title' => 'Naslov', 'body' => 'Tekst'], $send['message']['notification']);
        $this->assertSame(['type' => 'message', 'conversation_id' => '7', 'flag' => '1'], (array) $send['message']['data']);
        $this->assertSame('high', $send['message']['android']['priority']);
    }

    public function test_unregistered_tokens_are_removed(): void
    {
        $this->configureFcm([
            Http::response(['error' => ['code' => 404, 'status' => 'NOT_FOUND', 'message' => 'Requested entity was not found.']], 404),
            Http::response(['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT', 'details' => [
                ['@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError', 'errorCode' => 'UNREGISTERED'],
            ]]], 400),
            Http::response(['error' => ['code' => 500, 'status' => 'INTERNAL']], 500),
        ]);
        $user = User::factory()->create();
        foreach (['a', 'b', 'c'] as $s) {
            Device::create(['user_id' => $user->id, 'token' => $this->token($s), 'platform' => 'android']);
        }

        $user->notify($this->pushNotification());

        $this->assertSame([$this->token('c')], Device::pluck('token')->all(), 'a server error keeps the token');
    }

    public function test_expired_access_token_is_renewed_once(): void
    {
        $this->configureFcm([
            Http::response(['error' => ['code' => 401, 'status' => 'UNAUTHENTICATED']], 401),
            Http::response(['name' => 'ok']),
        ]);
        $user = User::factory()->create();
        Device::create(['user_id' => $user->id, 'token' => $this->token(), 'platform' => 'android']);

        $user->notify($this->pushNotification());

        $this->assertCount(2, Http::recorded(fn (Request $r) => str_contains($r->url(), 'oauth2.googleapis.com')));
        $this->assertSame(1, Device::count());
    }
}
