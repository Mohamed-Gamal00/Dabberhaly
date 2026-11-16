<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCreatedAdminNotification extends Notification
{
    use Queueable;

    public Order $order;

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order->relationLoaded('orderItems')
            ? $order
            : $order->load(['orderItems', 'addresses.city', 'addresses.country']);
    }

    /**
     * Determine delivery channels:
     * - Database notification for dashboard notifications menu.
     * - Mail notification if notifiable has a valid email address.
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return ['mail'];
        }

        $channels = ['database'];

        if (!empty($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Mail representation for admin email alerts.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("🛒 طلب جديد #{$this->order->number} — " . config('app.name'))
            ->view('emails.order_created_admin', ['order' => $this->order]);
    }

    /**
     * Database representation stored in notifications table.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title'    => 'يوجد طلب جديد',
            'body'     => "تمت اضافة الطلب #{$this->order->number} لقائمة الطلبات",
            'url'      => route('orders.show', $this->order->id, false),
            'order_id' => $this->order->id,
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
