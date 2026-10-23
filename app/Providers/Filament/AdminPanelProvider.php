<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Support\AuditHooks;
use App\Http\Middleware\Admin\EnsureAdminLogin;
use App\Http\Middleware\Admin\SetAdminLocale;
use App\Models\Setting;
use App\Support\Settings;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/** Admin panel at /admin: staff only (admin, moderator), mandatory TOTP. */
class AdminPanelProvider extends PanelProvider
{
    public function boot(): void
    {
        AuditHooks::register();

        // Settings edited as rows (key/value page) must not stay stale in the settings cache.
        Setting::saved(fn () => Settings::flush());
        Setting::deleted(fn () => Settings::flush());

        RateLimiter::for('admin-api', fn (Request $request) => Limit::perMinute(120)->by('admin-api:'.($request->user()?->id ?? $request->ip())));
    }

    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->profile(isSimple: false)
            ->multiFactorAuthentication(
                [AppAuthentication::make()->recoverable()->brandName('loglasi.rs')],
                isRequired: true,
            )
            ->brandName('loglasi.rs')
            ->brandLogo(new HtmlString('<span class="lg-admin-logo">loglasi<b>.rs</b></span>'))
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => Color::hex('#2B7A64'),
                'warning' => Color::hex('#B8501A'),
                'danger' => Color::Red,
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => Color::Emerald,
            ])
            ->font('Manrope', url: fn () => asset_v('css/admin.css'), provider: LocalFontProvider::class)
            ->renderHook(PanelsRenderHook::HEAD_END, fn () => new HtmlString('<meta name="robots" content="noindex, nofollow">'))
            ->navigationGroups(['Moderacija', 'Prodaja', 'Katalog', 'SEO', 'Sistem'])
            ->sidebarCollapsibleOnDesktop()
            ->unsavedChangesAlerts()
            ->databaseNotifications(false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                SetAdminLocale::class,
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsureAdminLogin::class,
            ]);
    }
}
