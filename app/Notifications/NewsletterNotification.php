<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewsletterNotification extends Notification
{
    use Queueable;

    public string $title;
    public string $body;
    public $user;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $title, string $body, $user = null)
    {
        $this->title = $title;
        $this->body  = $body;
        $this->user  = $user;
    }

    /**
     * Delivery channels: mail to subscribers.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Mail representation for newsletter broadcast.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title)
            ->greeting('مرحباً!')
            ->line($this->body)
            ->action('تسوق الآن', url('/'))
            ->line('نشكرك لاشتراكك في نشرتنا البريدية.');
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
