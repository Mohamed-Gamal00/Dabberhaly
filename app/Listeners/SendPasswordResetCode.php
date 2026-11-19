<?php

namespace App\Listeners;

use App\Events\UserForgotPassword;
use App\Notifications\PasswordResetCodeNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPasswordResetCode implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function handle(UserForgotPassword $event): void
    {
        $user = $event->user;

        // The code was already created synchronously when the request was
        // received; this listener only SENDS it. Fall back to creating one
        // here only for events dispatched without a code (e.g. older code
        // paths), so the mail still goes out.
        $code = $event->code ?? app(\App\Services\VerificationServices::class)
            ->createPasswordResetCode($user->id);

        $user->notify(new PasswordResetCodeNotification($code));
    }

    public function failed(UserForgotPassword $event, Throwable $exception): void
    {
        Log::error('Failed to send password reset code.', [
            'user_id' => $event->user->id,
            'email'   => $event->user->email,
            'error'   => $exception->getMessage(),
        ]);
    }
}
