<?php

// Messages and favourites (docs/modules/messages.md).
return [

    // Longest message body in characters (contract: maxLength 2000).
    'max_length' => 2000,

    // Sending limits per user, shared by the site and the API.
    'per_minute' => 10,
    'per_day' => 100,

    // New conversations a user may start per day.
    'new_conversations_per_day' => 30,

    // Spam guard: the same text may go to at most this many different conversations within the window.
    'duplicate_conversations' => 3,
    'duplicate_window_hours' => 24,

    // Page sizes.
    'inbox_per_page' => 20,
    'messages_per_page' => 50,
    'favorites_per_page' => 24,

    // Browser polling interval in the conversation view (only while the tab is visible).
    'poll_seconds' => 20,

    // Unread counter cache for the header and bottom nav.
    'unread_cache_seconds' => 300,
];
