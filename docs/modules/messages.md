# Messages module: buyer–seller conversations and favourites

Private messages between a buyer and a seller about a listing, read receipts, unread counters, blocking and reporting, and saved listings ("Omiljeni"). Email and push to the recipient belong to the notifications module (it listens to `MessageSent`). Site pages and the mobile API share one service (`App\Services\Messages\Messenger`), so the same rules hold on both.

## Site pages (Blade, work without JS, all `noindex,follow`)

| Path | Route name | What it does |
| --- | --- | --- |
| `GET /moj-nalog/poruke/` | `messages.index` | Inbox: conversations grouped by listing (newest activity first), other user, last message, unread badge. Keyset paging `?cursor=`. Old link `?korisnik=X&oglas=Y` redirects to the new form. |
| `GET /moj-nalog/poruke/novo/?oglas={id}` | `messages.create` | Form for the first message. Own listing → back to the listing; existing conversation → redirect to it; listing not live → 404. |
| `POST /moj-nalog/poruke/novo/` | `messages.store` | Fields `oglas`, `body`. Redirects to the conversation. |
| `GET /moj-nalog/poruke/{id}/` | `messages.show` | Conversation: listing summary, last 50 messages (`?before={id}` for older), "Pročitano" under my last message, reply form. Marks the conversation read. |
| `POST /moj-nalog/poruke/{id}/` | `messages.reply` | Reply (`body`). |
| `POST`/`DELETE /moj-nalog/poruke/{id}/blokiraj/` | `messages.block`, `messages.unblock` | Block or unblock the other participant. |
| `POST /moj-nalog/poruke/{id}/prijava/` | `messages.report` | Report the other participant (`reason` from `ReportReasons`, optional `details`); a `reports` row for the user with "Razgovor #id" in the details. One open report per reporter and user. 6 per 10 min. |
| `GET /moj-nalog/poruke/{id}/nove/?after={id}` | `messages.poll` | JSON for `public/js/messages.js`: `{html, last_id, read_until}`. The script polls every 20 s only while the tab is visible (`visibilitychange`), and once right when it becomes visible. |
| `GET /moj-nalog/omiljeni/` | `favorites.index` | Saved listings as `<x-listing-card>`, newest saved first; deleted and non-public (pending, rejected, draft) are hidden, expired and sold are shown with "Istekao" / "Prodato". Keyset paging. |
| `POST /moj-nalog/omiljeni/{listing}/` | `favorites.store` | **Toggle** (the card heart always posts): saves, or removes when already saved. Without JS redirects back with a status message; with `Accept: application/json` returns `{favorite, message}`. |
| `DELETE /moj-nalog/omiljeni/{listing}/` | `favorites.destroy` | Remove (form with `@method('DELETE')`). Same responses. |

The listing page links to `/moj-nalog/poruke/novo/?oglas={id}`; the listing card heart posts to `/moj-nalog/omiljeni/{id}/`.

`$unreadMessages` for `partials.bottom-nav` (and `partials.header`) comes from a view composer in `MessagesServiceProvider`.

## Mobile API (`/api/v1`, Bearer token)

| Route | Response |
| --- | --- |
| `GET /conversations?page=&per_page=&cursor=` | `Conversation[]` newest first (default 20, max 50). Header `X-Next-Cursor` when more exist; `cursor` is the keyset alternative to `page`. |
| `POST /conversations` `{listing_id, body}` | 201 `Conversation` (the existing one if the buyer already wrote about this listing). |
| `GET /conversations/{id}/messages?before=` | up to 50 `Message`, newest last; marks read. |
| `POST /conversations/{id}/messages` `{body}` | 201 `Message`. |
| `GET /me/favorites?page=&per_page=&cursor=` | `ListingCard[]` (default 50, max 50), `X-Next-Cursor`. Expired listings have `status: "expired"`. |
| `PUT /me/favorites/{id}` | 204, idempotent; 404 if the listing is not live. |
| `DELETE /me/favorites/{id}` | 204, idempotent (also for a deleted listing). |

`Message.read`: for my message, the other side opened the conversation after it was sent; for theirs, I have. `Conversation.other_user.name` is "Obrisan korisnik" for a deleted account; `listing` is the card also for a deleted listing (status from the row), `null` only when the row is gone.

Errors (`{code, message, fields}`):

| HTTP | code | When |
| --- | --- | --- |
| 401 | `unauthorized` | no token |
| 403 | `email_not_verified` | sender has not confirmed the email |
| 403 | `account_blocked` | sender is blocked (normally the token is already refused) |
| 403 | `own_listing` | message to own listing |
| 403 | `recipient_unavailable` | the other user is blocked by the moderators or deleted |
| 403 | `user_blocked` | one participant blocked the other |
| 404 | `not_found` | listing not live (new conversation), conversation of other users, unknown id |
| 422 | `validation_failed` | `fields.body` (empty after removing HTML, over 2000 characters), `fields.listing_id` |
| 429 | `too_many_requests` | rate limit (with `Retry-After`), same text to many sellers, too many new conversations |

## Rules

- Login and a verified email. Site: `verified` middleware on the new-conversation form; the service also refuses replies from unverified accounts (message with a link to resend the confirmation).
- No message to your own listing. A new conversation needs a live listing (published, not deleted, `expires_at` in the future). Replies in an existing conversation keep working after the listing expires or is deleted.
- Accounts blocked by the moderators, or deleted, cannot send and cannot receive.
- A user can block another user (`user_blocks`, from the conversation view): then neither can write to the other, in any conversation, until it is lifted. The API has no block endpoint in the contract; it only enforces it.
- One conversation per listing and buyer (unique key; parallel first messages end in the same row via `createOrFirst`).
- Body: HTML tags, comments, `<script>`/`<style>` blocks and control characters are removed (text like `<3` or `a < b` stays), at most 2000 characters, not empty. Stored as plain text; shown escaped with line breaks; `http(s)://` and `www.` links get `rel="nofollow ugc noopener"` and `target="_blank"`.
- Rate limits per user, shared by site and API (`throttle:messages-send`): 10 per minute, 100 per day. Polling: 30 per minute. Favourites: 60 per minute.
- Spam guard: the same text (case-insensitive) in more than 3 different conversations within 24 h is refused; at most 30 new conversations per day.
- Statistics: every **buyer** message adds 1 to `listing_daily_stats.messages` (seller replies do not); a new save adds 1 to `listing_daily_stats.favorites` (not when the owner saves his own listing; removing does not subtract). Date in Europe/Belgrade, one upsert statement.
- All values are in `config/messages.php`.

## Read receipts and unread counters

- `conversations.buyer_read_at` / `seller_read_at`: when that side last opened the conversation (or sent a message).
- `conversations.buyer_unread` / `seller_unread` (new columns): sending adds 1 to the other side and sets the sender's to 0; opening sets it to 0. No message scan is ever needed for unread counts.
- Total for the header: `SUM` of the user's side over his conversations (two index lookups), cached 5 min per user (`messages.unread.{id}`) and forgotten on every send and read.
- Imported conversations start with 0 unread (the importer marks them read).

## Event for notifications

`App\Events\MessageSent` (`ShouldDispatchAfterCommit`) carries the stored message with `conversation` loaded. This module sends no email or push; the notifications module listens to the event. The unread counters and read timestamps let it skip messages that were already read and batch per conversation.

## Scale

Indexes added (migration `2026_10_26_000100_messages_inbox_columns_and_indexes`):

| Index | Used by |
| --- | --- |
| `conversations (buyer_id, last_message_at, id)` | inbox, buyer side; unread sum |
| `conversations (seller_id, last_message_at, id)` | inbox, seller side; unread sum |
| `messages (sender_id, created_at)` | spam guard |
| `favorites (user_id, created_at, listing_id)` | favourites list (covering) |
| `user_blocks (blocker_id, blocked_id)` primary, `(blocked_id)` | block checks on send (two PK lookups) |
| existing `messages (conversation_id, id)` | thread, keyset `before`, last message |

The inbox runs two index range scans (buyer side and seller side, each `LIMIT n+1` from the keyset cursor) and merges them in PHP; one `buyer_id = ? OR seller_id = ?` query could not use an index for the order. Messages page by id (`before`/`after`), favourites by `(created_at, listing_id)`. No `COUNT(*)` on big tables per request.

Measured on MariaDB 10.11 with `database/seeders/MessagesScaleSeeder` (5,000 users, 200,000 listings, 60,000 conversations, 420,492 messages, 200,000 favourites; user 1 has 2,000 conversations as seller and 1,000 favourites). Times from the query log, warm buffer pool:

| Query | Plan | Time |
| --- | --- | --- |
| inbox seller side, heavy seller, first page | range on `conversations_seller_inbox_index`, LIMIT 21 | 0.44 ms |
| inbox seller side, next page by cursor | range on the same index | 0.48 ms |
| last message per conversation (20) | range on `messages_conversation_id_id_index`, covering, + PK lookup | 0.7–2.0 ms |
| inbox page total (7 queries incl. users, listings, images) | all PK or index | 11.3 ms first page, 5.8 ms next page |
| unread total, heavy seller (cache miss) | ref on the seller index, 2,000 rows | 1.9 ms (then cached) |
| thread, last 50 messages | ref on `(conversation_id, id)` | 0.3 ms |
| favourites page, 1,000 saved | ref on `favorites_user_created_index` (Using index) + PK join | 1.2 ms |
| favourites next page by cursor | range on the same index | 1.2 ms |
| spam guard | range on `messages_sender_created_index` | 0.4 ms |
| whole send (checks, insert, counters, stats upsert, cache) | | about 5 ms |

Full page render through the HTTP kernel (no opcache, no config cache, user 1): inbox 20 ms, conversation 12 ms, favourites 11 ms.

Reproduce:

```bash
DB_DATABASE=lb_messages php artisan db:seed --class=MessagesScaleSeeder   # wipes listings, users, messages, favourites in that DB
```

## Files

| File | What |
| --- | --- |
| `app/Services/Messages/Messenger.php` | start, reply, markRead, unreadTotal, inbox, messages, newerThan; all rules |
| `app/Services/Messages/Favorites.php` | canSave, add, remove, has, page, savedIds |
| `app/Services/Messages/MessageBody.php` | clean (storage), html (display with links), excerpt |
| `app/Services/Messages/MessageRejected.php` | rule violations with code, Serbian message and status |
| `app/Services/Messages/InboxCursor.php`, `ListingStats.php` | keyset cursor, daily stats upsert (`listing_daily_stats` only; `Support\Listing\Stats::increment` also needs a `listings` column, which messages and favourites do not have) |
| `app/Http/Controllers/Messages/` | `InboxController`, `ConversationController`, `FavoriteController`, `Api/ConversationApiController`, `Api/FavoriteApiController`, `Api/MessagesPresenter` |
| `app/Http/Requests/Messages/SendMessageRequest.php` | body cleaning and validation (site and API) |
| `app/Policies/ConversationPolicy.php` | only the two participants; others get 404 |
| `app/Events/MessageSent.php` | event for the notifications module |
| `app/Providers/MessagesServiceProvider.php` | rate limiters, view composer |
| `routes/web/messages.php`, `routes/api/messages.php`, `config/messages.php` | |
| `resources/views/messages/`, `public/css/messages.css`, `public/js/messages.js` | pages |
| `database/factories/ConversationFactory.php`, `MessageFactory.php`, `database/seeders/MessagesScaleSeeder.php` | tests and measurements |

Shared files touched: `bootstrap/providers.php` (one line), `app/Models/Conversation.php` (new relation `lastMessage`, helpers, casts for the two new columns). Listing shapes come from the listing module (`ListingFormatter::card()`, `<x-listing-card>`, `ListingImage::sizeUrl()`). No writes touch `listings`, so the search index needs no sync.

## Tests

```bash
DB_DATABASE=lb_messages_test php artisan test tests/Feature/Messages tests/Unit/MessageBodyTest.php
```

Every route and rule: login and verification, own listing, live listing, blocked and deleted users, user blocks, reports, the `MessageSent` event (and that no mail is sent), body cleaning and length, links and escaping, rate limits per minute and day, spam guard, new conversations per day, read receipts, unread counters and the bottom nav badge, inbox grouping and keyset pages, polling, favourites (site toggle and API) with stats and sold/expired marks, contract shapes and error codes.
