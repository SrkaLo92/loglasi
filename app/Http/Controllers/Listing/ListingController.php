<?php

namespace App\Http\Controllers\Listing;

use App\Http\Controllers\Controller;
use App\Http\Middleware\Gone;
use App\Models\Listing;
use App\Models\Report;
use App\Services\Catalog;
use App\Services\ListingFields;
use App\Support\Listing\LoanCalculator;
use App\Support\Listing\ReportReasons;
use App\Support\Listing\Seller;
use App\Support\Listing\SimilarListings;
use App\Support\Listing\Stats;
use App\Support\PageCache;
use App\Support\Seo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** /oglas/{slug}/: listing page, phone reveal and report form. */
class ListingController extends Controller
{
    public function show(Request $request, string $slug, Catalog $catalog, ListingFields $fields): View|Response
    {
        // An address that is not (or no longer) a listing here is gone for good: the old site's listings that
        // were not imported, deleted ones, typos. 410 lets search engines drop it (the 410 list and the
        // redirects table are checked before this, in the middleware).
        if (! Listing::query()->where('slug', $slug)->exists()) {
            return Gone::response();
        }
        $listing = $this->find($request, $slug, 'view');
        $viewer = $request->user();

        // Sold or expired for a long time: gone for visitors and search engines; the owner still sees it.
        $since = $listing->inactiveSince();
        if ($since !== null && $since->lt(now()->subDays((int) config('seo.inactive_gone_days', 90)))
            && ! ($viewer && ($viewer->id === $listing->user_id || $viewer->isStaff()))) {
            return Gone::response();
        }

        Seo::for($listing);
        Stats::viewFromWeb($listing, $request);
        PageCache::onHit('listing', $listing->id);

        $seller = Seller::forListing($listing);
        $isOwner = $viewer !== null && $viewer->id === $listing->user_id;
        $active = $listing->isActive();
        $showBar = $active && ! $isOwner;

        $crumbs = [['Početna', url_slash('/')], ['Oglasi', url_slash('oglasi')]];
        if ($listing->category && ($category = $catalog->category((int) $listing->category_id))) {
            foreach ($catalog->breadcrumbs($category) as $crumb) {
                $crumbs[] = [$crumb->name, url_slash('oglasi/'.$crumb->slug)];
            }
        }
        $crumbs[] = [$listing->title, null];

        return view('listing.show', [
            'listing' => $listing,
            'seller' => $seller,
            'fieldValues' => $fields->values($listing),
            'swap' => in_array(mb_strtolower((string) $fields->value('moguca_zamena', $listing)), ['1', 'da', 'yes', 'true'], true),
            'similar' => SimilarListings::forPage($listing),
            'breadcrumbs' => $crumbs,
            'isOwner' => $isOwner,
            'active' => $active,
            'showBar' => $showBar,
            'revealed' => Stats::revealed($request, $listing),
            'loan' => $active && LoanCalculator::applies($listing) ? LoanCalculator::example($listing) : null,
            'reasons' => ReportReasons::ALL,
            'hideMobileNav' => $showBar,
            'bodyClass' => $showBar ? 'lgo-has-bar' : '',
        ]);
    }

    /** POST /oglas/{slug}/telefon/: JSON for the script, redirect back (number visible) without JS. */
    public function revealPhone(Request $request, string $slug): JsonResponse|RedirectResponse
    {
        $listing = $this->find($request, $slug, 'revealPhone');
        $seller = Seller::forListing($listing);

        if ($seller->hasPhone()) {
            Stats::reveal($listing, $request, $request->user());
        }

        if ($request->expectsJson()) {
            $links = $seller->links();

            return response()->json($seller->hasPhone() ? [
                'phone' => $seller->phone,
                'display' => $seller->display(),
                'tel' => $links['tel'],
                'whatsapp' => $links['whatsapp'].'?text='.rawurlencode(static::whatsappText($listing)),
                'viber' => $links['viber'],
            ] : ['phone' => null], 200, ['Cache-Control' => 'no-store']);
        }

        $anchor = in_array($request->input('next'), ['tel', 'whatsapp', 'viber'], true) ? $request->input('next') : 'kontakt';

        return redirect()->to(url_slash('oglas/'.$listing->slug).'#'.($anchor === 'kontakt' ? 'kontakt' : 'kontakt-'.$anchor));
    }

    /** POST /oglas/{slug}/prijava/ */
    public function report(Request $request, string $slug): RedirectResponse
    {
        $listing = $this->find($request, $slug, 'report');
        $back = url_slash('oglas/'.$listing->slug).'#prijava';

        // Honeypot: bots get the success message, nothing is saved.
        if (filled($request->input('website'))) {
            return redirect()->to($back)->with('report_sent', true);
        }

        $data = $request->validateWithBag('report', [
            'reason' => ['required', Rule::in(ReportReasons::keys())],
            'details' => ['nullable', 'string', 'max:1000'],
        ], [
            'reason.required' => 'Izaberite razlog prijave.',
            'reason.in' => 'Izaberite razlog prijave.',
            'details.max' => 'Opis može imati najviše 1.000 znakova.',
        ]);

        Report::fileOnce($request->user(), $listing, $data['reason'], $data['details'] ?? null);

        return redirect()->to($back)->with('report_sent', true);
    }

    /** WhatsApp message with the listing link. */
    public static function whatsappText(Listing $listing): string
    {
        return 'Zdravo, pišem u vezi oglasa „'.$listing->title.'” na loglasi.rs: '.url_slash('oglas/'.$listing->slug);
    }

    /** Listing by slug that the visitor may see; otherwise 404 (deleted ones become 410 in the Gone middleware). */
    private function find(Request $request, string $slug, string $ability): Listing
    {
        $listing = Listing::query()
            ->where('slug', $slug)
            ->with(['category.parent', 'location', 'user.firm', 'images', 'fieldValues'])
            ->first();

        abort_if($listing === null || Gate::forUser($request->user())->denies($ability, $listing), 404);

        return $listing;
    }
}
