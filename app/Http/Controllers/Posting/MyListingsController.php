<?php

namespace App\Http\Controllers\Posting;

use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Services\Posting;
use App\Services\PostingRefused;
use App\Support\ListingLimits;
use App\Support\Posting\Wizard;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** /moj-nalog/oglasi/: the user's listings by status, renew, sold, deactivate, delete, statistics. */
class MyListingsController extends Controller
{
    use AuthorizesRequests;

    /** Tab (URL value) => label. */
    public const TABS = [
        'aktivni' => 'Aktivni',
        'na-cekanju' => 'Na čekanju',
        'istekli' => 'Istekli',
        'odbijeni' => 'Odbijeni',
        'nacrti' => 'Nacrti',
    ];

    public const PER_PAGE = 20;

    public function __construct(private readonly Posting $posting) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $tab = array_key_exists((string) $request->query('status'), self::TABS) ? (string) $request->query('status') : 'aktivni';

        $counts = [];
        foreach (array_keys(self::TABS) as $key) {
            $counts[$key] = $this->tabQuery($user->id, $key)->count();
        }

        $listings = $this->tabQuery($user->id, $tab)
            ->with(['images', 'category', 'location'])
            ->orderByDesc('updated_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        Seo::get()->page('Moji oglasi')->noindex();

        return view('posting.my-listings', [
            'tab' => $tab,
            'tabs' => self::TABS,
            'counts' => $counts,
            'listings' => $listings,
            'recent' => $this->recentStats($listings->pluck('id')->all()),
            'limits' => ListingLimits::for($user),
            'posting' => $this->posting,
        ]);
    }

    public function edit(Listing $listing): RedirectResponse
    {
        $this->authorize('update', $listing);

        return redirect(Wizard::url($listing, 'basics'));
    }

    public function confirmRenew(Listing $listing): View|RedirectResponse
    {
        $this->authorize('renew', $listing);
        if (! $this->posting->canRenew($listing)) {
            return redirect(url_slash('moj-nalog/oglasi'))->with('status', 'Oglas „'.$listing->title.'” je aktivan do '.$this->posting->expiresLabel($listing->expires_at).' i još ne može da se obnovi.');
        }

        Seo::get()->page('Obnova oglasa')->noindex();

        return view('posting.confirm', [
            'listing' => $listing,
            'heading' => 'Obnova oglasa',
            'text' => 'Oglas „'.$listing->title.'” biće ponovo aktivan još '.$this->posting->durationDays().' dana. Obnova je besplatna.',
            'action' => url_slash('moj-nalog/oglasi/'.$listing->id.'/obnova'),
            'method' => 'POST',
            'button' => 'Obnovi oglas',
            'danger' => false,
        ]);
    }

    public function renew(Listing $listing): RedirectResponse
    {
        $this->authorize('renew', $listing);
        try {
            $renewed = $this->posting->renew($listing);
        } catch (PostingRefused $e) {
            return redirect(url_slash('moj-nalog/oglasi').'?status=istekli')->with('status', $e->getMessage());
        }

        return redirect(url_slash('moj-nalog/oglasi').'?status=aktivni')->with('status', $renewed
            ? 'Oglas je obnovljen do '.$this->posting->expiresLabel($listing->expires_at).'.'
            : 'Ovaj oglas još ne može da se obnovi.');
    }

    public function sold(Listing $listing): RedirectResponse
    {
        $this->authorize('update', $listing);
        $done = $this->posting->markSold($listing);

        return redirect(url_slash('moj-nalog/oglasi').'?status='.($done ? 'istekli' : 'aktivni'))
            ->with('status', $done ? 'Oglas je označen kao prodat i više se ne prikazuje na sajtu.' : 'Samo aktivan oglas može da se označi kao prodat.');
    }

    public function deactivate(Listing $listing): RedirectResponse
    {
        $this->authorize('update', $listing);
        $done = $this->posting->deactivate($listing);

        return redirect(url_slash('moj-nalog/oglasi').'?status='.($done ? 'istekli' : 'aktivni'))
            ->with('status', $done ? 'Oglas je skinut sa sajta. Možete ga besplatno obnoviti kad god želite.' : 'Samo aktivan oglas može da se skine sa sajta.');
    }

    public function confirmDelete(Listing $listing): View
    {
        $this->authorize('delete', $listing);
        Seo::get()->page('Brisanje oglasa')->noindex();

        return view('posting.confirm', [
            'listing' => $listing,
            'heading' => 'Brisanje oglasa',
            'text' => $listing->status === ListingStatus::Draft
                ? 'Nezavršeni oglas i njegove slike biće obrisani.'
                : 'Oglas „'.$listing->title.'” biće obrisan i neće se više prikazivati. Brisanje ne vraća mesto u mesečnom limitu.',
            'action' => url_slash('moj-nalog/oglasi/'.$listing->id),
            'method' => 'DELETE',
            'button' => 'Obriši oglas',
            'danger' => true,
        ]);
    }

    public function destroy(Listing $listing): RedirectResponse
    {
        $this->authorize('delete', $listing);
        $draft = $listing->status === ListingStatus::Draft;
        $this->posting->delete($listing);

        return redirect(url_slash('moj-nalog/oglasi').($draft ? '?status=nacrti' : ''))->with('status', 'Oglas je obrisan.');
    }

    public function stats(Listing $listing): View
    {
        $this->authorize('manage', $listing);

        $days = DB::table('listing_daily_stats')
            ->where('listing_id', $listing->id)
            ->where('date', '>=', now()->subDays(29)->toDateString())
            ->orderByDesc('date')
            ->get(['date', 'views', 'phone_reveals', 'messages', 'favorites']);

        Seo::get()->page('Statistika oglasa')->noindex();

        return view('posting.stats', ['listing' => $listing, 'days' => $days]);
    }

    // ---------------------------------------------------------------- helpers

    private function tabQuery(int $userId, string $tab): Builder
    {
        $query = Listing::query()->where('user_id', $userId);
        $now = now();

        return match ($tab) {
            'na-cekanju' => $query->where('status', ListingStatus::Pending),
            'istekli' => $query->where(fn ($q) => $q->whereIn('status', [ListingStatus::Expired, ListingStatus::Sold])
                ->orWhere(fn ($q) => $q->where('status', ListingStatus::Published)->where('expires_at', '<=', $now))),
            'odbijeni' => $query->where('status', ListingStatus::Rejected),
            'nacrti' => $query->where('status', ListingStatus::Draft),
            default => $query->where('status', ListingStatus::Published)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now)),
        };
    }

    /**
     * Views and phone reveals in the last 30 days, per listing (one query).
     *
     * @param  list<int>  $ids
     * @return array<int, object{views: int, phone_reveals: int}>
     */
    private function recentStats(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return DB::table('listing_daily_stats')
            ->whereIn('listing_id', $ids)
            ->where('date', '>=', now()->subDays(29)->toDateString())
            ->groupBy('listing_id')
            ->selectRaw('listing_id, SUM(views) as views, SUM(phone_reveals) as phone_reveals')
            ->get()
            ->keyBy('listing_id')
            ->all();
    }
}
