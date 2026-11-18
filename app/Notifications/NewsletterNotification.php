<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Newsletter broadcast notification — mail representation only.
 *
 * It carries no queue, retry or backoff logic. That lives in
 * App\Listeners\SendNewsletterMail, which is ShouldQueue and is
 * triggered by App\Events\NewsletterRequested — the same pattern
 * as the verification / password-reset notifications in this project.
 */
class NewsletterNotification extends Notification
{
    use Queueable;

    public string $title;
    public string $body;

    public function __construct(string $title, string $body)
    {
        $this->title = $title;
        $this->body  = $body;
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
                'title' => $this->title,
                'body'  => $this->body,
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
}
