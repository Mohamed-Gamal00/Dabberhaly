<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The cart drawer's free-shipping progress bar used a hardcoded
     * 500 SAR threshold. It now comes from the store settings so the
     * admin can change it from /admin/front_settings.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->decimal('free_shipping_threshold', 10, 2)->nullable()->after('value_added_tax');
        });

        // Keep the current behaviour for existing stores.
        DB::table('settings')->whereNull('free_shipping_threshold')->update([
            'free_shipping_threshold' => 500,
        ]);
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('free_shipping_threshold');
        });
    }
};
