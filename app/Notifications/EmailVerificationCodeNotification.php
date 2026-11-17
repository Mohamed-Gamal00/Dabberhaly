<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code, public string $name = '')
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🔐 رمز التحقق — ' . config('app.name'))
            ->view('emails.email_verification_code', [
                'code' => $this->code,
                'name' => $this->name ?: ($notifiable->first_name ?? ''),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
