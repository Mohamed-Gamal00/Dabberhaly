<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderReturned
{
    use Dispatchable, SerializesModels;

    /**
     * Fired when a customer (user or guest) returns an order.
     * The Order is serialized by ID (SerializesModels) so queued
     * listeners re-fetch a fresh copy from the database.
     */
    public function __construct(public Order $order)
    {
    }
}
