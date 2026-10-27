<?php

namespace App\Console\Commands;

use App\Models\Firm;
use App\Models\Redirect;
use App\Models\User;
use App\Support\Accounts\PublicName;
use App\Support\Auth\Accounts;
use App\Support\PageCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Removes e-mail addresses and phone numbers from user and firm names (imported accounts often used
 * the e-mail as the display name). A user whose name changes gets a new profile slug and a 301 from the old one.
 */
class CleanPublicNames extends Command
{
    protected $signature = 'users:clean-names {--dry-run : Only list, change nothing}';

    protected $description = 'Remove e-mail addresses and phone numbers from public user and firm names';

    private const MAIL_HOST_SLUG = '/(gmail|googlemail|yahoo|hotmail|outlook|icloud|yandex|gmx|aol|protonmail|eunet|ptt)-(com|rs|net|org|ru|de)(-\d+)?$/';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $rows = [];

        User::withTrashed()->select(['id', 'name', 'slug', 'email'])->orderBy('id')->each(function (User $user) use ($dry, &$rows) {
            $badName = PublicName::hasContact((string) $user->name);
            if (! $badName && ! $this->slugFromEmail($user)) {
                return;
            }
            $name = $badName ? PublicName::clean((string) $user->name) : (string) $user->name;
            $slug = $this->slugFromEmail($user) ? Accounts::uniqueSlug($name) : $this->newSlug($user, $name);
            $rows[] = ['korisnik', $user->id, $user->name, $name, $user->slug.' → '.$slug];

            if (! $dry) {
                DB::transaction(function () use ($user, $name, $slug) {
                    $old = $user->slug;
                    $user->forceFill(['name' => $name, 'slug' => $slug])->save();
                    if ($old !== $slug) {
                        foreach (['', 'ocene/'] as $suffix) {
                            Redirect::query()->updateOrCreate(
                                ['from_path' => '/korisnik/'.$old.'/'.$suffix],
                                ['to_path' => '/korisnik/'.$slug.'/'.$suffix, 'status_code' => 301],
                            );
                        }
                    }
                });
            }
        });

        Firm::query()->select(['id', 'name'])->orderBy('id')->each(function (Firm $firm) use ($dry, &$rows) {
            if (! PublicName::hasContact((string) $firm->name)) {
                return;
            }
            $name = PublicName::clean((string) $firm->name);
            $rows[] = ['firma', $firm->id, $firm->name, $name, ''];
            if (! $dry) {
                $firm->forceFill(['name' => $name])->save();
            }
        });

        if ($rows !== []) {
            $this->table(['Vrsta', 'ID', 'Staro ime', 'Novo ime', 'Adresa profila'], $rows);
        }
        if (! $dry && $rows !== []) {
            PageCache::flush();
        }
        $this->info(($dry ? 'Za izmenu: ' : 'Izmenjeno: ').count($rows));

        return self::SUCCESS;
    }

    /** Imported accounts often got the e-mail as the profile address (srdjan92@gmail.com → srdjan92gmail-com). */
    private function slugFromEmail(User $user): bool
    {
        $alnum = fn (string $s) => preg_replace('/[^a-z0-9]/', '', Str::lower($s)) ?? '';
        [$local, $domain] = array_pad(explode('@', (string) $user->email, 2), 2, '');
        $local = $alnum($local);
        $host = $alnum(explode('.', $domain)[0]);

        if ($local !== '' && $host !== '' && str_contains($alnum((string) $user->slug), $local.$host)) {
            return true;
        }

        // An older e-mail (e.g. the WordPress login) left only its mail host in the slug.
        return preg_match(self::MAIL_HOST_SLUG, (string) $user->slug) === 1;
    }

    /** Keeps the slug when the cleaned name gives the same one; otherwise a fresh unique slug. */
    private function newSlug(User $user, string $name): string
    {
        $base = Str::limit(Str::slug($name, '-', 'sr') ?: 'korisnik', 60, '');

        return preg_replace('/-\d+$/', '', (string) $user->slug) === $base ? (string) $user->slug : Accounts::uniqueSlug($name);
    }
}
