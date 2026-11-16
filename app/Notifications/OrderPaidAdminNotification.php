<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\PaymentTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPaidAdminNotification extends Notification
{
    use Queueable;

    public Order $order;
    public PaymentTransaction $transaction;

    /**
     * Create a new notification instance.
     */
    public function __construct(Order $order, PaymentTransaction $transaction)
    {
        $this->order       = $order->relationLoaded('orderItems')
            ? $order
            : $order->load(['orderItems', 'addresses.city', 'addresses.country']);
        $this->transaction = $transaction;
    }

    /**
     * Delivery channels: database and mail if admin email is valid.
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
     * Mail representation for payment email alert.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("✅ تم الدفع — طلب #{$this->order->number} — " . config('app.name'))
            ->view('emails.order_paid_admin', [
                'order'       => $this->order,
                'transaction' => $this->transaction,
            ]);
    }

    /**
     * Database representation.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title'          => 'تم سداد الطلب بنجاح',
            'body'           => "تم سداد الطلب #{$this->order->number} بقيمة {$this->transaction->amount}",
            'url'            => route('orders.show', $this->order->id, false),
            'order_id'       => $this->order->id,
            'transaction_id' => $this->transaction->id,
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
