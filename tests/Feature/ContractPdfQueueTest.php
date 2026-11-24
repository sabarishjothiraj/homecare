<?php

namespace Tests\Feature;

use App\Jobs\GenerateContractPdf;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Contract PDF rendering is queued rather than inline.
 *
 * One render takes about six seconds, and it used to run inside the same
 * database transaction that creates the contract - holding that row's lock
 * for the whole render. These tests pin down that it is dispatched instead,
 * and that a reader never sees a missing PDF as a result.
 */
class ContractPdfQueueTest extends TestCase
{
    use RefreshDatabase;

    private function contractPayload(): array
    {
        $service = Service::factory()->create();
        $employee = Employee::factory()->create();
        $employee->services()->attach($service->id);

        return [
            'patient_id' => Patient::factory()->create()->id,
            'service_id' => $service->id,
            'duration_type' => 'monthly',
            'duration_value' => 1,
            'start_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'line_items' => [
                [
                    'service_id' => $service->id,
                    'employee_id' => $employee->id,
                    'amount' => 5000,
                ],
            ],
        ];
    }

    public function test_creating_a_contract_queues_the_render_instead_of_running_it(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->superAdmin()->create());

        $contract = app(ContractService::class)->createContract($this->contractPayload());

        Queue::assertPushed(
            GenerateContractPdf::class,
            fn ($job) => $job->contract->is($contract)
        );
    }

    /**
     * The contract must be usable immediately; only the PDF lags behind.
     */
    public function test_the_contract_is_saved_even_though_the_render_has_not_run(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->superAdmin()->create());

        $contract = app(ContractService::class)->createContract($this->contractPayload());

        $this->assertDatabaseHas('contracts', ['id' => $contract->id]);
        $this->assertSame(5000.0, (float) $contract->amount);
        $this->assertNull($contract->pdf_path);
    }

    public function test_updating_a_contract_queues_a_render(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $contract = Contract::factory()->create();

        Queue::fake();

        $payload = $this->contractPayload();
        $payload['line_items'][0]['amount'] = 7500;

        app(ContractService::class)->updateContract($contract, $payload);

        Queue::assertPushed(
            GenerateContractPdf::class,
            fn ($job) => $job->contract->is($contract)
        );
    }

    /**
     * Two edits before the worker catches up should produce one render, not
     * two - that is what ShouldBeUniqueUntilProcessing buys.
     */
    public function test_back_to_back_edits_collapse_into_a_single_queued_render(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->superAdmin()->create());

        $contract = app(ContractService::class)->createContract($this->contractPayload());

        $payload = $this->contractPayload();
        $payload['line_items'][0]['amount'] = 7500;
        app(ContractService::class)->updateContract($contract, $payload);

        Queue::assertPushed(GenerateContractPdf::class, 1);
    }

    public function test_the_job_hands_the_contract_to_the_renderer(): void
    {
        $contract = Contract::factory()->create();

        $contracts = Mockery::mock(ContractService::class);
        $contracts->shouldReceive('generateContractPDF')
            ->once()
            ->with(Mockery::on(fn ($given) => $given->is($contract)))
            ->andReturn('contracts/generated.pdf');

        (new GenerateContractPdf($contract))->handle($contracts);

        $this->addToAssertionCount(1);
    }

    /**
     * Two edits in quick succession should not queue two six-second renders.
     */
    public function test_the_job_is_unique_per_contract(): void
    {
        $contract = Contract::factory()->create();

        $this->assertSame(
            (string) $contract->getKey(),
            (new GenerateContractPdf($contract))->uniqueId()
        );
    }

    /**
     * A file rendered from the contract as it stands is served as-is. (The
     * path is keyed on the rendered content, so it is looked up rather than
     * hard-coded; see ContractPdfStorageTest for the stale-file cases.)
     */
    public function test_ensure_pdf_reuses_an_existing_file_without_re_rendering(): void
    {
        Storage::fake('local');

        $contract = Contract::factory()->create();

        $contracts = Mockery::mock(ContractService::class)->makePartial();
        $contracts->shouldNotReceive('generateContractPDF');

        $path = $contracts->pdfPathFor($contract);
        Storage::disk('local')->put($path, '%PDF-1.4 stub');
        $contract->update(['pdf_path' => $path]);

        $this->assertSame($path, $contracts->ensurePdf($contract));
    }

    /**
     * The window between a contract changing and the worker catching up must
     * not surface as a broken download.
     */
    public function test_ensure_pdf_renders_on_demand_when_the_file_is_missing(): void
    {
        Storage::fake('local');

        $contract = Contract::factory()->create(['pdf_path' => null]);

        $contracts = Mockery::mock(ContractService::class)->makePartial();
        $contracts->shouldReceive('generateContractPDF')
            ->once()
            ->andReturn('contracts/rendered.pdf');

        $this->assertSame('contracts/rendered.pdf', $contracts->ensurePdf($contract));
    }

    public function test_ensure_pdf_re_renders_when_the_recorded_file_has_vanished(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $contract = Contract::factory()->create(['pdf_path' => 'contracts/gone.pdf']);

        $contracts = Mockery::mock(ContractService::class)->makePartial();
        $contracts->shouldReceive('generateContractPDF')
            ->once()
            ->andReturn('contracts/rebuilt.pdf');

        $this->assertSame('contracts/rebuilt.pdf', $contracts->ensurePdf($contract));
    }

    /**
     * Contracts soft-delete, so one removed between dispatch and processing
     * would otherwise make SerializesModels throw and burn every retry.
     */
    public function test_a_job_for_a_deleted_contract_is_discarded_rather_than_failed(): void
    {
        $job = new GenerateContractPdf(Contract::factory()->create());

        $this->assertTrue($job->deleteWhenMissingModels);
    }

    public function test_retries_are_spaced_out(): void
    {
        $job = new GenerateContractPdf(Contract::factory()->create());

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 60], $job->backoff());
    }

    /**
     * A render that exhausts its retries is recoverable - ensurePdf() still
     * produces the file on first view - but it must not vanish silently.
     */
    public function test_a_permanently_failed_render_is_logged_with_the_contract(): void
    {
        $contract = Contract::factory()->create();

        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) use ($contract) {
                return str_contains($message, 'Contract PDF generation failed')
                    && $context['contract_id'] === $contract->getKey()
                    && $context['exception'] === 'disk full';
            });

        (new GenerateContractPdf($contract))->failed(new \RuntimeException('disk full'));

        // The expectation above is verified on teardown; this keeps PHPUnit
        // from reporting the test as assertion-free.
        $this->addToAssertionCount(1);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
