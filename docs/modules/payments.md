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

1. The user picks a product, a listing when needed, buyer type (private / firm with name, PIB, MB, address, e-mail), accepts the terms (`uslovi`, link to `/uslovi-placanja/`); a private buyer (consumer) also ticks the required consent `odmah`: "Saglasan/na sam da usluga počne odmah po evidentiranju uplate i razumem da time gubim pravo na odustanak od ugovora za izvršenu uslugu." Stored as `orders.consent_at` + `consent_ip` (`terms_accepted_at` for everyone); shown in the admin orders list (column "Saglasnost za početak usluge") and in the refund dialog. Firms are not consumers and give no consent.
2. The order gets reference `97` + control digits + order ID (6 digits, ISO 7064 MOD 97-10). Mail with the pro-forma and QR is queued.
3. Admin marks it paid (`markPaid`); activation applies the effect in a transaction with row locks, stores the service period and queues the "paid" mail. Failure is audited as `order.activation_failed` and the order stays paid.
4. Pending orders are cancelled after `pending_days` (7) by `orders:cancel-stale` (daily 03:15).

Audit actions: `order.paid`, `order.activated`, `order.activation_failed`, `order.receipt`, `order.refunded`.

## Receipts and refunds

`FiscalReceiptIssuer` is bound in `PaymentsServiceProvider`: `FakeReceiptIssuer` in test mode (numbers `TEST-0001`, `TEST-A-…`, `TEST-R-…`), otherwise the driver from `FiscalDriver::current()` (admin setting `fiscal.driver`, else `FISCAL_DRIVER`, default `manual`; map in `config/fiscal.php`).

### Fiscal register (ready to connect)

- `manual` (`ManualReceiptIssuer`): the admin issues the receipt in the fiscal app (Šljaker) and types the number in.
- `vpfr` (`Vpfr\VpfrReceiptIssuer` + `Vpfr\VpfrClient`): private buyers get a fiscal receipt automatically through the V-PFR API (ESIR to PFR, `POST /api/v3/invoices`, `GET /api/v3/status`): `Normal` + `Sale`, payment `WireTransfer`, one item with the tax label (`fiscal.tax_label`, default Cyrillic `А`, to confirm with the accountant), header `RequestId` = `loglasi-{order}-sale|refund` so a retry never makes a second receipt. A refund is `Normal` + `Refund` with `referentDocumentNumber`/`referentDocumentDT` of the sale and the refunded amount. The response's `invoiceNumber`, `sdcDateTime` and `verificationUrl` are stored (`receipt_url`, `refund_receipt_url`). A number typed in by the admin wins (receipt issued by hand). When the V-PFR fails, the error is logged, the order stays paid without a receipt and `fiskal:izdaj` (every 15 min) retries.
- Connecting: put `FISCAL_VPFR_URL`, `FISCAL_VPFR_PAC` and either `FISCAL_VPFR_CERT`/`FISCAL_VPFR_CERT_PASSWORD` or `FISCAL_VPFR_TOKEN` (fiscal services that put the V-PFR behind an API key) in `.env`, run `php artisan fiskal:provera`, then switch "Izdavanje fiskalnih računa" in admin settings to automatic. Secrets never go into admin settings. A fiscal refund needs the buyer id (`buyerId`, "20:" ID card, "23:" passport, "10:" PIB), entered by the admin in "Vrati novac" and never stored; without it the refund is not sent and the admin types the number of a receipt issued by hand. `fiskal:provera` lists the tax labels the processor knows; the final label is confirmed by the accountant.
- Firms (any driver, `FirmInvoices`): invoice numbered `{n}/{year}` and credit note `KO-{n}/{year}` (`DocumentNumbers`, table `document_counters`, row locked) unless the admin types a number (`fiscal.invoice_auto_number`). Printable pages `/moj-nalog/narudzbine/{id}/racun/` and `/odobrenje/` (owner or staff; note "Obveznik nije u sistemu PDV-a" or the `payment_vat_note` setting). The order page and the "paid" mail link to the invoice or to the fiscal receipt check (suf.purs.gov.rs). API `/me/orders`: `receipt` = `{number, kind: fiscal_receipt|invoice, check_url, invoice_url}` or null.

`refund()` takes back the unused part: whole unused days / days of the period (the current day counts as used); a bump pack refunds unused bumps; a single bump refunds nothing by default. The amount can be overridden by admin. It stores `refund_amount_rsd`, `refund_of_receipt` and the refund receipt.

## Settings (admin)

Payee data is never in code.

| Key | Use |
| --- | --- |
| `payment_recipient`, `payment_account` | payee name and account; without a valid account no QR is shown |
| `payment_pib`, `payment_mb` | payee tax IDs on the pro-forma |
| `payment_code` | payment code (default `ips_payment_code` 221) |
| `payment_vat_note` | VAT note on the pro-forma (default empty) |
| `payment_page_terms`, `payment_page_withdrawal`, `payment_page_complaints` | legal page links on the checkout and in the pro-forma mail; empty = `PaymentDetails::LEGAL_PAGES` (`/uslovi-placanja/`, `/odustanak-od-ugovora/`, `/reklamacije/`) |
| `payments.test_mode` | default **on**: full flow, "TEST" marks everywhere, fake receipts, `is_test` on orders |
| `payments.private_payments_enabled` | default off; private buyers can order only when on (always on in test mode) |

## IPS QR

`K:PR|V:01|C:1|R:<18 digits>|N:<payee, CRLF lines, ≤70>|I:RSD<amount with comma>|SF:221|S:<purpose ≤35>|RO:97<reference>`, rendered locally as SVG (page) and PNG (mail) with chillerlan/php-qrcode.
The format was written without access to the NBS specification: **check a generated code with the NBS IPS validator before going live.**

## Search ranking

`listing_search.featured` is a rank 0–3: TOP and Premium XL 3, priority 2, highlighted 1, normal 0, and `featured_until` is when the rank next changes; existing indexes are used. `Listing::promotionRank()` (Premium XL counts as 3), `isPremiumXl()`, `isPremiumHome()`.

Premium XL is drawn as the wide card at most once per `config('payments.premium_xl_every')` (10) listings of a list page; `premium_xl_home_7` also puts the listing in the home page Premium section (up to 4, random among the running ones each minute). See the search module docs, "Premium XL card".

## Mobile contract changes

Orders cannot be created through the API (`/me/orders` is read-only, the yaml says buying goes through the website), so the checkout consent needs no API field. If ordering is ever added to the app, the request must carry `consent: true` for a private buyer and get 422 without it.

`GET /api/v1/me/orders` (`OrderPresenter::order()`) follows the `Order` schema of the mobile repo's `api/openapi.yaml` with these additions, which the yaml should take over (the mobile repo is not changed from here):

- `product` keeps the contract codes `bump`, `featured_7d`, `featured_30d`, `business_year` for `bump`, `featured_7`, `featured_30`, `biznis_12m`. Every other product is returned with its `products.code` as a plain string, so the app must not reject an unknown code.
- New field `product_label`: the product name in Serbian (`products.name`, edited in the admin), for example "Premium XL + početna" or "Business Pro, godišnje".
- `status` gets `refunded`: money returned (`refunded` orders were shown as `cancelled` before). `paid` still covers both paid and activated.

Proposed yaml (`components.schemas.Order`):

```yaml
    Order:
      type: object
      properties:
        id: { type: integer }
        product:
          type: string
          description: |
            Šifra usluge. Poznate vrednosti: bump, featured_7d, featured_30d, business_year,
            bump_10, priority_3, top_7, premium_xl_7, premium_xl_home_7,
            listing_slots_3, listing_slots_10, listing_slots_30,
            business_start_monthly, business_start_yearly, business_pro_monthly,
            business_pro_yearly, business_max_monthly, business_max_yearly.
            Nepoznatu šifru prikazati preko product_label.
          examples: [premium_xl_7]
        product_label: { type: string, description: Naziv usluge za prikaz, examples: ["Premium XL kartica"] }
        amount: { type: integer }
        status: { type: string, enum: [awaiting_payment, paid, refunded, cancelled] }
        listing_id: { type: integer, nullable: true }
        user: { type: object, properties: { id: { type: integer }, name: { type: string } } }
        payment_reference: { type: string, description: Poziv na broj }
        created_at: { type: string, format: date-time }
        paid_at: { type: string, format: date-time, nullable: true }
```

The `Price` schema (`product` enum) is unchanged: `GET /catalog` still lists only products with a contract code.

## Follow-ups

- Admin UI for the settings above and for marking paid / receipts / refunds.
- Card and instant payments: `CardPaymentGateway` and `InstantPaymentFeed` are interfaces only.
