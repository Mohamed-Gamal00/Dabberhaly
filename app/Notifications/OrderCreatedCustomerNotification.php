<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCreatedCustomerNotification extends Notification
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
     * Delivery channels: mail to customer.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Mail representation for customer confirmation email.
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
     * Database representation in case customer dashboard notifications are enabled.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title'    => 'تم استلام طلبك بنجاح',
            'body'     => "تم استلام طلبك رقم #{$this->order->number} وهو قيد المعالجة",
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
