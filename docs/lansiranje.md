# Prelazak na loglasi.rs

Šta se menja kad novi sajt zameni WordPress na loglasi.rs. Odluke (Srdjan, 25. 10. 2026):

- nema novog uvoza sa živog WordPress-a: ostaje očišćena baza sa probnog sajta (samo Loznica i okolina);
- server ostaje Hostinger;
- fiskalna kasa (V-PFR) dolazi kasnije;
- živi sajt se dira tek uz Srdjanovo izričito odobrenje, na dan prelaska.

## 1. Pre prelaska (probni sajt)

- [ ] `php artisan test` prolazi lokalno, `composer audit` bez nalaza.
- [ ] SEO nit ponovila `seo:provera` nad probnim sajtom: bez 5xx, bez lanaca, bez neočekivanog 404.
- [ ] `scripts/bezbednost-provera.sh https://loglasi-custom.alphasoft.dev` prolazi.
- [ ] Rezervna kopija baze i `storage/app/public` probnog sajta (ovo postaje produkciona baza).
- [ ] Rezervna kopija celog WordPress-a na loglasi.rs (fajlovi + baza), da povratak bude moguć.

## 2. `.env` na produkciji

| Ključ | Probni sajt | loglasi.rs |
| --- | --- | --- |
| `APP_ENV` | production | production |
| `APP_DEBUG` | false | false |
| `APP_URL` | https://loglasi-custom.alphasoft.dev | https://loglasi.rs |
| `SEO_BASE_URL` | https://loglasi-custom.alphasoft.dev | **obrisati** (podrazumevano https://loglasi.rs) |
| `SEO_INDEXABLE` | false | **obrisati** ili true |
| `NOTIFICATIONS_HOLD` | true | ostaje true dok Srdjan ne odobri obaveštenja, pa false |
| `NOTIFICATIONS_ALLOW_REQUESTED` | true | true dok je HOLD uključen |
| `NOTIFICATIONS_ALLOW` | office@loglasi.rs | office@loglasi.rs |
| `MAIL_*` | smtp, noreply@loglasi.rs | isto |
| `FCM_CREDENTIALS` | prazno | putanja do Firebase JSON-a kad se uključi push |
| `SESSION_DOMAIN` | prazno | prazno (samo loglasi.rs) |
| `LOG_LEVEL` | warning | warning |

Posle izmene: `php artisan config:cache`.

## 3. Server

- [ ] `public/.htaccess`: ukloniti blok `X-Robots-Tag: noindex` sa vrha (dodat samo na probnom sajtu); ostatak je iz repozitorijuma.
- [ ] Domen loglasi.rs pokazuje na `laravel/public` novog sajta; www → bez www (301); http → https (301).
- [ ] Cron `schedule:run` svakog minuta (kao na probnom sajtu).
- [ ] `php artisan migrate --force`, `seo:preusmerenja`, `search:rebuild`, `config:cache`, `route:cache`, `view:cache`, `event:cache`.
- [ ] `/robots.txt` više nema `Disallow: /`, `/sitemap.xml` vraća adrese sa https://loglasi.rs.
- [ ] Admin → Podešavanja: isključiti „Test režim plaćanja”; „Plaćanje za privatna lica” ostaje isključeno.

## Plaćanje na produkciji (Srdjan, 25. 10. 2026)

- Firme i preduzetnici plaćaju uplatom na račun i dobijaju fakturu (van sistema PDV-a, bez SEF-a). Brojevi faktura i knjižnih odobrenja idu automatski po godini (`fiscal.invoice_auto_number`), admin može da upiše svoj broj.
- Privatna lica vide ceo cenovnik sa oznakom „Kupovina uskoro” i ne mogu da naruče dok fiskalna kasa ne proradi; tada se uključuje „Plaćanje za privatna lica” i bira fiskalni drajver.
- Probni sajt ostaje u test režimu, da probne narudžbine ne troše prave brojeve faktura.
- Dok je `NOTIFICATIONS_HOLD` uključen, predračun i faktura se ne šalju mejlom; firma ih vidi u „Moje narudžbine” (sa pozivom na broj i QR kodom).

## 4. Odmah posle prelaska

- [ ] `scripts/bezbednost-provera.sh https://loglasi.rs` i `seo:provera` nad loglasi.rs.
- [ ] Prijava, postavljanje oglasa sa slikom, poruka, reset lozinke (Srdjan lično).
- [ ] Google Search Console: poslati `https://loglasi.rs/sitemap.xml`.
- [ ] Aplikacije: produkciona verzija koristi `https://loglasi.rs/api/v1/` (debug ostaje na probnom).
- [ ] Probni sajt: ostaje noindex, ili se gasi.

## Otvoreno (odluke)

- Fiskalna kasa za privatna lica: kasnije (vidi „Plaćanje na produkciji”).
- Push obaveštenja: tek kad stignu Firebase/APNs ključevi i Srdjan odobri.
