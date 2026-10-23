<?php

namespace App\Http\Controllers\Posting;

use App\Enums\Currency;
use App\Enums\ListingStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Rules\PhoneNumber;
use App\Services\Catalog;
use App\Services\ListingFields;
use App\Services\ListingImages;
use App\Services\Posting;
use App\Services\PostingRefused;
use App\Support\ListingLimits;
use App\Support\Posting\Wizard;
use App\Support\Seo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * "Postavi oglas" wizard. Every step is a plain GET page with a POST/PUT form; the state
 * lives in a draft listing. The same steps edit a listing that was already submitted.
 */
class WizardController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private readonly Posting $posting,
        private readonly Catalog $catalog,
        private readonly ListingFields $fields,
        private readonly ListingImages $images,
    ) {}

    // ---------------------------------------------------------------- start

    /** /postavi-oglas/: limit, unfinished drafts, category choice. */
    public function start(Request $request): View
    {
        $user = $request->user();
        $limits = ListingLimits::for($user);
        $limitMessage = null;
        try {
            $this->posting->ensureCanCreate($user);
        } catch (PostingRefused $e) {
            $limitMessage = $e->getMessage();
        }

        $drafts = Listing::query()->where('user_id', $user->id)->where('status', ListingStatus::Draft)
            ->with('category')->latest('updated_at')->limit(Posting::MAX_DRAFTS)->get();

        $this->seo('Postavite oglas');

        return view('posting.start', [
            'limits' => $limits,
            'limitMessage' => $limitMessage,
            'drafts' => $drafts,
            'picker' => $this->picker($request),
            'action' => url_slash('postavi-oglas'),
            'listing' => null,
        ]);
    }

    /** Creates the draft in the chosen category. */
    public function create(Request $request): RedirectResponse
    {
        $category = $this->chosenCategory($request);

        try {
            $listing = $this->posting->createDraft($request->user(), $category);
        } catch (PostingRefused $e) {
            return redirect(url_slash('postavi-oglas'))->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect(Wizard::url($listing, 'basics'));
    }

    // ---------------------------------------------------------------- category

    public function category(Request $request, Listing $listing): View
    {
        $this->authorize('update', $listing);

        return $this->step('category', $listing, [
            'picker' => $this->picker($request, $listing),
            'action' => Wizard::url($listing, 'category'),
        ]);
    }

    public function updateCategory(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorize('update', $listing);
        $this->posting->changeCategory($listing, $this->chosenCategory($request));

        return redirect(Wizard::url($listing, Wizard::next($listing, 'category')))->with('status', 'Kategorija je sačuvana.');
    }

    // ---------------------------------------------------------------- basics

    public function basics(Listing $listing): View
    {
        $this->authorize('update', $listing);

        return $this->step('basics', $listing, [
            'locations' => $this->catalog->locations(),
            'currencies' => Currency::cases(),
        ]);
    }

    public function updateBasics(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorize('update', $listing);

        $negotiable = $request->input('price_type') === 'negotiable';
        $price = Posting::parsePrice($request->input('price'));
        $validator = Validator::make($request->all(), $this->posting->basicRules() + [
            'price_type' => ['nullable', 'in:fixed,negotiable'],
        ], $this->posting->basicMessages());
        $validator->after(function ($v) use ($negotiable, $price) {
            if ($negotiable) {
                return;
            }
            if ($price === null) {
                $v->errors()->add('price', 'Upišite cenu ili izaberite „Dogovor”.');
            } elseif ($price === false) {
                $v->errors()->add('price', 'Cena mora biti broj, npr. 8.900 ili 8900.');
            } elseif ($price < 0 || $price > Posting::PRICE_MAX) {
                $v->errors()->add('price', $price < 0 ? 'Cena ne može biti negativna.' : 'Cena je prevelika.');
            }
        });
        $data = $validator->validate();

        $title = Posting::cleanLine($data['title']);
        if (! Wizard::isEditing($listing) || $title !== $listing->title) {
            try {
                $this->posting->ensureNotDuplicate($listing, $title);
            } catch (PostingRefused $e) {
                return redirect(Wizard::url($listing, 'basics'))->withInput()->withErrors(['title' => $e->getMessage()])->with('duplicate', $this->duplicateLink($e));
            }
        }

        $before = [$listing->title, $listing->description];
        $this->posting->fillBasics($listing, $data, $negotiable ? null : $price);
        $listing->save();
        $this->posting->afterEdit($listing, $request->user(), $before !== [$listing->title, $listing->description]);

        return redirect(Wizard::url($listing, Wizard::next($listing, 'basics')))->with('status', 'Podaci su sačuvani.');
    }

    // ---------------------------------------------------------------- details

    public function details(Listing $listing): View|RedirectResponse
    {
        $this->authorize('update', $listing);
        $fields = $this->catalog->fieldsFor($listing->category_id);
        if ($fields->isEmpty()) {
            return redirect(Wizard::url($listing, 'images'));
        }

        return $this->step('details', $listing, [
            'categoryFields' => $fields,
            'values' => $this->posting->rawFieldValues($listing),
        ]);
    }

    public function updateDetails(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorize('update', $listing);
        $this->posting->saveFields($listing, (array) $request->input('fields', []), $request->user());

        return redirect(Wizard::url($listing, Wizard::next($listing, 'details')))->with('status', 'Detalji su sačuvani.');
    }

    // ---------------------------------------------------------------- images

    public function images(Listing $listing): View
    {
        $this->authorize('update', $listing);

        return $this->step('images', $listing, [
            'listingImages' => $listing->images()->get(),
            'maxImages' => $this->images->maxImages(),
            'maxMb' => $this->images->maxMegabytes(),
        ]);
    }

    /** Uploads one or more images (form field images[]); JSON for the page script. */
    public function uploadImages(Request $request, Listing $listing): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $listing);

        $files = $request->file('images', []);
        $files = is_array($files) ? array_values(array_filter($files)) : [$files];
        $errors = [];
        $saved = [];

        if ($files === []) {
            $errors[] = 'Izaberite bar jednu sliku.';
        }
        foreach ($files as $file) {
            try {
                $saved[] = $this->images->upload($file, $request->user(), $listing, 'images');
            } catch (ValidationException $e) {
                $errors[] = $file->getClientOriginalName().': '.collect($e->errors())->flatten()->first();
            }
        }
        if ($saved !== []) {
            $this->posting->afterEdit($listing, $request->user(), true);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'images' => array_map(fn (ListingImage $i) => $this->images->toApi($i), $saved),
                'errors' => $errors,
            ], $saved === [] && $errors !== [] ? 422 : 200);
        }

        $redirect = redirect(Wizard::url($listing, 'images'));
        if ($saved !== []) {
            $redirect->with('status', count($saved) === 1 ? 'Slika je dodata.' : 'Dodato slika: '.count($saved).'.');
        }

        return $errors === [] ? $redirect : $redirect->withErrors(['images' => $errors]);
    }

    public function reorderImages(Request $request, Listing $listing): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $listing);
        $data = $request->validate(['order' => ['required', 'array', 'max:100'], 'order.*' => ['integer']]);
        $this->images->reorder($listing, $data['order']);

        return $request->expectsJson()
            ? response()->json(['order' => $listing->images()->pluck('id')])
            : redirect(Wizard::url($listing, 'images'))->with('status', 'Redosled slika je sačuvan.');
    }

    public function coverImage(Listing $listing, ListingImage $image): RedirectResponse
    {
        $this->authorize('update', $listing);
        $this->images->setCover($listing, $image);

        return redirect(Wizard::url($listing, 'images'))->with('status', 'Naslovna slika je promenjena.');
    }

    public function moveImage(Request $request, Listing $listing, ListingImage $image): RedirectResponse
    {
        $this->authorize('update', $listing);
        $this->images->move($listing, $image, $request->input('direction') === 'up' ? -1 : 1);

        return redirect(Wizard::url($listing, 'images'))->with('status', 'Redosled slika je sačuvan.');
    }

    public function deleteImage(Request $request, Listing $listing, ListingImage $image): RedirectResponse|JsonResponse
    {
        $this->authorize('update', $listing);
        $this->images->delete($image);
        $this->posting->afterEdit($listing, $request->user(), true);

        return $request->expectsJson()
            ? response()->json(['deleted' => true])
            : redirect(Wizard::url($listing, 'images'))->with('status', 'Slika je obrisana.');
    }

    // ---------------------------------------------------------------- contact

    public function contact(Listing $listing): View
    {
        $this->authorize('update', $listing);
        $listing->loadMissing('user');

        return $this->step('contact', $listing, [
            'phone' => $this->posting->contactPhone($listing),
            'accountPhone' => $listing->user?->phone,
        ]);
    }

    public function updateContact(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorize('update', $listing);
        $request->validate(
            ['contact_phone' => ['nullable', 'string', 'max:30', new PhoneNumber]],
        );
        $this->posting->setContactPhone($listing, $request->input('contact_phone'));

        return redirect(Wizard::url($listing, 'preview'))->with('status', 'Kontakt je sačuvan.');
    }

    // ---------------------------------------------------------------- preview and submit

    public function preview(Listing $listing): View
    {
        $this->authorize('update', $listing);
        $listing->load(['images', 'fieldValues', 'location', 'category', 'user']);

        return $this->step('preview', $listing, [
            'missing' => $this->posting->missing($listing),
            'fieldValues' => $this->fields->values($listing),
            'phone' => $this->posting->contactPhone($listing),
            'needsReview' => $this->posting->needsReview($listing->user),
        ]);
    }

    public function submit(Request $request, Listing $listing): RedirectResponse
    {
        $this->authorize('update', $listing);

        try {
            $listing = $this->posting->submit($listing, $request->user());
        } catch (PostingRefused $e) {
            return redirect(Wizard::url($listing, 'preview'))->withErrors(['submit' => $e->getMessage()])->with('duplicate', $this->duplicateLink($e));
        } catch (ValidationException $e) {
            return redirect(Wizard::url($listing, 'preview'))->withErrors($e->errors());
        }

        $message = $listing->status === ListingStatus::Published
            ? 'Oglas je objavljen.'
            : 'Oglas je poslat na proveru. Biće vidljiv na sajtu čim ga odobrimo.';

        return redirect(url_slash('moj-nalog/oglasi').'?status='.($listing->status === ListingStatus::Published ? 'aktivni' : 'na-cekanju'))
            ->with('status', $message)
            ->with('posted', $listing->id);
    }

    // ---------------------------------------------------------------- helpers

    private function step(string $step, Listing $listing, array $data = []): View
    {
        $listing->loadMissing(['category', 'images']);
        $editing = Wizard::isEditing($listing);
        $heading = $editing ? 'Izmena oglasa' : 'Postavite oglas';
        $this->seo($heading.': '.Wizard::STEPS[$step][1], $heading);

        return view('posting.'.$step, $data + [
            'listing' => $listing,
            'step' => $step,
            'steps' => Wizard::steps($listing),
            'editing' => $editing,
            'next' => Wizard::next($listing, $step),
            'previous' => Wizard::previous($listing, $step),
            'breadcrumbs' => $this->catalog->breadcrumbs($listing->category_id),
            'hideMobileNav' => true, // the wizard has its own buttons at the bottom
        ]);
    }

    private function seo(string $title, ?string $h1 = null): void
    {
        Seo::get()->page($title)->h1($h1 ?? $title)->noindex();
    }

    /**
     * Category tree, one level at a time (?grupa=ID): works without JavaScript.
     *
     * @return array{parent: ?Category, trail: Collection, items: list<array{category: Category, leaf: bool}>}
     */
    private function picker(Request $request, ?Listing $listing = null): array
    {
        $parent = null;
        $groupId = $request->integer('grupa');
        if ($groupId > 0) {
            $candidate = $this->catalog->category($groupId);
            $parent = $candidate && $this->catalog->isVisible($candidate) ? $candidate : null;
        }

        $items = [];
        foreach ($this->catalog->children($parent) as $category) {
            $items[] = ['category' => $category, 'leaf' => $this->catalog->children($category)->isEmpty()];
        }

        return [
            'parent' => $parent,
            'trail' => $parent ? $this->catalog->breadcrumbs($parent) : collect(),
            'items' => $items,
            'current' => $listing?->category_id,
        ];
    }

    private function chosenCategory(Request $request): Category
    {
        $request->validate(['category_id' => ['required', 'integer']], [
            'category_id.required' => 'Izaberite kategoriju.',
            'category_id.integer' => 'Izaberite kategoriju sa spiska.',
        ]);
        $category = $this->catalog->category($request->integer('category_id'));
        if (! $this->posting->isPostableCategory($category)) {
            throw ValidationException::withMessages(['category_id' => 'Izaberite kategoriju sa spiska (poslednji nivo).']);
        }

        return $category;
    }

    /** @return array{title: string, url: string, renew: ?string}|null */
    private function duplicateLink(PostingRefused $e): ?array
    {
        if ($e->existing === null) {
            return null;
        }
        $existing = $e->existing;
        $public = $existing->status === ListingStatus::Published;

        return [
            'title' => $existing->title,
            'url' => $public ? url_slash('oglas/'.$existing->slug) : url_slash('postavi-oglas/'.$existing->id.'/pregled'),
            'renew' => $this->posting->canRenew($existing) ? url_slash('moj-nalog/oglasi/'.$existing->id.'/obnova') : null,
        ];
    }
}
