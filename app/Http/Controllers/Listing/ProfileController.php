<?php

namespace App\Http\Controllers\Listing;

use App\Http\Api\ListingFormatter;
use App\Http\Controllers\Controller;
use App\Models\Firm;
use App\Models\Listing;
use App\Models\User;
use App\Services\Catalog;
use App\Services\FirmBenefits;
use App\Support\Listing\Seller;
use App\Support\Listing\SimilarListings;
use App\Support\Listing\Stats;
use App\Support\Listing\WorkingHours;
use App\Support\Phone;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/** /korisnik/{slug}/: firm profile (rich) or private profile (simple, noindex). */
class ProfileController extends Controller
{
    public const PER_PAGE = 24;

    public const MAX_PAGES = 50;

    public function show(Request $request, string $slug, Catalog $catalog): View
    {
        $user = User::query()->where('slug', $slug)->with('firm.location')->first();
        $viewer = $request->user();
        $canEdit = $viewer !== null && $user !== null && ($viewer->id === $user->id || $viewer->isStaff());

        // Blocked accounts are hidden from the public (deleted ones become 410 in the Gone middleware).
        abort_if($user === null || ($user->isBlocked() && ! $canEdit), 404);

        $isFirm = $user->isFirm();
        $firm = $isFirm ? ($user->firm ?? new Firm(['name' => $user->name])) : null;

        // Listings: counts per top-level category for the tabs, one query.
        $base = Listing::query()->active()->where('user_id', $user->id);
        $tabs = $this->categoryTabs((clone $base), $user->id, $catalog);
        $total = array_sum(array_column($tabs, 'count'));

        $selected = null;
        $slugParam = $request->query('kategorija');
        if (is_string($slugParam) && $slugParam !== '') {
            $selected = collect($tabs)->firstWhere('slug', $slugParam);
            abort_if($selected === null, 404);
            $base->whereIn('category_id', $catalog->descendantIds((int) $selected['id']));
        }

        // Bounded pagination: the total comes from the tab counts (no second COUNT query),
        // and pages stop at MAX_PAGES so OFFSET stays small on the (user_id, status, bumped_at) index.
        $count = $selected['count'] ?? $total;
        $page = max(1, (int) $request->query('page', 1));
        $lastPage = max(1, min(self::MAX_PAGES, (int) ceil($count / self::PER_PAGE)));
        abort_if($page > $lastPage, 404);

        $items = SimilarListings::ordered($base)
            ->with(ListingFormatter::CARD_RELATIONS)
            ->forPage($page, self::PER_PAGE)
            ->get();

        $listings = (new LengthAwarePaginator($items, min($count, $lastPage * self::PER_PAGE), self::PER_PAGE, $page, [
            'path' => url_slash('korisnik/'.$user->slug),
            'pageName' => 'page',
        ]))->withQueryString();

        Seo::for($user, ['activity' => $firm?->activity]);

        $data = [
            'user' => $user,
            'firm' => $firm,
            'listings' => $listings,
            'tabs' => count($tabs) > 1 ? $tabs : [],
            'selected' => $selected,
            'total' => $total,
            'canEdit' => $canEdit,
            'isOwner' => $viewer !== null && $viewer->id === $user->id,
            'editUrl' => url_slash($isFirm ? 'moj-nalog/firma' : (string) config('listing.profile_edit_path', 'moj-nalog')),
            'messageUrl' => url_slash('moj-nalog/poruke/novo').'?korisnik='.$user->id,
        ];

        if (! $isFirm) {
            return view('listing.profile-private', $data + [
                'phone' => Phone::normalize($user->phone),
            ]);
        }

        if ($firm->exists) {
            Stats::profileView($firm, $request);
        }

        $hours = WorkingHours::days($firm->working_hours);
        $schedule = WorkingHours::schedule($hours);
        $phone = Phone::normalize($firm->phone) ?: Phone::normalize($user->phone);
        $showStats = $canEdit && $firm->exists && app(FirmBenefits::class)->canShowStats($firm);

        return view('listing.profile-firm', $data + [
            'phone' => $phone,
            'hours' => $hours,
            'schedule' => $schedule,
            'status' => WorkingHours::status($schedule),
            'hasHours' => WorkingHours::hasAny($hours),
            'city' => $firm->location?->name ?: 'Loznica',
            'stats' => $showStats ? Stats::totalsForUser($user->id, 30) : null,
            'hasSticky' => $phone !== '',
            'hideMobileNav' => false,
        ]);
    }

    /**
     * Top-level categories of the user's active listings, most listings first.
     *
     * @return list<array{id: int, name: string, slug: string, count: int}>
     */
    private function categoryTabs($query, int $userId, Catalog $catalog): array
    {
        // Cached with the seller count; cleared by Seller::forgetCount() on every listing change.
        $counts = Cache::remember(Seller::TABS_KEY.$userId, 600, fn () => $query->toBase()
            ->selectRaw('category_id, COUNT(*) as aggregate')->groupBy('category_id')
            ->pluck('aggregate', 'category_id')->all());
        $tabs = [];

        foreach ($counts as $categoryId => $count) {
            $category = $catalog->category((int) $categoryId);
            $top = $category ? ($catalog->breadcrumbs($category)->first() ?? $category) : null;
            if ($top === null) {
                continue;
            }
            $tabs[$top->id] ??= ['id' => (int) $top->id, 'name' => $top->name, 'slug' => $top->slug, 'count' => 0];
            $tabs[$top->id]['count'] += (int) $count;
        }

        usort($tabs, fn ($a, $b) => [$b['count'], $a['name']] <=> [$a['count'], $b['name']]);

        return $tabs;
    }
}
