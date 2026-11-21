<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Keep Telescope's tables from growing forever. Telescope is a
        // dev-only package (require-dev), so schedule the prune only
        // when it is actually installed — same filesystem guard as the
        // provider registration in config/app.php. On a --no-dev
        // server the command does not exist and schedule:run would
        // fail every day.
        if (is_dir(base_path('vendor/laravel/telescope'))) {
            $schedule->command('telescope:prune')->daily();
        }
        // Drop failed jobs older than 3 days.
        $schedule->command('queue:prune-failed --hours=72')->daily();
        // Delete expired verification codes and abandoned carts.
        $schedule->command('app:prune-expired-data')->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
