<?php

namespace App\Notifications;

use App\Models\ContactUs;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContactMessageAdminNotification extends Notification
{
    use Queueable;

    public ContactUs $message;

    /**
     * Create a new notification instance.
     */
    public function __construct(ContactUs $message)
    {
        $this->message = $message;
    }

    /**
     * Delivery channels: database and mail if admin email is valid.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Mail representation for new contact message alert.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('📩 رسالة جديدة — ' . config('app.name'))
            ->line('يوجد لديك رسالة جديدة عبر صفحة التواصل.')
            ->line("اسم المرسل: {$this->message->name}")
            ->line("البريد الإلكتروني: {$this->message->email}")
            ->line("الهاتف: {$this->message->phone_number}")
            ->line("الرسالة: {$this->message->message}")
            ->action('عرض الرسالة', route('contact_us.watch', $this->message->id));
    }

    /**
     * Database representation stored in notifications table.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title'      => 'يوجد رسالة جديده',
            'body'       => "تمت اضافة الرساله من ({$this->message->name}) لقائمة الرسائل",
            'url'        => route('contact_us.watch', $this->message->id, false),
            'message_id' => $this->message->id,
        ];
    }

    /**
     * Array fallback representation.
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
