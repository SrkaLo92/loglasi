<?php

namespace Tests\Feature\Nalozi;

use App\Models\Conversation;
use App\Models\Listing;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Auth\ApiTokens;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NalogTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/moj-nalog/')->assertRedirect(url_slash('prijava'));
        $this->put('/moj-nalog/podaci/', ['name' => 'X'])->assertRedirect(url_slash('prijava'));
        $this->get('/moj-nalog/brisanje/')->assertRedirect(url_slash('prijava'));
    }

    public function test_account_page_shows_data_and_limits(): void
    {
        $user = User::factory()->create(['name' => 'Petar Petrović', 'phone' => '+381655415128']);
        Listing::factory()->count(3)->for($user)->create();

        $this->actingAs($user)->get('/moj-nalog/')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('Moj nalog')
            ->assertSee('Petar Petrović')
            ->assertSee('Privatno lice')
            ->assertSee('065 541 5128')
            ->assertSee('3 od 10', false)
            ->assertSee('PP');
    }

    public function test_firm_account_shows_badge(): void
    {
        $user = User::factory()->firm()->create();

        $this->actingAs($user)->get('/moj-nalog/')->assertOk()->assertSee('Firma')->assertSee('nema ograničenje');
    }

    public function test_unverified_email_shows_notice(): void
    {
        $this->actingAs(User::factory()->unverified()->create())->get('/moj-nalog/')
            ->assertSee('Email adresa još nije potvrđena');
    }

    public function test_profile_update(): void
    {
        $user = User::factory()->create(['phone' => '+381655415128']);
        $location = Location::factory()->create(['name' => 'Banja Koviljača']);

        $this->actingAs($user)->put('/moj-nalog/podaci/', [
            'name' => 'Novo Ime',
            'phone' => '065 541 5128',
            'location_id' => $location->id,
        ])->assertRedirect(url_slash('moj-nalog'))->assertSessionHas('status', 'Podaci su sačuvani.');

        $user->refresh();
        $this->assertSame('Novo Ime', $user->name);
        $this->assertSame($location->id, $user->location_id);
    }

    public function test_changing_phone_clears_verification(): void
    {
        $user = User::factory()->phoneVerified()->create(['phone' => '+381655415128']);

        $this->actingAs($user)->put('/moj-nalog/podaci/', ['name' => $user->name, 'phone' => '064 111 2233'])
            ->assertSessionHas('status', 'Podaci su sačuvani. Novi broj telefona administrator treba ponovo da proveri.');

        $user->refresh();
        $this->assertSame('+381641112233', $user->phone);
        $this->assertNull($user->phone_verified_at);
    }

    public function test_same_phone_keeps_verification(): void
    {
        $user = User::factory()->phoneVerified()->create(['phone' => '+381655415128']);

        $this->actingAs($user)->put('/moj-nalog/podaci/', ['name' => 'Isto', 'phone' => '065/541-5128']);

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_admin_can_set_phone_and_verification_together(): void
    {
        $user = User::factory()->create(['phone' => '+381655415128']);
        $user->forceFill(['phone' => '+381641112233', 'phone_verified_at' => now()])->save();

        $this->assertNotNull($user->fresh()->phone_verified_at);
    }

    public function test_profile_validation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/moj-nalog/podaci/', ['name' => '', 'phone' => '123', 'location_id' => 999999])
            ->assertSessionHasErrorsIn('podaci', ['name', 'phone', 'location_id']);
    }

    public function test_password_change(): void
    {
        $user = User::factory()->create();
        ApiTokens::issue($user, 'iPhone');

        $this->actingAs($user)->put('/moj-nalog/lozinka/', [
            'current_password' => 'lozinka123',
            'password' => 'novalozinka9',
            'password_confirmation' => 'novalozinka9',
        ])->assertRedirect(url_slash('moj-nalog'))->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('novalozinka9', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertAuthenticatedAs($user);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/moj-nalog/lozinka/', [
            'current_password' => 'pogresna',
            'password' => 'novalozinka9',
            'password_confirmation' => 'novalozinka9',
        ])->assertSessionHasErrorsIn('lozinka', ['current_password' => 'Lozinka nije tačna.']);

        $this->assertTrue(Hash::check('lozinka123', $user->fresh()->password));
    }

    public function test_new_password_must_differ_and_match(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/moj-nalog/lozinka/', [
            'current_password' => 'lozinka123',
            'password' => 'lozinka123',
            'password_confirmation' => 'lozinka123',
        ])->assertSessionHasErrorsIn('lozinka', ['password' => 'Nova lozinka mora biti drugačija od trenutne.']);

        $this->actingAs($user)->put('/moj-nalog/lozinka/', [
            'current_password' => 'lozinka123',
            'password' => 'novalozinka9',
            'password_confirmation' => 'drugo',
        ])->assertSessionHasErrorsIn('lozinka', ['password']);
    }

    public function test_avatar_upload_and_removal(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/moj-nalog/slika/', ['avatar' => UploadedFile::fake()->image('ja.jpg', 900, 600)])
            ->assertRedirect(url_slash('moj-nalog'));

        $path = $user->fresh()->avatar_path;
        $this->assertStringStartsWith('avatars/'.$user->id.'/', $path);
        Storage::disk('public')->assertExists($path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([400, 400], [$w, $h]);

        // Nova slika briše staru.
        $this->actingAs($user)->post('/moj-nalog/slika/', ['avatar' => UploadedFile::fake()->image('ja2.png', 300, 300)]);
        Storage::disk('public')->assertMissing($path);
        $second = $user->fresh()->avatar_path;

        $this->actingAs($user)->get('/moj-nalog/')->assertSee($second);

        $this->actingAs($user)->delete('/moj-nalog/slika/')->assertRedirect(url_slash('moj-nalog'));
        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_avatar_validation(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post('/moj-nalog/slika/', ['avatar' => UploadedFile::fake()->create('dokument.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrorsIn('slika', ['avatar']);
        $this->actingAs($user)->post('/moj-nalog/slika/', ['avatar' => UploadedFile::fake()->image('mala.jpg', 50, 50)])
            ->assertSessionHasErrorsIn('slika', ['avatar' => 'Slika mora biti najmanje 100 × 100 piksela.']);
        $this->actingAs($user)->post('/moj-nalog/slika/', ['avatar' => UploadedFile::fake()->image('velika.jpg', 200, 200)->size(6000)])
            ->assertSessionHasErrorsIn('slika', ['avatar']);

        $this->assertNull($user->fresh()->avatar_path);
    }

    public function test_delete_confirmation_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/moj-nalog/brisanje/')->assertOk()->assertSee('Trajno obriši nalog')->assertSee($user->email);
    }

    public function test_delete_requires_password_and_confirmation(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/moj-nalog/brisanje/', ['password' => 'pogresna', 'confirm' => '1'])
            ->assertSessionHasErrorsIn('brisanje', ['password']);
        $this->actingAs($user)->delete('/moj-nalog/brisanje/', ['password' => 'lozinka123'])
            ->assertSessionHasErrorsIn('brisanje', ['confirm' => 'Potvrdite da želite trajno da obrišete nalog.']);

        $this->assertNotSoftDeleted($user);
    }

    public function test_account_deletion_removes_personal_data_and_content(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['email' => 'brisem@example.com', 'phone' => '+381655415128']);
        $other = User::factory()->create();
        $listing = Listing::factory()->for($user)->create();
        $otherListing = Listing::factory()->for($other)->create();
        $user->favorites()->attach($otherListing->id, ['created_at' => now()]);
        $conversation = Conversation::create(['listing_id' => $otherListing->id, 'buyer_id' => $user->id, 'seller_id' => $other->id]);
        $order = Order::create([
            'user_id' => $user->id,
            'product_id' => Product::factory()->create()->id,
            'amount_rsd' => 300,
            'reference' => 'LG-1',
        ]);
        ApiTokens::issue($user, 'Pixel');

        $this->actingAs($user)->delete('/moj-nalog/brisanje/', ['password' => 'lozinka123', 'confirm' => '1'])
            ->assertRedirect(url_slash('/'));

        $this->assertGuest();
        $deleted = User::withTrashed()->find($user->id);
        $this->assertSoftDeleted($deleted);
        $this->assertSame('Obrisan korisnik', $deleted->name);
        $this->assertNotSame('brisem@example.com', $deleted->email);
        $this->assertNull($deleted->phone);
        $this->assertSoftDeleted($listing);
        $this->assertNotSoftDeleted($otherListing);
        $this->assertModelMissing($conversation);
        $this->assertDatabaseMissing('favorites', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertModelExists($order);

        // Isti email može ponovo da se registruje, stara lozinka više ne radi.
        $this->post('/prijava/', ['email' => 'brisem@example.com', 'password' => 'lozinka123'])->assertSessionHasErrors('email');
        $this->post('/registracija/', [
            'name' => 'Ponovo', 'email' => 'brisem@example.com', 'phone' => '0655415128',
            'password' => 'lozinka123', 'password_confirmation' => 'lozinka123', 'accept_terms' => '1',
        ])->assertRedirect(url_slash('moj-nalog'));
    }
}
