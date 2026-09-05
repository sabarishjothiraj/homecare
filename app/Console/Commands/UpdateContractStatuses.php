<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ContractService;

class UpdateContractStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'contracts:update-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update contract statuses based on dates and assignments';

    protected $contractService;

    /**
     * Create a new command instance.
     */
    public function __construct(ContractService $contractService)
    {
        parent::__construct();
        $this->contractService = $contractService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Updating contract statuses...');

        $updated = $this->contractService->updateContractStatuses();

        $this->info("Successfully updated {$updated} contract(s).");

        return Command::SUCCESS;
    }
}

