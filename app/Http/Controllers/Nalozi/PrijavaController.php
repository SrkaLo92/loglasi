<?php

namespace App\Http\Controllers\Nalozi;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureNotBlocked;
use App\Support\Auth\Accounts;
use App\Support\Auth\LoginPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Prijava i odjava na sajtu. */
class PrijavaController extends Controller
{
    /** /login-registracija/: prijava i registracija u jednoj kartici sa dva taba. */
    public function show(Request $request): View
    {
        return view('nalozi.login-registracija', ['tab' => LoginPage::tab($request)]);
    }

    /** GET /prijava/: 301 na tab „Prijava”, poruka (status, greške) ostaje za sledeći zahtev. */
    public function legacy(Request $request): RedirectResponse
    {
        $request->session()->reflash();

        return redirect()->to(LoginPage::url(LoginPage::LOGIN), 301);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'string', 'max:'.Accounts::PASSWORD_MAX],
        ]);

        [$user, $error] = Accounts::attempt($data['email'], $data['password']);

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => $error === 'account_blocked' ? EnsureNotBlocked::MESSAGE : 'Pogrešan email ili lozinka.',
            ])->redirectTo(url()->previous(LoginPage::url(LoginPage::LOGIN)));
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(url_slash('moj-nalog'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(url_slash('/'));
    }
}
