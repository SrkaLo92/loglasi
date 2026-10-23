<?php

namespace App\Uvoz\Steps;

use Illuminate\Support\Facades\DB;

/**
 * URL-ovi koji vraćaju 410: profili i oglasi spam naloga i oglasi iz otpada
 * (docs/09-deploy-seo.md, odeljak 2). URL koji postoji u novoj bazi se ne dira.
 */
class GoneImport extends Step
{
    public function run(): void
    {
        $postType = config('uvoz.post_type');

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
    }

    /** Slug bez WordPress dodatka „__trashed”. */
    private function slug(string $name): string
    {
        return (string) preg_replace('/__trashed(-\d+)?$/', '', rawurldecode($name));
    }

    private function gone(string $path, string $note): void
    {
        $this->report->wp('gone');

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
