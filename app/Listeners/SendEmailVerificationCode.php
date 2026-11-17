<?php

namespace App\Listeners;

use App\Events\UserRegistered;
use App\Models\User_verfication;
use App\Notifications\EmailVerificationCodeNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendEmailVerificationCode implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public function handle(UserRegistered $event): void
    {
        $user = $event->user;

        $code = str_pad((string) mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);

        User_verfication::updateOrCreate(
            ['user_id' => $user->id, 'is_reset_password' => false],
            [
                'code'                         => $code,
                'is_verified'                  => false,
                'verification_code_expires_at' => now()->addMinutes(10),
            ]
        );

        $user->notify(new EmailVerificationCodeNotification($code));
    }

    public function failed(UserRegistered $event, Throwable $exception): void
    {
        Log::error('Failed to send email verification code.', [
            'user_id' => $event->user->id,
            'email'   => $event->user->email,
            'error'   => $exception->getMessage(),
        ]);
    }
}
