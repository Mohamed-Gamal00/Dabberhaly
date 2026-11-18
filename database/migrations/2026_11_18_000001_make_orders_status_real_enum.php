<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The 2026_11_11 migration renamed the order-status values but
     * never changed the column type: orders.status stayed varchar.
     * Make it a real MySQL ENUM matching the OrderStatus enum.
     * Raw SQL is used so no doctrine/dbal dependency is needed.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status ENUM('pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY COLUMN status VARCHAR(255) NOT NULL DEFAULT 'pending'");
    }
};
