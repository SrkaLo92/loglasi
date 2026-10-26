<?php

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pages\ContactRequest;
use App\Mail\ContactMessage;
use App\Support\Pages\PageText;
use App\Support\Pages\Pricing;
use App\Support\Phone;
use App\Support\Settings;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/** Contact: own form in place of CF7, with a honeypot, a timing check and a per-IP rate limit. */
class ContactController extends Controller
{
    public function show(Request $request): View
    {
        $teme = PageText::list('kontakt.teme');
        // ?tema=firma from the firms page preselects the "Za firme" topic.
        $izabrana = $request->query('tema') === 'firma'
            ? (collect($teme)->first(fn ($t) => is_string($t) && str_contains(mb_strtolower($t), 'firm')))
            : null;
        $phone = (string) Settings::get('contact_phone', '');

        return view('pages.contact', [
            'teme' => $teme,
            'izabranaTema' => old('tema', $izabrana),
            'token' => Crypt::encryptString((string) now()->getTimestamp()),
            'email' => (string) Settings::get('contact_email'),
            'phone' => Phone::normalize($phone),
            'phoneText' => Phone::display($phone),
            'hours' => array_values(array_filter(array_map('trim', preg_split('/\R/', (string) Settings::get('contact_hours', '')) ?: []))),
            'poslato' => (bool) $request->session()->get('kontakt_poslato'),
            'title' => PageText::get('seo.kontakt.title'),
            'description' => PageText::get('seo.kontakt.description'),
            'canonical' => '/kontakt/',
        ]);
    }

    public function send(ContactRequest $request): RedirectResponse
    {
        $cfg = config('pages.contact');
        $key = 'kontakt:'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, (int) $cfg['limit'])) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

            return back()->withInput()->withErrors([
                'forma' => strtr(PageText::get('kontakt.poruka.limit'), ['{minuta}' => $minutes.' '.Pricing::plural($minutes, 'minut', 'minuta', 'minuta')]),
            ]);
        }

        RateLimiter::hit($key, (int) $cfg['limit_minutes'] * 60);

        // Trap: a field people do not see. A bot is shown success, and the message is not sent.
        if (trim((string) $request->input('sajt', '')) !== '') {
            Log::info('Kontakt forma: odbijena poruka (zamka).', ['ip' => $request->ip()]);

            return $this->done();
        }

        $sentAt = $this->formTime((string) $request->input('_t', ''));
        if ($sentAt === null || now()->getTimestamp() - $sentAt > (int) $cfg['max_hours'] * 3600) {
            return back()->withInput()->withErrors([
                'forma' => PageText::get('kontakt.poruka.istekla'),
            ]);
        }

        if (now()->getTimestamp() - $sentAt < (int) $cfg['min_seconds']) {
            Log::info('Kontakt forma: odbijena poruka (prebrzo poslata).', ['ip' => $request->ip()]);

            return $this->done();
        }

        $data = $request->validated();

        Mail::to((string) Settings::get('contact_email'))->queue(new ContactMessage([
            'ime' => $data['ime'],
            'email' => ContactRequest::email($data['kontakt']) ?? '',
            'telefon' => ContactRequest::email($data['kontakt']) === null ? Phone::normalize($data['kontakt']) : '',
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
