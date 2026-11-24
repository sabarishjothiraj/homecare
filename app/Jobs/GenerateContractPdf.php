<?php

namespace App\Jobs;

use App\Models\Contract;
use App\Services\ContractService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Re-render a contract's Tamil agreement PDF off the request.
 *
 * The render costs roughly six seconds: mPDF has to run the Indic shaper over
 * the whole document. Doing that inline made every contract mutation wait on
 * it, and in createContract/updateContract it happened inside the database
 * transaction, holding row locks on the contract for the duration.
 */
class GenerateContractPdf implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Renders are slow but not fragile; a couple of retries covers a transient
     * disk or font-loading failure without hammering the worker.
     */
    public int $tries = 3;

    public int $timeout = 120;

    /**
     * Ceiling on how long the uniqueness lock may survive a crashed worker.
     */
    public int $uniqueFor = 300;

    /**
     * Contracts soft-delete. Without this, a contract removed between dispatch
     * and processing makes SerializesModels throw ModelNotFoundException, and
     * the job retries to exhaustion before landing in failed_jobs - noise for
     * work that is no longer wanted. Dropping it silently is the right answer:
     * a deleted contract does not need a PDF.
     */
    public bool $deleteWhenMissingModels = true;

    public function __construct(public readonly Contract $contract) {}

    /**
     * Spread retries out. A failure here is usually the disk or the font cache
     * rather than something that clears instantly.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(ContractService $contracts): void
    {
        $contracts->generateContractPDF($this->contract);
    }

    /**
     * A permanently failed render is not fatal - ensurePdf() will still
     * produce the document on first view - but it must be visible rather than
     * disappearing into failed_jobs unnoticed.
     */
    public function failed(?Throwable $e): void
    {
        Log::error('Contract PDF generation failed after all retries.', [
            'contract_id' => $this->contract->getKey(),
            'contract_number' => $this->contract->contract_number,
            'exception' => $e?->getMessage(),
        ]);
    }

    /**
     * Collapses repeated renders of the same contract while one is still
     * waiting, so a burst of edits does not queue a burst of six-second jobs.
     *
     * The lock releases as soon as processing begins (rather than when the
     * job ends), so an edit made *during* a render still queues a fresh one
     * and the PDF cannot be left reflecting stale data.
     */
    public function uniqueId(): string
    {
        return (string) $this->contract->getKey();
    }
}
