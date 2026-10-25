<?php

namespace Tests\Feature\Nalozi\Api;

use App\Enums\ListingStatus;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MeApiTest extends TestCase
{
    private function tokenFor(User $user): string
    {
        return $user->createToken('test', ['*'], now()->addMinutes(15))->plainTextToken;
    }

    public function test_me_shape(): void
    {
        $this->travelTo('2026-10-23 20:30:00');
        $location = Location::factory()->create(['name' => 'Loznica', 'slug' => 'loznica']);
        $user = User::factory()->phoneVerified()->create(['name' => 'Ana', 'email' => 'ana@example.com', 'phone' => '+381655415128']);
        $user->forceFill(['location_id' => $location->id])->save();

        $this->withToken($this->tokenFor($user))->getJson('/api/v1/me')
            ->assertOk()
            ->assertExactJson([
                'id' => $user->id,
                'name' => 'Ana',
                'email' => 'ana@example.com',
                'phone' => '+381655415128',
                'phone_verified' => true,
                'role' => 'private',
                'location' => ['id' => $location->id, 'name' => 'Loznica', 'slug' => 'loznica'],
                'avatar' => null,
                'firm_id' => null,
                'member_since' => '2026-10-23T18:30:00Z',
                'plus_seller_until' => null,
                'notifications' => ['messages' => true, 'listing_status' => true, 'payments' => true, 'expiring' => true],
            ]);
    }

    public function test_roles(): void
    {
        $firm = User::factory()->firm()->create();
        $admin = User::factory()->admin()->create();
        $moderator = User::factory()->moderator()->create();

        $this->withToken($this->tokenFor($firm))->getJson('/api/v1/me')
            ->assertJsonPath('role', 'firm')
            ->assertJsonPath('firm_id', $firm->firm->id);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($admin))->getJson('/api/v1/me')
            ->assertJsonPath('role', 'admin')
            ->assertJsonPath('notifications.admin_queue', true);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($moderator))->getJson('/api/v1/me')->assertJsonPath('role', 'admin');
    }

    public function test_patch_profile(): void
    {
        $user = User::factory()->create();
        $location = Location::factory()->create();

        $this->withToken($this->tokenFor($user))->patchJson('/api/v1/me', [
            'name' => '  Novo   Ime ',
            'location_id' => $location->id,
            'notifications' => ['messages' => false, 'payments' => false, 'nepoznato' => true],
        ])->assertOk()
            ->assertJsonPath('name', 'Novo Ime')
            ->assertJsonPath('location.id', $location->id)
            ->assertJsonPath('notifications.messages', false)
            ->assertJsonPath('notifications.payments', false)
            ->assertJsonPath('notifications.expiring', true)
            ->assertJsonMissingPath('notifications.nepoznato');

        $this->assertSame(['messages' => false, 'payments' => false], $user->fresh()->notification_settings);
    }

    public function test_patch_phone_clears_verification(): void
    {
        $user = User::factory()->phoneVerified()->create(['phone' => '+381655415128']);

        $this->withToken($this->tokenFor($user))->patchJson('/api/v1/me', ['phone' => '064 111 2233'])
            ->assertOk()
            ->assertJsonPath('phone', '+381641112233')
            ->assertJsonPath('phone_verified', false);
    }

    public function test_patch_without_phone_keeps_verification(): void
    {
        $user = User::factory()->phoneVerified()->create();

        $this->withToken($this->tokenFor($user))->patchJson('/api/v1/me', ['name' => 'Samo Ime'])
            ->assertOk()->assertJsonPath('phone_verified', true);
    }

    public function test_patch_validation(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))->patchJson('/api/v1/me', [
            'name' => '',
            'phone' => '123',
            'location_id' => 999999,
            'notifications' => ['messages' => 'možda'],
        ])->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['fields' => ['name', 'phone', 'location_id', 'notifications.messages']]);
    }

    public function test_patch_cannot_change_role_or_email(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);

        $this->withToken($this->tokenFor($user))->patchJson('/api/v1/me', [
            'email' => 'drugi@example.com', 'role' => 'admin', 'account_type' => 'firm', 'phone_verified' => true,
        ])->assertOk()->assertJsonPath('email', 'ana@example.com')->assertJsonPath('role', 'private')->assertJsonPath('phone_verified', false);
    }

    public function test_avatar_from_uploaded_media(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Storage::disk('public')->put('uploads/slika.jpg', UploadedFile::fake()->image('slika.jpg', 800, 800)->getContent());
        $image = ListingImage::create(['user_id' => $user->id, 'path' => 'uploads/slika.jpg']);

        $response = $this->withToken($this->tokenFor($user))->patchJson('/api/v1/me', ['avatar_media_id' => $image->id])->assertOk();

        $this->assertStringContainsString('/storage/avatars/'.$user->id.'/', $response->json('avatar.thumb'));
        $this->assertSame($response->json('avatar.thumb'), $response->json('avatar.large'));

        $this->withToken($this->tokenFor($user))->patchJson('/api/v1/me', ['avatar_media_id' => null])
            ->assertOk()->assertJsonPath('avatar', null);
    }

    public function test_avatar_media_of_other_user_is_rejected(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $other = User::factory()->create();
        Storage::disk('public')->put('uploads/tudja.jpg', UploadedFile::fake()->image('t.jpg', 300, 300)->getContent());
        $image = ListingImage::create(['user_id' => $other->id, 'path' => 'uploads/tudja.jpg']);

        $this->withToken($this->tokenFor($user))->patchJson('/api/v1/me', ['avatar_media_id' => $image->id])
            ->assertUnprocessable()
            ->assertJsonPath('fields.avatar_media_id', 'Slika nije pronađena. Otpremite je ponovo.');
    }

    public function test_delete_account(): void
    {
        $user = User::factory()->create();
        $listing = Listing::factory()->for($user)->create();
        $token = $this->tokenFor($user);

        $this->withToken($token)->deleteJson('/api/v1/me', ['password' => 'pogresna'])
            ->assertUnauthorized()
            ->assertJson(['code' => 'invalid_credentials', 'message' => 'Pogrešna lozinka.']);
        $this->assertNotSoftDeleted($user);

        $this->withToken($token)->deleteJson('/api/v1/me', ['password' => 'lozinka123'])->assertNoContent();

        $this->assertSoftDeleted($user);
        $this->assertSoftDeleted($listing);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_delete_requires_password(): void
    {
        $user = User::factory()->create();

        $this->withToken($this->tokenFor($user))->deleteJson('/api/v1/me', [])
            ->assertUnprocessable()->assertJsonPath('fields.password', 'Polje lozinka je obavezno.');
    }

    public function test_me_routes_require_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->patchJson('/api/v1/me', ['name' => 'x'])->assertUnauthorized();
        $this->deleteJson('/api/v1/me', ['password' => 'x'])->assertUnauthorized();
        $this->getJson('/api/v1/me/limits')->assertUnauthorized();
        $this->getJson('/api/v1/me/overview')->assertUnauthorized();
    }

    public function test_session_does_not_authenticate_api(): void
    {
        $this->actingAs(User::factory()->create(), 'web')->getJson('/api/v1/me')->assertUnauthorized();
    }

    // Limiti

    public function test_limits_private_user(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = User::factory()->create();
        Listing::factory()->count(2)->for($user)->create();
        Listing::factory()->for($user)->create(['created_at' => '2026-09-30 23:00:00']); // older, still active: counts
        Listing::factory()->for($user)->create()->delete(); // deleted: frees the slot
        Listing::factory()->for($user)->expired()->create(); // expired: frees the slot
        Listing::factory()->for($user)->create(['status' => 'draft']); // draft: no slot
        Listing::factory()->create(); // someone else's

        // Active listings against private_active_limit; the contract names stay.
        $this->withToken($this->tokenFor($user))->getJson('/api/v1/me/limits')
            ->assertOk()
            ->assertJson([
                'monthly_limit' => 10,
                'used_this_month' => 3,
                'remaining' => 7,
                'resets_at' => '2026-10-31T23:00:00Z',
                'unlimited' => false,
            ]);
    }

    public function test_limit_comes_from_settings_and_never_negative(): void
    {
        Settings::set('private_active_limit', 2);
        $user = User::factory()->create();
        Listing::factory()->count(3)->for($user)->create();

        $this->withToken($this->tokenFor($user))->getJson('/api/v1/me/limits')
            ->assertJson(['monthly_limit' => 2, 'used_this_month' => 3, 'remaining' => 0]);
    }

    public function test_firm_with_max_package_is_unlimited_unless_product_sets_limit(): void
    {
        $product = Product::byCode('business_max_monthly'); // inserted by the pricing migration, unlimited
        $firm = User::factory()->firm()->create();
        Listing::factory()->count(2)->for($firm)->create();

        $this->withToken($this->tokenFor($firm))->getJson('/api/v1/me/limits')
            ->assertJson(['monthly_limit' => 999, 'used_this_month' => 2, 'remaining' => 997, 'unlimited' => true]);

        $product->update(['listing_limit' => 50]);
        $this->app['auth']->forgetGuards();
        $this->withToken($this->tokenFor($firm))->getJson('/api/v1/me/limits')
            ->assertJson(['monthly_limit' => 50, 'remaining' => 48, 'unlimited' => false]);
    }

    public function test_firm_without_active_package_has_private_limit(): void
    {
        $firm = User::factory()->firm()->create();
        Firm::where('user_id', $firm->id)->update(['package_ends_at' => now()->subDay()]);

        $this->withToken($this->tokenFor($firm))->getJson('/api/v1/me/limits')
            ->assertJson(['monthly_limit' => 10, 'unlimited' => false]);
    }

    public function test_overview_has_counts_tasks_and_numbers(): void
    {
        $user = User::factory()->create();
        Listing::factory()->count(2)->for($user)->create();
        Listing::factory()->for($user)->create(['status' => ListingStatus::Rejected]);

        $this->withToken($this->tokenFor($user))->getJson('/api/v1/me/overview')
            ->assertOk()
            ->assertJsonPath('counts.listings', 2)
            ->assertJsonPath('counts.unread', 0)
            ->assertJsonPath('tasks.0.type', 'rejected')
            ->assertJsonPath('tasks.0.count', 1)
            ->assertJsonPath('tasks.0.target', ['screen' => 'my_listings', 'status' => 'rejected'])
            ->assertJsonPath('tasks.1.type', 'missing_location')
            ->assertJsonPath('last_30_days', ['views' => 0, 'phone_reveals' => 0, 'saves' => 0])
            ->assertJsonPath('limits.monthly_limit', 10)
            ->assertJsonPath('plus_seller_until', null);
    }

    public function test_patch_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $token = $this->tokenFor($user);

        for ($i = 0; $i < 20; $i++) {
            $this->withToken($token)->patchJson('/api/v1/me', ['name' => 'Ime '.$i]);
        }

        $this->withToken($token)->patchJson('/api/v1/me', ['name' => 'Previše'])->assertStatus(429)->assertJsonPath('code', 'too_many_requests');
    }
}
