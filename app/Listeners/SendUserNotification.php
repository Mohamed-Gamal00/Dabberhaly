<?php

namespace App\Listeners;

use App\Events\UserOrder;
use App\Notifications\SendOrderCreatedToUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends order confirmation email to the customer (registered user or guest).
 *
 * Implements ShouldQueueAfterCommit so it runs on the queue worker only after
 * the order transaction has been safely committed to the database.
 */
class SendUserNotification implements ShouldQueueAfterCommit
{
    use InteractsWithQueue, Queueable;

    public int $tries = 2;

    public array|int $backoff = [100, 200];

    /**
     * Handle the event.
     */
    public function handle(UserOrder $event): void
    {
        $order = $event->order;

        $email = $order->user?->email
            ?? $order->guest?->email
            ?? $order->addresses()->first()?->email;

        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::info("Order #{$order->number}: No valid customer email found to send confirmation.", [
                'order_id' => $order->id,
            ]);

            return;
        }

        $order->user
            ? $order->user->notify(new SendOrderCreatedToUser($order))
            : Notification::route('mail', $email)->notify(new SendOrderCreatedToUser($order));
    }

    /**
     * Handle a job failure.
     */
    public function fail(?Throwable $exception = null): void
    {
        Log::warning('Failed to send order confirmation email to customer.', [
            'exception' => $exception?->getMessage(),
        ]);
    }
}
