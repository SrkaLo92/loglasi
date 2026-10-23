<?php

namespace App\Filament\Pages\Auth;

use App\Models\AuditLog;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;

/**
 * Panel login. Marks the session as signed in through the panel (with the TOTP
 * challenge), so a session from the site login alone cannot open the panel.
 */
class Login extends BaseLogin
{
    public const SESSION_KEY = 'admin.login_user_id';

    public function mount(): void
    {
        $user = Filament::auth()->user();

        if ($user !== null && (int) session(self::SESSION_KEY) === (int) $user->getAuthIdentifier()) {
            redirect()->intended(Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response !== null && ($user = Filament::auth()->user()) !== null) {
            session()->put(self::SESSION_KEY, $user->getAuthIdentifier());
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
            AuditLog::record('admin.login', $user);
        }

        return $response;
    }
}
