<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop dead tables and columns (D-08 + D-15). Old data does not
     * matter while the app is in development.
     *
     * Tables:
     * - product_user — wishlists use wishlist_products_user instead.
     * - user_discount_codes — discount flows use cookie_discount_ids.
     * - designs_extra — no model maps to it (Design uses designs).
     * - user_tokens / forget_passwords — unused models; password reset
     *   uses verification codes instead.
     *
     * Columns:
     * - discount_codes.product_ids — the discount_code_product pivot
     *   is used instead (the controller unsets it before saving).
     * - users.name — neither registration path writes it; the app uses
     *   first_name / family_name (the admin guard's name is untouched).
     */
    public function up(): void
    {
        Schema::dropIfExists('product_user');
        Schema::dropIfExists('user_discount_codes');
        Schema::dropIfExists('designs_extra');
        Schema::dropIfExists('user_tokens');
        Schema::dropIfExists('forget_passwords');

        Schema::table('discount_codes', function ($table) {
            $table->dropColumn('product_ids');
        });

        Schema::table('users', function ($table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        // Tables/columns are intentionally not recreated: they held no
        // live data and nothing in the app references them.
    }
};
