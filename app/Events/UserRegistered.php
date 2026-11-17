<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserRegistered
{
    use Dispatchable, SerializesModels;

    /**
     * Fired after a new user registers.
     * The User is serialized by ID so queued listeners re-fetch a fresh copy.
     */
    public function __construct(public User $user)
    {
    }
}
