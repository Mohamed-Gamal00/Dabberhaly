<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserForgotPassword
{
    use Dispatchable, SerializesModels;

    /**
     * Fired when a user requests a password-reset code.
     *
     * The code is created synchronously by the request path (see
     * VerificationServices::createPasswordResetCode) and passed here —
     * it must NOT be created inside the queued listener, or no queue
     * worker would mean no code is ever created.
     */
    public function __construct(public User $user, public ?string $code = null)
    {
    }
}
