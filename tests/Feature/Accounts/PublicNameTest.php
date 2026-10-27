<?php

namespace Tests\Feature\Accounts;

use App\Models\Redirect;
use App\Models\User;
use Tests\TestCase;

class PublicNameTest extends TestCase
{
    public function test_registration_rejects_name_with_email_or_phone(): void
    {
        $data = [
            'email' => 'novi@example.com', 'phone' => '065/541-5128', 'password' => 'lozinka123',
            'password_confirmation' => 'lozinka123', 'accept_terms' => '1',
        ];

        $this->post('/registracija/', $data + ['name' => 'novi@example.com'])->assertSessionHasErrors('name');
        $this->post('/registracija/', $data + ['name' => 'Novi 065 541 5128'])->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('users', ['email' => 'novi@example.com']);
    }

    public function test_api_profile_update_rejects_name_with_email(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->patchJson('/api/v1/me', ['name' => 'Ja ja@gmail.com'])
            ->assertStatus(422)->assertJsonPath('fields.name', 'Ime ne sme da sadrži mejl adresu ni broj telefona. Njih upišite u posebna polja.');
    }

    public function test_clean_command_fixes_name_slug_and_adds_redirect(): void
    {
        $user = User::factory()->create(['name' => 'Flat.sale.vracar@Gmail.com +381654635963', 'slug' => 'flat-sale-vracargmail-com']);
        $ok = User::factory()->create(['name' => 'Jovana Đurić', 'slug' => 'jovana-djuric']);

        $this->artisan('users:clean-names', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('flat-sale-vracargmail-com', $user->fresh()->slug);

        $this->artisan('users:clean-names')->assertSuccessful();

        $user->refresh();
        $this->assertSame('Korisnik', $user->name);
        $this->assertStringStartsWith('korisnik', $user->slug);
        $this->assertSame('jovana-djuric', $ok->fresh()->slug);
        $this->assertTrue(Redirect::query()->where('from_path', '/korisnik/flat-sale-vracargmail-com/')->where('to_path', '/korisnik/'.$user->slug.'/')->exists());
        $this->get('/korisnik/flat-sale-vracargmail-com/')->assertRedirect();
    }

    public function test_clean_command_renews_slug_made_from_email(): void
    {
        $user = User::factory()->create(['name' => 'Srdjan Marinković', 'email' => 'srdjan92@gmail.com', 'slug' => 'srdjan92gmail-com']);

        $this->artisan('users:clean-names')->assertSuccessful();

        $user->refresh();
        $this->assertSame('Srdjan Marinković', $user->name);
        $this->assertSame('srdjan-marinkovic', $user->slug);
        $this->get('/korisnik/srdjan92gmail-com/')->assertRedirect();
    }

    public function test_clean_command_renews_slug_from_an_old_email(): void
    {
        $user = User::factory()->create(['name' => 'loglasi tim', 'email' => 'kontakt@example.com', 'slug' => 'srdjan92gmail-com']);
        $keep = User::factory()->create(['name' => 'Gmail Com', 'slug' => 'gmail-com-servis']);

        $this->artisan('users:clean-names')->assertSuccessful();

        $this->assertSame('loglasi-tim', $user->fresh()->slug);
        $this->assertSame('gmail-com-servis', $keep->fresh()->slug);
    }
}
