<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserOrder
{
    use Dispatchable, SerializesModels;

    /**
     * Fired when a customer (user or guest) places a new order.
     * The Order is serialized by ID (SerializesModels) so queued
     * listeners re-fetch a fresh copy from the database.
     */
    public function __construct(public Order $order)
    {
    }
}
