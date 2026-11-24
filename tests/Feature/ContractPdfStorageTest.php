<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Employee;
use App\Models\Patient;
use App\Models\Service;
use App\Models\User;
use App\Services\ContractService;
use App\Services\TamilPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Where agreement PDFs live, when they are re-rendered, and what they say.
 *
 * They used to sit on the public disk under a guessable name (anyone with a
 * contract number could fetch one without logging in), and once rendered they
 * were served as-is after edits, so a changed contract printed stale.
 *
 * mPDF itself is stubbed: the stub writes a placeholder to the disk it is
 * given and keeps the HTML it was asked to render.
 */
class ContractPdfStorageTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private array $rendered = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $pdf = Mockery::mock(TamilPdfService::class);
        $pdf->shouldReceive('htmlToFile')->andReturnUsing(function (string $html, string $path, string $title, string $disk) {
            $this->rendered[] = $html;
            Storage::disk($disk)->put($path, '%PDF-1.4 stub');

            return $path;
        });
        $this->app->instance(TamilPdfService::class, $pdf);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function contracts(): ContractService
    {
        return app(ContractService::class);
    }

    private function staffedContract(array $attributes = []): Contract
    {
        $service = Service::factory()->create(['name' => 'Home Nurse']);
        $contract = Contract::factory()->create(array_merge(['service_id' => $service->id, 'status' => 'active'], $attributes));
        $item = ContractItem::factory()->create(['contract_id' => $contract->id, 'service_id' => $service->id]);
        Assignment::factory()->create([
            'contract_id' => $contract->id,
            'contract_item_id' => $item->id,
            'service_id' => $service->id,
            'is_active' => true,
        ]);

        return $contract;
    }

    // ---- contracts-11: private storage ---------------------------------------

    public function test_the_agreement_is_written_to_the_private_disk_only(): void
    {
        $contract = $this->staffedContract();

        $path = $this->contracts()->generateContractPDF($contract);

        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $this->assertSame($path, $contract->fresh()->pdf_path);
    }

    public function test_the_file_name_cannot_be_guessed_from_the_contract_number(): void
    {
        $contract = $this->staffedContract();

        $path = $this->contracts()->generateContractPDF($contract);

        $this->assertMatchesRegularExpression(
            '#^contracts/contract_'.preg_quote($contract->contract_number, '#').'_[0-9a-f]{32}\.pdf$#',
            $path
        );
    }

    /**
     * Files written by earlier releases were web-readable. The first access
     * takes them off the public disk.
     */
    public function test_a_legacy_public_file_is_moved_off_the_public_disk_on_first_access(): void
    {
        $contract = $this->staffedContract();
        $legacy = 'contracts/contract_'.$contract->contract_number.'.pdf';
        Storage::disk('public')->put($legacy, '%PDF-1.4 legacy');
        $contract->update(['pdf_path' => $legacy]);

        $path = $this->contracts()->ensurePdf($contract);

        Storage::disk('public')->assertMissing($legacy);
        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $contract->fresh()->pdf_path);
    }

    public function test_the_bulk_command_moves_every_legacy_file(): void
    {
        Storage::disk('public')->put('contracts/contract_CNT-A.pdf', 'a');
        Storage::disk('public')->put('contracts/contract_CNT-B.pdf', 'b');

        $this->artisan('contracts:secure-pdfs')
            ->expectsOutput('Moved 2 contract PDF(s) to private storage.')
            ->assertSuccessful();

        Storage::disk('public')->assertMissing('contracts/contract_CNT-A.pdf');
        Storage::disk('public')->assertMissing('contracts/contract_CNT-B.pdf');
        $this->assertSame('b', Storage::disk('local')->get('contracts/contract_CNT-B.pdf'));
    }

    public function test_the_download_route_serves_the_private_file_to_staff(): void
    {
        $contract = $this->staffedContract();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('admin.contracts.download', $contract))
            ->assertOk()
            ->assertDownload('Contract-'.$contract->contract_number.'.pdf');

        $this->actingAs($admin)
            ->get(route('admin.contracts.view-pdf', $contract))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_download_route_needs_a_login(): void
    {
        $contract = $this->staffedContract();

        $this->get(route('admin.contracts.download', $contract))->assertRedirect();
        $this->assertSame([], $this->rendered);
    }

    // ---- contracts-12: never serve a stale render ------------------------------

    public function test_an_up_to_date_file_is_served_without_re_rendering(): void
    {
        $contract = $this->staffedContract();

        $first = $this->contracts()->ensurePdf($contract);
        $second = $this->contracts()->ensurePdf($contract->fresh());

        $this->assertSame($first, $second);
        $this->assertCount(1, $this->rendered);
    }

    public function test_an_edited_contract_is_re_rendered_on_the_next_view(): void
    {
        $contract = $this->staffedContract(['amount' => 10000]);
        $old = $this->contracts()->ensurePdf($contract);

        $contract->update(['amount' => 12000]);
        $new = $this->contracts()->ensurePdf($contract->fresh());

        $this->assertNotSame($old, $new);
        $this->assertCount(2, $this->rendered);
        $this->assertStringContainsString('12,000', end($this->rendered));
        Storage::disk('local')->assertMissing($old);
        Storage::disk('local')->assertExists($new);
    }

    /**
     * Not only the contract row: anything printed (here the nurse on the line)
     * changing makes the file stale.
     */
    public function test_a_replaced_employee_is_re_rendered_on_the_next_view(): void
    {
        $contract = $this->staffedContract();
        $this->contracts()->ensurePdf($contract);

        $assignment = $contract->assignments()->first();
        $assignment->update(['is_active' => false]);
        $incoming = Employee::factory()->create(['first_name' => 'Kavya', 'last_name' => 'Replacement']);
        Assignment::factory()->create([
            'contract_id' => $contract->id,
            'contract_item_id' => $assignment->contract_item_id,
            'service_id' => $assignment->service_id,
            'employee_id' => $incoming->id,
            'replaced_assignment_id' => $assignment->id,
            'is_active' => true,
        ]);

        $this->contracts()->ensurePdf($contract->fresh());

        $this->assertCount(2, $this->rendered);
        $this->assertStringContainsString('Kavya Replacement', end($this->rendered));
    }

    // ---- contracts-18: what the agreement says -------------------------------

    public function test_contract_specific_terms_are_printed(): void
    {
        $contract = $this->staffedContract(['terms' => "Night shift only.\nNo cooking."]);

        $this->contracts()->generateContractPDF($contract);

        $this->assertStringContainsString('Night shift only.<br />', $this->rendered[0]);
        $this->assertStringContainsString('No cooking.', $this->rendered[0]);
    }

    /**
     * Blank terms are stored as the generic English defaults; the Tamil
     * agreement already prints the company's standard conditions.
     */
    public function test_the_generic_default_terms_are_not_printed(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());
        $service = Service::factory()->create();
        $employee = Employee::factory()->create();
        $employee->services()->attach($service->id);

        $contract = $this->contracts()->createContract([
            'patient_id' => Patient::factory()->create()->id,
            'duration_type' => 'monthly',
            'duration_value' => 1,
            'start_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'line_items' => [['service_id' => $service->id, 'employee_id' => $employee->id, 'amount' => 5000]],
        ]);
        $this->rendered = [];

        $this->contracts()->generateContractPDF($contract);

        $this->assertStringNotContainsString('Special Terms', $this->rendered[0]);
        $this->assertStringNotContainsString('professional liability insurance', $this->rendered[0]);
    }

    public function test_every_staffed_line_is_printed_not_just_the_first(): void
    {
        $contract = $this->staffedContract();
        $second = Service::factory()->create(['name' => 'Caretaker']);
        $item = ContractItem::factory()->create(['contract_id' => $contract->id, 'service_id' => $second->id]);
        $caretaker = Employee::factory()->create(['first_name' => 'Bala', 'last_name' => 'Caretaker']);
        Assignment::factory()->create([
            'contract_id' => $contract->id,
            'contract_item_id' => $item->id,
            'service_id' => $second->id,
            'employee_id' => $caretaker->id,
            'is_active' => true,
        ]);
        $nurse = $contract->assignments()->first()->employee->getField('name');

        $this->contracts()->generateContractPDF($contract);

        $this->assertStringContainsString($nurse, $this->rendered[0]);
        $this->assertStringContainsString('Bala Caretaker', $this->rendered[0]);
        $this->assertStringContainsString($nurse.', Bala Caretaker', $this->rendered[0]);
    }

    public function test_names_are_escaped_once_not_twice(): void
    {
        $contract = $this->staffedContract();
        $contract->patient->update(['name' => "Mary D'Souza"]);

        $this->contracts()->generateContractPDF($contract->fresh());

        $this->assertStringContainsString('Mary D&#039;Souza', $this->rendered[0]);
        $this->assertStringNotContainsString('&amp;#039;', $this->rendered[0]);
    }
}
