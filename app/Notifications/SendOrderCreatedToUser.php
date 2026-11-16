<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendOrderCreatedToUser extends Notification
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
            : $order->load(['orderItems', 'addresses.city', 'addresses.country', 'user', 'guest']);
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $customerName = $this->order->customer_name;

        return (new MailMessage)
            ->subject("🛍️ تأكيد استلام طلبك #{$this->order->number} — " . config('app.name'))
            ->view('emails.order_created_user', [
                'order'        => $this->order,
                'customerName' => $customerName,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [];
    }
}
