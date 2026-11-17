<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(public string $code)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🔑 رمز إعادة تعيين كلمة المرور — ' . config('app.name'))
            ->view('emails.password_reset_code', [
                'code' => $this->code,
                'name' => $notifiable->first_name ?? '',
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
