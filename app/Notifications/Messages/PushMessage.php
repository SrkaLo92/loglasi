<?php

namespace App\Notifications\Messages;

/**
 * Push notification for the mobile app. data values are strings (FCM requirement);
 * "type" tells the app which screen to open: message, saved_search.
 */
class PushMessage
{
    /** @param  array<string, scalar>  $data */
    public function __construct(
        public string $title,
        public string $body,
        public array $data = [],
    ) {}

    /** @return array<string, string> */
    public function stringData(): array
    {
        return array_map(fn ($v) => is_bool($v) ? ($v ? '1' : '0') : (string) $v, $this->data);
    }
}
