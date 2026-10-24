<?php

namespace App\Http\Controllers\Admin\Api;

use App\Enums\AccountType;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Http\Api\ApiError;
use App\Http\Api\ApiResponse;
use App\Http\Api\ListingFormatter;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Order;
use App\Models\User;
use App\Services\Admin\AdminStats;
use App\Services\Admin\ListingModeration;
use App\Services\Admin\OrderAdministration;
use App\Services\Admin\UserAdministration;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

/** Admin routes of the mobile app (/api/v1/admin/*). Same services as the admin panel. */
class AdminApiController extends Controller
{
    private const PER_PAGE = 20;

    /** Contract status => database status. "sold" does not exist yet: empty list. */
    private const LISTING_STATUS = [
        'pending' => ListingStatus::Pending,
        'active' => ListingStatus::Published,
        'rejected' => ListingStatus::Rejected,
        'expired' => ListingStatus::Expired,
    ];

    public function listings(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:pending,active,rejected,expired,sold'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ], [
            'status.in' => 'Status mora biti pending, active, rejected, expired ili sold.',
            'page.*' => 'Strana mora biti pozitivan ceo broj.',
        ]);

        $key = $data['status'] ?? 'pending';
        $status = self::LISTING_STATUS[$key] ?? null;
        $page = (int) ($data['page'] ?? 1);

        if ($status === null) {
            return ApiResponse::json($request, ['items' => [], 'total' => 0], ApiResponse::CACHE_PRIVATE);
        }

        $query = Listing::query()->where('status', $status);
        // Pending: oldest first (moderation queue); otherwise newest first.
        $query->orderBy('created_at', $status === ListingStatus::Pending ? 'asc' : 'desc')->orderBy('id', $status === ListingStatus::Pending ? 'asc' : 'desc');

        $total = $status === ListingStatus::Pending
            ? (clone $query)->count()
            : Cache::remember('admin.api.listings.total.'.$status->value, 60, fn () => (clone $query)->count());

        $items = $query->with([
            'user' => fn ($q) => $q->withCount(['listings as active_listings_count' => fn ($l) => $l->where('status', ListingStatus::Published)]),
            'user.firm', 'category', 'location', 'images', 'fieldValues',
        ])
            ->forPage($page, self::PER_PAGE)
            ->get()
            ->map(fn (Listing $l) => ListingFormatter::detail($l, $request->user()))
            ->all();

        return ApiResponse::json($request, ['items' => $items, 'total' => $total], ApiResponse::CACHE_PRIVATE);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $listing = $this->listing($id);
        app(ListingModeration::class)->approve($listing);

        return ApiResponse::json($request, ListingFormatter::detail($listing->fresh(['user.firm', 'category', 'location', 'images', 'fieldValues']), $request->user()), ApiResponse::CACHE_PRIVATE);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:'.ListingModeration::REASON_MAX],
        ], [
            'reason.required' => 'Upišite razlog odbijanja.',
            'reason.max' => 'Razlog može imati najviše '.ListingModeration::REASON_MAX.' znakova.',
        ]);

        $listing = $this->listing($id);
        $this->domain(fn () => app(ListingModeration::class)->reject($listing, $data['reason']));

        return ApiResponse::json($request, ListingFormatter::detail($listing->fresh(['user.firm', 'category', 'location', 'images', 'fieldValues']), $request->user()), ApiResponse::CACHE_PRIVATE);
    }

    public function users(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'filter' => ['sometimes', 'string', 'in:phone_unverified,firms,all'],
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ], [
            'filter.in' => 'Filter mora biti phone_unverified, firms ili all.',
            'q.max' => 'Pretraga može imati najviše 100 znakova.',
            'page.*' => 'Strana mora biti pozitivan ceo broj.',
        ]);

        $query = User::query()->withCount(['listings as active_listings_count' => fn ($q) => $q->where('status', ListingStatus::Published)]);

        match ($data['filter'] ?? 'phone_unverified') {
            'phone_unverified' => $query->whereNotNull('phone')->where('phone', '!=', '')->whereNull('phone_verified_at')->orderBy('created_at'),
            'firms' => $query->where('account_type', AccountType::Firm)->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };

        $term = trim((string) ($data['q'] ?? ''));
        if ($term !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term).'%';
            $digits = ltrim((string) preg_replace('/\D+/', '', $term), '0');
            $query->where(function ($w) use ($like, $digits) {
                $w->where('name', 'like', $like)->orWhere('email', 'like', $like);
                if (strlen($digits) >= 3) {
                    $w->orWhere('phone', 'like', '%'.$digits.'%');
                }
            });
        }

        $users = $query->orderBy('id')->forPage((int) ($data['page'] ?? 1), self::PER_PAGE)->get();

        return ApiResponse::json($request, $users->map(fn (User $u) => AdminFormatter::user($u))->all(), ApiResponse::CACHE_PRIVATE);
    }

    public function verifyPhone(Request $request, int $id): JsonResponse
    {
        $this->adminOnly($request);
        $user = User::query()->find($id) ?? throw ApiError::notFound('Korisnik ne postoji.');
        Gate::authorize('verifyPhone', $user);

        try {
            app(UserAdministration::class)->verifyPhone($user);
        } catch (DomainException $e) {
            throw new ApiError('no_phone', $e->getMessage(), 422);
        }

        $user->loadCount(['listings as active_listings_count' => fn ($q) => $q->where('status', ListingStatus::Published)]);

        return ApiResponse::json($request, AdminFormatter::user($user), ApiResponse::CACHE_PRIVATE);
    }

    public function orders(Request $request): Response|JsonResponse
    {
        $this->adminOnly($request);
        $data = $request->validate([
            'status' => ['sometimes', 'string', 'in:awaiting_payment,paid,cancelled'],
            'page' => ['sometimes', 'integer', 'min:1', 'max:10000'],
        ], [
            'status.in' => 'Status mora biti awaiting_payment, paid ili cancelled.',
            'page.*' => 'Strana mora biti pozitivan ceo broj.',
        ]);

        $statuses = match ($data['status'] ?? 'awaiting_payment') {
            'paid' => [OrderStatus::Paid->value, OrderStatus::Activated->value],
            'cancelled' => [OrderStatus::Cancelled->value, OrderStatus::Refunded->value],
            default => [OrderStatus::Pending->value],
        };

        $orders = Order::query()
            ->whereIn('status', $statuses)
            ->with(['user', 'product'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->forPage((int) ($data['page'] ?? 1), 50)
            ->get();

        return ApiResponse::json($request, $orders->map(fn (Order $o) => AdminFormatter::order($o))->all(), ApiResponse::CACHE_PRIVATE);
    }

    public function markPaid(Request $request, int $id): JsonResponse
    {
        $this->adminOnly($request);
        $data = $request->validate([
            'receipt_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ], [
            'receipt_number.*' => 'Broj računa može imati najviše 64 znaka.',
            'note.*' => 'Napomena može imati najviše 1000 znakova.',
        ]);

        $order = Order::query()->find($id) ?? throw ApiError::notFound('Narudžbina ne postoji.');

        try {
            app(OrderAdministration::class)->markPaid($order, $request->user(), $data['receipt_number'] ?? null, note: $data['note'] ?? null);
        } catch (DomainException|OrderException $e) {
            throw new ApiError('order_not_payable', $e->getMessage(), 409);
        }

        return ApiResponse::json($request, AdminFormatter::order($order->fresh(['user', 'product'])), ApiResponse::CACHE_PRIVATE);
    }

    public function stats(Request $request): Response|JsonResponse
    {
        $data = $request->validate([
            'period' => ['sometimes', 'string', 'in:today,7d,30d'],
        ], ['period.in' => 'Period mora biti today, 7d ili 30d.']);

        return ApiResponse::json($request, app(AdminStats::class)->api($data['period'] ?? '7d'), ApiResponse::CACHE_PRIVATE);
    }

    /** Moderators work on listings only; accounts and money are for admins. */
    private function adminOnly(Request $request): void
    {
        if (! $request->user()?->isAdmin()) {
            throw ApiError::forbidden('Ovo može samo administrator.');
        }
    }

    private function listing(int $id): Listing
    {
        return Listing::query()->find($id) ?? throw ApiError::notFound('Oglas ne postoji.');
    }

    private function domain(callable $callback): mixed
    {
        try {
            return $callback();
        } catch (DomainException $e) {
            throw new ApiError('invalid_action', $e->getMessage(), 422);
        }
    }
}
