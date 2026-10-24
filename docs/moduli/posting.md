# Posting module

Posting a listing (`/postavi-oglas/`), "Moji oglasi" (`/moj-nalog/oglasi/`), the image pipeline, expiry and the mobile API for listings owned by the user.

## Pieces

| Area | Class / file |
| --- | --- |
| Rules, drafts, submit, renew, sold, delete | `App\Services\Posting` (refusals throw `PostingRefused`) |
| Duplicate detection (title + image hash) | `App\Services\DuplicateListings` |
| Images: upload, variants, order, cover, delete | `App\Services\ListingImages`, job `GenerateImageVariants` |
| Slugs | `App\Support\Posting\ListingSlug` |
| Wizard steps | `App\Support\Posting\Wizard`, `Posting\WizardController` |
| My listings (web) | `Posting\MyListingsController`, views `resources/views/posting/` |
| API | `Posting\Api\MediaController`, `Posting\Api\MyListingsController`, `routes/api/posting.php` |
| Policies | `ListingPolicy::update/delete/renew/manage` (owner or staff) |
| Expiry mail | `App\Mail\ListingExpiringSoon` (queued) |

## Wizard

The wizard works without JS; `public/js/posting.js` only adds upload progress and drag reordering.

The steps are category, basics (title, description, price EUR/RSD/Dogovor, place), details (category fields, skipped when the category has none), images, contact phone and preview, followed by publish. The first step creates a draft (`status=draft`, slug `draft-…`). Users can have at most 5 drafts, and drafts are pruned after 30 days.

Submitting a listing does the following:

- Checks the monthly limit (`ListingLimits`, drafts are not counted) and duplicates.
- Generates a transliterated unique slug (`Str::slug(..., 'sr')`, `-2` suffixes, trashed rows included). The slug never changes after that.
- Sets `expires_at` from the `listing_duration_days` setting.
- Applies moderation (setting `moderation`: `all|new_users|none`; new users are those under 7 days old; staff skip moderation).

Editing a published listing keeps its slug. A substantial edit goes back to review when moderation applies.

The "new condition only for firms" rule is behind the `new_condition_firms_only` setting, which is off by default.

## Images

- Accepted formats are JPEG, PNG and WebP, and the format is checked from the content. HEIC is refused with a Serbian message.
- Images are re-encoded, which strips EXIF after applying the orientation.
- Other limits:
  - longest edge capped at 2560 px;
  - 20 images per listing (setting `listing_max_images`);
  - size from setting `image_max_mb`.
- Storage is the public disk: `oglasi/{listing_id|tmp}/`. Temporary uploads are moved when the image is attached.
- Variants are generated on the queue, in the shape `{"sm":{"webp","jpg","width":400},"md":{…800},"lg":{…1600}}`.
- `sha1` and a dHash are stored for duplicate checks.
- Commands:
  - `images:variants` fills missing variants;
  - `images:prune` removes unattached temporary uploads older than 24 h.

## Expiry

`listings:expire` runs hourly and does the following:

- Expires listings in batches of 1000 on `(status, expires_at)`.
- Calls `SearchIndex::syncIds()` per batch, because the batch update bypasses model events.
- Flushes catalog counts.
- Queues a mail 3 days before expiry, with a renew link. The mail is sent once (`expiry_notified_at`) and respects the user's `expiring` notification setting.

Renew adds 30 days and is allowed within 3 days of expiry or after it.

## API

The endpoints are `POST /media`, `GET/POST /me/listings`, `GET/PATCH/DELETE /me/listings/{id}` and `POST /me/listings/{id}/renew`, as in `openapi.yaml`.

Errors:

- A limit hit returns 403 `limit_reached`.
- Duplicates and validation errors return 422 `validation_failed`.
- A listing that can't be renewed returns 409 `not_renewable`.

## Rate limits

- `posting-write`: 40/min.
- `posting-create`: 5/min and 30/h.
- `posting-upload`: 60/min and 300/h.

## Indexes (migration `2026_10_26_000100`)

- `listing_images`: `hash`, `phash`, `(user_id, created_at)`.
- `listings`:
  - `(status, expires_at)` for expiry;
  - `(user_id, category_id, created_at)` for duplicate titles and the monthly limit;
  - `(user_id, status, updated_at)` for the "my listings" tabs.

All per-user queries are bounded by `user_id`, so they stay cheap at 100–200k listings. Expiry is chunked, and no query uses a leading-wildcard LIKE.

## Shared code touched

- `ListingStatus::Sold`.
- `ListingLimits::usedThisMonth` ignores drafts.
- The `sold` status in `ListingFormatter::status()`.
- `ListingPolicy` (abilities added).
- A link in `nalozi/moj-nalog.blade.php`.
- `bootstrap/providers.php`.
