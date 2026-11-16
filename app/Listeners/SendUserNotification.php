<?php

namespace App\Listeners;

use App\Events\UserOrder;
use App\Notifications\SendOrderCreatedToUser;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sends order confirmation email to the customer (registered user or guest).
 *
 * Implements ShouldQueueAfterCommit so it runs on the queue worker only after
 * the order transaction has been safely committed to the database.
 */
class SendUserNotification implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    /**
     * Handle the event.
     */
    public function handle(UserOrder $event): void
    {
        $order = $event->order;

        // Resolve customer email: User -> Guest -> Order Address
        $email = $order->user?->email
            ?? $order->guest?->email
            ?? $order->addresses()->first()?->email;

        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::info("Order #{$order->number}: No valid customer email found to send confirmation.", [
                'order_id' => $order->id,
            ]);
            return;
        }

        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                if ($order->user && filter_var($order->user->email, FILTER_VALIDATE_EMAIL)) {
                    $order->user->notify(new SendOrderCreatedToUser($order));
                } else {
                    Notification::route('mail', $email)
                        ->notify(new SendOrderCreatedToUser($order));
                }
                break;
            } catch (\Throwable $e) {
                // Handle Mailtrap free tier rate limit: 1 email/sec (error 550)
                if (str_contains($e->getMessage(), 'Too many emails per second') && $attempt < $maxAttempts) {
                    sleep(2);
                    continue;
                }

                Log::warning('Failed to send order confirmation email to customer.', [
                    'order_id'     => $order->id,
                    'order_number' => $order->number,
                    'email'        => $email,
                    'error'        => $e->getMessage(),
                ]);
                break;
            }
        }
    }
}
