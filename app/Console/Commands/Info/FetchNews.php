<?php

namespace App\Console\Commands\Info;

use App\Models\InfoPost;
use App\Models\InfoSource;
use App\Support\Info\NewsRewriter;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Hourly: reads the RSS feeds of active sources, drops politics/crime/deaths, has the rest retold in our
 * own words and leaves them in "Na čekanju". Nothing is published without an editor.
 */
class FetchNews extends Command
{
    protected $signature = 'info:fetch-news {--dry : only show what would be added}';

    protected $description = 'Info portal: pull new local articles, retell them in our words, leave them pending';

    private const SEEN_FILE = 'info-news-seen.json';

    public function handle(): int
    {
        if (! config('info.enabled') || ! NewsRewriter::enabled()) {
            $this->line('Portal off or no API key, nothing to do.');

            return self::SUCCESS;
        }

        $seen = $this->seen();
        $limit = (int) config('info.news.max_per_run');
        $counts = ['added' => 0, 'blocked' => 0, 'declined' => 0, 'failed' => 0];

        foreach (InfoSource::query()->where('active', true)->whereNotNull('feed_url')->get() as $source) {
            foreach ($this->items((string) $source->feed_url) as $item) {
                if ($limit <= $counts['added'] + $counts['declined']) {
                    break 2;
                }
                $hash = sha1(InfoPost::normalizeUrl($item['link']));
                if (isset($seen[$hash]) || InfoPost::query()->where('source_hash', $hash)->exists()) {
                    continue;
                }
                $seen[$hash] = now()->toDateString();

                if (NewsRewriter::blocked($item['title'].' '.$item['text'])) {
                    $counts['blocked']++;

                    continue;
                }
                $text = $this->articleText($item['link']) ?: $item['text'];
                $data = NewsRewriter::rewrite((string) $source->name, $item['title'], Str::limit($text, 4000));
                if ($data === null) {
                    unset($seen[$hash]); // try again next hour
                    $counts['failed']++;

                    continue;
                }
                if (! $data['allowed'] || blank($data['title'] ?? null) || blank($data['summary'] ?? null)
                    || NewsRewriter::blocked($data['title'].' '.$data['summary'])) {
                    $counts['declined']++;
                    $this->line('  - '.$item['title'].' ('.($data['reason'] ?? 'nije za portal').')');

                    continue;
                }

                $this->line('  + '.$data['title']);
                if (! $this->option('dry')) {
                    $this->store($source, $item, $data);
                }
                $counts['added']++;
            }
        }

        if (! $this->option('dry')) {
            $this->saveSeen($seen);
        }
        $this->info(sprintf('Added %d, blocked by words %d, declined %d, failed %d.', ...array_values($counts)));

        return self::SUCCESS;
    }

    /** @param  array{link: string}  $item */
    private function store(InfoSource $source, array $item, array $data): void
    {
        $type = in_array($data['type'] ?? null, ['news', 'event', 'notice'], true) ? $data['type'] : 'news';
        $starts = null;
        if ($type === 'event' && filled($data['event_starts_at'] ?? null)) {
            try {
                $starts = Carbon::parse($data['event_starts_at'], 'Europe/Belgrade');
            } catch (\Throwable) {
                $starts = null;
            }
        }
        InfoPost::query()->create([
            'type' => $type === 'event' && ! $starts ? 'news' : $type,
            'status' => 'pending',
            'origin' => 'automation',
            'needs_check' => true,
            'title' => Str::limit((string) $data['title'], 150, ''),
            'summary' => Str::limit((string) $data['summary'], 2000, ''),
            'topic' => isset($data['topic'], config('info.topics')[$data['topic']]) ? $data['topic'] : null,
            'place' => filled($data['place'] ?? null) ? Str::limit((string) $data['place'], 80, '') : null,
            'source_name' => $source->name,
            'source_url' => $item['link'],
            'event_starts_at' => $starts,
            'event_venue' => filled($data['event_venue'] ?? null) ? Str::limit((string) $data['event_venue'], 150, '') : null,
        ]);
    }

    /** @return list<array{title: string, link: string, text: string}> */
    private function items(string $feedUrl): array
    {
        try {
            $response = Http::timeout(max(10, (int) config('info.http_timeout')))->get($feedUrl);
        } catch (\Throwable) {
            return [];
        }
        if (! $response->successful()) {
            return [];
        }
        $xml = @simplexml_load_string($response->body(), 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
        if (! $xml) {
            return [];
        }

        $since = now()->subDays((int) config('info.news.max_age_days'));
        $items = [];
        foreach ($xml->channel->item ?? [] as $node) {
            $date = (string) $node->pubDate;
            try {
                if ($date !== '' && Carbon::parse($date)->lt($since)) {
                    continue;
                }
            } catch (\Throwable) {
            }
            $link = trim((string) $node->link);
            if ($link === '' || ! filter_var($link, FILTER_VALIDATE_URL)) {
                continue;
            }
            $items[] = [
                'title' => trim(html_entity_decode(strip_tags((string) $node->title))),
                'link' => $link,
                'text' => trim(html_entity_decode(strip_tags((string) $node->description))),
            ];
        }

        return $items;
    }

    /** Readable text of the article page (paragraphs only), so the retelling has enough to go on. */
    private function articleText(string $url): string
    {
        try {
            $html = Http::timeout(max(10, (int) config('info.http_timeout')))->get($url)->body();
        } catch (\Throwable) {
            return '';
        }
        preg_match_all('~<p[^>]*>(.*?)</p>~is', $html, $m);
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(implode("\n", $m[1] ?? [])))) ?? '');

        return mb_strlen($text) > 200 ? $text : '';
    }

    /** @return array<string, string> */
    private function seen(): array
    {
        $data = json_decode((string) @file_get_contents(storage_path('app/'.self::SEEN_FILE)), true);

        return is_array($data) ? $data : [];
    }

    /** @param  array<string, string>  $seen */
    private function saveSeen(array $seen): void
    {
        $cutoff = now()->subDays(30)->toDateString();
        $seen = array_filter($seen, fn ($day) => $day >= $cutoff);
        file_put_contents(storage_path('app/'.self::SEEN_FILE), json_encode($seen));
    }
}
