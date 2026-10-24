<?php

namespace Tests\Feature\Admin;

use App\Enums\UserStatus;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Tests\Feature\Admin\Concerns\ActsAsStaff;
use Tests\TestCase;

class AccessTest extends TestCase
{
    use ActsAsStaff;

    public function test_guest_is_sent_to_panel_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/admin/listings')->assertRedirect('/admin/login');
        $this->get('/admin/login')->assertOk()->assertSee('loglasi');
    }

    public function test_regular_user_gets_403(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession([Login::SESSION_KEY => $user->id]);

        $this->get('/admin')->assertForbidden();
        $this->get('/admin/listings')->assertForbidden();
    }

    public function test_site_login_alone_does_not_open_the_panel(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->get('/admin')->assertRedirect('/admin/login');
        // The login page is shown even though the site session exists.
        $this->get('/admin/login')->assertOk();
    }

    public function test_staff_without_totp_must_set_it_up(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->withSession([Login::SESSION_KEY => $admin->id]);

        $this->get('/admin')->assertRedirect('/admin/multi-factor-authentication/set-up');
    }

    public function test_blocked_staff_gets_403(): void
    {
        $admin = $this->admin(['status' => UserStatus::Blocked]);
        $this->actingAs($admin)->withSession([Login::SESSION_KEY => $admin->id]);

        $this->get('/admin')->assertForbidden();
    }

    public function test_moderator_cannot_open_settings_prices_or_raw_settings(): void
    {
        $this->signIn($this->moderator());

        $this->get('/admin')->assertOk();
        $this->get('/admin/listings')->assertOk();
        $this->get('/admin/users')->assertOk();
        $this->get('/admin/reports')->assertOk();
        // Moderators work on listings and reports only.
        foreach (['/admin/orders', '/admin/firms', '/admin/categories', '/admin/locations', '/admin/redirects', '/admin/audit-logs'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/admin/products')->assertForbidden();
        $this->get('/admin/setting-keys')->assertForbidden();
    }

    public function test_admin_opens_everything(): void
    {
        $this->signIn($this->admin());

        foreach (['/admin', '/admin/settings', '/admin/products', '/admin/setting-keys', '/admin/categories', '/admin/redirects'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_login_with_password_and_totp_code_marks_the_session(): void
    {
        $admin = $this->admin(['email' => 'srdjan@loglasi.rs', 'password' => 'tajna-lozinka-1']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(Login::class)
            ->fillForm(['email' => 'srdjan@loglasi.rs', 'password' => 'tajna-lozinka-1'])
            ->call('authenticate');

        // Password alone is not enough: the TOTP challenge is shown.
        $this->assertGuest();

        $code = app(Google2FA::class)->getCurrentOtp('JBSWY3DPEHPK3PXP');
        $component->set('data.multiFactor.app.code', $code)->call('authenticate')->assertHasNoErrors();

        $this->assertAuthenticatedAs($admin);
        $this->assertSame($admin->id, session(Login::SESSION_KEY));
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.login', 'user_id' => $admin->id]);
    }

    public function test_wrong_totp_code_does_not_log_in(): void
    {
        $this->admin(['email' => 'srdjan@loglasi.rs', 'password' => 'tajna-lozinka-1']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => 'srdjan@loglasi.rs', 'password' => 'tajna-lozinka-1'])
            ->call('authenticate')
            ->set('data.multiFactor.app.code', '000000')
            ->call('authenticate');

        $this->assertGuest();
    }

    public function test_regular_user_cannot_log_in_to_panel(): void
    {
        User::factory()->create(['email' => 'kupac@example.com', 'password' => 'tajna-lozinka-1']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->fillForm(['email' => 'kupac@example.com', 'password' => 'tajna-lozinka-1'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);

        $this->assertGuest();
    }
}
