<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Setting model now casts secret_key / sms_api_key /
     * publishable_key as `encrypted`. Two things are needed for rows
     * that were written before that cast existed:
     *
     *  1. The columns are VARCHAR(255), but an encrypted payload is
     *     longer than the plaintext (base64 JSON envelope) and would
     *     be truncated on save — widen them to TEXT.
     *  2. Any still-plaintext value must be encrypted in place, or
     *     the first read of the settings row throws a
     *     DecryptException (500) after deploy.
     *
     * Values that already decrypt cleanly are left untouched, so the
     * migration is safe to run more than once.
     */
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        foreach (['secret_key', 'sms_api_key', 'publishable_key'] as $column) {
            if (Schema::hasColumn('settings', $column)) {
                DB::statement("ALTER TABLE `settings` MODIFY `{$column}` TEXT NULL");
            }
        }

        foreach (DB::table('settings')->select('id', 'secret_key', 'sms_api_key', 'publishable_key')->get() as $row) {
            $updates = [];

            foreach (['secret_key', 'sms_api_key', 'publishable_key'] as $column) {
                $value = $row->{$column};

                if ($value === null || $value === '') {
                    continue;
                }

                try {
                    Crypt::decryptString($value);
                } catch (DecryptException) {
                    $updates[$column] = Crypt::encryptString($value);
                }
            }

            if ($updates !== []) {
                DB::table('settings')->where('id', $row->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        // Encrypted values cannot be turned back into plaintext safely
        // without the app key, and TEXT columns accept VARCHAR values —
        // nothing to undo.
    }
};
