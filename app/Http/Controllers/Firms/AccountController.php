<?php

namespace App\Http\Controllers\Firms;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\FirmBenefits;
use App\Support\Firms\FirmAccount;
use App\Support\Firms\FirmImages;
use App\Support\Firms\FirmProfileUpdater;
use App\Support\Firms\FirmStats;
use App\Support\Firms\WorkingHours;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

/** /moj-nalog/firma/: firm profile, Biznis status and statistics (firm accounts only). */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $firm = FirmAccount::firmFor($user->loadMissing('firm.location'));
        Gate::authorize('update', $firm);

        return view('firms.account.edit', [
            'user' => $user,
            'firm' => $firm,
            'status' => FirmAccount::status($user, $firm),
            'hours' => WorkingHours::days($firm->working_hours),
            'locations' => Location::query()->orderBy('sort')->orderBy('name')->get(['id', 'name']),
            'canStats' => app(FirmBenefits::class)->canShowStats($firm),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $firm = FirmAccount::firmFor($user);
        Gate::authorize('update', $firm);

        $data = $request->validate(FirmProfileUpdater::webRules(), FirmProfileUpdater::messages());

        DB::transaction(function () use ($firm, $data) {
            FirmProfileUpdater::fillFromWeb($firm, $data);
            $firm->save();
        });

        foreach (['logo' => ['logo_path', 'storeLogo'], 'cover' => ['cover_path', 'storeCover']] as $field => [$column, $store]) {
            if ($request->boolean('remove_'.$field)) {
                FirmImages::delete($firm, $column);
            } elseif ($request->hasFile($field)) {
                try {
                    FirmImages::{$store}($firm, $request->file($field)->getRealPath());
                } catch (RuntimeException) {
                    throw ValidationException::withMessages([$field => 'Slika ne može da se obradi. Probajte drugu sliku.']);
                }
            }
        }

        return redirect()->to(url_slash('moj-nalog/firma'))->with('status', 'Podaci firme su sačuvani.');
    }

    public function stats(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $firm = FirmAccount::firmFor($user);
        Gate::authorize('update', $firm);

        $periods = array_map('intval', (array) config('firms.stats.periods', [30, 90]));
        $period = (int) $request->query('period', (string) config('firms.stats.default_period', 30));
        if (! in_array($period, $periods, true)) {
            return redirect()->to(url_slash('moj-nalog/firma/statistika'));
        }

        $allowed = Gate::allows('viewStats', $firm);

        return view('firms.account.stats', [
            'user' => $user,
            'firm' => $firm,
            'allowed' => $allowed,
            'period' => $period,
            'periods' => $periods,
            'stats' => $allowed ? FirmStats::daily($user->id, $period) : null,
            'listings' => $allowed ? FirmStats::perListing($user->id, $period) : [],
            'status' => $allowed ? null : FirmAccount::status($user, $firm),
        ]);
    }
}
