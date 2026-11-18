<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Static pages (terms, privacy, shipping policy, exchanges/returns)
     * were Arabic-only. The admin can now fill in English title/content;
     * rendering serves the English version when the locale is English and
     * the English value is non-empty, otherwise it falls back to Arabic.
     */
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('title');
            $table->longText('content_en')->nullable()->after('content');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'content_en']);
        });
    }
};
