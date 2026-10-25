<?php

namespace App\Http\Controllers\Ratings;

use App\Exceptions\RatingException;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\SellerRating;
use App\Models\User;
use App\Services\Ratings\SellerRatings;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Seller ratings on the site: give one from a conversation, the seller answers or reports, public list. */
class RatingController extends Controller
{
    public const PER_PAGE = 20;

    public function __construct(private readonly SellerRatings $ratings) {}

    /** /korisnik/{slug}/ocene/ */
    public function index(Request $request, string $slug): View
    {
        $user = User::query()->where('slug', $slug)->with('firm')->first();
        $viewer = $request->user();
        abort_if($user === null || ($user->isBlocked() && ! ($viewer?->isStaff() || $viewer?->id === $user->id)), 404);

        $items = SellerRating::query()->visible()->where('seller_id', $user->id)
            ->with(['rater:id,name,deleted_at', 'listing:id,title,slug,deleted_at'])
            ->latest()->paginate(self::PER_PAGE)->withPath(url_slash('korisnik/'.$user->slug.'/ocene'));
        abort_if($items->currentPage() > 1 && $items->isEmpty(), 404);

        $name = $user->firm?->name ?: $user->name;
        Seo::get()->title('Ocene: '.$name.' | loglasi.rs')->noindex()
            ->canonical(url_slash('korisnik/'.$user->slug.'/ocene'));

        return view('ratings.index', [
            'user' => $user,
            'name' => $name,
            'summary' => $this->ratings->summary($user->id),
            'items' => $items,
            'viewer' => $viewer,
        ]);
    }

    /** POST /moj-nalog/poruke/{conversation}/ocena */
    public function store(Request $request, Conversation $conversation): RedirectResponse
    {
        Gate::authorize('view', $conversation);
        $data = $request->validateWithBag('ocena', [
            'score' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:'.SellerRatings::COMMENT_MAX],
        ], [
            'score.required' => 'Izaberite ocenu od 1 do 5 zvezdica.',
            'score.between' => 'Izaberite ocenu od 1 do 5 zvezdica.',
            'comment.max' => 'Komentar može imati najviše '.SellerRatings::COMMENT_MAX.' znakova.',
        ]);

        $back = redirect()->to(url_slash('moj-nalog/poruke/'.$conversation->id).'#ocena');
        try {
            $rating = $this->ratings->rate($request->user(), $conversation, (int) $data['score'], $data['comment'] ?? null);
        } catch (RatingException $e) {
            return $back->withErrors(['score' => $e->getMessage()], 'ocena')->withInput();
        }

        return $back->with('status', $rating->wasRecentlyCreated ? 'Hvala, ocena je objavljena.' : 'Ocena je izmenjena.');
    }

    /** POST /ocene/{rating}/odgovor */
    public function reply(Request $request, SellerRating $rating): RedirectResponse
    {
        $data = $request->validate(['reply' => ['nullable', 'string', 'max:'.SellerRatings::REPLY_MAX]], [
            'reply.max' => 'Odgovor može imati najviše '.SellerRatings::REPLY_MAX.' znakova.',
        ]);

        return $this->sellerAction($request, $rating, fn () => $this->ratings->reply($request->user(), $rating, $data['reply'] ?? null), 'Odgovor je sačuvan.');
    }

    /** POST /ocene/{rating}/prijava */
    public function report(Request $request, SellerRating $rating): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [
            'reason.required' => 'Napišite zašto prijavljujete ocenu.',
        ]);

        return $this->sellerAction($request, $rating, fn () => $this->ratings->report($request->user(), $rating, $data['reason']), 'Prijava je poslata. Administrator će pregledati ocenu.');
    }

    private function sellerAction(Request $request, SellerRating $rating, callable $action, string $done): RedirectResponse
    {
        abort_unless((int) $rating->seller_id === (int) $request->user()->id, 404);
        $back = redirect()->to(url_slash('korisnik/'.$request->user()->slug.'/ocene').'#ocena-'.$rating->id);
        try {
            $action();
        } catch (RatingException $e) {
            return $back->withErrors(['rating' => $e->getMessage()]);
        }

        return $back->with('status', $done);
    }
}
