#!/usr/bin/env bash
# Spoljna bezbednosna provera sajta, samo čitanje (ništa ne menja i ne pogađa lozinke).
# Upotreba: scripts/bezbednost-provera.sh https://loglasi-custom.alphasoft.dev
# Izlaz: PASS/FAIL po stavci i broj grešaka (exit 1 ako ih ima). Spisak ručnih provera: docs/bezbednost.md.
set -u
BASE="${1:?Navedite adresu sajta, npr. https://loglasi-custom.alphasoft.dev}"
BASE="${BASE%/}"
FAILS=0
pass() { printf '  PASS  %s\n' "$1"; }
fail() { printf '  FAIL  %s\n' "$1"; FAILS=$((FAILS + 1)); }
status() { curl -s -o /dev/null -w '%{http_code}' --max-time 20 "$@"; }

echo "== Zaglavlja početne strane"
HEAD=$(curl -sS -D - -o /dev/null --max-time 20 "$BASE/")
for h in 'x-content-type-options: nosniff' 'x-frame-options: sameorigin' 'referrer-policy:' 'content-security-policy:' 'permissions-policy:'; do
  grep -qi "^$h" <<<"$HEAD" && pass "$h" || fail "nedostaje $h"
done
[[ "$BASE" == https://* ]] && { grep -qi '^strict-transport-security:' <<<"$HEAD" && pass 'HSTS' || fail 'nedostaje Strict-Transport-Security'; }
grep -qiE '^x-powered-by:|^server: .*[0-9]' <<<"$HEAD" && fail 'server otkriva verziju (X-Powered-By ili Server sa brojem)' || pass 'server ne otkriva verziju'

echo "== Kolačići"
COOKIES=$(grep -i '^set-cookie:' <<<"$HEAD")
if [[ -n "$COOKIES" ]]; then
  while read -r c; do
    name=$(sed -E 's/^set-cookie: *([^=]+)=.*/\1/I' <<<"$c")
    [[ "$BASE" == https://* ]] && { grep -qi 'secure' <<<"$c" && pass "$name: Secure" || fail "$name: nema Secure"; }
    grep -qi 'samesite' <<<"$c" && pass "$name: SameSite" || fail "$name: nema SameSite"
    [[ "$name" == *session* ]] && { grep -qi 'httponly' <<<"$c" && pass "$name: HttpOnly" || fail "$name: nema HttpOnly"; }
  done <<<"$COOKIES"
fi

echo "== Osetljive putanje (moraju biti 403/404/410)"
for p in /.env /.env.backup /.git/config /.git/HEAD /composer.json /composer.lock /artisan /storage/logs/laravel.log \
         /vendor/autoload.php /phpinfo.php /info.php /wp-config.php /.htaccess /server-status /storage/test.php /database/database.sqlite; do
  code=$(status -L "$BASE$p")
  [[ "$code" =~ ^(403|404|410)$ ]] && pass "$p → $code" || fail "$p → $code"
done

echo "== Listanje direktorijuma"
for d in /storage/ /storage/oglasi/ /css/ /js/ /img/; do
  body=$(curl -s --max-time 20 "$BASE$d")
  grep -qi 'Index of' <<<"$body" && fail "$d prikazuje listu fajlova" || pass "$d bez liste fajlova"
done

echo "== Metode"
code=$(status -X TRACE "$BASE/")
[[ "$code" =~ ^(405|403|501|400)$ ]] && pass "TRACE → $code" || fail "TRACE → $code"

echo "== HTTP → HTTPS"
if [[ "$BASE" == https://* ]]; then
  HOST=${BASE#https://}
  loc=$(curl -s -o /dev/null -w '%{redirect_url}' --max-time 20 "http://$HOST/")
  [[ "$loc" == https://* ]] && pass "http preusmerava na $loc" || fail "http ne preusmerava na https"
fi

echo "== API"
code=$(status "$BASE/api/v1/me")
[[ "$code" == 401 ]] && pass "/api/v1/me bez tokena → 401" || fail "/api/v1/me bez tokena → $code"
body=$(curl -s --max-time 20 "$BASE/api/v1/listings?per_page=5")
grep -qiE '"email"|"password"|"phone":"\+?[0-9]' <<<"$body" && fail 'lista oglasa otkriva email, lozinku ili telefon' || pass 'lista oglasa bez emaila, lozinke i telefona'
code=$(status -X OPTIONS -H 'Origin: https://zlonameran.example' -H 'Access-Control-Request-Method: POST' "$BASE/api/v1/auth/login")
acao=$(curl -s -D - -o /dev/null -X OPTIONS -H 'Origin: https://zlonameran.example' -H 'Access-Control-Request-Method: POST' "$BASE/api/v1/auth/login" | grep -i '^access-control-allow-origin:')
grep -qiE 'zlonameran|\*' <<<"$acao" && fail "CORS dozvoljava strani sajt ($acao)" || pass 'CORS ne dozvoljava strani sajt'

echo "== Admin"
code=$(status "$BASE/admin/login")
[[ "$code" =~ ^(200|302)$ ]] && pass "/admin/login → $code (prijava sa 2FA)" || fail "/admin/login → $code"
code=$(status "$BASE/admin")
[[ "$code" =~ ^(302|401|403)$ ]] && pass "/admin bez prijave → $code" || fail "/admin bez prijave → $code"

echo "== Greške ne otkrivaju kod"
body=$(curl -s --max-time 20 "$BASE/oglas/%27%22%3E%3Cscript%3E/")
grep -qiE 'Whoops|Stack trace|vendor/laravel|SQLSTATE' <<<"$body" && fail 'stranica greške otkriva detalje (APP_DEBUG?)' || pass 'stranica greške bez detalja'
grep -qF '"><script>' <<<"$body" && fail 'URL se ispisuje bez escapovanja' || pass 'URL je escapovan'

echo
[[ $FAILS -eq 0 ]] && echo "Sve provere prošle." || echo "Neuspešnih provera: $FAILS"
exit $(( FAILS > 0 ))
