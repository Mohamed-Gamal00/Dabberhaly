<?php

namespace App\Listeners;

use App\Events\UserOrder;
use App\Listeners\Concerns\RetriesRateLimitedMail;
use App\Models\Admin;
use App\Models\Order;
use App\Notifications\OrderCreatedEmailAdmin;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Sends the order-created notifications to admins.
 *
 * Implements ShouldQueueAfterCommit so it runs on the queue worker only after
 * the order transaction has been safely committed to the database.
 */
class SendAdminNotification implements ShouldQueueAfterCommit
{
    use InteractsWithQueue, Queueable;

    public int $tries = 2;

    public array $backoff = [10, 20];

    public function handle(UserOrder $event): void
    {
        $admin = Admin::where('super_admin', 1)->get();

        try {
            Notification::route('mail', $admin->email)
                ->notify(new OrderCreatedEmailAdmin($event->order));
        } catch (Throwable $e) {
            Log::warning($e->getMessage());

            throw $e;
        }
    }

    public function failed(UserOrder $event, Throwable $exception): void
    {
        Log::error('Admin notification failed', [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
