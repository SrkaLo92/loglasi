<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureNotBlocked;
use App\Support\Auth\Accounts;
use App\Support\Auth\LoginPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Login and logout on the site. */
class LoginController extends Controller
{
    /** /login-registracija/: login and registration in one card with two tabs. */
    public function show(Request $request): View
    {
        LoginPage::rememberBack($request);

        return view('accounts.login', ['tab' => LoginPage::tab($request)]);
    }

    /** GET /prijava/: 301 to the "Prijava" tab; the message (status, errors) stays for the next request. */
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
