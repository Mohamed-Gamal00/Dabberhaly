<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Rename `cookie_discount_ids` to `applied_discount_codes`.
     *
     * The old name described neither the model (`UserDiscountCode`)
     * nor what a row means: a discount code applied to a cart
     * (keyed by the cart cookie). The rename keeps the data —
     * it only renames the table.
     */
    public function up(): void
    {
        if (Schema::hasTable('cookie_discount_ids') && !Schema::hasTable('applied_discount_codes')) {
            Schema::rename('cookie_discount_ids', 'applied_discount_codes');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('applied_discount_codes') && !Schema::hasTable('cookie_discount_ids')) {
            Schema::rename('applied_discount_codes', 'cookie_discount_ids');
        }
    }
};
