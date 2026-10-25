<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** /moj-nalog/obavestenja/: which mails and push notifications the user wants. */
class SettingsController extends Controller
{
    public function show(Request $request): View
    {
        return view('notifications.settings', [
            'user' => $request->user(),
            'labels' => NotificationPreferences::labels(),
            'values' => NotificationPreferences::all($request->user()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        Gate::authorize('update', $user);

        $rules = [];
        foreach (array_keys(NotificationPreferences::DEFAULTS) as $key) {
            $rules[$key] = ['sometimes', 'boolean'];
        }
        $data = $request->validate($rules);

        // Unchecked boxes are not sent: every category not in the form is off.
        $values = [];
        foreach (array_keys(NotificationPreferences::DEFAULTS) as $key) {
            $values[$key] = (bool) ($data[$key] ?? false);
        }
        NotificationPreferences::set($user, $values);

        return redirect()->to(url_slash('moj-nalog/obavestenja/podesavanja'))->with('status', 'Podešavanja obaveštenja su sačuvana.');
    }
}
