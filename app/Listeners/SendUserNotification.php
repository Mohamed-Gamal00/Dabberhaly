<?php

namespace App\Listeners;

use App\Events\UserOrder;
use App\Notifications\OrderCreatedCustomerNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendUserNotification implements ShouldQueueAfterCommit
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;

    public array $backoff = [10,20,40,30,60];

    public function handle(UserOrder $event): void
    {
        $order = $event->order;

        $email = $order->user?->email
            ?? $order->guest?->email
            ?? $order->addresses()->first()?->email;

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::info(
                "Order #{$order->number}: No valid customer email found.",
                ['order_id' => $order->id]
            );

            return;
        }

        try {
            $notification = new OrderCreatedCustomerNotification($order);

            if ($order->user) {
                $order->user->notify($notification);
            } else {
                Notification::route('mail', $email)
                    ->notify($notification);
            }
        } catch (Throwable $e) {
            Log::warning('Failed to send order confirmation email.', [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(
        UserOrder $event,
        Throwable $exception
    ): void {
        Log::error('Order confirmation email permanently failed.', [
            'order_id' => $event->order->id,
            'order_number' => $event->order->number,
            'error' => $exception->getMessage(),
        ]);
    }
}
