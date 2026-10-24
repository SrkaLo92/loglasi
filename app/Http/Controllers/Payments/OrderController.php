<?php

namespace App\Http\Controllers\Payments;

use App\Enums\BuyerType;
use App\Enums\OrderStatus;
use App\Exceptions\OrderException;
use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\Order;
use App\Services\OrderService;
use App\Services\Payments\BuyerDetails;
use App\Services\Payments\ProductCatalog;
use App\Support\Payments\IpsQr;
use App\Support\Payments\PaymentDetails;
use App\Support\Payments\PaymentMode;
use App\Support\Payments\TaxId;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Ordering paid features on the website: choose → confirm (amount, buyer, consent) → order is
 * created → pro-forma with payment instructions and the IPS QR code. Included credits (firm
 * monthly promotions, bought bump packs) are used here without an order. Also "Moje narudžbine"
 * and cancelling.
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

    /** /moj-nalog/narudzbine/nova/?usluga=top_7&oglas=123: choose a listing, then confirm the amount. */
    public function create(Request $request): View
    {
        $product = $this->orders->product((string) $request->query('usluga', ''));
        abort_if($product === null, 404);

        $user = $request->user();
        $listing = null;
        $listings = collect();

        if (ProductCatalog::needsListing($product)) {
            $listingId = (string) $request->query('oglas', '');
            if ($listingId !== '') {
                abort_unless(ctype_digit($listingId), 404);
                $listing = Listing::query()->find((int) $listingId);
                abort_if($listing === null, 404);
                Gate::authorize('create', [Order::class, $listing]);
            } else {
                // No listing chosen yet (e.g. coming from the price list): pick one of the active ones.
                $listings = Listing::query()->where('user_id', $user->getKey())->active()
                    ->latest('published_at')->limit(100)->get(['id', 'title']);
            }
        }

        $needsChoice = ProductCatalog::needsListing($product) && $listing === null;
        $reason = $needsChoice ? null : $this->orders->unavailableReason($user, $product, $listing);

        return view('payments.create', [
            'product' => $product,
            'listing' => $listing,
            'listings' => $listings,
            'needsChoice' => $needsChoice,
            'reason' => $reason,
            'credit' => $needsChoice || $reason ? null : $this->orders->availableCredit($user, $product),
            'firm' => $user->firm,
            'privateEnabled' => PaymentMode::privatePaymentsEnabled(),
            'testMode' => PaymentMode::testMode(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $digits = fn (string $label, Closure $valid) => function (string $attribute, mixed $value, Closure $fail) use ($label, $valid) {
            if (! $valid(preg_replace('/\s+/', '', (string) $value))) {
                $fail($label);
            }
        };

        $data = $request->validate([
            'usluga' => ['required', 'string', Rule::in(array_keys((array) config('payments.catalog')))],
            'oglas' => [Rule::requiredIf(ProductCatalog::needsListing((string) $request->input('usluga'))), 'nullable', 'integer'],
            'kupac' => ['required', Rule::enum(BuyerType::class)],
            'naziv' => ['exclude_unless:kupac,firm', 'required', 'string', 'max:150'],
            'pib' => ['exclude_unless:kupac,firm', 'required', 'string', $digits('PIB nije ispravan (9 cifara).', TaxId::isValidPib(...))],
            'mb' => ['exclude_unless:kupac,firm', 'required', 'string', $digits('Matični broj nije ispravan (8 cifara).', TaxId::isValidMb(...))],
            'adresa' => ['exclude_unless:kupac,firm', 'required', 'string', 'max:200'],
            'mejl' => ['exclude_unless:kupac,firm', 'required', 'email', 'max:150'],
            'odmah' => ['exclude_unless:kupac,private', 'accepted'],
            'uslovi' => ['accepted'],
        ], [
            'oglas.required' => 'Izaberite oglas.',
            'kupac.required' => 'Izaberite da li plaćate kao fizičko lice ili kao firma.',
            'naziv.required' => 'Upišite naziv firme.',
            'pib.required' => 'Upišite PIB.',
            'mb.required' => 'Upišite matični broj.',
            'adresa.required' => 'Upišite adresu firme.',
            'mejl.required' => 'Upišite mejl za račun.',
            'mejl.email' => 'Upišite ispravan mejl za račun.',
            'odmah.accepted' => 'Potvrdite da želite da usluga počne odmah po uplati.',
            'uslovi.accepted' => 'Potvrdite da ste pročitali Uslove korišćenja i plaćanja.',
        ]);

        $listing = $this->listingFor($data['usluga'], $data['oglas'] ?? null);
        Gate::authorize('create', [Order::class, $listing]);

        $buyer = $data['kupac'] === BuyerType::Firm->value
            ? BuyerDetails::firm(strip_tags($data['naziv']), preg_replace('/\s+/', '', $data['pib']), preg_replace('/\s+/', '', $data['mb']),
                strip_tags($data['adresa']), $data['mejl'], $request->ip())
            : BuyerDetails::private($request->ip());

        try {
            $order = $this->orders->create($request->user(), $data['usluga'], $listing, $buyer);
        } catch (OrderException $e) {
            return back()->withInput()->withErrors(['order' => $e->getMessage()]);
        }

        return redirect()->to(url_slash('moj-nalog/narudzbine/'.$order->id))->with('status', $order->wasRecentlyCreated
            ? 'Porudžbina je primljena. Plati skeniranjem QR koda u aplikaciji svoje banke ili uplatom po podacima ispod. Obavezno upiši poziv na broj. Usluga se uključuje čim uplata bude potvrđena; račun stiže na mejl.'
            : 'Ova narudžbina već čeka uplatu. Uputstvo za uplatu je ispod.');
    }

    /** Uses an included promotion (firm monthly credit or a bought bump pack) for a listing. */
    public function useCredit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'usluga' => ['required', 'string', Rule::in(array_keys((array) config('payments.catalog')))],
            'oglas' => ['required', 'integer'],
        ]);
        $listing = $this->listingFor($data['usluga'], $data['oglas']);
        Gate::authorize('create', [Order::class, $listing]);

        try {
            $this->orders->useCredit($request->user(), $data['usluga'], $listing);
        } catch (OrderException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return redirect()->to($listing->url())->with('status', 'Usluga je uključena, bez plaćanja.');
    }

    /** Pro-forma with payment instructions (pending) or the order status. */
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

    private function listingFor(string $code, mixed $listingId): ?Listing
    {
        if (! ProductCatalog::needsListing($code)) {
            return null;
        }
        $listing = Listing::query()->find((int) $listingId);
        abort_if($listing === null, 404);

        return $listing;
    }
}
