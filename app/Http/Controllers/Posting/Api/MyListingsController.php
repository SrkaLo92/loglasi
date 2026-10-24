<?php

namespace App\Http\Controllers\Posting\Api;

use App\Enums\Currency;
use App\Enums\ListingStatus;
use App\Http\Api\ApiError;
use App\Http\Api\ApiResponse;
use App\Http\Api\ListingFormatter;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Services\Catalog;
use App\Services\ListingFields;
use App\Services\ListingImages;
use App\Services\Posting;
use App\Services\PostingRefused;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/** /me/listings for the mobile app (ListingInput, ListingCard, Listing from the contract). */
class MyListingsController extends Controller
{
    use AuthorizesRequests;

    public const PER_PAGE = 20;

    private const STATUSES = ['pending', 'active', 'rejected', 'expired', 'sold'];

    public function __construct(
        private readonly Posting $posting,
        private readonly Catalog $catalog,
        private readonly ListingFields $fields,
        private readonly ListingImages $images,
    ) {}

    public function index(Request $request): Response
    {
        $input = Validator::make($request->query(), [
            'status' => ['nullable', Rule::in(self::STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
        ], [
            'status.in' => 'Status može biti: '.implode(', ', self::STATUSES).'.',
            'page.integer' => 'Strana mora biti ceo broj od 1 naviše.',
            'page.min' => 'Strana mora biti ceo broj od 1 naviše.',
        ])->validate();

        $page = (int) ($input['page'] ?? 1);
        $query = $this->statusQuery($request->user()->id, $input['status'] ?? null);
        $total = (clone $query)->count();
        $items = $query->with(ListingFormatter::CARD_RELATIONS)
            ->orderByDesc('updated_at')->orderByDesc('id')
            ->forPage($page, self::PER_PAGE)->get();

        return ApiResponse::json($request, [
            'items' => $items->map(fn (Listing $l) => ListingFormatter::card($l))->values()->all(),
            'page' => $page,
            'total' => $total,
        ], ApiResponse::CACHE_PRIVATE);
    }

    public function show(Request $request, Listing $listing): Response
    {
        $this->authorize('manage', $listing);

        return ApiResponse::json($request, ListingFormatter::detail($listing, $request->user()), ApiResponse::CACHE_PRIVATE);
    }

    public function store(Request $request): Response
    {
        $user = $request->user();
        $data = $this->validateInput($request, false);
        $category = $this->category($data['category_id']);
        $price = $this->price($data);

        try {
            $this->posting->ensureCanCreate($user);
        } catch (PostingRefused $e) {
            throw new ApiError('limit_reached', $e->getMessage(), 403);
        }

        $probe = new Listing(['user_id' => $user->id, 'category_id' => $category->id]);
        $fieldValues = $this->fieldValues($probe, $data, $user);
        $images = $this->ownImages($user, $data['image_ids'] ?? [], null);

        $this->refusalToValidation(function () use ($probe, $data, $category, $images) {
            $this->posting->ensureNotDuplicate($probe, $data['title'], $category->id);
            $this->posting->ensureImagesNotDuplicate($probe, $images);
        });

        $listing = $this->refusalToValidation(fn () => $this->posting->createDraft($user, $category, false));
        $this->posting->fillBasics($listing, $data, $price);
        $listing->save();
        $this->fields->save($listing, $fieldValues);
        if (array_key_exists('image_ids', $data)) {
            $this->images->sync($listing, $user, $data['image_ids']);
        }

        try {
            $listing = $this->posting->submit($listing, $user);
        } catch (PostingRefused|ValidationException $e) {
            $this->posting->delete($listing);
            $this->refusalToValidation(fn () => throw $e);
        }

        return ApiResponse::json($request, ListingFormatter::detail($listing->fresh(), $user), ApiResponse::CACHE_PRIVATE, 201);
    }

    public function update(Request $request, Listing $listing): Response
    {
        $this->authorize('update', $listing);
        $user = $request->user();
        $data = $this->validateInput($request, true);
        $before = [$listing->title, $listing->description];

        if (isset($data['category_id']) && $data['category_id'] !== $listing->category_id) {
            $this->posting->changeCategory($listing, $this->category($data['category_id']));
        }
        if (isset($data['title']) && Posting::cleanLine($data['title']) !== $listing->title) {
            $this->refusalToValidation(fn () => $this->posting->ensureNotDuplicate($listing, Posting::cleanLine($data['title'])));
        }

        $priceGiven = array_key_exists('price', $data) || array_key_exists('price_negotiable', $data);
        $this->posting->fillBasics($listing, $data, $priceGiven ? $this->price($data + ['price' => $listing->price]) : null, $priceGiven);

        if (array_key_exists('fields', $data) || array_key_exists('condition', $data)) {
            $fields = array_key_exists('fields', $data) ? (array) $data['fields'] : $this->posting->rawFieldValues($listing);
            if (isset($data['condition']) && ! isset(((array) ($data['fields'] ?? []))['stanje'])) {
                unset($fields['stanje']);
            }
            $this->fields->save($listing, $this->fieldValues($listing, ['fields' => $fields] + $data, $user));
        }

        $imagesChanged = false;
        if (array_key_exists('image_ids', $data)) {
            $current = $listing->images()->pluck('id')->all();
            $new = $this->ownImages($user, $data['image_ids'], $listing);
            $this->refusalToValidation(fn () => $this->posting->ensureImagesNotDuplicate($listing, $new->whereNull('listing_id')));
            $this->images->sync($listing, $user, $data['image_ids']);
            $imagesChanged = array_values(array_diff($data['image_ids'], $current)) !== [] || array_diff($current, $data['image_ids']) !== [];
        }

        $listing->save();
        $this->posting->afterEdit($listing, $user, $imagesChanged || $before !== [$listing->title, $listing->description]);

        if ($listing->status === ListingStatus::Rejected) {
            // a rejected listing goes back to review once it is changed
            $this->refusalToValidation(fn () => $this->posting->submit($listing, $user));
        }

        return ApiResponse::json($request, ListingFormatter::detail($listing->fresh(), $user), ApiResponse::CACHE_PRIVATE);
    }

    public function destroy(Listing $listing): Response
    {
        $this->authorize('delete', $listing);
        $this->posting->delete($listing);

        return response()->noContent();
    }

    public function renew(Request $request, Listing $listing): Response
    {
        $this->authorize('renew', $listing);
        if (! $this->posting->renew($listing)) {
            throw new ApiError('not_renewable', 'Oglas je aktivan do '.$this->posting->expiresLabel($listing->expires_at).' i još ne može da se obnovi.', 409);
        }

        return ApiResponse::json($request, ListingFormatter::detail($listing->fresh(), $request->user()), ApiResponse::CACHE_PRIVATE);
    }

    /** POST /me/listings/{id}/sold: an active listing is marked as sold and leaves the site (status "sold"). */
    public function sold(Request $request, Listing $listing): Response
    {
        $this->authorize('update', $listing);
        if (! $this->posting->markSold($listing)) {
            throw new ApiError('not_active', 'Samo aktivan oglas može da se označi kao prodat.', 409);
        }

        return ApiResponse::json($request, ListingFormatter::detail($listing->fresh(), $request->user()), ApiResponse::CACHE_PRIVATE);
    }

    /** POST /me/listings/{id}/deactivate: an active listing leaves the site (status "expired") and can be renewed for free. */
    public function deactivate(Request $request, Listing $listing): Response
    {
        $this->authorize('update', $listing);
        if (! $this->posting->deactivate($listing)) {
            throw new ApiError('not_active', 'Samo aktivan oglas može da se skine sa sajta.', 409);
        }

        return ApiResponse::json($request, ListingFormatter::detail($listing->fresh(), $request->user()), ApiResponse::CACHE_PRIVATE);
    }

    /** GET /me/listings/{id}/stats: totals and the last 30 days, newest day first (days without activity are left out). */
    public function stats(Request $request, Listing $listing): Response
    {
        $this->authorize('manage', $listing);

        $days = DB::table('listing_daily_stats')
            ->where('listing_id', $listing->id)
            ->where('date', '>=', now()->subDays(29)->toDateString())
            ->orderByDesc('date')
            ->get(['date', 'views', 'phone_reveals', 'messages', 'favorites']);

        return ApiResponse::json($request, [
            'listing_id' => $listing->id,
            'views' => (int) $listing->views,
            'phone_reveals' => (int) $listing->phone_reveals,
            'last_30_days' => [
                'views' => (int) $days->sum('views'),
                'phone_reveals' => (int) $days->sum('phone_reveals'),
                'messages' => (int) $days->sum('messages'),
                'favorites' => (int) $days->sum('favorites'),
            ],
            'days' => $days->map(fn ($d) => [
                'date' => (string) $d->date,
                'views' => (int) $d->views,
                'phone_reveals' => (int) $d->phone_reveals,
                'messages' => (int) $d->messages,
                'favorites' => (int) $d->favorites,
            ])->values()->all(),
        ], ApiResponse::CACHE_PRIVATE);
    }

    // ---------------------------------------------------------------- helpers

    private function validateInput(Request $request, bool $partial): array
    {
        $req = $partial ? 'sometimes' : 'required';
        $max = $this->images->maxImages();

        return Validator::make($request->all(), [
            'title' => [$req, 'string', 'min:'.Posting::TITLE_MIN, 'max:'.Posting::TITLE_MAX],
            'category_id' => [$req, 'integer'],
            'location_id' => [$req, 'integer', Rule::exists('locations', 'id')],
            'description' => [$req, 'string', 'min:'.Posting::DESCRIPTION_MIN, 'max:'.Posting::DESCRIPTION_MAX],
            'price' => ['nullable', 'integer', 'min:0', 'max:'.Posting::PRICE_MAX],
            'currency' => ['nullable', Rule::enum(Currency::class)],
            'price_negotiable' => ['nullable', 'boolean'],
            'condition' => ['nullable', Rule::in(['novo', 'polovno'])],
            'image_ids' => ['nullable', 'array', 'max:'.$max],
            'image_ids.*' => ['integer', 'distinct'],
            'fields' => ['nullable', 'array'],
        ], $this->posting->basicMessages() + [
            'category_id.required' => 'Izaberite kategoriju.',
            'category_id.integer' => 'Izaberite kategoriju sa spiska.',
            'price_negotiable.boolean' => 'Polje „Dogovor” može biti samo da ili ne.',
            'condition.in' => 'Stanje može biti „novo” ili „polovno”.',
            'image_ids.array' => 'Slike se šalju kao spisak ID-jeva.',
            'image_ids.max' => 'Oglas može imati najviše '.$max.' slika.',
            'image_ids.*.integer' => 'ID slike mora biti ceo broj.',
            'image_ids.*.distinct' => 'Ista slika je poslata dva puta.',
            'fields.array' => 'Dodatna polja nisu u ispravnom obliku.',
        ])->validate();
    }

    private function category(int $id): Category
    {
        $category = $this->catalog->category($id);
        if (! $this->posting->isPostableCategory($category)) {
            throw ValidationException::withMessages(['category_id' => 'Izaberite kategoriju sa spiska (poslednji nivo).']);
        }

        return $category;
    }

    /** null = "Dogovor" (no price or price_negotiable). */
    private function price(array $data): ?float
    {
        if (! empty($data['price_negotiable']) || ! isset($data['price'])) {
            return null;
        }

        return (float) $data['price'];
    }

    /** Validated field values; `condition` fills the "stanje" field when the category has it. */
    private function fieldValues(Listing $listing, array $data, User $actor): array
    {
        $input = $this->posting->conditionToField($listing, $data['condition'] ?? null, (array) ($data['fields'] ?? []));
        $values = $this->fields->validate($listing->category_id, $input);
        $owner = User::query()->find($listing->user_id) ?? $actor;
        if (($values['stanje'] ?? null) === 'novo' && ! $this->posting->newConditionAllowed($owner)) {
            throw ValidationException::withMessages(['fields.stanje' => 'Stanje „Novo” mogu da izaberu samo firme. Izaberite drugo stanje.']);
        }

        return $values;
    }

    /**
     * The user's uploaded images (or ones already on this listing), for image_ids.
     *
     * @param  list<int>  $ids
     * @return Collection<int, ListingImage>
     */
    private function ownImages(User $user, array $ids, ?Listing $listing)
    {
        $images = ListingImage::query()->whereIn('id', $ids)->get();
        foreach ($ids as $id) {
            $image = $images->firstWhere('id', $id);
            $ok = $image && (($image->listing_id === null && $image->user_id === $user->id) || ($listing && $image->listing_id === $listing->id));
            if (! $ok) {
                throw ValidationException::withMessages(['image_ids' => 'Slika #'.$id.' ne postoji ili nije vaša.']);
            }
        }

        return $images;
    }

    /** Duplicates become 422 with the message on the field (title or image_ids). */
    private function refusalToValidation(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (PostingRefused $e) {
            if ($e->reason === 'limit_reached') {
                throw new ApiError('limit_reached', $e->getMessage(), 403);
            }
            $field = match ($e->field) {
                'images' => 'image_ids',
                'limit' => 'title',
                default => $e->field,
            };

            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }
    }

    private function statusQuery(int $userId, ?string $status): Builder
    {
        $query = Listing::query()->where('user_id', $userId)->where('status', '!=', ListingStatus::Draft);
        $now = now();

        return match ($status) {
            'pending' => $query->where('status', ListingStatus::Pending),
            'rejected' => $query->where('status', ListingStatus::Rejected),
            'sold' => $query->where('status', ListingStatus::Sold),
            'expired' => $query->where(fn ($q) => $q->where('status', ListingStatus::Expired)
                ->orWhere(fn ($q) => $q->where('status', ListingStatus::Published)->where('expires_at', '<=', $now))),
            'active' => $query->where('status', ListingStatus::Published)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', $now)),
            default => $query,
        };
    }
}
