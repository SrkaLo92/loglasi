<?php

namespace Tests\Feature\Nalozi;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DozvoleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth', 'staff'])->get('/test-osoblje/', fn () => 'osoblje');
        Route::middleware(['web', 'auth', 'staff:admin'])->get('/test-admin', fn () => 'admin');
        Route::middleware(['api', 'auth:sanctum', 'staff'])->get('/api/v1/test-osoblje', fn () => ['ok' => true]);
    }

    public function test_gates(): void
    {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();
        $user = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('admin'));
        $this->assertTrue(Gate::forUser($admin)->allows('staff'));
        $this->assertFalse(Gate::forUser($moderator)->allows('admin'));
        $this->assertTrue(Gate::forUser($moderator)->allows('staff'));
        $this->assertFalse(Gate::forUser($user)->allows('staff'));
        $this->assertFalse(Gate::allows('staff'));
    }

    public function test_staff_middleware(): void
    {
        $this->get('/test-osoblje/')->assertRedirect(url_slash('login-registracija'));
        $this->actingAs(User::factory()->create())->get('/test-osoblje/')->assertForbidden();
        $this->actingAs(User::factory()->moderator()->create())->get('/test-osoblje/')->assertOk()->assertSee('osoblje');
        $this->actingAs(User::factory()->moderator()->create())->get('/test-admin/')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/test-admin/')->assertOk();
    }

    public function test_staff_middleware_on_api(): void
    {
        $this->getJson('/api/v1/test-osoblje')->assertUnauthorized()->assertJsonPath('code', 'unauthorized');

        $user = User::factory()->create();
        $this->withToken($user->createToken('t')->plainTextToken)->getJson('/api/v1/test-osoblje')
            ->assertForbidden()
            ->assertJson(['code' => 'forbidden', 'message' => 'Nemate pravo pristupa.']);
    }

    public function test_user_policy(): void
    {
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->assertTrue($user->can('update', $user));
        $this->assertFalse($user->can('update', $other));
        $this->assertTrue($admin->can('update', $other));
        $this->assertFalse($moderator->can('update', $other));

        $this->assertTrue($user->can('delete', $user));
        $this->assertFalse($user->can('delete', $other));
        $this->assertFalse($admin->can('delete', User::factory()->admin()->create()));

        $this->assertTrue($moderator->can('block', $user));
        $this->assertFalse($moderator->can('block', $admin));
        $this->assertFalse($admin->can('block', $admin));
        $this->assertFalse($user->can('block', $other));

        $this->assertTrue($moderator->can('verifyPhone', $user));
        $this->assertFalse($user->can('verifyPhone', $user));

        $this->assertTrue($admin->can('changeAccountType', $user));
        $this->assertFalse($moderator->can('changeAccountType', $user));
        $this->assertFalse($user->can('changeAccountType', $user));

        $this->assertTrue($admin->can('changeRole', $user));
        $this->assertFalse($admin->can('changeRole', $admin));

        $this->assertTrue($moderator->can('viewAny', User::class));
        $this->assertFalse($user->can('viewAny', User::class));
    }

    public function test_not_blocked_middleware_returns_json_for_api(): void
    {
        Route::middleware(['web', 'not-blocked'])->get('/test-blok', fn () => 'ok');
        $user = User::factory()->create();
        $user->forceFill(['status' => UserStatus::Blocked])->save();

        $this->actingAs($user)->getJson('/test-blok/')
            ->assertForbidden()
            ->assertJsonPath('code', 'account_blocked');
    }
}
