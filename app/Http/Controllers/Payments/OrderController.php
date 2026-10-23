<?php

namespace App\Http\Controllers\Payments;

use App\Enums\BuyerType;
use App\Enums\ListingStatus;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\Payments\BuyerDetails;
use App\Support\Payments\IpsQr;
use App\Support\Payments\PaymentDetails;
use App\Support\Payments\TaxId;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ordering paid features on the website: choose → confirm (amount) → order is created →
 * payment instructions with the IPS QR code. Also "Moje narudžbine" and cancelling.
 */
class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    /** /moj-nalog/narudzbine/ */
    public function index(Request $request): View
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->getKey())
            ->with(['product:id,code,name', 'listing:id,title,slug,deleted_at'])
            ->latest('created_at')->latest('id')
            ->simplePaginate(20)
            ->withPath(url_slash('moj-nalog/narudzbine'));

        return view('payments.index', ['orders' => $orders]);
    }

    /** /moj-nalog/narudzbine/nova/?usluga=featured_7&oglas=123 — choose a listing and confirm the amount. */
    public function create(Request $request): View
    {
        $product = $this->orders->product((string) $request->query('usluga', ''));
        abort_if($product === null, 404);

        $user = $request->user();
        $listing = null;
        $listings = collect();

        if (OrderService::needsListing($product)) {
            $listingId = $request->query('oglas');
            if ($listingId !== null && $listingId !== '') {
                abort_unless(ctype_digit((string) $listingId), 404);
                $listing = Listing::query()->find((int) $listingId);
                abort_if($listing === null, 404);
                Gate::authorize('create', [Order::class, $listing]);
            } else {
                // No listing chosen yet (e.g. coming from the price list): pick one of the active ones.
                $listings = Listing::query()
                    ->where('user_id', $user->getKey())
                    ->where('status', ListingStatus::Published)
                    ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                    ->latest('published_at')
                    ->limit(100)
                    ->get(['id', 'title']);
            }
        }

        $needsChoice = OrderService::needsListing($product) && $listing === null;

        return view('payments.create', [
            'product' => $product,
            'listing' => $listing,
            'listings' => $listings,
            'needsChoice' => $needsChoice,
            'reason' => $needsChoice ? null : $this->orders->unavailableReason($user, $product, $listing),
            'firm' => $user->firm,
            'privateEnabled' => OrderService::privateBuyersEnabled(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'usluga' => ['required', 'string', Rule::in([...OrderService::LISTING_PRODUCTS, ...OrderService::ACCOUNT_PRODUCTS])],
            'oglas' => [Rule::requiredIf(OrderService::needsListing((string) $request->input('usluga'))), 'nullable', 'integer'],
            'kupac' => ['required', Rule::enum(BuyerType::class)],
            'naziv' => ['exclude_unless:kupac,firm', 'required', 'string', 'max:150'],
            'pib' => ['exclude_unless:kupac,firm', 'required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (! TaxId::isValidPib(preg_replace('/\s+/', '', (string) $value))) {
                    $fail('PIB nije ispravan (9 cifara).');
                }
            }],
            'mb' => ['exclude_unless:kupac,firm', 'required', 'string', function (string $attribute, mixed $value, \Closure $fail) {
                if (! TaxId::isValidMb(preg_replace('/\s+/', '', (string) $value))) {
                    $fail('Matični broj nije ispravan (8 cifara).');
                }
            }],
            'saglasnost' => ['accepted'],
        ], [
            'oglas.required' => 'Izaberite oglas.',
            'kupac.required' => 'Izaberite da li plaćate kao fizičko lice ili kao firma.',
            'naziv.required' => 'Upišite naziv firme.',
            'pib.required' => 'Upišite PIB.',
            'mb.required' => 'Upišite matični broj.',
            'saglasnost.accepted' => 'Potvrdite da usluga počinje odmah po uplati.',
        ]);

        $listing = null;
        if (OrderService::needsListing($data['usluga'])) {
            $listing = Listing::query()->find((int) $data['oglas']);
            abort_if($listing === null, 404);
        }
        Gate::authorize('create', [Order::class, $listing]);

        $buyer = $data['kupac'] === BuyerType::Firm->value
            ? BuyerDetails::firm(
                strip_tags($data['naziv']),
                preg_replace('/\s+/', '', $data['pib']),
                preg_replace('/\s+/', '', $data['mb']),
                $request->ip(),
            )
            : BuyerDetails::private($request->ip());

        try {
            $order = $this->orders->create($request->user(), $data['usluga'], $listing, $buyer);
        } catch (OrderException $e) {
            return back()->withInput()->withErrors(['order' => $e->getMessage()]);
        }

        return redirect()->to(url_slash('moj-nalog/narudzbine/'.$order->id))->with('status', $order->wasRecentlyCreated
            ? 'Narudžbina je primljena. Uputstvo za uplatu smo poslali i na vašu e-poštu.'
            : 'Ova narudžbina već čeka uplatu. Uputstvo za uplatu je ispod.');
    }

    /** Payment instructions (pending) or the order status. */
    public function show(Request $request, Order $order): View
    {
        Gate::authorize('view', $order);
        $order->load(['user:id,name', 'product:id,code,name,duration_days', 'listing:id,title,slug,deleted_at,featured_until,bumped_at']);

        $details = PaymentDetails::forOrder($order);
        $payload = $order->status === OrderStatus::Pending ? $details->ipsPayload() : null;

        return view('payments.show', [
            'order' => $order,
            'details' => $details,
            'qrSvg' => $payload !== null ? IpsQr::svg($payload) : null,
            'expiresAt' => OrderService::expiresAt($order),
        ]);
    }

    public function cancel(Request $request, Order $order): RedirectResponse
    {
        Gate::authorize('view', $order);
        if (! $order->isPending()) {
            return redirect()->to(url_slash('moj-nalog/narudzbine/'.$order->id))
                ->withErrors(['order' => 'Ova narudžbina se više ne može otkazati.']);
        }
        Gate::authorize('cancel', $order);

        $this->orders->cancel($order, $request->user());

        return redirect()->to(url_slash('moj-nalog/narudzbine/'.$order->id))->with('status', 'Narudžbina je otkazana.');
    }
}
