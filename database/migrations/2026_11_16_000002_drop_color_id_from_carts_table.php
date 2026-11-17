<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the color_id foreign key and column from the carts table.
 *
 * Color selection is tracked on the product level; storing it per cart row
 * is redundant and not used in order processing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (Schema::hasColumn('carts', 'color_id')) {
                $table->dropForeign(['color_id']);
                $table->dropColumn('color_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            if (!Schema::hasColumn('carts', 'color_id')) {
                $table->foreignId('color_id')->nullable()->constrained('colors')->nullOnDelete();
            }
        });
    }
};
