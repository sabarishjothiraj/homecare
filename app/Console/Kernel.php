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
        // End temporary covers after their last day, before statuses are recomputed
        $schedule->command('assignments:end-covers')->dailyAt('00:00');

        // Update contract statuses daily at midnight (also ends assignments
        // past their own end date, and releases staff on expired contracts)
        $schedule->command('contracts:update-status')->dailyAt('00:05');

        // Safety net for PDFs an earlier release left on the public disk; a
        // no-op once they have all been moved.
        $schedule->command('contracts:secure-pdfs')->dailyAt('00:10');

        // Generate contract expiry notifications daily at 1 AM
        $schedule->command('notifications:generate')->dailyAt('01:00');

        // Keep failed_jobs from growing without bound. A week is long enough
        // to investigate a failure and retry it by hand.
        $schedule->command('queue:prune-failed --hours=168')->daily();

        // API tokens expire after SANCTUM_EXPIRATION minutes; drop the dead
        // rows once they are a day past expiry.
        $schedule->command('sanctum:prune-expired --hours=24')->daily();
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
