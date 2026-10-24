<?php

namespace App\Notifications\Listeners;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mime\Address;

/**
 * While notifications are on hold (before the launch), a mail is sent only when every
 * recipient is on the allow list; anything else is dropped and logged.
 */
class HoldOutgoingMail
{
    public function handle(MessageSending $event): ?bool
    {
        if (! config('notifications.hold')) {
            return null;
        }

        $message = $event->message;
        $recipients = array_map(
            fn (Address $address) => mb_strtolower($address->getAddress()),
            [...$message->getTo(), ...$message->getCc(), ...$message->getBcc()],
        );

        $blocked = array_values(array_filter($recipients, fn (string $email) => ! $this->allowed($email)));
        if ($recipients !== [] && $blocked === []) {
            return null;
        }

        Log::info('Mail held: notifications are on hold.', ['subject' => $message->getSubject(), 'to' => $recipients]);

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
