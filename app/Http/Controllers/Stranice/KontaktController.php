<?php

namespace App\Http\Controllers\Stranice;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stranice\KontaktRequest;
use App\Mail\KontaktPoruka;
use App\Support\Settings;
use App\Support\Stranice\Cenovnik;
use App\Support\Stranice\PageText;
use App\Support\Stranice\Telefon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/** Kontakt: sopstvena forma umesto CF7, sa zamkom za botove, vremenskom zamkom i limitom po IP adresi. */
class KontaktController extends Controller
{
    public function show(Request $request): View
    {
        $teme = PageText::list('kontakt.teme');
        // ?tema=firma sa strane Za firme bira „Prijava firme (Biznis paket)”.
        $izabrana = $request->query('tema') === 'firma' ? ($teme[1] ?? null) : null;
        $phone = (string) Settings::get('contact_phone', '');

        return view('stranice.kontakt', [
            'teme' => $teme,
            'izabranaTema' => old('tema', $izabrana),
            'token' => Crypt::encryptString((string) now()->getTimestamp()),
            'email' => (string) Settings::get('contact_email'),
            'phone' => Telefon::normalize($phone),
            'phoneText' => Telefon::display($phone),
            'hours' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) Settings::get('contact_hours', '')) ?: []))),
            'poslato' => (bool) $request->session()->get('kontakt_poslato'),
            'title' => PageText::get('seo.kontakt.title'),
            'description' => PageText::get('seo.kontakt.description'),
            'canonical' => '/kontakt/',
        ]);
    }

    public function send(KontaktRequest $request): RedirectResponse
    {
        $cfg = config('stranice.kontakt');
        $key = 'kontakt:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) $cfg['limit'])) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

            return back()->withInput()->withErrors([
                'forma' => sprintf('Poslali ste više poruka za kratko vreme. Pokušajte ponovo za %d %s.', $minutes, Cenovnik::plural($minutes, 'minut', 'minuta', 'minuta')),
            ]);
        }

        RateLimiter::hit($key, (int) $cfg['limit_minuta'] * 60);

        // Zamka: polje koje ljudi ne vide. Botu se pokaže uspeh, a poruka se ne šalje.
        if (trim((string) $request->input('sajt', '')) !== '') {
            Log::info('Kontakt forma: odbijena poruka (zamka).', ['ip' => $request->ip()]);

            return $this->done();
        }

        $sentAt = $this->formTime((string) $request->input('_t', ''));
        if ($sentAt === null || now()->getTimestamp() - $sentAt > (int) $cfg['max_sati'] * 3600) {
            return back()->withInput()->withErrors([
                'forma' => 'Forma je istekla. Proverite podatke i pošaljite poruku ponovo.',
            ]);
        }

        if (now()->getTimestamp() - $sentAt < (int) $cfg['min_sekundi']) {
            Log::info('Kontakt forma: odbijena poruka (prebrzo poslata).', ['ip' => $request->ip()]);

            return $this->done();
        }

        $data = $request->validated();

        Mail::to((string) Settings::get('contact_email'))->queue(new KontaktPoruka([
            'ime' => $data['ime'],
            'email' => $data['email'],
            'telefon' => Telefon::normalize($data['telefon'] ?? ''),
            'tema' => $data['tema'],
            'poruka' => $data['poruka'],
            'ip' => $request->ip(),
        ]));

        return $this->done();
    }

    private function done(): RedirectResponse
    {
        return redirect()->to(url_slash('kontakt').'#poruka-poslata')->with('kontakt_poslato', true);
    }

    private function formTime(string $token): ?int
    {
        if ($token === '') {
            return null;
        }

        try {
            $value = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($value) ? (int) $value : null;
    }
}
