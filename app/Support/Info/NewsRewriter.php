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
        NIKAKO: politika i političari, stranke, izbori, protesti, vlast i opozicija, litijum/Jadar/Rio Tinto, crna hronika, nesreće, smrti i čitulje, vesti koje nemaju veze sa ovim krajem.
        Vest JESTE iz kraja i kad se dogodila drugde, ako u njoj učestvuju ljudi, klubovi, škole ili ansambli iz Loznice i okoline (npr. naši sportisti osvojili medalje u Čačku, naš hor nastupio u Beogradu).

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

        self::$lastError = null;
        $prompt = self::utf8($prompt);

        try {
            $response = Http::timeout(60)->withHeaders([
                'x-api-key' => (string) config('info.news.api_key'),
                'anthropic-version' => '2023-06-01',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => (string) config('info.news.model'),
                'max_tokens' => 1500,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ]);
        } catch (\Throwable $e) {
            return self::fail('request failed: '.get_class($e).': '.$e->getMessage());
        }
        if (! $response->successful()) {
            return self::fail('status '.$response->status().': '.mb_substr($response->body(), 0, 300));
        }

        // Newer models can put other blocks (e.g. thinking) first: use the first text block.
        $blocks = (array) $response->json('content');
        $out = '';
        foreach ($blocks as $block) {
            if (($block['type'] ?? null) === 'text') {
                $out = (string) ($block['text'] ?? '');
                break;
            }
        }
        $stop = (string) $response->json('stop_reason');
        $types = implode(',', array_map(fn ($b) => (string) ($b['type'] ?? '?'), $blocks));
        $out = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($out)) ?? $out;
        if (! preg_match('/\{.*\}/s', $out, $m)) {
            return self::fail("no JSON (stop_reason {$stop}, blocks {$types}): ".mb_substr($out, 0, 200));
        }
        $data = json_decode($m[0], true);
        if (! is_array($data) || ! array_key_exists('allowed', $data)) {
            return self::fail("bad JSON (stop_reason {$stop}, ".json_last_error_msg().'): '.mb_substr($m[0], 0, 200));
        }

        return $data;
    }

    /** Why the last rewrite() returned null (never contains the key). */
    public static ?string $lastError = null;

    private static function fail(string $reason): null
    {
        self::$lastError = $reason;
        Log::error('info:fetch-news: '.$reason);

        return null;
    }

    /** Drops invalid UTF-8 (pages in other encodings) so the JSON body can always be encoded. */
    private static function utf8(string $text): string
    {
        return mb_check_encoding($text, 'UTF-8') ? $text : (string) mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    }
}
