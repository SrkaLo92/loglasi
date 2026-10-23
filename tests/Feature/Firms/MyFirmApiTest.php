<?php

namespace Tests\Feature\Firms;

use App\Enums\AccountType;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MyFirmApiTest extends TestCase
{
    private function firmUser(array $firm = [], array $user = []): User
    {
        $account = User::factory()->create(['account_type' => AccountType::Firm] + $user);
        Firm::factory()->for($account)->create($firm);

        return $account->load('firm');
    }

    private function as(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('test', ['*'], now()->addMinutes(15))->plainTextToken);
    }

    public function test_requires_token_and_firm_account(): void
    {
        $this->getJson('/api/v1/me/firm')->assertUnauthorized();
        $this->getJson('/api/v1/me/firm/stats')->assertUnauthorized();

        $private = User::factory()->create();
        $this->as($private)->getJson('/api/v1/me/firm')->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->as($private)->patchJson('/api/v1/me/firm', ['name' => 'X firma'])->assertNotFound();
        $this->as($private)->getJson('/api/v1/me/firm/stats')->assertNotFound();
    }

    public function test_get_shape(): void
    {
        $this->travelTo('2026-10-23 20:30:00');
        $user = $this->firmUser([
            'name' => 'Auto servis Drina',
            'description' => 'Servis vozila.',
            'address' => 'Kneza Miloša 12',
            'website' => 'https://drina.rs',
            'working_hours' => ['1' => '08–16', '6' => 'Zatvoreno'],
            'biznis_until' => '2027-10-01 10:00:00',
        ], ['phone_verified_at' => now(), 'created_at' => '2025-05-01 08:00:00']);
        Listing::factory()->count(2)->for($user)->create();
        Listing::factory()->expired()->for($user)->create();

        $this->as($user)->getJson('/api/v1/me/firm')->assertOk()
            ->assertHeader('Cache-Control', 'no-cache, private')
            ->assertExactJson([
                'id' => $user->firm->id,
                'name' => 'Auto servis Drina',
                'about' => 'Servis vozila.',
                'logo' => null,
                'address' => 'Kneza Miloša 12',
                'website' => 'https://drina.rs',
                'working_hours' => "Ponedeljak: 08–16\nSubota: Zatvoreno",
                'phone_verified' => true,
                'package' => 'Biznis',
                'package_expires_at' => '2027-10-01T08:00:00Z',
                'active_listings' => 2,
                'member_since' => '2025-05-01T06:00:00Z',
            ]);
    }

    public function test_get_creates_missing_firm_row_and_shows_no_package(): void
    {
        $user = User::factory()->create(['account_type' => AccountType::Firm, 'name' => 'Nova firma']);

        $this->as($user)->getJson('/api/v1/me/firm')->assertOk()
            ->assertJsonPath('name', 'Nova firma')
            ->assertJsonPath('package', null)
            ->assertJsonPath('package_expires_at', null);
        $this->assertNotNull($user->fresh()->firm);
    }

    public function test_patch_updates_fields(): void
    {
        $user = $this->firmUser(['name' => 'Staro']);

        $this->as($user)->patchJson('/api/v1/me/firm', [
            'name' => ' Pekara <i>Žito</i> ',
            'about' => "Hleb i peciva.\nSvaki dan.",
            'address' => 'Trg 1',
            'website' => 'zito.rs',
            'working_hours' => "Pon–Pet: 07–20\nSub 07-14\nNed: Zatvoreno",
        ])->assertOk()
            ->assertJsonPath('name', 'Pekara Žito')
            ->assertJsonPath('about', "Hleb i peciva.\nSvaki dan.")
            ->assertJsonPath('website', 'https://zito.rs')
            ->assertJsonPath('working_hours', "Ponedeljak: 07–20\nUtorak: 07–20\nSreda: 07–20\nČetvrtak: 07–20\nPetak: 07–20\nSubota: 07-14\nNedelja: Zatvoreno");

        $firm = $user->firm->fresh();
        $this->assertSame('07–20', $firm->working_hours['3']);

        // Only sent fields change; empty working_hours clears them.
        $this->as($user)->patchJson('/api/v1/me/firm', ['working_hours' => ''])->assertOk()->assertJsonPath('working_hours', '')->assertJsonPath('name', 'Pekara Žito');
    }

    public function test_patch_validation_errors(): void
    {
        $user = $this->firmUser();

        $this->as($user)->patchJson('/api/v1/me/firm', ['name' => '', 'about' => str_repeat('a', 601)])
            ->assertUnprocessable()->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['fields' => ['name', 'about']]);
        $this->as($user)->patchJson('/api/v1/me/firm', ['working_hours' => 'po dogovoru'])
            ->assertUnprocessable()->assertJsonStructure(['fields' => ['working_hours']]);
        $this->as($user)->patchJson('/api/v1/me/firm', ['website' => 'ftp://x.rs'])
            ->assertUnprocessable()->assertJsonStructure(['fields' => ['website']]);
    }

    public function test_patch_logo_from_own_media_only(): void
    {
        Storage::fake('public');
        $user = $this->firmUser();
        $other = User::factory()->create();

        Storage::disk('public')->putFileAs('uploads', UploadedFile::fake()->image('logo.jpg', 800, 800), 'logo.jpg');
        $mine = ListingImage::create(['user_id' => $user->id, 'path' => 'uploads/logo.jpg']);
        $foreign = ListingImage::create(['user_id' => $other->id, 'path' => 'uploads/logo.jpg']);

        $this->as($user)->patchJson('/api/v1/me/firm', ['logo_media_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonStructure(['fields' => ['logo_media_id']]);

        $response = $this->as($user)->patchJson('/api/v1/me/firm', ['logo_media_id' => $mine->id])->assertOk();
        $this->assertStringEndsWith('.webp', $response->json('logo.thumb'));
        $this->assertSame($user->firm->id, $response->json('logo.id'));

        $this->as($user)->patchJson('/api/v1/me/firm', ['logo_media_id' => null])->assertOk()->assertJsonPath('logo', null);
    }

    public function test_stats_shape_and_periods(): void
    {
        $this->travelTo('2026-10-23 12:00:00');
        $user = $this->firmUser();
        $listing = Listing::factory()->for($user)->create();
        DB::table('listing_daily_stats')->insert([
            ['listing_id' => $listing->id, 'date' => '2026-10-23', 'views' => 5, 'phone_reveals' => 1, 'messages' => 2, 'favorites' => 3],
            ['listing_id' => $listing->id, 'date' => '2026-10-10', 'views' => 9, 'phone_reveals' => 0, 'messages' => 0, 'favorites' => 0],
        ]);

        $this->as($user)->getJson('/api/v1/me/firm/stats')->assertOk()
            ->assertJsonPath('views', 5)
            ->assertJsonPath('contacts', 1)
            ->assertJsonPath('messages', 2)
            ->assertJsonPath('favorites', 3)
            ->assertJsonCount(7, 'daily')
            ->assertJsonPath('daily.6', ['date' => '2026-10-23', 'value' => 5])
            ->assertJsonPath('daily.0', ['date' => '2026-10-17', 'value' => 0]);

        $this->as($user)->getJson('/api/v1/me/firm/stats?period=today')->assertOk()->assertJsonCount(1, 'daily');
        $this->as($user)->getJson('/api/v1/me/firm/stats?period=30d')->assertOk()->assertJsonPath('views', 14)->assertJsonCount(30, 'daily');
        $this->as($user)->getJson('/api/v1/me/firm/stats?period=1y')->assertStatus(400)->assertJsonPath('code', 'invalid_param');
    }

    public function test_stats_need_active_biznis(): void
    {
        $user = $this->firmUser(['biznis_until' => now()->subDay()]);

        $this->as($user)->getJson('/api/v1/me/firm/stats')->assertForbidden()->assertJsonPath('code', 'biznis_required');
    }
}
