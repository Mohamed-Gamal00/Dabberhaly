<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Newsletter broadcast notification.
 *
 * Queued (not sent synchronously): each subscriber gets their own queued
 * job, so one failing address never blocks the rest, and a transient
 * SMTP / Mailtrap failure is retried with backoff instead of failing
 * the whole admin request.
 *
 * Requires QUEUE_CONNECTION=database (see .env.example) and a running
 * worker: `php artisan queue:work`
 * Failed jobs land in the `failed_jobs` table after all retries.
 */
class NewsletterNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $title;
    public string $body;
    public ?string $subscriberEmail = null;

    /** How many times the job may be attempted in total (1 + 4 retries). */
    public int $tries = 5;

    /** Give the SMTP server up to 2 minutes per attempt. */
    public int $timeout = 120;

    public function __construct(string $title, string $body, $user = null)
    {
        $this->title = $title;
        $this->body  = $body;

        // Store a scalar only: the whole Eloquent model must not be
        // serialized into the queue payload.
        $this->subscriberEmail = $user?->subscription_email ?? $user?->email ?? null;
    }

    /**
     * Backoff between retries (seconds): 1m, 5m, 15m, 30m.
     * Used by the queue worker when sending fails (SMTP down, rate
     * limited, timeout, ...). The 4 values cover the 4 retries of $tries=5.
     */
    public function backoff(): array
    {
        return [60, 300, 900, 1800];
    }

    /**
     * Delivery channels: mail to subscribers.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Mail representation: Vanilla Latte Blade template, not the
     * default Laravel MailMessage lines.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->view('emails.newsletter', [
                'title'          => $this->title,
                'body'           => $this->body,
                'subscriberEmail' => $this->subscriberEmail
                    ?? $notifiable->subscription_email
                    ?? $notifiable->email
                    ?? null,
            ]);
    }

    /**
     * Array fallback representation.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
        ];
    }

    /**
     * Called when the job has exhausted all retries.
     * The record stays in `failed_jobs` for `queue:retry` / inspection.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Newsletter send failed permanently for: '
            . ($this->subscriberEmail ?? 'unknown')
            . ' — ' . $exception->getMessage());
    }
}
