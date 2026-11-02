<?php

namespace App\Console\Commands\Info;

use App\Models\InfoPharmacyDuty;
use App\Models\InfoPost;
use App\Models\InfoServiceNotice;
use App\Support\Info\InfoCity;
use App\Support\Info\InfoToday;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;

/**
 * Imports a JSON file of drafts made off the server (news summaries in our own words, events, water
 * and road notices, pharmacy duties). Same rules as the drafts API: duplicates by source URL are skipped,
 * posts land in "Na čekanju" unless --publish is given.
 */
class ImportDrafts extends Command
{
    protected $signature = 'info:import-drafts {file : JSON file with a list of drafts} {--publish : publish posts and show notices right away}';

    protected $description = 'Info portal: import drafts (posts, events, water/road notices, pharmacies) from a JSON file';

    public function handle(): int
    {
        $file = (string) $this->argument('file');
        $items = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if (! is_array($items)) {
            $this->error('The file is missing or is not a JSON list.');

            return self::FAILURE;
        }

        $publish = (bool) $this->option('publish');
        $counts = ['created' => 0, 'duplicate' => 0, 'invalid' => 0];
        foreach ($items as $i => $item) {
            $v = Validator::make(is_array($item) ? $item : [], [
                'kind' => ['nullable', 'in:post,water,road,pharmacy'],
                'type' => ['nullable', 'in:news,event,notice,story,guide'],
                'title' => ['required_unless:kind,water,road', 'nullable', 'string', 'max:160'],
                'summary' => ['required_unless:kind,pharmacy', 'nullable', 'string', 'max:3000'],
                'topic' => ['nullable', 'string', 'max:32'],
                'place' => ['nullable', 'string', 'max:80'],
                'source_name' => ['required', 'string', 'max:120'],
                'source_url' => ['required', 'url', 'max:700'],
                'published_at' => ['nullable', 'date'],
                'event_starts_at' => ['nullable', 'date'],
                'event_ends_at' => ['nullable', 'date'],
                'event_venue' => ['nullable', 'string', 'max:160'],
                'starts_at' => ['required_if:kind,water,road', 'nullable', 'date'],
                'ends_at' => ['nullable', 'date'],
                'date' => ['required_if:kind,pharmacy', 'nullable', 'date'],
                'address' => ['nullable', 'string', 'max:160'],
                'phone' => ['nullable', 'string', 'max:60'],
                'hours' => ['nullable', 'string', 'max:60'],
                'city' => ['nullable', 'in:'.implode(',', array_keys((array) config('info.cities')))],
            ]);
            if ($v->fails()) {
                $counts['invalid']++;
                $this->warn('#'.$i.': '.$v->errors()->first());

                continue;
            }
            $counts[$this->store($v->validated(), $publish)]++;
        }

        InfoToday::forget();
        $this->info("Added {$counts['created']}, duplicates {$counts['duplicate']}, invalid {$counts['invalid']}.");

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $data */
    private function store(array $data, bool $publish): string
    {
        $kind = $data['kind'] ?? 'post';
        $city = $data['city'] ?? InfoCity::current();
        $at = fn (?string $value) => $value ? Carbon::parse($value, 'Europe/Belgrade') : null;

        if ($kind === 'pharmacy') {
            $date = Carbon::parse($data['date'])->toDateString();
            if (InfoPharmacyDuty::query()->inCity($city)->whereDate('date', $date)->exists()) {
                return 'duplicate';
            }
            InfoPharmacyDuty::query()->create(['city' => $city, 'date' => $date, 'name' => $data['title'], 'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null, 'hours' => $data['hours'] ?? null]);

            return 'created';
        }

        if ($kind === 'water' || $kind === 'road') {
            $hash = sha1($kind.'|'.InfoPost::normalizeUrl($data['source_url']).'|'.$data['starts_at']);
            $notice = InfoServiceNotice::query()->firstOrCreate(['external_hash' => $hash], [
                'city' => $city, 'kind' => $kind, 'place' => $data['place'] ?? null, 'area' => $data['summary'],
                'starts_at' => $at($data['starts_at']), 'ends_at' => $at($data['ends_at'] ?? null),
                'source_name' => $data['source_name'], 'source_url' => $data['source_url'], 'published' => $publish,
            ]);

            return $notice->wasRecentlyCreated ? 'created' : 'duplicate';
        }

        if (InfoPost::query()->where('source_hash', sha1(InfoPost::normalizeUrl($data['source_url'])))->exists()) {
            return 'duplicate';
        }
        $publishedAt = $at($data['published_at'] ?? null);
        InfoPost::query()->create([
            'city' => $city,
            'type' => $data['type'] ?? 'news',
            'status' => $publish ? 'published' : 'pending',
            'published_at' => $publish ? ($publishedAt && $publishedAt->isPast() ? $publishedAt : now()) : null,
            'origin' => 'automation',
            'title' => $data['title'],
            'summary' => $data['summary'],
            'topic' => isset($data['topic'], config('info.topics')[$data['topic']]) ? $data['topic'] : null,
            'place' => $data['place'] ?? null,
            'source_name' => $data['source_name'],
            'source_url' => $data['source_url'],
            'event_starts_at' => $at($data['event_starts_at'] ?? null),
            'event_ends_at' => $at($data['event_ends_at'] ?? null),
            'event_venue' => $data['event_venue'] ?? null,
        ]);

        return 'created';
    }
}
