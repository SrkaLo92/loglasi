# loglasi.rs: backend i admin panel

Novi backend za loglasi.rs (oglasi za Loznicu i okolinu): PHP 8.3, Laravel 13, MySQL/MariaDB, stranice se renderuju na serveru, admin panel na `/admin`.

## Pokretanje lokalno

```bash
composer install
cp .env.example .env
php artisan key:generate
# baza: MySQL/MariaDB, podaci u .env (DB_DATABASE=loglasi)
php artisan migrate --seed
php artisan serve
```

Testovi koriste bazu `loglasi_test` (vidi `phpunit.xml`), druga baza se zadaje sa `DB_DATABASE=... php artisan test`.

```bash
php artisan test
vendor/bin/pint        # stil koda
```

## Struktura

| Gde | Šta |
| --- | --- |
| `app/Models`, `app/Enums` | model podataka (oglasi, korisnici, firme, kategorije, polja, poruke, narudžbine) |
| `app/Support` | zajednički alati: `Price` (EUR/RSD), `Settings` (podešavanja iz admina) |
| `app/Http/Controllers/<Modul>` | kontroleri po modulu |
| `routes/web/*.php`, `routes/api/*.php` | rute po modulu; API je na `/api/v1` |
| `resources/views` | Blade šabloni |
| `public/css`, `public/js` | CSS i JS bez build koraka |
| `docs/` | konvencije i beleške po modulima |

Pravila rada: `docs/KONVENCIJE.md`.
