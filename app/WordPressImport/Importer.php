<?php

namespace App\WordPressImport;

use App\Support\Catalog\CatalogDefinition;
use App\WordPressImport\Steps\CategoryImport;
use App\WordPressImport\Steps\FavoriteImport;
use App\WordPressImport\Steps\FieldImport;
use App\WordPressImport\Steps\FirmImport;
use App\WordPressImport\Steps\GoneImport;
use App\WordPressImport\Steps\ListingImport;
use App\WordPressImport\Steps\LocationImport;
use App\WordPressImport\Steps\MessageImport;
use App\WordPressImport\Steps\OrderImport;
use App\WordPressImport\Steps\SettingsImport;
use App\WordPressImport\Steps\UserImport;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Import from the WordPress/Listivo database into the new schema. Every step can be repeated:
 * a row is looked up by wp_id / wp_term_id / wp_key and updated, not duplicated.
 */
class Importer
{
    /** Step order; the key is the name for --only. */
    public const STEPS = [
        'settings' => SettingsImport::class,
        'users' => UserImport::class,
        'categories' => CategoryImport::class,
        'locations' => LocationImport::class,
        'firms' => FirmImport::class,
        'fields' => FieldImport::class,
        'listings' => ListingImport::class,
        'favorites' => FavoriteImport::class,
        'messages' => MessageImport::class,
        'gone' => GoneImport::class,
        'orders' => OrderImport::class,
    ];

    public Report $report;

    /** @var array<string, array<int, int>> */
    private array $maps = [];

    /** @var int[] */
    public array $spamUsers;

    public ?Closure $output = null;

    public function __construct(
        public WordPress $wp,
        public bool $dryRun = false,
        public ?Carbon $since = null,
        public ?string $uploads = null,
    ) {
        $this->report = new Report;
        $this->spamUsers = array_map('intval', (array) config('wordpress_import.spam_users', []));
        $this->uploads = $uploads !== null && $uploads !== '' ? rtrim($uploads, '/') : null;
    }

    /** @param  string[]  $only */
    public function run(array $only = []): Report
    {
        $steps = $only ? array_intersect_key(self::STEPS, array_flip($only)) : self::STEPS;

        if ($this->dryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($steps as $name => $class) {
                $this->say('Uvoz: '.$name);
                (new $class($this))->run();
            }

            // WordPress fields come in as Listivo had them; the catalog definition makes them KP-like again.
            if (config('wordpress_import.catalog_definition') && array_intersect(array_keys($steps), ['categories', 'fields', 'listings']) !== []) {
                $this->say('Uvoz: catalog:apply');
                foreach ((new CatalogDefinition)->apply()['counts'] as $what => $n) {
                    $this->report->note('fields', "catalog:apply: {$what} {$n}");
                }
            }
        } finally {
            if ($this->dryRun) {
                DB::rollBack();
            }
        }

        return $this->report;
    }

    public function say(string $line): void
    {
        if ($this->output) {
            ($this->output)($line);
        }
    }

    /** The --since condition in UTC (WordPress *_gmt columns). */
    public function sinceUtc(): ?string
    {
        return $this->since?->copy()->utc()->format('Y-m-d H:i:s');
    }

    public function isSpam(int $wpUserId): bool
    {
        return in_array($wpUserId, $this->spamUsers, true);
    }

    /**
     * WP id → new id, from the rows already imported.
     *
     * @return array<int, int>
     */
    public function map(string $table, bool $fresh = false): array
    {
        if ($fresh || ! isset($this->maps[$table])) {
            $column = in_array($table, ['categories', 'locations'], true) ? 'wp_term_id' : 'wp_id';
            $query = DB::table($table)->whereNotNull($column);
            if ($table === 'listings') {
                $query->whereNull('deleted_at');
            }
            $this->maps[$table] = $query->pluck('id', $column)->mapWithKeys(fn ($id, $wp) => [(int) $wp => (int) $id])->all();
        }

        return $this->maps[$table];
    }

    public function forget(string $table): void
    {
        unset($this->maps[$table]);
    }

    /** Date from WordPress (GMT, or local as a fallback) in the app's time zone. */
    public function date(?string $gmt, ?string $local = null): ?Carbon
    {
        $tz = config('app.timezone');
        if ($gmt !== null && $gmt !== '' && ! str_starts_with($gmt, '0000')) {
            return Carbon::parse($gmt, 'UTC')->setTimezone($tz);
        }
        if ($local !== null && $local !== '' && ! str_starts_with($local, '0000')) {
            return Carbon::parse($local, 'Europe/Belgrade')->setTimezone($tz);
        }

        return null;
    }

    /**
     * Attachment data: file (the original if the image was scaled down), dimensions, alt.
     *
     * @param  int[]  $ids
     * @return array<int, array{file:string, width:?int, height:?int, alt:?string}>
     */
    public function attachments(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if (! $ids) {
            return [];
        }

        $existing = $this->wp->table('posts')->whereIn('ID', $ids)->where('post_type', 'attachment')->pluck('ID')->map(fn ($id) => (int) $id)->all();
        $meta = $this->wp->postMeta($existing, ['_wp_attached_file', '_wp_attachment_metadata', '_wp_attachment_image_alt']);

        $out = [];
        foreach ($existing as $id) {
            $file = (string) ($meta[$id]['_wp_attached_file'][0] ?? '');
            if ($file === '') {
                continue;
            }
            $md = Text::unserialize($meta[$id]['_wp_attachment_metadata'][0] ?? '');
            $md = is_array($md) ? $md : [];
            $width = isset($md['width']) ? (int) $md['width'] : null;
            $height = isset($md['height']) ? (int) $md['height'] : null;

            // WordPress 5.3+ scales big images down to "-scaled"; the original is in original_image.
            if (! empty($md['original_image']) && is_string($md['original_image'])) {
                $dir = dirname($file);
                $file = ($dir === '.' ? '' : $dir.'/').basename($md['original_image']);
                $width = $height = null;
            }

            $alt = trim((string) ($meta[$id]['_wp_attachment_image_alt'][0] ?? ''));
            $out[$id] = ['file' => $file, 'width' => $width, 'height' => $height, 'alt' => $alt !== '' ? $alt : null];
        }

        return $out;
    }

    /**
     * Copies a file from wp-content/uploads to the "public" disk.
     *
     * @return string copied | exists | missing | skipped
     */
    public function copyUpload(string $relative, string $destination): string
    {
        if ($this->uploads === null || $this->dryRun) {
            return 'skipped';
        }

        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if (str_contains($relative, '..')) {
            return 'missing';
        }

        $source = $this->uploads.'/'.$relative;
        if (! is_file($source)) {
            return 'missing';
        }

        $disk = Storage::disk('public');
        if ($disk->exists($destination) && $disk->size($destination) === filesize($source)) {
            return 'exists';
        }

        $stream = fopen($source, 'rb');
        $disk->put($destination, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        return 'copied';
    }

    /** File name safe for a path on disk. */
    public static function fileName(string $path): string
    {
        $name = basename(str_replace('\\', '/', $path));
        $name = (string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $name);

        return trim($name, '-.') ?: 'slika';
    }

    /**
     * Copies one attachment image and returns the path on disk, or null.
     */
    public function copyAttachment(int $attachmentId, string $dir, string $entity, string $label): ?string
    {
        $att = $this->attachments([$attachmentId])[$attachmentId] ?? null;
        if ($att === null) {
            $this->report->note($entity, "{$label}: prilog #{$attachmentId} ne postoji");

            return null;
        }

        $path = $dir.'/'.$attachmentId.'-'.self::fileName($att['file']);
        if ($this->copyUpload($att['file'], $path) === 'missing') {
            $this->report->note($entity, "{$label}: nema fajla {$att['file']}");
        }

        return $path;
    }
}
