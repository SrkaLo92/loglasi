<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * One-click unsubscribe from a signed link (mail footer, List-Unsubscribe).
 * GET shows a confirmation button (link scanners open GET links), POST turns the
 * category off. Mail clients POST "List-Unsubscribe=One-Click" without a CSRF token
 * (RFC 8058), so the POST route is signed instead of CSRF-protected.
 */
class UnsubscribeController extends Controller
{
    public function show(Request $request, int $user, string $category): View
    {
        $account = $this->user($user);

        return view('notifications.unsubscribe', [
            'category' => $category,
            'label' => NotificationPreferences::labels()[$category]['label'],
            'done' => ! NotificationPreferences::allows($account, $category),
            'action' => $request->fullUrl(),
        ]);
    }

    public function store(Request $request, int $user, string $category): Response
    {
        $account = $this->user($user);
        NotificationPreferences::set($account, [$category => false]);

        return response()->view('notifications.unsubscribe', [
            'category' => $category,
            'label' => NotificationPreferences::labels()[$category]['label'],
            'done' => true,
            'action' => null,
        ]);
    }

    private function user(int $id): User
    {
        return User::query()->findOrFail($id);
    }
}
