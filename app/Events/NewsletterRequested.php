<?php

namespace App\Events;

use App\Models\SendNewsToUser;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the admin sends the newsletter to one subscriber.
 *
 * One event per subscriber, so each mail is its own queued job and a
 * failure retries only that subscriber. The subscriber is serialized
 * by ID (SerializesModels) so the queued listener re-fetches a fresh
 * copy from the database.
 */
class NewsletterRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public SendNewsToUser $subscriber,
        public string $title,
        public string $body,
    ) {
    }
}
