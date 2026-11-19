<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneExpiredData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-expired-data {--days=30 : Delete carts untouched for this many days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete expired verification codes and abandoned guest/user carts';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Verification codes: users_verificationCodes.verification_code_expires_at
        $codes = DB::table('users_verificationCodes')
            ->whereNotNull('verification_code_expires_at')
            ->where('verification_code_expires_at', '<', now())
            ->delete();

        // Carts: carts.updated_at — a cart untouched for --days is abandoned.
        // Deleting the cart row is enough; guests are left alone (they are
        // referenced by orders too) and products are never touched.
        $days = (int) $this->option('days');
        $carts = DB::table('carts')
            ->where('updated_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$codes} expired verification code(s) and {$carts} abandoned cart(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
