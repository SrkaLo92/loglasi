<?php

namespace App\Notifications\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * While notifications are on hold (before the launch), a mail is sent only when every
 * recipient is on the allow list, or (with notifications.allow_requested) when the person asked
 * for it themselves: password reset, email confirmation. Anything else is dropped and logged.
 */
class HoldOutgoingMail
{
    /** Header on mails the recipient asked for; removed before sending. */
    public const REQUESTED = 'X-Loglasi-Requested';

    public static function markRequested(Email $message): void
    {
        $message->getHeaders()->addTextHeader(self::REQUESTED, '1');
    }

    public function handle(MessageSending $event): ?bool
    {
        $message = $event->message;
        $requested = $message->getHeaders()->has(self::REQUESTED);
        $message->getHeaders()->remove(self::REQUESTED);

        if (! config('notifications.hold') || ($requested && config('notifications.allow_requested'))) {
            return null;
        }

        $recipients = array_map(
            fn (Address $address) => mb_strtolower($address->getAddress()),
            [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()],
        );

        $blocked = array_values(array_filter($recipients, fn (string $email) => ! $this->allowed($email)));
        if ($recipients !== [] && $blocked === []) {
            return null;
        }

        Log::warning('Mail held: notifications are on hold.', ['subject' => $message->getSubject(), 'to' => $recipients]);

        return false;
    }

    private function allowed(string $email): bool
    {
        foreach (config('notifications.allow', []) as $entry) {
            $entry = mb_strtolower($entry);
            if ($entry === $email || (str_starts_with($entry, '@') && str_ends_with($email, $entry))) {
                return true;
            }
        }

        return false;
    }
}
