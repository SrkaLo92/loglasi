<?php

namespace App\Services;

use App\Models\Listing;
use RuntimeException;

/**
 * A listing cannot be created or submitted: monthly limit, duplicate, too many drafts.
 * `reason` is a machine code, the message is Serbian and ready to show.
 */
class PostingRefused extends RuntimeException
{
    public function __construct(
        public readonly string $reason,
        string $message,
        public readonly ?Listing $existing = null,
        public readonly string $field = 'title',
    ) {
        parent::__construct($message);
    }
}
