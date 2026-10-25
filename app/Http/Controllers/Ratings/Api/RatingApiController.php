<?php

namespace App\Http\Controllers\Ratings\Api;

use App\Exceptions\RatingException;
use App\Http\Api\ApiError;
use App\Http\Api\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\SellerRating;
use App\Models\User;
use App\Services\Ratings\SellerRatings;
use App\Support\Ratings\RatingPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/** Seller ratings in the app (/api/v1). Same rules as the site: App\Services\Ratings\SellerRatings. */
class RatingApiController extends Controller
{
    public const PER_PAGE = 20;

    public function __construct(private readonly SellerRatings $ratings) {}

    /** GET /users/{id}/ratings: summary and a page of visible ratings, newest first. */
    public function index(Request $request, int $id)
    {
        $user = User::query()->find($id);
        if ($user === null || $user->isBlocked()) {
            throw ApiError::notFound('Korisnik ne postoji.');
        }
        $page = $this->valid($request->query(), ['page' => ['nullable', 'integer', 'min:1', 'max:500']])['page'] ?? 1;

        $items = SellerRating::query()->visible()->where('seller_id', $user->id)
            ->with(['rater:id,name,deleted_at', 'listing:id,title,deleted_at'])
            ->latest()->forPage((int) $page, self::PER_PAGE + 1)->get();
        $viewer = $request->user('sanctum');

        return ApiResponse::json($request, [
            'summary' => $this->ratings->summary($user->id),
            'items' => $items->take(self::PER_PAGE)->map(fn (SellerRating $r) => RatingPresenter::api($r, $viewer))->values(),
            'next_page' => $items->count() > self::PER_PAGE ? (int) $page + 1 : null,
        ], $viewer ? ApiResponse::CACHE_PRIVATE : ApiResponse::CACHE_DEFAULT);
    }

    /** GET /conversations/{id}/rating: can the buyer rate, and their rating so far. */
    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeOr404($conversation);
        $card = $this->ratings->card($request->user(), $conversation);

        return ApiResponse::json($request, $this->cardPayload($card, $request->user()), ApiResponse::CACHE_PRIVATE);
    }

    /** PUT /conversations/{id}/rating {score, comment}: gives or changes the rating. */
    public function update(Request $request, Conversation $conversation)
    {
        $this->authorizeOr404($conversation);
        $data = $this->valid($request->all(), [
            'score' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:'.SellerRatings::COMMENT_MAX],
        ]);

        $rating = $this->guard(fn () => $this->ratings->rate($request->user(), $conversation, (int) $data['score'], $data['comment'] ?? null));
        $rating->load(['rater:id,name,deleted_at', 'listing:id,title,deleted_at']);

        return ApiResponse::json($request, RatingPresenter::api($rating, $request->user()), null, $rating->wasRecentlyCreated ? 201 : 200);
    }

    /** POST /ratings/{id}/reply {text}: the seller's public answer (empty removes it). */
    public function reply(Request $request, SellerRating $rating)
    {
        $data = $this->valid($request->all(), ['text' => ['nullable', 'string', 'max:'.SellerRatings::REPLY_MAX]]);
        $rating = $this->guard(fn () => $this->ratings->reply($request->user(), $rating, $data['text'] ?? null));
        $rating->load(['rater:id,name,deleted_at', 'listing:id,title,deleted_at']);

        return ApiResponse::json($request, RatingPresenter::api($rating, $request->user()), null);
    }

    /** POST /ratings/{id}/report {reason}: the seller asks the admin to look at it. */
    public function report(Request $request, SellerRating $rating)
    {
        $data = $this->valid($request->all(), ['reason' => ['required', 'string', 'max:500']]);
        $this->guard(fn () => $this->ratings->report($request->user(), $rating, $data['reason']));

        return ApiResponse::json($request, ['reported' => true], null, 202);
    }

    /** @return array<string, mixed> */
    private function cardPayload(?array $card, User $viewer): array
    {
        if ($card === null) {
            return ['can_rate' => false, 'reason' => 'Ocenu daje kupac.', 'rating' => null];
        }
        $card['existing']?->load(['rater:id,name,deleted_at', 'listing:id,title,deleted_at']);

        return [
            'can_rate' => $card['blocker'] === null,
            'reason' => $card['blocker'],
            'rating' => $card['existing'] ? RatingPresenter::api($card['existing'], $viewer) : null,
        ];
    }

    private function guard(callable $action): SellerRating
    {
        try {
            return $action();
        } catch (RatingException $e) {
            throw new ApiError($e->reason, $e->getMessage(), $e->reason === 'not_owner' ? 404 : 422);
        }
    }

    private function authorizeOr404(Conversation $conversation): void
    {
        if (! Gate::allows('view', $conversation)) {
            throw ApiError::notFound('Razgovor ne postoji.');
        }
    }

    /** @return array<string, mixed> */
    private function valid(array $input, array $rules): array
    {
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            throw ApiError::invalidParams(array_map(fn (array $errors) => $errors[0], $validator->errors()->toArray()));
        }

        return $validator->validated();
    }
}
