<?php

namespace App\Services;

use App\Enums\Currency;
use App\Enums\FieldType;
use App\Enums\ListingStatus;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\User;
use App\Support\ListingLimits;
use App\Support\Phone;
use App\Support\Posting\ListingSlug;
use App\Support\Settings;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Posting and managing listings: draft, basic data, fields, contact, submit
 * (limit, duplicates, moderation, slug, expiry), edit, renew, sold, deactivate, delete.
 * Used by the web wizard (/postavi-oglas/) and the app API (/me/listings).
 */
class Posting
{
    public const TITLE_MIN = 3;

    public const TITLE_MAX = 90;

    public const DESCRIPTION_MIN = 10;

    public const DESCRIPTION_MAX = 5000;

    public const PRICE_MAX = 99_999_999_999;

    /** Unfinished drafts a user may have at the same time. */
    public const MAX_DRAFTS = 5;

    /** Drafts untouched for this many days are removed. */
    public const DRAFT_LIFETIME_DAYS = 30;

    /** Accounts younger than this go to review when moderation = new_users. */
    public const NEW_USER_DAYS = 7;

    /** An active listing may be renewed this many days before it expires (link from the mail). */
    public const RENEW_WINDOW_DAYS = 3;

    public function __construct(
        private readonly Catalog $catalog,
        private readonly ListingFields $fields,
        private readonly ListingImages $images,
        private readonly DuplicateListings $duplicates,
    ) {}

    // ---------------------------------------------------------------- rules

    public function durationDays(): int
    {
        return max(1, (int) Settings::get('listing_duration_days', 30));
    }

    /** Whether a new or substantially changed listing of this user waits for approval. */
    public function needsReview(User $user): bool
    {
        if ($user->isStaff()) {
            return false;
        }

        return match ((string) Settings::get('moderation', 'new_users')) {
            'none' => false,
            'all' => true,
            default => $user->created_at === null
                || $user->created_at->gt(now()->subDays(self::NEW_USER_DAYS))
                || ! $user->isPhoneVerified(),
        };
    }

    /** A listing can only be in a visible category without subcategories. */
    public function isPostableCategory(?Category $category): bool
    {
        return $category !== null
            && $this->catalog->isVisible($category)
            && $this->catalog->children($category)->isEmpty();
    }

    /** "Novo" (new) only for firms, unless the setting new_condition_firms_only is turned off. */
    public function newConditionAllowed(User $user): bool
    {
        return $user->isFirm() || ! Settings::get('new_condition_firms_only', true);
    }

    /** @throws PostingRefused */
    public function ensureCanCreate(User $user): void
    {
        if (! ListingLimits::canCreate($user)) {
            $limits = ListingLimits::for($user);

            throw new PostingRefused('limit_reached', $user->isFirm()
                ? 'Imate '.$limits['monthly_limit'].' aktivnih oglasa, koliko dozvoljava vaš paket. Mesto se oslobađa kad oglas istekne ili ga obrišete.'
                : 'Imate već '.$limits['monthly_limit'].' aktivnih oglasa, koliko je besplatno za privatna lica. Mesto se oslobađa kad oglas istekne, prodate ga ili obrišete, a dodatna mesta možete kupiti.', field: 'limit');
        }
    }

    // ---------------------------------------------------------------- draft

    /** @throws PostingRefused */
    public function createDraft(User $user, Category $category, bool $limitDrafts = true): Listing
    {
        if (! $this->isPostableCategory($category)) {
            throw new PostingRefused('invalid_category', 'Izaberite kategoriju sa spiska (poslednji nivo).', field: 'category_id');
        }
        $this->ensureCanCreate($user);

        $drafts = Listing::query()->where('user_id', $user->id)->where('status', ListingStatus::Draft)->count();
        if ($limitDrafts && $drafts >= self::MAX_DRAFTS) {
            throw new PostingRefused('too_many_drafts', 'Imate '.$drafts.' nezavršenih oglasa. Završite ili obrišite neki od njih u delu „Moji oglasi”, pa započnite novi.', field: 'category_id');
        }

        return Listing::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'location_id' => $user->location_id,
            'title' => '',
            'slug' => ListingSlug::draft(),
            'currency' => Currency::EUR,
            'status' => ListingStatus::Draft,
        ]);
    }

    /** Changing the category keeps only the field values the new category also has. */
    public function changeCategory(Listing $listing, Category $category): void
    {
        if ($listing->category_id === $category->id) {
            return;
        }
        $values = $this->rawFieldValues($listing);
        $listing->category_id = $category->id;
        $listing->save();
        $this->fields->save($listing, $this->fields->normalize($category, $values));
    }

    // ---------------------------------------------------------------- basic data

    /**
     * Rules for title, description, price, currency, location. The web form sends price
     * as text ("8.900") and price_type fixed|negotiable; the API sends a number and price_negotiable.
     *
     * @return array<string, list<mixed>>
     */
    public function basicRules(bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return [
            'title' => [$req, 'string', 'min:'.self::TITLE_MIN, 'max:'.self::TITLE_MAX],
            'description' => [$req, 'string', 'min:'.self::DESCRIPTION_MIN, 'max:'.self::DESCRIPTION_MAX],
            'location_id' => [$req, 'integer', Rule::exists('locations', 'id')],
            'currency' => ['nullable', Rule::enum(Currency::class)],
        ];
    }

    /** @return array<string, string> */
    public function basicMessages(): array
    {
        return [
            'title.required' => 'Upišite naslov oglasa.',
            'title.min' => 'Naslov mora imati bar '.self::TITLE_MIN.' znaka.',
            'title.max' => 'Naslov može imati najviše '.self::TITLE_MAX.' znakova.',
            'description.required' => 'Upišite opis oglasa.',
            'description.min' => 'Opis mora imati bar '.self::DESCRIPTION_MIN.' znakova.',
            'description.max' => 'Opis može imati najviše '.self::DESCRIPTION_MAX.' znakova.',
            'location_id.required' => 'Izaberite mesto.',
            'location_id.integer' => 'Izaberite mesto sa spiska.',
            'location_id.exists' => 'Izaberite mesto sa spiska.',
            'currency.enum' => 'Valuta može biti evro (EUR) ili dinar (RSD).',
            'price.numeric' => 'Cena mora biti broj, npr. 8900.',
            'price.integer' => 'Cena mora biti ceo broj.',
            'price.min' => 'Cena ne može biti negativna.',
            'price.max' => 'Cena je prevelika.',
            'price.required' => 'Upišite cenu ili izaberite „Dogovor”.',
        ];
    }

    /** "8.900" → 8900, "1.250,50" → 1250.5, "12 500" → 12500; null when empty, false when not a number. */
    public static function parsePrice(mixed $raw): float|false|null
    {
        if ($raw === null || (is_string($raw) && trim($raw) === '')) {
            return null;
        }
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        $value = preg_replace('/[\s\x{00A0}€]+|din\.?|rsd|eur/iu', '', (string) $raw);
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $value)) {
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? (float) $value : false;
    }

    /**
     * Saves validated basic data. Plain text only: tags are dropped.
     *
     * @param  array{title?: string, description?: string, location_id?: int, currency?: ?string}  $data
     */
    public function fillBasics(Listing $listing, array $data, float|false|null $price, bool $setPrice = true): void
    {
        if (array_key_exists('title', $data)) {
            $listing->title = static::cleanLine((string) $data['title']);
        }
        if (array_key_exists('description', $data)) {
            $listing->description = static::cleanText((string) $data['description']);
        }
        if (array_key_exists('location_id', $data)) {
            $listing->location_id = (int) $data['location_id'];
        }
        if (! empty($data['currency'])) {
            $listing->currency = Currency::from($data['currency']);
        }
        if ($setPrice) {
            $listing->price = $price === false ? null : $price;
        }
    }

    public static function cleanLine(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', strip_tags($value)));
    }

    public static function cleanText(string $value): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", strip_tags($value));
        $value = (string) preg_replace('/[ \t]+\n/', "\n", $value);

        return trim((string) preg_replace("/\n{3,}/", "\n\n", $value));
    }

    // ---------------------------------------------------------------- fields

    /**
     * Field values as stored, in the input shape: [key => value | list | true].
     *
     * @return array<string, string|list<string>|bool>
     */
    public function rawFieldValues(Listing $listing): array
    {
        $listing->loadMissing('fieldValues');
        $byField = $listing->fieldValues->groupBy('field_id');
        $out = [];
        foreach ($this->catalog->fieldsFor($listing->category_id) as $field) {
            $rows = $byField->get($field->id);
            if ($rows === null || $rows->isEmpty()) {
                continue;
            }
            $values = $rows->pluck('value')->filter(fn ($v) => $v !== null && $v !== '')->values()->all();
            $out[$field->key] = match ($field->type) {
                FieldType::Multiselect => $values,
                FieldType::Checkbox => true,
                default => (string) ($values[0] ?? ''),
            };
        }

        return $out;
    }

    /**
     * Checks and saves the category fields; "Novo" for private users when the firm-only rule is on.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function saveFields(Listing $listing, array $input, User $actor, string $prefix = 'fields'): void
    {
        $values = $this->fields->validate($listing->category_id, $input, $prefix);
        $this->checkCondition($listing, $values, $prefix);
        $this->fields->save($listing, $values);
        $listing->touch();
    }

    private function checkCondition(Listing $listing, array $values, string $prefix): void
    {
        $owner = $listing->user ?? User::query()->find($listing->user_id);
        if (($values['stanje'] ?? null) === 'novo' && $owner && ! $this->newConditionAllowed($owner)) {
            throw ValidationException::withMessages([$prefix.'.stanje' => 'Stanje „Novo” mogu da izaberu samo firme. Izaberite drugo stanje.']);
        }
    }

    /** Maps the app's condition (novo|polovno) to the "stanje" field when the category has it. */
    public function conditionToField(Listing $listing, ?string $condition, array $fields): array
    {
        if ($condition === null || array_key_exists('stanje', $fields)) {
            return $fields;
        }
        $field = $this->catalog->fieldsFor($listing->category_id)->first(fn ($f) => $f->key === 'stanje');
        $options = collect((array) $field?->options)->map(fn ($o) => is_array($o) ? (string) ($o['value'] ?? '') : (string) $o)->all();
        if ($field !== null && in_array($condition, $options, true)) {
            $fields['stanje'] = $condition;
        }

        return $fields;
    }

    // ---------------------------------------------------------------- contact

    /** Saves the contact phone in E.164; the account phone (or empty) is stored as null = "use the account phone". */
    public function setContactPhone(Listing $listing, ?string $raw): void
    {
        $phone = Phone::normalize($raw);
        $owner = $listing->user ?? User::query()->find($listing->user_id);
        $listing->contact_phone = ($phone === '' || $phone === $owner?->phone) ? null : $phone;
        $listing->save();
    }

    public function contactPhone(Listing $listing): ?string
    {
        return $listing->contact_phone ?: ($listing->user?->phone ?: null);
    }

    // ---------------------------------------------------------------- submit

    /**
     * What is still missing before the listing can be submitted: [step => message].
     *
     * @return array<string, string>
     */
    public function missing(Listing $listing): array
    {
        $missing = [];
        if (! $this->isPostableCategory($this->catalog->category($listing->category_id))) {
            $missing['category'] = 'Izaberite kategoriju.';
        }
        if (trim((string) $listing->title) === '' || trim((string) $listing->description) === '' || $listing->location_id === null) {
            $missing['basics'] = 'Upišite naslov, opis i mesto.';
        }
        if (! isset($missing['category'])) {
            try {
                $this->fields->validate($listing->category_id, $this->rawFieldValues($listing));
            } catch (ValidationException) {
                $missing['details'] = 'Popunite obavezna polja kategorije.';
            }
        }

        return $missing;
    }

    /**
     * Submits a draft (or a rejected listing after changes): limit, duplicates, moderation,
     * slug, expiry. Returns the listing with its new status (pending or published).
     *
     * @throws PostingRefused
     * @throws ValidationException when something is missing (key = wizard step)
     */
    public function submit(Listing $listing, User $actor): Listing
    {
        if (! in_array($listing->status, [ListingStatus::Draft, ListingStatus::Rejected], true)) {
            return $listing;
        }

        $missing = $this->missing($listing);
        if ($missing !== []) {
            throw ValidationException::withMessages($missing);
        }

        $owner = $listing->user ?? User::query()->findOrFail($listing->user_id);
        $isDraft = $listing->status === ListingStatus::Draft;
        if ($isDraft) {
            $this->ensureCanCreate($owner);
        }
        $this->ensureNotDuplicate($listing);

        return DB::transaction(function () use ($listing, $owner, $actor, $isDraft) {
            if ($isDraft) {
                // the monthly limit counts from the moment of submitting
                $listing->created_at = now();
                $listing->slug = ListingSlug::unique($listing->title, $listing->id);
            }
            $listing->rejection_reason = null;

            if ($this->needsReview($actor->isStaff() ? $actor : $owner)) {
                $listing->status = ListingStatus::Pending;
                $listing->expires_at = now()->addDays($this->durationDays());
                $listing->save();
            } else {
                $this->activate($listing);
            }

            return $listing;
        });
    }

    /** @throws PostingRefused */
    public function ensureNotDuplicate(Listing $listing, ?string $title = null, ?int $categoryId = null): void
    {
        $same = $this->duplicates->similarTitle($listing, $title, $categoryId);
        if ($same !== null) {
            throw new PostingRefused('duplicate', $this->duplicateMessage($same), $same, 'title');
        }

        if ($listing->exists && ($same = $this->duplicates->sameImage($listing)) !== null) {
            throw $this->duplicateImage($same);
        }
    }

    /**
     * Images about to be attached (app: image_ids) already used in another listing of the user.
     *
     * @param  iterable<ListingImage>  $images
     *
     * @throws PostingRefused
     */
    public function ensureImagesNotDuplicate(Listing $listing, iterable $images): void
    {
        $same = $this->duplicates->sameImageAs((int) $listing->user_id, $images, $listing->exists ? $listing->id : null);
        if ($same !== null) {
            throw $this->duplicateImage($same);
        }
    }

    private function duplicateImage(Listing $same): PostingRefused
    {
        return new PostingRefused('duplicate_image', 'Ista slika već postoji u vašem oglasu „'.$same->title.'”. Isti oglas se ne postavlja ponovo; izmenite ili obnovite postojeći.', $same, 'images');
    }

    private function duplicateMessage(Listing $same): string
    {
        return match ($same->status) {
            ListingStatus::Expired => 'Već ste postavili sličan oglas u ovoj kategoriji: „'.$same->title.'”. Oglas je istekao, obnovite ga besplatno umesto da postavljate novi.',
            ListingStatus::Pending => 'Već ste postavili sličan oglas u ovoj kategoriji: „'.$same->title.'”. Taj oglas čeka odobrenje.',
            default => 'Već ste postavili sličan oglas u ovoj kategoriji: „'.$same->title.'”. Isti oglas se ne postavlja dva puta; izmenite postojeći.',
        };
    }

    /**
     * Makes the listing public now for listing_duration_days days.
     * The admin module calls this when it approves a pending listing.
     */
    public function activate(Listing $listing): void
    {
        $listing->status = ListingStatus::Published;
        $listing->published_at ??= now();
        $listing->bumped_at ??= now();
        $listing->expires_at = now()->addDays($this->durationDays());
        $listing->expiry_notified_at = null;
        $listing->rejection_reason = null;
        $listing->save();
    }

    /**
     * After an edit of a submitted listing: a substantial change (title, description, images)
     * of a public listing sends it back to review when the owner's listings are moderated.
     */
    public function afterEdit(Listing $listing, User $actor, bool $substantial): void
    {
        if (! $substantial || $listing->status !== ListingStatus::Published) {
            return;
        }
        $owner = $listing->user ?? User::query()->find($listing->user_id);
        if (! $actor->isStaff() && $owner && $this->needsReview($owner)) {
            $listing->status = ListingStatus::Pending;
            $listing->save();
        }
    }

    // ---------------------------------------------------------------- my listings

    public function isExpired(Listing $listing): bool
    {
        return $listing->status === ListingStatus::Expired
            || ($listing->status === ListingStatus::Published && $listing->expires_at !== null && $listing->expires_at->isPast());
    }

    public function canRenew(Listing $listing): bool
    {
        if (in_array($listing->status, [ListingStatus::Expired, ListingStatus::Sold], true)) {
            return true;
        }

        return $listing->status === ListingStatus::Published
            && ($listing->expires_at === null || $listing->expires_at->lte(now()->addDays(self::RENEW_WINDOW_DAYS)));
    }

    /** Free renewal: active again for listing_duration_days days (counted from the old expiry when it has not passed). */
    public function renew(Listing $listing): bool
    {
        if (! $this->canRenew($listing)) {
            return false;
        }

        $from = $listing->status === ListingStatus::Published && $listing->expires_at?->isFuture()
            ? $listing->expires_at
            : now();
        $listing->status = ListingStatus::Published;
        $listing->published_at ??= now();
        $listing->expires_at = $from->copy()->addDays($this->durationDays());
        $listing->expiry_notified_at = null;
        $listing->save();

        return true;
    }

    public function markSold(Listing $listing): bool
    {
        if ($listing->status !== ListingStatus::Published) {
            return false;
        }
        $listing->status = ListingStatus::Sold;
        $listing->save();

        return true;
    }

    /** Takes an active listing off the site; it can be renewed later. */
    public function deactivate(Listing $listing): bool
    {
        if ($listing->status !== ListingStatus::Published) {
            return false;
        }
        $listing->status = ListingStatus::Expired;
        $listing->expires_at = now();
        $listing->save();

        return true;
    }

    /** Soft delete; a draft is removed for good together with its images. */
    public function delete(Listing $listing): void
    {
        if ($listing->status === ListingStatus::Draft) {
            foreach ($listing->images()->get() as $image) {
                $this->images->delete($image);
            }
            $listing->forceDelete();

            return;
        }
        $listing->delete();
    }

    /** Removes drafts nobody touched for DRAFT_LIFETIME_DAYS days. */
    public function pruneDrafts(): int
    {
        $count = 0;
        Listing::query()->where('status', ListingStatus::Draft)
            ->where('updated_at', '<', now()->subDays(self::DRAFT_LIFETIME_DAYS))
            ->chunkById(100, function ($drafts) use (&$count) {
                foreach ($drafts as $draft) {
                    $this->delete($draft);
                    $count++;
                }
            });

        return $count;
    }

    public function expiresLabel(?CarbonInterface $date): string
    {
        return $date ? $date->copy()->timezone(config('app.timezone'))->format('j. n. Y.') : '';
    }

    /** Validator for the basic data with Serbian messages. */
    public function validateBasics(array $input, bool $partial = false): array
    {
        return Validator::make($input, $this->basicRules($partial), $this->basicMessages())->validate();
    }
}
