<?php

namespace App\Support\Notifications;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * One-off announcement mails to all users (mail:announce). Each campaign has its subject,
 * preheader and Blade views in notifications/mail/announcements/{view}(-text).
 */
class Announcements
{
    public const CAMPAIGNS = [
        'novi-sajt-2026-10' => [
            'subject' => 'Osvežili smo loglasi.rs',
            'preheader' => 'Tvoj nalog je tu gde si ga ostavio, a oglas sada postavljaš lakše nego ikad.',
            'view' => 'new-site',
        ],
    ];

    public static function exists(string $campaign): bool
    {
        return array_key_exists($campaign, self::CAMPAIGNS);
    }

    /** @return array{subject: string, preheader: string, view: string} */
    public static function get(string $campaign): array
    {
        return self::CAMPAIGNS[$campaign];
    }

    /**
     * Active accounts with a confirmed e-mail that did not turn off "Novosti sa loglasi.rs"
     * and did not get this campaign yet. Soft-deleted users are left out by the model.
     *
     * @return Builder<User>
     */
    public static function recipients(string $campaign): Builder
    {
        return User::query()
            ->where('status', UserStatus::Active)
            ->whereNotNull('email_verified_at')
            ->where('email', 'like', '%@%')
            ->where('email', 'not like', '%@example.%')
            ->where(fn ($q) => $q->whereNull('notification_settings')
                ->orWhereNull('notification_settings->'.NotificationPreferences::NEWSLETTER)
                ->orWhere('notification_settings->'.NotificationPreferences::NEWSLETTER, true))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('announcement_sends as s')
                ->whereColumn('s.user_id', 'users.id')->where('s.campaign', $campaign));
    }
}
