<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Payment-method toggles: the admin decides which payment
     * channels the customer sees at checkout (e.g. hide card
     * payments until Moyasar is configured, launch cash-only).
     * Both default to visible so existing stores keep working.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('payment_cod_enabled')->default(true)->after('free_shipping_threshold');
            $table->boolean('payment_card_enabled')->default(true)->after('payment_cod_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['payment_cod_enabled', 'payment_card_enabled']);
        });
    }
};
