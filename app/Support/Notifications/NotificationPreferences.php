<?php

namespace App\Support\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Per-user notification categories, stored in users.notification_settings (JSON).
 *
 * The same JSON also holds the keys of the mobile API (User::NOTIFICATION_DEFAULTS:
 * messages, listing_status, payments, expiring, admin_queue); the keys shared with the
 * API (messages, expiring) mean the same thing on the site and in the app.
 *
 *   NotificationPreferences::allows($user, 'saved_searches');   // check before sending
 *   NotificationPreferences::unsubscribeUrl($user, 'newsletter'); // signed, never expires
 *
 * Account and money mails are transactional and always sent: email confirmation, password,
 * orders and payments (payments module), Biznis package reminders (firms module).
 */
class NotificationPreferences
{
    public const MESSAGES = 'messages';

    public const SAVED_SEARCHES = 'saved_searches';

    public const EXPIRING = 'expiring';

    public const NEWSLETTER = 'newsletter';

    /** Categories shown on /moj-nalog/obavestenja/ with their default value. */
    public const DEFAULTS = [
        self::MESSAGES => true,
        self::SAVED_SEARCHES => true,
        self::EXPIRING => true,
        self::NEWSLETTER => false,
    ];

    /** @return array<string, array{label: string, help: string}> */
    public static function labels(): array
    {
        return [
            self::MESSAGES => [
                'label' => 'Nove poruke',
                'help' => 'Javljamo vam kad vam neko pošalje poruku o oglasu.',
            ],
            self::SAVED_SEARCHES => [
                'label' => 'Novi oglasi za sačuvane pretrage',
                'help' => 'Najviše jedan mejl dnevno po pretrazi, samo kad ima novih oglasa.',
            ],
            self::EXPIRING => [
                'label' => 'Istek oglasa',
                'help' => 'Podsetnik pre nego što oglas istekne, da ga besplatno obnovite.',
            ],
            self::NEWSLETTER => [
                'label' => 'Novosti sa loglasi.rs',
                'help' => 'Povremeno obaveštenje o novim mogućnostima sajta.',
            ],
        ];
    }

    public static function exists(string $category): bool
    {
        return array_key_exists($category, self::DEFAULTS);
    }

    /** @return array<string, bool> */
    public static function all(User $user): array
    {
        $stored = (array) $user->notification_settings;
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $out[$key] = array_key_exists($key, $stored) ? (bool) $stored[$key] : $default;
        }

        return $out;
    }

    public static function allows(User $user, string $category): bool
    {
        self::assertCategory($category);

        return self::all($user)[$category];
    }

    /**
     * Writes the given categories and keeps every other stored key (the API ones).
     *
     * @param  array<string, bool>  $values
     */
    public static function set(User $user, array $values): void
    {
        $known = array_map(fn ($v) => (bool) $v, array_intersect_key($values, self::DEFAULTS));

        $user->forceFill(['notification_settings' => $known + (array) $user->notification_settings])->save();
    }

    /** Signed link that turns one category off; used in the mail footer and List-Unsubscribe. */
    public static function unsubscribeUrl(User $user, string $category): string
    {
        self::assertCategory($category);

        return URL::signedRoute('notifications.unsubscribe', ['user' => $user->getKey(), 'category' => $category]);
    }

    private static function assertCategory(string $category): void
    {
        if (! self::exists($category)) {
            throw new InvalidArgumentException('Unknown notification category: '.$category);
        }
    }
}
