<?php

namespace App\Support\Info;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Asks Claude whether a local article belongs on the portal (curiosities, service info, culture, events;
 * never politics, lithium, crime or deaths) and, if it does, retells it in our own words.
 */
class NewsRewriter
{
    public static function enabled(): bool
    {
        return filled(config('info.news.api_key'));
    }

    /** Lower-case keyword check before anything is sent anywhere. */
    public static function blocked(string $text): bool
    {
        $text = mb_strtolower($text);
        foreach ((array) config('info.news.blocked_words') as $word) {
            if ($word !== '' && preg_match('/(?<!\\p{L})'.preg_quote(mb_strtolower($word), '/').'/u', $text)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{allowed: bool, reason?: string, type?: string, topic?: string, title?: string, summary?: string, place?: ?string, event_starts_at?: ?string, event_venue?: ?string}|null
     */
    public static function rewrite(string $sourceName, string $title, string $text): ?array
    {
        $topics = implode(', ', array_keys((array) config('info.topics')));
        $prompt = <<<TXT
        Ti si urednik lokalnog portala info.loglasi.rs za Loznicu i okolinu (Banja Koviljača, Lešnica, Mali Zvornik, Krupanj, Ljubovija).
        Portal objavljuje SAMO: zanimljivosti iz grada, servisne informacije (voda, struja, putevi, radovi, rasporedi, ustanove), kulturu i događaje (koncerti, izložbe, manifestacije, predstave, sajmovi, sportski događaji).
        NIKAKO: politika i političari, stranke, izbori, protesti, vlast i opozicija, litijum/Jadar/Rio Tinto, crna hronika, nesreće, smrti i čitulje, vesti koje nisu iz ovog kraja.

        Članak sa portala {$sourceName}:
        Naslov: {$title}
        Tekst: {$text}

        Odgovori SAMO JSON objektom, bez ičeg drugog:
        {"allowed": true/false, "reason": "kratko zašto",
         "type": "news|event|notice", "topic": jedno od [{$topics}],
         "title": "naš naslov, do 90 znakova, drugačiji od originala",
         "summary": "2-4 rečenice našim rečima, gramatički ispravno, srpski latinicom, bez preuzimanja rečenica iz originala, bez senzacionalizma",
         "place": "mesto ili null", "event_starts_at": "YYYY-MM-DD HH:MM ili null", "event_venue": "mesto održavanja ili null"}
        Ako allowed=false, ostala polja mogu biti null.
        TXT;

        try {
            $response = Http::timeout(60)->withHeaders([
                'x-api-key' => (string) config('info.news.api_key'),
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => (string) config('info.news.model'),
                'max_tokens' => 800,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);
        } catch (\Throwable $e) {
            Log::warning('info:fetch-news API call failed: '.$e->getMessage());

            return null;
        }
        if (! $response->successful()) {
            Log::warning('info:fetch-news API status '.$response->status());

            return null;
        }

        $out = (string) $response->json('content.0.text');
        if (! preg_match('/\{.*\}/s', $out, $m)) {
            return null;
        }
        $data = json_decode($m[0], true);

        return is_array($data) && array_key_exists('allowed', $data) ? $data : null;
    }
}
