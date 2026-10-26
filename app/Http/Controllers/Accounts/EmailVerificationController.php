<?php

namespace App\Http\Controllers\Accounts;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Auth\LoginPage;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Email address confirmation with the link from the mail. */
class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->to(url_slash('moj-nalog'));
        }

        return view('accounts.verify-email');
    }

    /** The link works without login too: the signature and the email hash prove ownership. */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::find($id);

        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        $target = $request->user()?->is($user) ? url_slash('moj-nalog') : LoginPage::url();

        return redirect()->to($target)->with('status', 'Email adresa je potvrđena. Hvala!');
    }

    public function send(Request $request): RedirectResponse
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->user()->sendEmailVerificationNotification();
        }

        return back()->with('status', 'Poslali smo novi link za potvrdu na '.$request->user()->email.'.');
    }
}
