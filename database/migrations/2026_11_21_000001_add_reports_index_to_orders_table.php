<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every revenue report filters orders by payment_status = 'paid'
     * AND status != 'cancelled' AND created_at in a range (the paid
     * scope in ReportsRepository), and joins order_items to it. No
     * existing index covers those columns: orders only has the primary
     * key, the unique index on number, and the foreign-key indexes.
     * Equality columns first, then the range column, so the paid-scope
     * queries can seek instead of scanning. Additive only — safe to
     * run on a live table.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['payment_status', 'status', 'created_at'], 'orders_reports_idx');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_reports_idx');
        });
    }
};
