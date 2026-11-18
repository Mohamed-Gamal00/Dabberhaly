<?php

namespace App\Listeners;

use App\Events\OrderReturned;
use App\Models\Admin;
use App\Notifications\OrderReturnedAdminNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends the order-returned notifications to admins.
 *
 * Implements ShouldQueueAfterCommit so it runs on the queue worker only after
 * the return has been safely committed to the database.
 */
class SendReturnNotification implements ShouldQueueAfterCommit
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;

    public array $backoff = [10, 15, 20, 25, 30];

    public function handle(OrderReturned $event): void
    {
        $admins = Admin::where('super_admin', 1)->get();
        if ($admins->isEmpty()) {
            $admins = Admin::all();
        }

        try {
            foreach ($admins as $admin) {
                $admin->notify(new OrderReturnedAdminNotification($event->order));
            }
        } catch (Throwable $e) {
            Log::warning($e->getMessage());

            throw $e;
        }
    }

    public function failed(OrderReturned $event, Throwable $exception): void
    {
        Log::error('Admin return notification failed', [
            'order_id' => $event->order->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
