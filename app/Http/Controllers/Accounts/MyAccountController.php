<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Rules\PhoneNumber;
use App\Support\Account\AccountOverview;
use App\Support\Auth\AccountDeletion;
use App\Support\Auth\Accounts;
use App\Support\Auth\ApiTokens;
use App\Support\Auth\Avatar;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/** „Moj nalog”: overview, settings (data, photo, password) and account deletion. */
class MyAccountController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user()->load('location', 'firm');
        $overview = new AccountOverview($user);

        return view('accounts.overview', [
            'user' => $user,
            'tasks' => $overview->tasks(),
            'stats' => $overview->stats(),
            'recent' => $overview->recentListings(),
        ]);
    }

    /** /moj-nalog/podesavanja/: personal data, profile photo, password, sign out and account deletion. */
    public function settings(Request $request): View
    {
        return view('accounts.settings', [
            'user' => $request->user()->load('location', 'firm'),
            'locations' => Location::query()->orderBy('sort')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('update', $user);

        $data = $request->validateWithBag('podaci', [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'phone' => ['required', 'string', 'max:40', new PhoneNumber],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')],
        ]);

        $user->forceFill([
            'name' => trim(preg_replace('/\s+/u', ' ', strip_tags($data['name']))),
            'phone' => Phone::normalize($data['phone']),
            'location_id' => $data['location_id'] ?? null,
        ]);
        $phoneChanged = $user->isDirty('phone');
        $user->save();

        return redirect()->to(url_slash('moj-nalog/podesavanja'))->with('status', $phoneChanged
            ? 'Podaci su sačuvani. Novi broj telefona administrator treba ponovo da proveri.'
            : 'Podaci su sačuvani.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('update', $user);

        $data = $request->validateWithBag('lozinka', [
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'min:'.Accounts::PASSWORD_MIN, 'max:'.Accounts::PASSWORD_MAX, 'confirmed', 'different:current_password'],
        ], [
            'password.different' => 'Nova lozinka mora biti drugačija od trenutne.',
        ]);

        $user->forceFill(['password' => Hash::make($data['password'])])->save();

        // The other devices (site and app) are logged out.
        ApiTokens::revokeAll($user);
        DB::table('sessions')->where('user_id', $user->id)->where('id', '!=', $request->session()->getId())->delete();

        return redirect()->to(url_slash('moj-nalog/podesavanja'))->with('status', 'Lozinka je promenjena. Ostali uređaji su odjavljeni.');
    }

    public function updateAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('update', $user);

        $request->validateWithBag('slika', [
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=100,min_height=100,max_width=8000,max_height=8000'],
        ], [
            'avatar.dimensions' => 'Slika mora biti najmanje 100 × 100 piksela.',
            'avatar.max' => 'Slika može imati najviše 5 MB.',
        ]);

        try {
            Avatar::storeFromFile($user, $request->file('avatar')->getRealPath());
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['avatar' => 'Slika ne može da se obradi. Probajte drugu sliku.'])->errorBag('slika');
        }

        return redirect()->to(url_slash('moj-nalog/podesavanja'))->with('status', 'Slika je sačuvana.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('update', $user);

        Avatar::delete($user);

        return redirect()->to(url_slash('moj-nalog/podesavanja'))->with('status', 'Slika je uklonjena.');
    }

    public function confirmDelete(Request $request): View
    {
        return view('accounts.delete', ['user' => $request->user()]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('delete', $user);

        $request->validateWithBag('brisanje', [
            'password' => ['required', 'string', 'current_password:web'],
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Potvrdite da želite trajno da obrišete nalog.',
        ]);

        Auth::guard('web')->logout();
        AccountDeletion::delete($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->to(url_slash('/'))->with('status', 'Nalog je obrisan. Hvala što ste koristili loglasi.rs.');
    }
}
