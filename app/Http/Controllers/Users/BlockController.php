<?php

namespace App\Http\Controllers\Users;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Users\UserBlocks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** POST / DELETE /moj-nalog/blokirani/{user}/: block or unblock a user (from the profile, the conversation or the settings list). */
class BlockController extends Controller
{
    public function store(Request $request, int $user, UserBlocks $blocks): RedirectResponse
    {
        $target = User::query()->findOrFail($user);
        abort_if($target->id === $request->user()->id, 404);
        $blocks->block($request->user(), $target->id);

        return back()->with('status', 'Korisnik je blokiran. Ne može da vam piše, a njegove oglase više ne vidite u pretrazi.');
    }

    public function destroy(Request $request, int $user, UserBlocks $blocks): RedirectResponse
    {
        $blocks->unblock($request->user(), $user);

        return back()->with('status', 'Korisnik je odblokiran.');
    }
}
