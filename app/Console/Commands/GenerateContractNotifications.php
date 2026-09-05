<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Contract;
use App\Models\Notification;
use App\Models\Setting;
use Carbon\Carbon;

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
        $alertDate = Carbon::now()->addDays($alertDays)->format('Y-m-d');

        // Find contracts expiring within alert period
        $expiringContracts = Contract::whereIn('status', ['active', 'closing_soon'])
            ->whereDate('end_date', '=', $alertDate)
            ->get();

        $count = 0;

        foreach ($expiringContracts as $contract) {
            // Check if notification already exists for this contract and date
            $exists = Notification::where('type', 'contract_expiring')
                ->where('related_id', $contract->id)
                ->whereDate('created_at', Carbon::today())
                ->exists();

            if (!$exists) {
                Notification::create([
                    'type' => 'contract_expiring',
                    'title' => 'Contract Expiring Soon',
                    'message' => "Contract {$contract->contract_number} for patient {$contract->patient->name} will expire on {$contract->end_date->format('M d, Y')}.",
                    'related_id' => $contract->id,
                    'is_read' => false,
                ]);

                $count++;
            }
        }

        $this->info("Generated {$count} new notification(s).");

        return Command::SUCCESS;
    }
}

