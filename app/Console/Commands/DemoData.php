<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Services\Catalog;
use App\Services\SearchIndex;
use App\Support\Home\HomeSections;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Test site only: deletes every non-staff account with all user content (listings, images,
 * messages, orders, firms...) and fills the site with invented users and listings
 * (dev:listings). Admin and moderator accounts, the catalog, settings and SEO redirects stay.
 * Refuses on the live site; --sajt must repeat the host from APP_URL.
 */
class DemoData extends Command
{
    protected $signature = 'demo:data
        {--site= : host of this site from APP_URL, e.g. loglasi-custom.alphasoft.dev (safety check)}
        {--listings=400 : number of invented listings}
        {--keep-users : only add invented data, keep the existing users}
        {--no-images : do not add photos (demo:images)}';

    protected $description = 'Test site only: delete the real users and listings and add invented ones';

    /** Old name, kept so existing server scripts keep working. */
    protected $aliases = ['proba:podaci'];

    /** Hosts of the live site: the command never runs there. */
    public const LIVE_HOSTS = ['loglasi.rs', 'www.loglasi.rs'];

    /** User content, deleted completely (children first). */
    private const CONTENT_TABLES = [
        'saved_search_matches', 'saved_searches', 'user_notifications', 'favorites', 'seller_ratings',
        'messages', 'conversations', 'reports', 'user_blocks', 'listing_daily_stats', 'listing_search',
        'listing_field_values', 'listing_images', 'bump_credits', 'listing_slot_grants', 'plus_seller_terms',
        'orders', 'listings', 'firm_monthly_credits', 'firms', 'devices', 'jobs', 'failed_jobs',
    ];

    /** Files of users on the "public" disk. */
    private const FILE_DIRS = ['oglasi', 'avatars', 'firms'];

    public static function isLiveHost(): bool
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        return in_array($host, self::LIVE_HOSTS, true);
    }

    public function handle(Catalog $catalog, SearchIndex $index): int
    {
        $host = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));

        if (self::isLiveHost()) {
            $this->error('Ovo je pravi sajt (loglasi.rs). Komanda radi samo na probnom sajtu.');

            return self::FAILURE;
        }
        if ($host === '' || strtolower((string) $this->option('site')) !== $host) {
            $this->error("Za potvrdu dodaj --site={$host} (host iz APP_URL).");

            return self::FAILURE;
        }

        if (! $this->option('keep-users')) {
            $this->wipe();
        }

        $this->call('dev:listings', ['count' => max(1, (int) $this->option('listings')), '--demo' => true]);
        if (! $this->option('no-images')) {
            $this->call('demo:images', ['--site' => $host]);
        }

        Cache::flush();
        $index->rebuild();
        $catalog->flushCounts();
        HomeSections::flush();
        $this->call('seo:sitemap');

        $this->info('Gotovo: probni sajt ima izmišljene korisnike i oglase.');

        return self::SUCCESS;
    }

    private function wipe(): void
    {
        $staff = DB::table('users')->whereIn('role', [Role::Admin->value, Role::Moderator->value])->pluck('id')->all();

        Schema::withoutForeignKeyConstraints(function () use ($staff) {
            foreach (self::CONTENT_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }

            $users = DB::table('users')->whereNotIn('id', $staff ?: [0]);
            $emails = (clone $users)->pluck('email')->all();
            $ids = (clone $users)->pluck('id')->all();

            foreach (array_chunk($ids, 500) as $chunk) {
                DB::table('refresh_tokens')->whereIn('user_id', $chunk)->delete();
                DB::table('personal_access_tokens')->where('tokenable_type', 'App\\Models\\User')->whereIn('tokenable_id', $chunk)->delete();
                DB::table('sessions')->whereIn('user_id', $chunk)->delete();
                DB::table('audit_logs')->whereIn('user_id', $chunk)->update(['user_id' => null]);
                DB::table('users')->whereIn('id', $chunk)->delete();
            }
            foreach (array_chunk($emails, 500) as $chunk) {
                DB::table('password_reset_tokens')->whereIn('email', $chunk)->delete();
            }

            $this->line('Obrisano korisnika: '.count($ids).' (ostali admini i moderatori: '.count($staff).').');
        });

        DB::table('users')->whereIn('id', $staff ?: [0])->update(['avatar_path' => null]);

        $disk = Storage::disk('public');
        foreach (self::FILE_DIRS as $dir) {
            $disk->deleteDirectory($dir);
        }
        $this->line('Obrisane slike oglasa, profila i firmi.');
    }
}
