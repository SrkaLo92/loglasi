<?php

namespace App\Uvoz\Steps;

use App\Support\Seo\UrlTables;
use Illuminate\Support\Facades\DB;

/**
 * URL-ovi koji vraćaju 410: profili i oglasi spam naloga i oglasi iz otpada
 * (docs/09-deploy-seo.md, odeljak 2). URL koji postoji u novoj bazi se ne dira.
 */
class GoneImport extends Step
{
    /** @var array<string, true> paths that get a 301 from the redirect file, never a 410 */
    private array $redirected = [];

    public function run(): void
    {
        $postType = config('uvoz.post_type');
        $this->importRedirects();

        if ($this->ctx->spamUsers) {
            foreach ($this->wp->table('users')->whereIn('ID', $this->ctx->spamUsers)->get(['ID', 'user_nicename']) as $user) {
                $slug = rawurldecode((string) $user->user_nicename);
                if ($slug === '') {
                    continue;
                }
                $this->gone('/korisnik/'.$slug.'/', "spam nalog #{$user->ID}");
                $this->gone('/author/'.$slug.'/', "spam nalog #{$user->ID}");
            }

            $spamListings = $this->wp->table('posts')->where('post_type', $postType)
                ->whereIn('post_author', $this->ctx->spamUsers)
                ->whereNotIn('post_status', ['auto-draft', 'inherit'])
                ->where('post_name', '!=', '')
                ->get(['ID', 'post_author', 'post_name']);
            foreach ($spamListings as $post) {
                $this->gone('/oglas/'.$this->slug((string) $post->post_name).'/', "oglas #{$post->ID} spam naloga #{$post->post_author}");
            }
        }

        $trashed = $this->wp->table('posts as p')
            ->leftJoin('postmeta as m', fn ($j) => $j->on('m.post_id', '=', 'p.ID')->where('m.meta_key', '_wp_desired_post_slug'))
            ->where('p.post_type', $postType)->where('p.post_status', 'trash')
            ->get(['p.ID', 'p.post_name', 'm.meta_value as desired']);
        foreach ($trashed as $post) {
            $slug = $this->slug((string) ($post->desired ?: $post->post_name));
            if ($slug !== '') {
                $this->gone('/oglas/'.$slug.'/', "oglas #{$post->ID} u otpadu");
            }
        }

        // Addresses Google still knows from the live sitemap, deleted when the copy was cleaned.
        foreach (static::listedPaths((string) config('uvoz.gone_file')) as $path) {
            $this->gone($path, 'sa živog sajta, obrisano pri čišćenju');
        }

        UrlTables::flushGone();
    }

    /** 301s from the redirect file (old addresses with clicks in Search Console); they leave the 410 list. */
    private function importRedirects(): void
    {
        $redirects = static::listedRedirects((string) config('uvoz.redirect_file'));
        if ($redirects === []) {
            return;
        }

        foreach ($redirects as $from => $to) {
            $to = $this->existingTarget($to);
            $this->redirected[UrlTables::key($from)] = true;
            if (! DB::table('redirects')->where('from_path', $from)->update(['to_path' => $to, 'status_code' => 301, 'updated_at' => now()])
                && ! DB::table('redirects')->where('from_path', $from)->exists()) {
                DB::table('redirects')->insert(['from_path' => $from, 'to_path' => $to, 'status_code' => 301, 'created_at' => now(), 'updated_at' => now()]);
            }
            $this->report->imported('redirect');
        }

        // Earlier 410 rows for these addresses, stored decoded or percent-encoded.
        $stale = DB::table('gone_urls')->where('path', 'like', '/oglas/%')->get(['id', 'path'])
            ->filter(fn ($row) => isset($this->redirected[UrlTables::keyFromStored($row->path)]))->pluck('id');
        DB::table('gone_urls')->whereIn('id', $stale)->delete();

        UrlTables::flushRedirects();
    }

    /**
     * A category target that exists here (or redirects on); otherwise its WordPress parent, otherwise
     * /oglasi/. Never a 404.
     */
    private function existingTarget(string $to): string
    {
        if (! preg_match('#^/oglasi/([^/]+)/$#', $to, $m)) {
            return $to;
        }

        $known = fn (string $slug) => DB::table('categories')->where('slug', $slug)->where('is_active', true)->exists()
            || DB::table('redirects')->where('from_path', '/oglasi/'.$slug.'/')->exists();
        if ($known($m[1])) {
            return $to;
        }

        $terms = collect($this->wp->terms((string) config('uvoz.tax_category')));
        $term = $terms->first(fn ($t) => rawurldecode((string) $t->slug) === $m[1]);
        while ($term && $term->parent > 0 && ($term = $terms->get($term->parent))) {
            $slug = rawurldecode((string) $term->slug);
            if ($known($slug)) {
                $this->report->note('redirect', "{$to}: kategorija ne postoji, ide na roditelja /oglasi/{$slug}/");

                return '/oglasi/'.$slug.'/';
            }
        }

        $this->report->note('redirect', "{$to}: kategorija ne postoji, ide na /oglasi/");

        return '/oglasi/';
    }

    /** @return array<string, string> "path target" per line (paths or full URLs), "#" starts a comment */
    public static function listedRedirects(string $file): array
    {
        if ($file === '' || ! is_file($file)) {
            return [];
        }

        $path = fn (string $url) => '/'.trim(rawurldecode((string) (parse_url($url, PHP_URL_PATH) ?? '')), '/').'/';
        $redirects = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $parts = preg_split('/\s+/', trim((string) preg_replace('/#.*$/', '', $line)));
            if (count($parts) !== 2) {
                continue;
            }
            [$from, $to] = [$path($parts[0]), $path($parts[1])];
            if ($from !== '//' && $from !== $to) {
                $redirects[$from] = $to === '//' ? '/' : $to;
            }
        }

        return $redirects;
    }

    /** @return list<string> one path per line ("/oglas/x/" or a full URL), "#" starts a comment */
    public static function listedPaths(string $file): array
    {
        if ($file === '' || ! is_file($file)) {
            return [];
        }

        $paths = [];
        foreach (file($file, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if ($line === '') {
                continue;
            }
            $path = rawurldecode((string) (parse_url($line, PHP_URL_PATH) ?? ''));
            if ($path === '' || $path === '/') {
                continue;
            }
            $paths[] = '/'.trim($path, '/').'/';
        }

        return array_values(array_unique($paths));
    }

    /** Slug bez WordPress dodatka „__trashed”. */
    private function slug(string $name): string
    {
        return (string) preg_replace('/__trashed(-\d+)?$/', '', rawurldecode($name));
    }

    private function gone(string $path, string $note): void
    {
        $this->report->wp('gone');

        if (isset($this->redirected[UrlTables::key($path)])) {
            return;
        }

        if (preg_match('~^/(oglas|korisnik|author)/([^/]+)/$~', $path, $m)) {
            $live = match ($m[1]) {
                'oglas' => DB::table('listings')->where('slug', $m[2])->whereNull('deleted_at')->exists(),
                default => DB::table('users')->where('slug', $m[2])->whereNull('deleted_at')->exists(),
            };
            if ($live) {
                $this->report->error('gone', "{$path}: postoji u novoj bazi, ne ide na 410 ({$note})");

                return;
            }
        }

        $values = ['note' => mb_substr('Uvoz: '.$note, 0, 250), 'updated_at' => now()];
        if (! DB::table('gone_urls')->where('path', $path)->update($values) && ! DB::table('gone_urls')->where('path', $path)->exists()) {
            DB::table('gone_urls')->insert($values + ['path' => $path, 'created_at' => now()]);
        }
        $this->report->imported('gone');
    }
}
