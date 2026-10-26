<?php

namespace App\Support\Seo;

use Illuminate\Support\Facades\DB;

/** Hit counter for redirects and 410s; written after the response is sent (SeoServiceProvider calls flush()). */
class Hits
{
    /** @var list<callable> */
    private array $pending = [];

    public static function redirect(int $id): void
    {
        static::queue(fn () => DB::table('redirects')->where('id', $id)
            ->update(['hits' => DB::raw('hits + 1'), 'last_hit_at' => now()]));
    }

    public static function gone(int $id): void
    {
        static::queue(fn () => DB::table('gone_urls')->where('id', $id)
            ->update(['hits' => DB::raw('hits + 1'), 'last_hit_at' => now()]));
    }

    /** Rules from code (kategorija=, Woo, Yoast sitemap...), by rule key. */
    public static function rule(string $rule): void
    {
        static::queue(function () use ($rule) {
            $updated = DB::table('seo_rule_hits')->where('rule', $rule)
                ->update(['hits' => DB::raw('hits + 1'), 'last_hit_at' => now()]);

            if ($updated === 0) {
                DB::table('seo_rule_hits')->insertOrIgnore(['rule' => $rule, 'hits' => 1, 'last_hit_at' => now()]);
            }
        });
    }

    public function flush(): void
    {
        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $write) {
            try {
                $write();
            } catch (\Throwable $e) {
                report($e); // the counter must never break the page
            }
        }
    }

    private static function queue(callable $write): void
    {
        app(self::class)->pending[] = $write;
    }
}
