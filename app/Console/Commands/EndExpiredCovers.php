<?php

namespace App\Console\Commands;

use App\Services\AssignmentCoverService;
use Illuminate\Console\Command;

class EndExpiredCovers extends Command
{
    protected $signature = 'assignments:end-covers';

    protected $description = 'End temporary covers whose last day has passed so the original employee resumes';

    public function handle(AssignmentCoverService $covers): int
    {
        $ended = $covers->endExpiredCovers();

        $this->info("Ended {$ended} temporary cover(s).");

        return Command::SUCCESS;
    }
}
