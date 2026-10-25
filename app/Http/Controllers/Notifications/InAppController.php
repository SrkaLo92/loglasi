<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use App\Services\Notifications\InAppNotifications;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * /moj-nalog/obavestenja/: the bell's list. Opening the first page marks everything as read
 * (the unread ones stay highlighted on that view).
 */
class InAppController extends Controller
{
    public function __construct(private InAppNotifications $inApp) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $before = ctype_digit((string) $request->query('pre')) ? (int) $request->query('pre') : null;
        $page = $this->inApp->page($user, $before);

        if ($before === null) {
            $this->inApp->markRead($user);
        }

        return view('notifications.index', [
            'items' => $page['items']->map(fn ($n) => $this->inApp->present($n) + ['unread' => $n->read_at === null, 'at' => $n->created_at])->all(),
            'next' => $page['next'] ? url_slash('moj-nalog/obavestenja').'?pre='.$page['next'] : null,
        ]);
    }

    public function read(Request $request): RedirectResponse
    {
        $this->inApp->markRead($request->user());

        return redirect()->to(url_slash('moj-nalog/obavestenja'));
    }
}
