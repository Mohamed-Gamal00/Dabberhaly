<?php

namespace App\Listeners;

use App\Events\UserOrder;
use App\Models\Admin;
use App\Models\Order;
use App\Notifications\OrderCreatedAdminNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
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

    public array $backoff = [30, 60];

    public function handle(UserOrder $event): void
    {
        $admins = Admin::where('super_admin', 1)->get();
        if ($admins->isEmpty()) {
            $admins = Admin::all();
        }

        try {
            foreach ($admins as $admin) {
                $admin->notify(new OrderCreatedAdminNotification($event->order));
            }
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
