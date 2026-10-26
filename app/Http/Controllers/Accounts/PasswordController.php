<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\Accounts;
use App\Support\Auth\ApiTokens;
use App\Support\Auth\LoginPage;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Forgotten password: a link by mail, then a new password. */
class PasswordController extends Controller
{
    public const SENT = 'Ako nalog sa tom adresom postoji, poslali smo link za novu lozinku. Proverite i folder za neželjenu poštu.';

    public function request(): View
    {
        return view('accounts.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'email', 'max:191']]);

        static::sendLink($data['email']);

        // Same response when the account does not exist, so it does not reveal who has an account.
        return back()->with('status', self::SENT);
    }

    /** Sends the link if the account exists and is not blocked (the API uses it too). */
    public static function sendLink(string $email): void
    {
        $user = User::where('email', Accounts::normalizeEmail($email))->first();
        if ($user === null || $user->isBlocked()) {
            return;
        }

        Password::broker()->sendResetLink(['email' => $user->email]);
    }

    public function reset(Request $request, string $token): View
    {
        return view('accounts.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'password' => ['required', 'string', 'min:'.Accounts::PASSWORD_MIN, 'max:'.Accounts::PASSWORD_MAX, 'confirmed'],
        ]);
        $data['email'] = Accounts::normalizeEmail($data['email']);

        $status = Password::broker()->reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            ApiTokens::revokeAll($user);
            DB::table('sessions')->where('user_id', $user->id)->delete();

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => __('passwords.token')]);
        }

        return redirect()->to(LoginPage::url())->with('status', __('passwords.reset'));
    }
}
