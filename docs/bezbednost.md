# Bezbednost i testiranje napada

Šta štiti sajt, kako se proverava i šta se radi pre izlaska na loglasi.rs.

## Šta je ugrađeno

| Napad | Zaštita | Gde |
|---|---|---|
| XSS (skripta u oglasu, poruci, oceni, imenu) | Blade escapuje sav korisnički tekst; Markdown strana iz admina escapuje HTML; CSP zabranjuje `object`, strane `base`/`form-action` i uokviravanje | `SecurityHeaders`, `config/security.php` |
| Clickjacking | `X-Frame-Options: SAMEORIGIN`, `frame-ancestors 'self'` | `SecurityHeaders` |
| IDOR (tuđi oglas, poruka, narudžbina menjanjem broja) | Policy za svaki model, razgovor tuđem korisniku je 404, API `me/*` uvek po vlasniku | `app/Policies`, testovi `AttackSurfaceTest` |
| PHP ili HTML prerušen u sliku | provera `getimagesize` + dekodiranje GD-om + ponovno snimanje u WebP pod nasumičnim imenom; `.htaccess` ne izvršava PHP u `storage/` | `ListingImages::store`, `Avatar`, `FirmImages` |
| Pogađanje lozinke | 5 pokušaja/min po emailu i IP-u, 30/min po IP-u (sajt i API), isto za registraciju i reset lozinke | `NaloziServiceProvider` |
| Preuzimanje admina | admin samo za osoblje, obavezan 2FA, moderator ne vidi podešavanja i cene | Filament `StaffAccess`, `EnsureStaff` |
| Mass assignment (role, verifikacija) | `User::$fillable` bez role i datuma potvrde, kontroleri pišu samo validirana polja | `User`, `Firm::$guarded` |
| CSRF | Laravel token na svim formama; API radi samo sa Bearer tokenom, CORS ne dozvoljava strane sajtove | `config/cors.php` |
| Krađa sesije | kolačić `HttpOnly`, `SameSite=Lax`, `Secure` kad je APP_URL https; HSTS godinu dana | `config/session.php`, `config/security.php` |
| Curenje podataka | API nikad ne vraća email, lozinku ni telefon (telefon samo preko `/contact` sa ograničenjem) | `ListingFormatter`, test |
| Skriveni fajlovi | `.env`, `.git` i ostali skriveni fajlovi se ne služe; `X-Powered-By` uklonjen | `public/.htaccess` |
| Spam i zloupotreba | ograničenja (throttle) za poruke, oglase, otpremanje, porudžbine, ocene; provera duplikata; blokiranje korisnika | rute |

## Automatske provere

- `php artisan test` (u CI-ju i pre svakog deploy-a). Bezbednost posebno: `tests/Feature/Security/AttackSurfaceTest.php` (zaglavlja, XSS, IDOR, PHP kao slika, pogađanje lozinke, eskalacija prava, admin, curenje podataka, skriveni fajlovi), plus testovi modula (politike, ograničenja, 2FA).
- `scripts/bezbednost-provera.sh https://adresa-sajta` spolja, samo čitanje: zaglavlja, kolačići, osetljive putanje, listanje direktorijuma, TRACE, http→https, API bez tokena, CORS, admin, stranice grešaka. Pokrenuti posle svakog deploy-a na probni sajt i obavezno na loglasi.rs posle prelaska.
- `composer audit` (poznate ranjivosti paketa) pre svakog izlaska.

## Ručni pentest pre izlaska (lista)

1. **Nalog**: registracija sa `role=admin` u zahtevu; reset lozinke za tuđi email (odgovor ne sme da otkrije da li nalog postoji); stari link za reset posle promene lozinke; odjava sa ostalih uređaja posle promene lozinke.
2. **Oglasi**: izmena, brisanje, obnova, „prodato” tuđeg oglasa preko sajta i API-ja (promeniti broj u adresi); slika iz tuđeg oglasa (`/postavi-oglas/{moj}/slike/{tuđa}`); HTML i `javascript:` linkovi u opisu.
3. **Otpremanje**: `.php`, `.svg` sa skriptom, `.html`, GIF/JPG poliglot, slika od 20.000 × 20.000 px, 0 bajtova, HEIC; proveriti da u `storage/oglasi` završava samo `.webp`.
4. **Poruke i ocene**: poruka i ocena sa skriptom; čitanje tuđeg razgovora preko `/moj-nalog/poruke/{id}` i `/api/v1/conversations/{id}/messages`; ocena bez razgovora i dvostruka ocena.
5. **Plaćanja**: tuđa narudžbina i tuđa faktura (`/moj-nalog/narudzbine/{id}/racun/`); iznos ili proizvod izmenjen u zahtevu; otkazivanje plaćene narudžbine.
6. **Admin**: `/admin` bez 2FA; moderator na `/admin/settings` i `/admin/products`; obični korisnik na svim `/admin/*` adresama.
7. **API**: istekao access token (15 min), refresh token posle odjave, token posle brisanja naloga; `per_page=100000`; nepostojeći i negativni brojevi; SQL znakovi u pretrazi (`' OR 1=1 --`).
8. **Infrastruktura (Hostinger)**: `APP_DEBUG=false`, `APP_ENV=production`, `.env` van `public_html`, dozvole fajlova 644/755, `storage` nije izvršiv, SSL ocena A na ssllabs.com, rezervne kopije baze se ne nalaze u javnoj fascikli.

Alat za dublji test (po želji, samo na probnom sajtu i uz dogovor sa hostingom): OWASP ZAP „baseline scan” (pasivan) pa „active scan” sa nalogom za testiranje.
