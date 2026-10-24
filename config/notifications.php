<?php

// Notifications module: push (FCM), saved-search alerts. See docs/modules/notifications.md.

return [

    // Before the official launch nothing may reach real users: with hold on, mail goes only to the
    // addresses (or @domains) in "allow" and push is not sent at all.
    'hold' => (bool) env('NOTIFICATIONS_HOLD', false),
    'allow' => array_values(array_filter(array_map('trim', explode(',', (string) env('NOTIFICATIONS_ALLOW', ''))))),

    'fcm' => [
        // Absolute path to the Firebase service-account JSON. Empty = push is a no-op.
        'credentials' => env('FCM_CREDENTIALS'),
        // Defaults to project_id from the credentials file.
        'project_id' => env('FCM_PROJECT_ID'),
        'timeout' => 10,
    ],

    'devices' => [
        // Oldest tokens of a user are dropped beyond this number.
        'max_per_user' => 10,
    ],

    'saved_searches' => [
        'max_per_user' => 20,
        // Hours between two alerts for the same search.
        'cooldown_hours' => 24,
        // Listings per search shown in the digest mail.
        'listings_per_search' => 5,
        // Listings matched per queued job.
        'chunk' => 500,
        // Window overlap with the previous run (late published_at writes); duplicates are ignored.
        'overlap_minutes' => 15,
        // The first run, or a run after a long pause, looks back at most this far.
        'max_window_hours' => 72,
        // Sent matches are kept this long so a re-published listing is not announced twice.
        'keep_days' => 30,
    ],

    // New-message mail to the same person about the same conversation at most once per this many minutes
    // (push is sent for every message).
    'message_mail_throttle_minutes' => 15,

    // Site paths used in mails and push data ({id} is replaced).
    'links' => [
        'conversation' => '/moj-nalog/poruke/{id}/',
    ],

];
