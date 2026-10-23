<?php

namespace App\Notifications\Messages;

use App\Models\User;
use App\Support\Notifications\NotificationPreferences;
use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

/**
 * Notification mail in the loglasi.rs layout (resources/views/vendor/notifications/email.blade.php).
 *
 *   return BrandedMailMessage::to($notifiable, NotificationPreferences::SAVED_SEARCHES)
 *       ->subject('...')->line('...')->action('...', $url);
 *
 * With a category the footer gets a one-click unsubscribe link and the mail gets the
 * List-Unsubscribe / List-Unsubscribe-Post headers (RFC 8058). Without one (account mail:
 * password, email confirmation, payments) only the settings link is shown.
 */
class BrandedMailMessage extends MailMessage
{
    public static function to(User $user, ?string $category = null): static
    {
        $message = new static;
        $message->salutation("Pozdrav,\nloglasi.rs");

        if ($category !== null) {
            $url = NotificationPreferences::unsubscribeUrl($user, $category);
            $message->viewData['unsubscribeUrl'] = $url;
            $message->withSymfonyMessage(fn (Email $email) => ListUnsubscribe::apply($email, $url));
        }

        return $message;
    }

    /** Own Markdown view; it starts with <x-mail::message :unsubscribe="$unsubscribeUrl">. */
    public function markdown($view, array $data = [])
    {
        return parent::markdown($view, $data + $this->viewData);
    }

    /**
     * User text (message body, listing title) inside a line: HTML is escaped by Blade,
     * this keeps Markdown from turning it into links, images or headings.
     */
    public static function plain(string $text): string
    {
        return addcslashes(trim((string) preg_replace('/\s+/u', ' ', $text)), '\\[]`*_#<>!|');
    }
}
