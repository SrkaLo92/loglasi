<?php

namespace App\Support\Info;

use App\Models\InfoServiceNotice;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Planned power outages from Elektrodistribucija Srbije. Each page is a table
 * "Ogranak | Opština | Vreme | Ulice" for one date; only rows of our municipalities are kept,
 * stored once (hash of the row) and published right away with the source named.
 */
final class PowerOutages
{
    public const SOURCE = 'Elektrodistribucija Srbije';

    /** @return array{pages: int, failed: int, saved: int} */
    public static function sync(): array
    {
        $config = config('info.power');
        $result = ['pages' => 0, 'failed' => 0, 'saved' => 0];

        foreach ($config['areas'] as $area) {
            for ($day = 0; $day < (int) $config['days']; $day++) {
                $url = strtr($config['url'], ['{area}' => trim($area), '{day}' => $day]);
                try {
                    $response = Http::timeout((int) config('info.http_timeout'))->get($url);
                } catch (\Throwable $e) {
                    $result['failed']++;
                    Log::warning('info: power outages page failed', ['url' => $url, 'error' => $e->getMessage()]);

                    continue;
                }
                if (! $response->successful()) {
                    $result['failed']++;

                    continue;
                }
                $result['pages']++;
                foreach (self::parse($response->body(), $config['municipalities']) as $row) {
                    $result['saved'] += self::store($row, $url) ? 1 : 0;
                }
            }
        }

        return $result;
    }

    /**
     * @param  list<string>  $municipalities
     * @return list<array{branch: string, municipality: string, starts_at: CarbonImmutable, ends_at: ?CarbonImmutable, area: string}>
     */
    public static function parse(string $html, array $municipalities): array
    {
        if (trim($html) === '') {
            return [];
        }
        // The pages are old-style HTML; the charset is given so Cyrillic survives.
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?>'.self::utf8($html));
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $text = preg_replace('/\s+/u', ' ', (string) $doc->textContent) ?? '';
        if (! preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $text, $m)) {
            return [];
        }
        $date = CarbonImmutable::create((int) $m[3], (int) $m[2], (int) $m[1], 0, 0, 0, 'Europe/Belgrade');

        $rows = [];
        foreach ((new DOMXPath($doc))->query('//tr') ?: [] as $tr) {
            $cells = [];
            foreach ($tr->childNodes as $cell) {
                if (in_array(strtolower($cell->nodeName), ['td', 'th'], true)) {
                    $cells[] = trim(preg_replace('/\s+/u', ' ', $cell->textContent) ?? '');
                }
            }
            if (count($cells) < 4) {
                continue;
            }
            [$branch, $municipality, $time, $area] = $cells;
            if (! collect($municipalities)->contains(fn ($name) => Transliterator::same($name, $municipality))) {
                continue;
            }
            if (! preg_match('/(\d{1,2}):(\d{2})\s*[-–]\s*(\d{1,2}):(\d{2})/', $time, $t)) {
                continue;
            }
            $rows[] = [
                'branch' => Transliterator::toLatin($branch),
                'municipality' => self::title(Transliterator::toLatin($municipality)),
                'starts_at' => $date->setTime((int) $t[1], (int) $t[2]),
                'ends_at' => $date->setTime((int) $t[3], (int) $t[4]),
                'area' => Transliterator::toLatin($area),
            ];
        }

        return $rows;
    }

    /** @param array{branch: string, municipality: string, starts_at: CarbonImmutable, ends_at: ?CarbonImmutable, area: string} $row */
    private static function store(array $row, string $url): bool
    {
        $hash = sha1('power|'.$row['municipality'].'|'.$row['starts_at']->toIso8601String().'|'.$row['area']);
        $notice = InfoServiceNotice::query()->firstOrCreate(['external_hash' => $hash], [
            'kind' => 'power',
            'municipality' => $row['municipality'],
            'place' => self::firstPlace($row['area']),
            'area' => $row['area'],
            'starts_at' => $row['starts_at'],
            'ends_at' => $row['ends_at'],
            'source_name' => self::SOURCE,
            'source_url' => $url,
            'published' => true,
        ]);

        return $notice->wasRecentlyCreated;
    }

    /** "Naselje: Lešnica: ulice..." or "Lešnica: ..." -> "Lešnica" (shown as the title of the row). */
    private static function firstPlace(string $area): ?string
    {
        $area = preg_replace('/^\s*(naselje|naselja|mesto)\s*:?\s*/iu', '', $area) ?? $area;
        $first = trim((string) preg_split('/[:,;]/u', $area)[0]);

        return $first !== '' && mb_strlen($first) <= 60 ? self::title($first) : null;
    }

    private static function title(string $text): string
    {
        return mb_convert_case(mb_strtolower($text), MB_CASE_TITLE, 'UTF-8');
    }

    private static function utf8(string $html): string
    {
        if (mb_check_encoding($html, 'UTF-8')) {
            return $html;
        }

        return mb_convert_encoding($html, 'UTF-8', 'Windows-1251');
    }
}
