# Notifications: mail layout, settings, saved searches, push

One branded mail layout for every module, per-user notification settings with one-click unsubscribe, saved searches with hourly alerts, and FCM push for the mobile app.

## Mail

### Layout

- Markdown mail theme `loglasi` (`resources/views/vendor/mail/html/themes/loglasi.css`), the site colours: buttons `#2B7A64`, accents `#379B7F`/`#EF7A38`, text `#1D2B26`.
- Header: brand logo `public/img/brand/logo.png` (absolute URL, alt "loglasi.rs"). Footer: "loglasi.rs je besplatni sajt za oglase za Loznicu i okolinu.", link to `/moj-nalog/obavestenja/` and, for category mail, "Odjavite se od ovih poruka".
- Every Markdown mail gets a plain-text part (`resources/views/vendor/mail/text/message.blade.php`) with the same footer.
- `resources/views/vendor/notifications/email.blade.php` is the Serbian version of the framework notification mail, so `MailMessage` mails (email confirmation, password reset from the accounts module) use the layout too.
- Sender: `MAIL_FROM_ADDRESS` / `MAIL_FROM_NAME` (`noreply@loglasi.rs`, "loglasi.rs"). Hostinger SMTP settings are in `.env.example` (`smtp.hostinger.com`, SSL on 465, password only in the server's `.env`).

### How other modules send mail

Notification (preferred, the channel list and preference check live in one place):

```php
class ListingApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function via(User $user): array { return ['mail']; }

    public function toMail(User $user): BrandedMailMessage
    {
        return BrandedMailMessage::to($user, NotificationPreferences::EXPIRING) // null = account mail, cannot be turned off
            ->subject('...')
            ->line('Oglas „'.BrandedMailMessage::plain($listing->title).'” je odobren.')
            ->action('Pogledaj oglas', $listing->url());
    }
}
```

Mailable:

```php
class Something extends BrandedMailable          // queued, To = $user, List-Unsubscribe when $category is set
{
    protected ?string $category = NotificationPreferences::EXPIRING;
    public function envelope(): Envelope { return new Envelope(subject: '...'); }
    public function content(): Content { return $this->markdownContent('module.mail.view', [...]); }
}
// the view starts with <x-mail::message :unsubscribe="$unsubscribeUrl">
```

Rules:

- Queue everything (`ShouldQueue`, `Mail::...->queue()`).
- With a category the mail gets `List-Unsubscribe: <signed URL>` and `List-Unsubscribe-Post: List-Unsubscribe=One-Click` (RFC 8058). A mailable must check `NotificationPreferences::allows($user, $category)` before queueing; a notification checks it in `via()`.
- User text in Markdown lines (titles, names, message bodies) goes through `BrandedMailMessage::plain()`, so it cannot turn into links or headings. HTML is escaped by Blade anyway.
- Account and money mails are transactional, have no category and are always sent.

### Mail inventory (who sends what)

| Mail | Module | Category | Note |
| --- | --- | --- | --- |
| Email confirmation, password reset | accounts (`AccountsServiceProvider`) | none | uses this layout through `notifications::email` |
| New message | **notifications** (`NewMessageNotification`) | `messages` | from `App\Events\MessageSent`; the messages module sends nothing |
| Saved-search digest | **notifications** (`SavedSearchDigestNotification`) | `saved_searches` | hourly job |
| Listing expires in 3 days | posting (`ListingExpiringSoon`, `listings:expire`) | `expiring` | posting checks the preference and `expiry_notified_at` (sent once); this module only added the `List-Unsubscribe` header. Not duplicated here. |
| Order placed (pro-forma, QR), order paid | payments (`OrderPlaced`, `OrderPaid`) | none | transactional; nothing here |
| Biznis package expiring | firms (`PackageExpiringSoon`, `firms:package-reminders`) | none | transactional, always sent; the earlier "Biznis paket" toggle was removed so the page does not promise an opt-out that does not exist |
| Contact form | pages (`ContactMessage`) | none | to the site address |

The posting, payments and firms mails still use their own inline HTML templates (same colours and tagline). Moving them onto `BrandedMailable` is a follow-up; their content is not duplicated anywhere.

## Notification settings

`users.notification_settings` (JSON) holds the site categories and the app keys together:

| Key | Default | Site `/moj-nalog/obavestenja/` | API `PATCH /me` `notifications` |
| --- | --- | --- | --- |
| `messages` | on | "Nove poruke" | yes |
| `saved_searches` | on | "Novi oglasi za sačuvane pretrage" | not in the contract |
| `expiring` | on | "Istek oglasa" | yes |
| `newsletter` | **off** | "Novosti sa loglasi.rs" | not in the contract |
| `listing_status`, `payments`, `admin_queue` | on | — | yes (accounts module) |

`App\Support\Notifications\NotificationPreferences`: `all($user)`, `allows($user, $key)`, `set($user, [...])` (keeps every other stored key), `unsubscribeUrl($user, $key)`, `labels()`. The site form sends only checked boxes, so every site category not sent is turned off; the app keys stay as they were.

### One-click unsubscribe

`/moj-nalog/obavestenja/odjava/{user}/{category}/` (route `notifications.unsubscribe`), signed and without expiry, works without signing in.

- GET shows "Odjavi me" (link scanners in mail clients open GET links, so GET changes nothing).
- POST turns that one category off. The POST route skips CSRF because mail providers post `List-Unsubscribe=One-Click` without a token; the signature protects it. 20 requests per minute.

## Saved searches ("Sačuvane pretrage")

| Route | What |
| --- | --- |
| `GET /moj-nalog/pretrage/` (`saved-searches.index`) | list: name, link to results, rename, alerts on/off, delete |
| `POST /moj-nalog/pretrage/` (`saved-searches.store`) | target of the "Sačuvaj pretragu" button on `/oglasi/` |
| `PATCH /moj-nalog/pretrage/{id}/` | `name` (empty = automatic name), `notify` (0/1) |
| `DELETE /moj-nalog/pretrage/{id}/` | delete |

- The button posts the parameters of `/oglasi/`: `keyword`, `kategorija` (slug or path), `mesto` (slug), `price_min`, `price_max` (`5.000` is accepted), `currency`, `f[...]` (validated by `ListingFields::parseFilters`), optional `name`. Anything else (`sort`, `page`) is ignored.
- `App\Support\Notifications\SavedSearchQuery` normalizes them (fixed key order, empty values dropped) and stores them in `saved_searches.query`; `query_hash` (sha1) + unique `(user_id, query_hash)` saves the same search once. `SavedSearch::url()` rebuilds `/oglasi/?...`.
- At least one condition is required; at most 20 searches per user (`notifications.saved_searches.max_per_user`). Errors go to the list page (the button lives on another module's page). With `Accept: application/json` the answer is `201` (new) / `200` (already saved) `{id, name, notify, url}` or 422.
- Guests: the normalized search is kept in the session (`SavedSearches::PENDING`) and they go to `/prijava/` ("Prijavite se ili napravite nalog, a pretragu ćemo sačuvati odmah posle toga."). The `SavePendingSearch` middleware (web group) saves it on the first signed-in GET, after login or registration, and sends the user to `/moj-nalog/pretrage/` with "Pretraga je sačuvana". If the user is over the limit, the error is shown on that page. A JSON request from a guest gets 401. Saving logic shared by both paths: `App\Support\Notifications\SavedSearches::save()`.
- Policy `SavedSearchPolicy`: only the owner changes or deletes. Rate limit `notifications-account`, 30 per minute.

### Alerts

```
notifications:saved-searches  (hourly, withoutOverlapping)
  1. window = (watermark − 15 min, now], first run 1 h, at most 72 h back
     SELECT id FROM listings WHERE status='published' AND published_at in window   -- index (status, published_at, id)
  2. Bus::chain:
     MatchSavedSearches(500 listing ids)  × n    -- in-memory matcher, insertOrIgnore into saved_search_matches
     SendSavedSearchDigests                        -- one digest per user
  3. watermark = now (setting notifications.saved_search_watermark)
```

- `App\Services\SavedSearchMatcher::match($listings, $searches)` is the single entry point. It compiles every search once per call (category → descendant ids, location id, price bounds in EUR, keyword words, field conditions) and puts the searches into buckets by category, so a listing is only checked against the searches of its category tree plus the ones without a category. There is no query per search. The same rules as `/oglasi/`: subcategories included, exact location, `price_eur` compared with the bounds ("Dogovor" never matches a bound), every keyword word must start a word of the title or description (diacritics ignored, a prefix-set lookup per word), `f[...]` as `ListingFields::apply()`. The seller's own listings never match. Searches of blocked users and searches with alerts off are not loaded. It can later delegate to the search module (`ListingSearch` restricted to the window ids) without changing its signature. Text normalization is the search module's `App\Support\Search\SearchText` (`forListing()` for the listing, `words()` for the keyword), so alerts and `/oglasi/` agree.
- `saved_search_matches (saved_search_id, listing_id)` is unique: a listing that matched once is never announced again, even with overlapping windows or a listing that is published again. Sent rows are kept 30 days.
- Digest: searches with pending matches, `notify = true` and `last_notified_at` older than 24 h (at most one alert per search per day). Matches of searches in cooldown wait for a later run. Listings that are no longer active are dropped at send time. One `SavedSearchDigestNotification` per user, with up to 5 listings per search and a link to all results. Mail goes only to verified addresses; push goes to the user's devices. If the user turned `saved_searches` off, the matches are marked done so nothing piles up.

## Push (mobile app)

### Devices API (`/api/v1`, Bearer token, contract `openapi.yaml`)

| Route | Answer |
| --- | --- |
| `POST /devices` `{token, platform: android|ios, app_version?}` | 204. The token moves to the account that registered it last (a phone has one account). At most 10 devices per user; the oldest are dropped. |
| `DELETE /devices/{token}` | always 204; deletes only the caller's own token |

Validation errors: 422 `validation_failed` (`fields.token` "Neispravan token uređaja."). 30 requests per minute per user (`notifications-devices`, 429 `too_many_requests`).

### Channel

`App\Notifications\Channels\PushChannel` with `toPush($user): PushMessage(title, body, data)`. It sends through `App\Support\Push\FcmClient`, FCM HTTP v1 without an SDK:

- `FCM_CREDENTIALS` = path to the Firebase service-account JSON (outside `public/`), `FCM_PROJECT_ID` optional (taken from the JSON).
- OAuth: an RS256 JWT (`iss` = client_email, `scope` firebase.messaging, `aud` = token_uri, 1 h), signed with `openssl_sign`, is exchanged at `token_uri`. The access token is cached for 50 minutes; on 401 it is fetched again once.
- `404` or `UNREGISTERED` → the device row is deleted. Other errors are logged as a warning; the token stays.
- Without configuration the channel is a no-op logged at debug.

Payload `data` (all strings):

| `type` | Other keys | When |
| --- | --- | --- |
| `message` | `conversation_id`, `message_id` | new message (title = sender's name, body = start of the message) |
| `saved_search` | `saved_search_id`, `url` | saved-search digest |

## New messages

`App\Events\MessageSent` (messages module, after commit) → `App\Notifications\Listeners\NotifyMessageRecipient` (registered in `NotificationsServiceProvider`, kept out of `app/Listeners` so event discovery does not register it twice) → `NewMessageNotification` to the participant who is not the sender (blocked users get nothing).

- Push for every message, if the user has devices and `messages` is on.
- Mail at most once per conversation per 15 minutes per recipient (`notifications.message_mail_throttle_minutes`; `Cache::add`), only to a verified address, and not if the recipient's unread counter for the conversation is 0 by the time the queued mail runs (they already read it). The link is `/moj-nalog/poruke/{id}/` (`notifications.links.conversation`).

## Database

Migration `2026_10_27_000100_notifications_tables`: `devices.app_version`, `devices.last_seen_at` (+ index with `user_id`); `saved_searches.query_hash` (unique with `user_id`), index `(notify, id)`; table `saved_search_matches`; index `listings_status_published_index (status, published_at, id)`.

## Measured at scale

Local MariaDB 10.11, `lb_notifications` with 200,000 listings (`dev:listings 200000`, 184k active), 5,000 random saved searches from 3,000 users (70% with a category, 40% with a place, 40% with a keyword, 30% with a price cap). 600 listings published in the last hour, which is about 10× the expected traffic:

| Step | Result |
| --- | --- |
| Window query (`EXPLAIN`: `listings_status_published_index`, range, no filesort) | 600 ids, 24 ms |
| Compile 5,000 searches | ~0.1–0.2 s per job |
| Match 500 listings × 5,000 searches in memory | 0.86 s (before tuning: 8.2 s) |
| Whole matching step, 600 listings, including writing 97,727 matches | 4.4 s, peak 80 MB |
| Digests for 2,792 searches | 124 queries, 3.9 s (before tuning: 2,386 queries, 27 s) |

With 2,069 listings in one hour (an extreme backlog) matching took 11.9 s and wrote 357k matches. The cost there is the insert, not the matching. Queries per digest chunk are constant: users, device owners, pending matches joined with active listings, the listings shown, two updates. Each user notification is a queued job.

## Files

`app/Support/Notifications/` (preferences, query), `app/Services/SavedSearchMatcher.php`, `app/Jobs/Notifications/`, `app/Console/Commands/SendSavedSearchAlerts.php`, `app/Notifications/` (notifications, channel, messages, listener), `app/Support/Push/FcmClient.php`, `app/Mail/BrandedMailable.php`, `app/Http/Controllers/Notifications/`, `app/Policies/SavedSearchPolicy.php`, `app/Models/SavedSearchMatch.php`, `app/Providers/NotificationsServiceProvider.php`, `config/notifications.php`, `routes/web/notifications.php`, `routes/api/notifications.php`, `resources/views/notifications/`, `resources/views/vendor/{mail,notifications}/`, `public/css/notifications.css`.

Shared changes: `bootstrap/providers.php` (provider), `SavePendingSearch` pushed to the `web` group, `config/mail.php` (theme, sender), `.env.example`, `User::savedSearches()` and `routeNotificationForPush()`, `SavedSearch::matches()` and `url()`, a card with both links on `/moj-nalog/`, the `List-Unsubscribe` header on `ListingExpiringSoon`.

## Tests

`DB_DATABASE=lb_notifications_test php artisan test tests/Feature/Notifications` covers: the layout, text part and headers; escaping; account mail; settings and the signed unsubscribe (GET confirms, POST without CSRF, bad signature); the saved-search pages (normalization, dedupe, JSON, validation, limit, policy, guest search saved after login and after registration); the matcher rules; alerts (schedule, digest content, one per user, daily cooldown, overlapping windows, inactive listings, blocked users and preferences, chunks, pruning, plural forms); the devices API; FCM (JWT verified with the public key, token cache, payload, 404/UNREGISTERED cleanup, 401 retry, no-op); new messages through `Messenger` (throttle, already read, preference, push, unverified address).

## Hold before the launch

`NOTIFICATIONS_HOLD=true` stops every notification to real users (test sites with imported data, and production until the official launch):

- Mail: `HoldOutgoingMail` listens to `MessageSending` and drops any mail with a recipient outside `NOTIFICATIONS_ALLOW` (comma separated emails or `@domain`). Dropped mails are logged as "Mail held".
- Push: `FcmClient::send()` returns `disabled` and sends nothing.
- There is no SMS sending in the app.

Saved-search alerts still record their matches while on hold, so turning the hold off does not send a backlog.
