<?php

namespace App\Console\Commands;

use App\Support\Seo\UrlTables;
use App\Uvoz\Steps\GoneImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads the redirect file ("path target" per line) into `redirects` without a new import, and takes
 * those paths off the 410 list. A category target that does not exist goes to /oglasi/.
 */
class SeoPreusmerenja extends Command
{
    protected $signature = 'seo:preusmerenja {file? : default config uvoz.redirect_file}';

    protected $description = 'Učitava 301 preusmerenja iz fajla „putanja odredište” i skida te adrese sa 410 liste';

    public function handle(): int
    {
        $file = (string) ($this->argument('file') ?? config('uvoz.redirect_file'));
        $redirects = GoneImport::listedRedirects($file);
        if ($redirects === []) {
            $this->error("Nema preusmerenja u fajlu {$file}.");

            return self::FAILURE;
        }

        $keys = [];
        foreach ($redirects as $from => $to) {
            if (preg_match('#^/oglasi/([^/]+)/$#', $to, $m)
                && ! DB::table('categories')->where('slug', $m[1])->where('is_active', true)->exists()
                && ! DB::table('redirects')->where('from_path', $to)->exists()) {
                $this->warn("{$to} ne postoji, {$from} ide na /oglasi/");
                $to = '/oglasi/';
            }
            DB::table('redirects')->updateOrInsert(['from_path' => $from], ['to_path' => $to, 'status_code' => 301, 'updated_at' => now(), 'created_at' => now()]);
            $keys[UrlTables::key($from)] = true;
        }

        $stale = DB::table('gone_urls')->get(['id', 'path'])->filter(fn ($row) => isset($keys[UrlTables::keyFromStored($row->path)]))->pluck('id');
        DB::table('gone_urls')->whereIn('id', $stale)->delete();
        UrlTables::flushRedirects();
        UrlTables::flushGone();

        $this->info('Preusmerenja: '.count($redirects).', skinuto sa 410 liste: '.$stale->count().'.');

        return self::SUCCESS;
    }
}
