<?php

namespace Tests\Feature\Firms;

use App\Enums\AccountType;
use App\Enums\OrderStatus;
use App\Models\Firm;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AccountTest extends TestCase
{
    private function firmUser(array $firm = []): User
    {
        $user = User::factory()->create(['account_type' => AccountType::Firm]);
        Firm::factory()->for($user)->create($firm);

        return $user->load('firm');
    }

    private function valid(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Auto servis Drina',
            'activity' => 'Auto servis',
            'description' => 'Servis svih vrsta vozila.',
            'address' => 'Kneza Miloša 12',
            'coordinates' => '',
            'phone' => '',
            'website' => '',
            'facebook' => '',
            'instagram' => '',
            'hours' => [],
        ], $overrides);
    }

    public function test_guest_is_sent_to_login_and_private_user_gets_403(): void
    {
        $this->get('/moj-nalog/firma/')->assertRedirect(url_slash('prijava'));
        $this->get('/moj-nalog/firma/statistika/')->assertRedirect(url_slash('prijava'));

        $private = User::factory()->create();
        $this->actingAs($private)->get('/moj-nalog/firma/')->assertForbidden();
        $this->actingAs($private)->put('/moj-nalog/firma/', $this->valid())->assertForbidden();
        $this->actingAs($private)->get('/moj-nalog/firma/statistika/')->assertForbidden();
    }

    public function test_edit_page_shows_form_and_biznis_box(): void
    {
        $user = $this->firmUser(['name' => 'Pekara Žito', 'biznis_until' => now()->addMonths(6)]);

        $html = $this->actingAs($user)->get('/moj-nalog/firma/')->assertOk()
            ->assertSee('Profil firme')
            ->assertSee('value="Pekara Žito"', false)
            ->assertSee('Biznis paket')
            ->assertSee('Aktivan')
            ->assertSee('Važi do')
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->getContent();
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_update_saves_and_normalizes_fields(): void
    {
        $user = $this->firmUser();
        $location = Location::factory()->create();

        $this->actingAs($user)->put('/moj-nalog/firma/', $this->valid([
            'name' => '  Vulkanizer   <b>Čeda</b> ',
            'activity' => 'Vulkanizer',
            'description' => "Prvi red <script>x</script>\r\n\r\n\r\n\r\nDrugi red",
            'location_id' => $location->id,
            'coordinates' => '44.5331, 19.2258',
            'phone' => '+381 (0)65 541-5128',
            'whatsapp' => '1',
            'viber' => '0',
            'website' => 'vulkanizer.rs',
            'facebook' => '@vulkanizer.test',
            'instagram' => 'https://www.instagram.com/vulkanizer/',
            'pib' => '123456789',
            'mb' => '12345678',
            'hours' => [1 => '08–16', 2 => '8–12, 13–17', 6 => 'Zatvoreno'],
        ]))->assertRedirect(url_slash('moj-nalog/firma'))->assertSessionHas('status');

        $firm = $user->firm->fresh();
        $this->assertSame('Vulkanizer Čeda', $firm->name);
        $this->assertSame('Vulkanizer', $firm->activity);
        $this->assertSame("Prvi red x\n\nDrugi red", $firm->description);
        $this->assertSame($location->id, $firm->location_id);
        $this->assertSame(44.5331, $firm->lat);
        $this->assertSame(19.2258, $firm->lng);
        $this->assertSame('+381655415128', $firm->phone);
        $this->assertTrue((bool) $firm->whatsapp);
        $this->assertFalse((bool) $firm->viber);
        $this->assertSame('https://vulkanizer.rs', $firm->website);
        $this->assertSame('https://www.facebook.com/vulkanizer.test', $firm->facebook);
        $this->assertSame('https://www.instagram.com/vulkanizer/', $firm->instagram);
        $this->assertSame(['1' => '08–16', '2' => '8–12, 13–17', '3' => '', '4' => '', '5' => '', '6' => 'Zatvoreno', '7' => ''], $firm->working_hours);
    }

    public function test_invalid_input_is_rejected(): void
    {
        $user = $this->firmUser(['name' => 'Staro ime']);

        $this->actingAs($user)->put('/moj-nalog/firma/', $this->valid([
            'name' => '',
            'coordinates' => 'negde u Loznici',
            'website' => 'javascript:alert(1)',
            'phone' => '123',
            'pib' => '12',
            'hours' => [1 => str_repeat('x', 41)],
        ]))->assertSessionHasErrors(['name', 'phone', 'pib', 'hours.1']);

        $this->actingAs($user)->put('/moj-nalog/firma/', $this->valid([
            'coordinates' => '144.5, 19.2',
            'website' => 'javascript:alert(1)',
            'facebook' => 'https://evil.example.com/x',
        ]))->assertSessionHasErrors(['coordinates', 'website', 'facebook']);

        $this->assertSame('Staro ime', $user->firm->fresh()->name);
    }

    public function test_admin_only_fields_cannot_be_set(): void
    {
        $until = now()->addMonth()->startOfSecond();
        $user = $this->firmUser(['biznis_until' => $until]);

        $this->actingAs($user)->put('/moj-nalog/firma/', $this->valid([
            'biznis_until' => now()->addYears(10)->toDateTimeString(),
            'founding_free' => '1',
            'verified_at' => now()->toDateTimeString(),
            'user_id' => 999,
        ]))->assertRedirect();

        $firm = $user->firm->fresh();
        $this->assertTrue($firm->biznis_until->equalTo($until));
        $this->assertFalse($firm->founding_free);
        $this->assertNull($firm->verified_at);
        $this->assertSame($user->id, $firm->user_id);
    }

    public function test_firm_row_is_created_on_first_save(): void
    {
        $user = User::factory()->create(['account_type' => AccountType::Firm, 'name' => 'Marko Firma']);

        $this->actingAs($user)->get('/moj-nalog/firma/')->assertOk()->assertSee('value="Marko Firma"', false)->assertSee('Nije aktivan');
        $this->actingAs($user)->put('/moj-nalog/firma/', $this->valid(['name' => 'Moja firma']))->assertRedirect();

        $this->assertSame('Moja firma', $user->fresh()->firm->name);
    }

    public function test_logo_and_cover_are_resized_to_webp(): void
    {
        Storage::fake('public');
        $user = $this->firmUser();

        $this->actingAs($user)->put('/moj-nalog/firma/', $this->valid([
            'logo' => UploadedFile::fake()->image('logo.png', 1200, 600),
            'cover' => UploadedFile::fake()->image('cover.jpg', 3000, 1500),
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $firm = $user->firm->fresh();
        $this->assertStringStartsWith('firms/'.$firm->id.'/logo-', $firm->logo_path);
        $this->assertStringEndsWith('.webp', $firm->logo_path);
        Storage::disk('public')->assertExists($firm->logo_path);
        [$w, $h, $type] = getimagesize(Storage::disk('public')->path($firm->logo_path));
        $this->assertSame([400, 200, IMAGETYPE_WEBP], [$w, $h, $type]);

        [$w, $h, $type] = getimagesize(Storage::disk('public')->path($firm->cover_path));
        $this->assertSame([1600, 500, IMAGETYPE_WEBP], [$w, $h, $type]);

        // A new logo replaces the old file; remove deletes it.
        $old = $firm->logo_path;
        $this->actingAs($user)->put('/moj-nalog/firma/', $this->valid(['logo' => UploadedFile::fake()->image('n.jpg', 300, 300)]))->assertRedirect();
        Storage::disk('public')->assertMissing($old);

        $this->actingAs($user)->put('/moj-nalog/firma/', $this->valid(['remove_logo' => '1', 'remove_cover' => '1']))->assertRedirect();
        $firm->refresh();
        $this->assertNull($firm->logo_path);
        $this->assertNull($firm->cover_path);
    }

    public function test_too_small_or_non_image_files_are_rejected(): void
    {
        Storage::fake('public');
        $user = $this->firmUser();

        $this->actingAs($user)->put('/moj-nalog/firma/', $this->valid([
            'logo' => UploadedFile::fake()->image('mali.png', 50, 50),
            'cover' => UploadedFile::fake()->create('cover.pdf', 10, 'application/pdf'),
        ]))->assertSessionHasErrors(['logo', 'cover']);
    }

    public function test_expired_biznis_keeps_data_and_shows_renewal(): void
    {
        $user = $this->firmUser(['name' => 'Istekla firma', 'biznis_until' => now()->subDays(3), 'website' => 'https://firma.rs']);
        Product::factory()->create(['code' => Product::BIZNIS_12M, 'price_rsd' => 6000]);

        $this->actingAs($user)->get('/moj-nalog/firma/')->assertOk()
            ->assertSee('Istekao')
            ->assertSee('Podaci firme su sačuvani', false)
            ->assertSee('Produžite Biznis paket')
            ->assertSee('6.000 din')
            ->assertSee(url_slash('cenovnik'), false)
            ->assertSee('value="https://firma.rs"', false);

        // Editing still works while expired.
        $this->actingAs($user)->put('/moj-nalog/firma/', $this->valid(['name' => 'Ime posle isteka']))->assertRedirect();
        $this->assertSame('Ime posle isteka', $user->firm->fresh()->name);
    }

    public function test_expiring_soon_founding_and_pending_order(): void
    {
        $user = $this->firmUser(['biznis_until' => now()->addDays(5)]);
        $user->firm->forceFill(['founding_free' => true])->save();
        $product = Product::factory()->create(['code' => Product::BIZNIS_12M]);
        Order::create(['user_id' => $user->id, 'product_id' => $product->id, 'amount_rsd' => 6000, 'reference' => 'LG-123', 'status' => OrderStatus::Pending]);

        $this->actingAs($user)->get('/moj-nalog/firma/')->assertOk()
            ->assertSee('Ističe uskoro')
            ->assertSee('Osnivačka firma')
            ->assertSee('još 5 dana')
            ->assertSee('Narudžbina LG-123 čeka uplatu.');
    }

    public function test_my_account_links_firms_to_firm_page(): void
    {
        $user = $this->firmUser();
        $this->actingAs($user)->get('/moj-nalog/')->assertOk()->assertSee(url_slash('moj-nalog/firma'), false);

        $private = User::factory()->create();
        $this->actingAs($private)->get('/moj-nalog/')->assertOk()->assertDontSee(url_slash('moj-nalog/firma'), false);
    }

    public function test_updates_are_rate_limited(): void
    {
        $user = $this->firmUser();
        for ($i = 0; $i < 20; $i++) {
            $this->actingAs($user)->put('/moj-nalog/firma/', $this->valid())->assertRedirect(url_slash('moj-nalog/firma'));
        }
        $this->actingAs($user)->put('/moj-nalog/firma/', $this->valid())->assertSessionHasErrors('name');
    }
}
