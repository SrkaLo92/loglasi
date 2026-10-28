<?php

namespace App\Services\Admin;

use App\Support\Push\FcmClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Numbers for the "Stanje sistema" admin page: scheduler, queue, errors, settings that matter. */
class SystemHealth
{
    /** Written by the scheduler every minute (see NotificationsServiceProvider). */
    public const HEARTBEAT = 'system.schedule_heartbeat';

    private const LOG_TAIL_BYTES = 262144;

    /** @return array{at: ?int, ok: bool} the last schedule:run, ok when it ran in the last 3 minutes */
    public function scheduler(): array
    {
        $at = Cache::get(self::HEARTBEAT);

        return ['at' => $at ? (int) $at : null, 'ok' => $at !== null && time() - (int) $at < 180];
    }

    /** @return array{waiting: int, delayed: int, oldest: ?int, failed: int} */
    public function queue(): array
    {
        $now = time();

        return [
            'waiting' => DB::table('jobs')->where('available_at', '<=', $now)->count(),
            'delayed' => DB::table('jobs')->where('available_at', '>', $now)->count(),
            'oldest' => ($at = DB::table('jobs')->where('available_at', '<=', $now)->min('available_at')) ? (int) $at : null,
            'failed' => DB::table('failed_jobs')->count(),
        ];
    }

    /** @return list<array{id: int, uuid: string, job: string, error: string, at: string}> newest first */
    public function failedJobs(int $limit = 20): array
    {
        return DB::table('failed_jobs')->orderByDesc('id')->limit($limit)->get()->map(function ($row) {
            $payload = json_decode((string) $row->payload, true) ?: [];

            return [
                'id' => (int) $row->id,
                'uuid' => (string) $row->uuid,
                'job' => class_basename((string) ($payload['displayName'] ?? 'Job')),
                'error' => mb_strimwidth(strtok((string) $row->exception, "\n") ?: '', 0, 300, '…'),
                'at' => (string) $row->failed_at,
            ];
        })->all();
    }

    /** @return list<array{at: string, level: string, message: string}> errors and warnings from the newest log, newest first */
    public function logErrors(int $limit = 20): array
    {
        $files = glob(storage_path('logs/laravel*.log')) ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $file = $files[0] ?? null;
        if ($file === null || ! is_readable($file)) {
            return [];
        }

        $size = filesize($file);
        $handle = fopen($file, 'r');
        fseek($handle, max(0, $size - self::LOG_TAIL_BYTES));
        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        preg_match_all('/^\[(\d{4}-\d\d-\d\d[ T]\d\d:\d\d:\d\d)[^\]]*\] \w+\.(EMERGENCY|ALERT|CRITICAL|ERROR|WARNING): (.*)$/m', $tail, $m, PREG_SET_ORDER);

        return array_map(fn ($row) => [
            'at' => $row[1],
            'level' => $row[2],
            'message' => mb_strimwidth($row[3], 0, 300, '…'),
        ], array_slice(array_reverse($m), 0, $limit));
    }

    /** @return array<string, string> label => value */
    public function settings(): array
    {
        $yes = fn ($v) => $v ? 'da' : 'ne';

        return [
            'Okruženje' => (string) app()->environment(),
            'Debug' => $yes(config('app.debug')),
            'Mejlovi zadržani (hold)' => $yes(config('notifications.hold')),
            'Push (FCM)' => $yes(app(FcmClient::class)->enabled()),
            'Probna plaćanja' => $yes(config('payments.test_mode')),
            'Keš stranica za posetioce' => $yes(config('page_cache.enabled')),
            'Keš / sesije / red' => config('cache.default').' / '.config('session.driver').' / '.config('queue.default'),
            'PHP / Laravel' => PHP_VERSION.' / '.app()->version(),
            'Slobodno na disku' => ($free = @disk_free_space(base_path())) ? round($free / 1073741824, 1).' GB' : '—',
        ];
    }

    /** @return list<array{campaign: string, sent: int, last: ?string}> */
    public function announcements(): array
    {
        return DB::table('announcement_sends')->selectRaw('campaign, COUNT(*) AS sent, MAX(created_at) AS last')
            ->groupBy('campaign')->orderByDesc('last')->get()
            ->map(fn ($r) => ['campaign' => (string) $r->campaign, 'sent' => (int) $r->sent, 'last' => $r->last])->all();
    }
}
