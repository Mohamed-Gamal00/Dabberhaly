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
// class SendAdminNotification implements ShouldQueueAfterCommit
// {
//     use InteractsWithQueue, Queueable, RetriesRateLimitedMail;

//     public int $tries = 2;

//     public array|int $backoff = [100, 200];

//     /**
//      * Handle the event.
//      */
//     public function handle(UserOrder $event): void
//     {
//         $order = $event->order;

//         $admins = Admin::all();

//         Notification::send($admins, new OrderCreatedNotification($order));

//         $admins
//             ->filter(fn (Admin $admin) => filter_var($admin->email, FILTER_VALIDATE_EMAIL))
//             ->each(fn (Admin $admin) => $this->sendEmail($admin, $order));
//     }

//     /**
//      * Send the order-created email to a single admin.
//      */
//     private function sendEmail(Admin $admin, Order $order): void
//     {
//         try {
//             $this->sendMailWithRetry(
//                 fn () => Notification::route('mail', $admin->email)
//                     ->notify(new OrderCreatedEmailAdmin($order))
//             );
//         } catch (Throwable $e) {
//             Log::warning('Failed to send order-created email to admin.', [
//                 'admin_id' => $admin->id,
//                 'order_id' => $order->id,
//                 'error' => $e->getMessage(),
//             ]);
//         }
//     }

//     /**
//      * Handle a job failure.
//      */
//     public function fail(?Throwable $exception = null): void
//     {
//         Log::warning('Failed to send order-created notifications to admins.', [
//             'exception' => $exception?->getMessage(),
//         ]);
//     }
// }



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
