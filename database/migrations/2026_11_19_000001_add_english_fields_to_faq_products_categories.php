<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FAQ questions, product descriptions and category descriptions
     * were Arabic-only. The admin can now fill in the English versions;
     * rendering serves the English value when the locale is English and
     * the English value is non-empty, otherwise it falls back to Arabic
     * (same pattern as the pages table, 2026_11_18_000003).
     */
    public function up(): void
    {
        Schema::table('common_questions', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
            $table->text('description_en')->nullable()->after('description');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->text('description_en')->nullable()->after('description');
        });

        Schema::table('main_categories', function (Blueprint $table) {
            $table->text('description_en')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('common_questions', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'description_en']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('description_en');
        });

        Schema::table('main_categories', function (Blueprint $table) {
            $table->dropColumn('description_en');
        });
    }
};
