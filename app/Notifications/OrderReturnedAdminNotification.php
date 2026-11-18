<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrderReturnedAdminNotification extends Notification
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
     * - Database notification for the dashboard notifications menu.
     * (Same as the order-created admin notification: the admin is alerted
     * in the dashboard, not by email.)
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Database representation stored in notifications table.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title'    => 'تم إرجاع طلب',
            'body'     => "تم إرجاع الطلب #{$this->order->number} — راجع صفحة المرتجعات",
            'url'      => route('return_orders.show', $this->order->id, false),
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
