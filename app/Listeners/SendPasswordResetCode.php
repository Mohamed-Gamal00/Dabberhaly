<?php

namespace App\Listeners;

use App\Events\UserForgotPassword;
use App\Models\User_verfication;
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

        $code = str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

        User_verfication::updateOrCreate(
            ['user_id' => $user->id, 'is_reset_password' => true],
            [
                'code'                         => $code,
                'is_verified'                  => false,
                'verification_code_expires_at' => now()->addMinutes(10),
            ]
        );

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
