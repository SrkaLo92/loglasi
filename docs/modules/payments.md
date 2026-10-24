# Payments module

Paid features from the price list of 24 Oct 2026, paid by bank transfer with an NBS IPS QR code.
Owns `OrderService` (ordering, credits, cancelling) and `OrderFulfillment` (paid, activation, receipts, refunds, free firm year).

## URLs

| Path | What | Route name |
| --- | --- | --- |
| `/moj-nalog/narudzbine/` | "Moje narudžbine" | `payments.index` |
| `/moj-nalog/narudzbine/nova/?usluga={code}&oglas={id}` | choose listing, buyer, consent (verified e-mail) | `payments.create` |
| `POST /moj-nalog/narudzbine` | create the order (rate limiter `payments-orders`) | `payments.store` |
| `POST /moj-nalog/narudzbine/iskoristi` | use an included credit, no order | `payments.credits.use` |
| `/moj-nalog/narudzbine/{order}/` | pro-forma: payment details, IPS QR, status | `payments.show` |
| `POST /moj-nalog/narudzbine/{order}/otkazi` | cancel a pending order | `payments.cancel` |
| `GET /api/v1/me/orders` | mobile contract | — |

## Catalog

`config/payments.catalog` maps a product code to what it does; prices and names live in `products` (admin).

| Code | Type | Effect |
| --- | --- | --- |
| `bump` | bump | `bumped_at = now`, at most once per `bump_cooldown_hours` (24) |
| `bump_10` | bump pack | 10 bumps in `bump_credits` |
| `featured_7`, `featured_30` | promotion | `featured_until` |
| `priority_3` | promotion | `priority_until` |
| `top_7` | promotion | `top_until` |
| `premium_xl_7` | promotion | `premium_xl_until` |
| `premium_xl_home_7` | promotion | `premium_xl_until` + `premium_home_until` |
| `listing_slots_3/10/30` | slots | +N active listings for 30 days (`listing_slot_grants`, added by `ListingLimits`) |
| `business_{start,pro,max}_{monthly,yearly}` | firm package | `FirmPackage::grant()` |

Promotions extend from the current end when still running. "Prvih 30 firmi" is never sold: admin grants it with `OrderFulfillment::grantFoundingFreeYear()` (counter, lock, audit `firm.free_year`).
A package bought during the free year starts when the free year ends (`firms.free_program_ends_at`).
B2B extras (Partner nedelje, sponsor, banner) are "na upit": no checkout.

Credits are used before an order is allowed: a firm's monthly credits (`FirmCredits::consume()`, bumps / highlights / Premium XL), then bought bump packs. `store` refuses to create an order while a credit covers it.

## Flow and statuses

`pending` → `paid` → `activated` → `refunded`, or `pending` → `cancelled`.

1. The user picks a product, a listing when needed, buyer type (private / firm with name, PIB, MB, address, e-mail), accepts the terms; a private buyer also asks for the service to start at once (consent time and IP stored).
2. The order gets reference `97` + control digits + order ID (6 digits, ISO 7064 MOD 97-10). Mail with the pro-forma and QR is queued.
3. Admin marks it paid (`markPaid`); activation applies the effect in a transaction with row locks, stores the service period and queues the "paid" mail. Failure is audited as `order.activation_failed` and the order stays paid.
4. Pending orders are cancelled after `pending_days` (7) by `orders:cancel-stale` (daily 03:15).

Audit actions: `order.paid`, `order.activated`, `order.activation_failed`, `order.receipt`, `order.refunded`.

## Receipts and refunds

`FiscalReceiptIssuer` is bound in `PaymentsServiceProvider`: `FakeReceiptIssuer` in test mode (numbers `TEST-0001`, `TEST-A-…`, `TEST-R-…`), otherwise `config('payments.receipt_issuer')` (`ManualReceiptIssuer`: admin types the V-PFR number). A V-PFR integration is a new implementation of the same interface.

`refund()` takes back the unused part: whole unused days / days of the period (the current day counts as used); a bump pack refunds unused bumps; a single bump refunds nothing by default. The amount can be overridden by admin. It stores `refund_amount_rsd`, `refund_of_receipt` and the refund receipt.

## Settings (admin)

Payee data is never in code.

| Key | Use |
| --- | --- |
| `payment_recipient`, `payment_account` | payee name and account; without a valid account no QR is shown |
| `payment_pib`, `payment_mb` | payee tax IDs on the pro-forma |
| `payment_code` | payment code (default `ips_payment_code` 221) |
| `payment_vat_note` | VAT note on the pro-forma (default empty) |
| `payment_page_terms`, `payment_page_withdrawal`, `payment_page_complaints` | legal page links on the checkout |
| `payments.test_mode` | default **on**: full flow, "TEST" marks everywhere, fake receipts, `is_test` on orders |
| `payments.private_payments_enabled` | default off; private buyers can order only when on (always on in test mode) |

## IPS QR

`K:PR|V:01|C:1|R:<18 digits>|N:<payee, CRLF lines, ≤70>|I:RSD<amount with comma>|SF:221|S:<purpose ≤35>|RO:97<reference>`, rendered locally as SVG (page) and PNG (mail) with chillerlan/php-qrcode.
The format was written without access to the NBS specification: **check a generated code with the NBS IPS validator before going live.**

## Search ranking

`listing_search.featured` is a rank 0–3: TOP and Premium XL 3, priority 2, highlighted 1, normal 0, and `featured_until` is when the rank next changes; existing indexes are used. `Listing::promotionRank()` (Premium XL counts as 3), `isPremiumXl()`, `isPremiumHome()`.

Premium XL is drawn as the wide card at most once per `config('payments.premium_xl_every')` (10) listings of a list page; `premium_xl_home_7` also puts the listing in the home page Premium section (up to 4, random among the running ones each minute). See the search module docs, "Premium XL card".

## Follow-ups

- Mobile contract: product codes beyond `bump, featured_7d, featured_30d, business_year` are returned raw, and the status enum lacks `refunded`.
- Admin UI for the settings above and for marking paid / receipts / refunds.
- Legal page slugs for `payment_page_*`.
- Card and instant payments: `CardPaymentGateway` and `InstantPaymentFeed` are interfaces only.
