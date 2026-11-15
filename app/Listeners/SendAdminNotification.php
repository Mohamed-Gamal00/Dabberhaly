<?php

namespace App\Listeners;

use App\Events\UserOrder;
use App\Models\Admin;
use App\Notifications\OrderCreatedEmailAdmin;
use App\Notifications\OrderCreatedNotification;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Sends the order-created notifications to admins.
 *
 * Implements ShouldQueue so it runs on the queue worker instead of
 * blocking the HTTP request that created the order.
 */
class SendAdminNotification implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    /**
     * Handle the event. 
     */
    public function handle(UserOrder $event): void
    {
        $order  = $event->order;
        $admins = Admin::all();

        // In-app (database) notification for every admin.
        Notification::send($admins, new OrderCreatedNotification($order));

        // Email only to admins with a valid address. Isolated per admin so
        // one bad mailbox can't stop the rest from going out.
        $admins
            ->filter(fn($admin) => filter_var($admin->email, FILTER_VALIDATE_EMAIL))
            ->each(function ($admin) use ($order) {
                try {
                    Notification::route('mail', $admin->email)
                        ->notify(new OrderCreatedEmailAdmin($order));
                } catch (\Throwable $e) {
                    Log::warning('Failed to send order-created email to admin.', [
                        'admin_id' => $admin->id,
                        'order_id' => $order->id,
                        'error'    => $e->getMessage(),
                    ]);
                }
            });
    }
}
