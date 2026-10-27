<?php

namespace App\Console\Commands;

use App\Mail\Announcement;
use App\Models\User;
use App\Support\Notifications\Announcements;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Queues a one-off announcement (Announcements::CAMPAIGNS) to every recipient, spread out at
 * --per-minute so the SMTP account stays under its limit. Each user is recorded before the mail
 * is queued, so running the command again only reaches users who did not get it yet.
 *
 *   php artisan mail:announce novi-sajt-2026-10 --test=kontakt@loglasi.rs --test=ja@gmail.com
 *   php artisan mail:announce novi-sajt-2026-10 --dry-run
 *   php artisan mail:announce novi-sajt-2026-10
 */
class SendAnnouncement extends Command
{
    protected $signature = 'mail:announce {campaign} {--test=* : Send only to these addresses (not recorded)} {--dry-run : Only count recipients} {--per-minute=10 : Mails per minute}';

    protected $description = 'Queue a one-off announcement mail to all users, at a safe pace and never twice';

    public function handle(): int
    {
        $campaign = (string) $this->argument('campaign');
        if (! Announcements::exists($campaign)) {
            $this->error('Nepoznata kampanja. Postoje: '.implode(', ', array_keys(Announcements::CAMPAIGNS)));

            return self::FAILURE;
        }

        if ($tests = (array) $this->option('test')) {
            foreach ($tests as $email) {
                $user = User::query()->where('email', $email)->first()
                    ?? (new User)->forceFill(['name' => '', 'email' => $email]);
                Mail::sendNow(new Announcement($user, $campaign));
                $this->line('Probni mejl poslat: '.$email);
            }

            return self::SUCCESS;
        }

        $count = Announcements::recipients($campaign)->count();
        if ($this->option('dry-run')) {
            $this->info('Primalaca: '.$count);

            return self::SUCCESS;
        }

        $perMinute = max(1, (int) $this->option('per-minute'));
        $gap = 60 / $perMinute;
        $queued = 0;

        Announcements::recipients($campaign)->orderBy('id')->select(['id', 'name', 'email'])
            ->chunkById(200, function ($users) use ($campaign, $gap, &$queued) {
                foreach ($users as $user) {
                    $recorded = DB::table('announcement_sends')->insertOrIgnore([
                        'campaign' => $campaign, 'user_id' => $user->id, 'created_at' => now(),
                    ]);
                    if ($recorded === 0) {
                        continue;
                    }
                    Mail::later(now()->addSeconds((int) round($queued * $gap)), new Announcement($user, $campaign));
                    $queued++;
                }
            });

        $minutes = (int) ceil($queued / $perMinute);
        $this->info('U redu za slanje: '.$queued.' (oko '.$minutes.' min).');

        return self::SUCCESS;
    }
}
