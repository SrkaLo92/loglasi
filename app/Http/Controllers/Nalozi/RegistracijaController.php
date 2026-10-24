<?php

namespace App\Http\Controllers\Nalozi;

use App\Http\Controllers\Controller;
use App\Support\Auth\Accounts;
use App\Support\Auth\LoginPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/** Registracija privatnog lica. Nalog firme pravi samo administrator. */
class RegistracijaController extends Controller
{
    /** GET /registracija/: 301 na tab „Novi nalog” strane /login-registracija/. */
    public function legacy(Request $request): RedirectResponse
    {
        $request->session()->reflash();

        return redirect()->to(LoginPage::url(LoginPage::REGISTER), 301);
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: people never see the "website" field, bots fill it in. No account, no hint why.
        if (trim((string) $request->input('website', '')) !== '') {
            Log::info('Registration rejected (honeypot).', ['ip' => $request->ip()]);

            return back()->withInput($request->except('password', 'password_confirmation', 'website'))
                ->withErrors(['email' => 'Registracija nije uspela. Pokušajte ponovo malo kasnije.']);
        }

        $rules = Accounts::registrationRules(phoneRequired: true);
        $rules['password'][] = 'confirmed';

        $data = $request->validate($rules);

        if (Accounts::emailTaken($data['email'])) {
            throw ValidationException::withMessages([
                'email' => 'Nalog sa ovom email adresom već postoji. Prijavite se ili zatražite novu lozinku.',
            ]);
        }

        $user = Accounts::register($data);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->to(url_slash('moj-nalog'))
            ->with('status', 'Nalog je napravljen. Poslali smo vam mejl da potvrdite email adresu.');
    }
}
