<?php

namespace App\Listeners;

use App\Events\NewsletterRequested;
use App\Notifications\NewsletterNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one newsletter mail to one subscriber.
 *
 * This is where the queue, retry and failure handling live — the same
 * pattern as SendEmailVerificationCode / SendPasswordResetCode in this
 * project. The Notification itself is only the mail representation.
 *
 * Implements ShouldQueue, so dispatching NewsletterRequested only
 * pushes a job to the `jobs` table. The queue worker runs handle()
 * in the background. If sending throws (SMTP down, rate limited,
 * timeout, ...), the exception is re-thrown so the worker retries
 * with backoff; after all tries the job lands in `failed_jobs`
 * and failed() logs it.
 */
class SendNewsletterMail implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;

    /** Backoff between retries (seconds): 1m, 5m, 15m, 30m. */
    public array $backoff = [60, 300, 900, 1800];

    /** Give the SMTP server up to 2 minutes per attempt. */
    public int $timeout = 120;

    public function handle(NewsletterRequested $event): void
    {
        try {
            $event->subscriber->notify(new NewsletterNotification(
                $event->title,
                $event->body,
                $event->subscriber
            ));
        } catch (Throwable $e) {
            Log::warning('Failed to send newsletter mail.', [
                'subscriber_id' => $event->subscriber->id,
                'email'         => $event->subscriber->subscription_email,
                'title'         => $event->title,
                'error'         => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(NewsletterRequested $event, Throwable $exception): void
    {
        Log::error('Newsletter mail permanently failed.', [
            'subscriber_id' => $event->subscriber->id,
            'email'         => $event->subscriber->subscription_email,
            'title'         => $event->title,
            'error'         => $exception->getMessage(),
        ]);
    }
}
