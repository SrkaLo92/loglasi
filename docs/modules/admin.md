# Admin panel

Filament 5 panel at `/admin` for staff (admin, moderator), plus the admin routes of the mobile app (`/api/v1/admin/*`). UI text is Serbian; code, keys and audit actions are English.

## Access

- Only staff accounts that are active and not deleted (`User::canAccessPanel`). Everyone else gets 403.
- Password plus TOTP (Filament app authentication, recovery codes). TOTP is required; a staff account without it is sent to the setup page. The secret and recovery codes live in `users.two_factor_secret` / `two_factor_recovery_codes`.
- A site login (`/prijava/`) does not open the panel. The panel login sets the session marker `admin.login_user_id` (`App\Filament\Pages\Auth\Login`), and `EnsureAdminLogin` checks it on every panel request. Logins are audit-logged (`admin.login`).
- Roles:
  - **admin**: everything.
  - **moderator**: listings (moderation queue, edit, promote, expire, delete), reports, viewing users and blocking them (needed for user reports). Orders, firms, prices, settings, catalog, SEO, audit log and statistics pages are admin only. Revenue and order counters are hidden from moderators on the dashboard.
- Resource access goes through `App\Filament\Support\StaffAccess` (`$adminOnly`, `$readOnly`). Account actions also check `UserPolicy`.

## Audit log

Every write is recorded in `audit_logs` (who, action, subject, changes, IP):

- Standard Filament create/edit/delete/restore/attach/detach actions: global hooks in `App\Filament\Support\AuditHooks`.
- Custom actions: the services in `app/Services/Admin` call `AuditLog::record()`. Order and free-year actions are logged by the payments module (`order.paid`, `order.activated`, `order.receipt`, `order.refunded`, `order.cancelled_by_admin`, `firm.free_year`).
- The **Dnevnik izmena** screen lists the log (read only).

## Screens

| Group | Screen | What it does |
| --- | --- | --- |
| | Pregled (dashboard) | Waiting counters (pending listings, phones, unpaid orders, open reports), new listings/users/firms, views, paid orders and net revenue for today/7/30 days, 30-day chart, top categories. |
| Moderacija | Oglasi | Tabs (pending first), filters, FULLTEXT search, simple pagination. Approve, reject with reason (mail to owner), free promotion (featured, priority, TOP, Premium XL, Premium XL + home), remove promotions, bump, expire now, delete, bulk approve/reject. Edit form with category fields. |
| Moderacija | Korisnici | Search by name, e-mail or phone (any format). Block/unblock (revokes app tokens), manual phone verification, make firm / back to private, role change (admin only, last admin protected). User page lists listings and orders. |
| Moderacija | Firme | Profile, address, working hours (`{"1": "08–16", ...}`), PIB/MB. Package: grant Start/Pro/Max monthly or yearly (`FirmPackage::grant`), "Prvih 30 firmi" free year (`OrderFulfillment::grantFoundingFreeYear`), end package. Shows free places left and this month's included promotions left. |
| Moderacija | Prijave | Listing and user reports: resolve or dismiss; from the report, reject or delete the listing or block the user. |
| Prodaja | Narudžbine i uplate | Tabs by status, filters (status, product, test). Mark paid (date, receipt and advance receipt number, note), activate again, enter receipt number later, refund (pro-rata amount pre-filled, editable, reason required, refund document number), cancel. Banner "TEST REŽIM" while payments are in test mode. |
| Prodaja | Cene | Every product: name, price, duration, listing limit, active, display only, description. |
| Katalog | Kategorije, Polja, Mesta | Category tree with child categories and attached fields; SEO title/description, H1, intro and FAQ. Field definitions. Locations. |
| SEO | Preusmerenja, Uklonjene adrese (410) | Redirects and the 410 list; sitemap rebuild. |
| Sistem | Podešavanja | Limits and rules (`private_active_limit`, listing duration, images, moderation, "Novo" only for firms, free firm places, euro rate), contact, Google tag / GA4 / Search Console meta, home page texts (`home.*`), payments (`payments.test_mode`, `payments.private_payments_enabled`, payment data and legal page paths), page texts (`page_text.*`, including `pocetna.*` and `seo.*`). |
| Sistem | Ključevi podešavanja | Raw key/value list for keys the settings page does not show. |
| Sistem | Pretrage i obaveštenja | Read only: saved searches, alert matches, app devices, most saved searches (cached 10 minutes). |
| Sistem | Dnevnik izmena | Audit log. |

## Services

All panel actions and the API call the same services:

- `App\Services\Admin\ListingModeration`: approve, reject, feature (promotion column), unfeature, bump, expire, delete.
- `App\Services\Admin\UserAdministration`: verify/unverify phone, block/unblock, make firm/private, change role.
- `App\Services\Admin\FirmAdministration`: grant package, free year, end package (wraps the firms and payments modules).
- `App\Services\Admin\OrderAdministration`: input checks around `OrderFulfillment` (markPaid, activate, recordReceipt, refund) and `OrderService::cancel`.
- `App\Services\Admin\ReportHandling`, `App\Services\Admin\SettingsSchema`.
- `App\Services\Admin\AdminStats`: dashboard and API numbers. Totals and charts are cached for 120 s, waiting counters for 30 s (cleared on every audit log entry), the notifications page for 10 minutes. Revenue = paid, activated and refunded non-test orders minus refunded amounts.

Service errors are `DomainException` (or the payments module's `OrderException`); the panel shows them as a red notification (`App\Filament\Support\Notify`).

## API (`routes/api/admin.php`)

`auth:sanctum`, `staff`, `throttle:admin-api` (120 requests per minute per user). Shapes follow the OpenAPI contract.

| Route | Who |
| --- | --- |
| `GET /admin/listings?status=pending\|active\|rejected\|expired\|sold&page=` | staff |
| `POST /admin/listings/{id}/approve`, `POST /admin/listings/{id}/reject` (`reason`) | staff |
| `GET /admin/users?filter=phone_unverified\|firms\|all&q=&page=` | staff |
| `POST /admin/users/{id}/verify-phone` | admin |
| `GET /admin/orders?status=awaiting_payment\|paid\|cancelled&page=` | admin |
| `POST /admin/orders/{id}/mark-paid` (`receipt_number`, `note`) | admin |
| `GET /admin/stats?period=today\|7d\|30d` | staff |

## Performance

- Listing and user tables use simple pagination (no total COUNT) and eager loads; search on listings uses the FULLTEXT index.
- Indexes for the admin filters: `database/migrations/2026_10_26_000100_admin_indexes.php` (listings by status/category/date, users, orders, reports, audit log, daily stats, refresh tokens).

## Assets

- `public/css/admin.css` (brand font, logo, dashboard, test-mode banner, notifications page).
- Published Filament assets in `public/js/filament`, `public/css/filament`, `public/fonts/filament`. After a Filament update: `php artisan filament:assets`.

## Manual for staff

1. **Sign in** at `/admin/login` with e-mail, password and the 6-digit code from the authenticator app. First login: scan the QR code and keep the recovery codes.
2. **Moderation**: Oglasi › "Na čekanju". Open a listing, check photos and text, then **Odobri** or **Odbij** with a short reason the owner will read. Several at once: tick them and use the bulk action.
3. **Reports**: Prijave › "Otvorene". Reject or delete the reported listing, or block the user, then mark the report resolved; dismiss reports that need nothing.
4. **Payments** (admin): when a transfer arrives, find the order by its reference (poziv na broj) in Narudžbine › "Čeka uplatu", choose **Označi kao plaćeno**, enter the date and the receipt number. The service switches on at once. A late transfer for a cancelled order can still be marked paid. **Vrati novac** pre-fills the pro-rata amount for unused days; enter the reason and the refund document number.
5. **Firms** (admin): Korisnici › user › **Pretvori u firmu**, then Firme › firm › **Dodeli paket** or **Prvih 30 firmi: besplatna godina**. The firms list shows how many free places are left.
6. **Settings** (admin): Podešavanja. Leave a field empty to use the default. Turn off **Test režim plaćanja** only when real payments start.
