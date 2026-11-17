<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the guest_id foreign key and column from the carts table.
 *
 * Guest cart items are already identified by cookie_id, which is a NOT NULL
 * UUID column set on every cart row. The guest_id FK to the guests table is
 * redundant and couples the cart to a separate guests record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasColumn('carts', 'guest_id')) {
                $table->dropForeign(['guest_id']);
                $table->dropColumn('guest_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (!Schema::hasColumn('carts', 'guest_id')) {
                $table->foreignId('guest_id')->nullable()->constrained('guests')->nullOnDelete();
            }
        });
    }
};
