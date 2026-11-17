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
     */
    public function __construct(public User $user)
    {
    }
}
