<?php

use App\Helper\SlugHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * products.slug and main_categories.slug are looked up by value
     * but had no unique index, and slugs were generated without a
     * uniqueness check. Dedupe any existing duplicates first, then
     * add the unique indexes. Raw SQL is used so no doctrine/dbal
     * dependency is needed.
     */
    public function up(): void
    {
        foreach (['products', 'main_categories'] as $table) {
            $duplicates = DB::select(
                "SELECT slug FROM {$table} WHERE slug IS NOT NULL AND slug != '' GROUP BY slug HAVING COUNT(*) > 1"
            );

            foreach ($duplicates as $duplicate) {
                $rows = DB::table($table)
                    ->where('slug', $duplicate->slug)
                    ->orderBy('id')
                    ->get(['id']);

                // Keep the first row's slug, rename the rest.
                foreach ($rows->skip(1) as $row) {
                    DB::table($table)
                        ->where('id', $row->id)
                        ->update(['slug' => SlugHelper::unique($table, $duplicate->slug)]);
                }
            }

            if (! $this->hasUniqueIndex($table, 'slug')) {
                DB::statement("ALTER TABLE {$table} ADD UNIQUE INDEX {$table}_slug_unique (slug)");
            }
        }
    }

    public function down(): void
    {
        foreach (['products', 'main_categories'] as $table) {
            if ($this->hasUniqueIndex($table, 'slug')) {
                Schema::table($table, function ($table) {
                    $table->dropUnique(['slug']);
                });
            }
        }
    }

    private function hasUniqueIndex(string $table, string $column): bool
    {
        $database = DB::getDatabaseName();

        return (bool) DB::selectOne(
            'SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND NON_UNIQUE = 0',
            [$database, $table, $column]
        );
    }
};
