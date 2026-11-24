<?php

namespace App\Console\Commands;

use App\Services\ContractService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Earlier releases wrote agreement PDFs to storage/app/public/contracts, which
 * the web server serves to anyone at /storage/contracts/... with no login or
 * permission check. This moves any still there to the private disk; the
 * contract pages already find them there.
 */
class SecureContractPdfs extends Command
{
    protected $signature = 'contracts:secure-pdfs';

    protected $description = 'Move contract PDFs left on the public disk by earlier releases to private storage';

    public function handle(ContractService $contracts): int
    {
        $moved = 0;

        foreach (Storage::disk('public')->files('contracts') as $path) {
            if (! str_ends_with(strtolower($path), '.pdf')) {
                continue;
            }

            $contracts->movePdfOffPublicDisk($path);

            if (! Storage::disk('public')->exists($path)) {
                $moved++;
            }
        }

        $this->info("Moved {$moved} contract PDF(s) to private storage.");

        return Command::SUCCESS;
    }
}
