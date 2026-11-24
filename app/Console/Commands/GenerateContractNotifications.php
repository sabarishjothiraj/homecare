<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Console\Command;

class GenerateContractNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate notifications for expiring contracts';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating contract expiry notifications...');

        $alertDays = (int) Setting::get('contract_alert_days', 7);
        $today = today();

        // The whole alert window, not only the day exactly $alertDays out: a
        // contract created inside the window, or a day the scheduler missed,
        // was otherwise never flagged at all.
        $expiringContracts = Contract::whereIn('status', ['active', 'closing_soon'])
            ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays($alertDays)->toDateString()])
            ->with(['patient' => fn ($query) => $query->withTrashed()])
            ->get();

        $count = 0;

        foreach ($expiringContracts as $contract) {
            // Once per window: a notification raised since this end date's
            // window opened counts. Extending the contract opens a new window.
            $windowStart = $contract->end_date->copy()->subDays($alertDays)->startOfDay();

            $exists = Notification::where('type', 'contract_expiring')
                ->where('contract_id', $contract->id)
                ->where('created_at', '>=', $windowStart)
                ->exists();

            if (! $exists) {
                Notification::createContractExpiringNotification($contract);

                $count++;
            }
        }

        $this->info("Generated {$count} new notification(s).");

        return Command::SUCCESS;
    }
}
