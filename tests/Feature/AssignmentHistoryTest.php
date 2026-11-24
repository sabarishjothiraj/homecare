<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who is placed where, and who replaced whom.
 *
 * The same labels drive the contract page, employee pages and the assignment
 * report, so they're derived once on the model and checked here.
 */
class AssignmentHistoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Contract $contract;

    private ContractItem $item;

    private Employee $alice;

    private Employee $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
        $service = Service::factory()->create();
        $this->contract = Contract::factory()->create(['service_id' => $service->id, 'status' => 'active']);
        $this->item = ContractItem::factory()->create(['contract_id' => $this->contract->id, 'service_id' => $service->id]);

        $this->alice = Employee::factory()->create(['first_name' => 'Alice', 'last_name' => 'Nurse']);
        $this->bob = Employee::factory()->create(['first_name' => 'Bob', 'last_name' => 'Carer']);
    }

    private function assignment(Employee $employee, array $attributes = []): Assignment
    {
        return Assignment::factory()->create(array_merge([
            'contract_id' => $this->contract->id,
            'contract_item_id' => $this->item->id,
            'service_id' => $this->item->service_id,
            'employee_id' => $employee->id,
            'start_date' => now()->subMonth()->toDateString(),
        ], $attributes));
    }

    /**
     * Alice was replaced by Bob ten days ago.
     */
    private function replacement(): array
    {
        $original = $this->assignment($this->alice, [
            'is_active' => false,
            'end_date' => now()->subDays(10)->toDateString(),
        ]);
        $successor = $this->assignment($this->bob, [
            'start_date' => now()->subDays(10)->toDateString(),
            'replaced_assignment_id' => $original->id,
            'notes' => 'Alice relocated',
        ]);

        return [$original, $successor];
    }

    public function test_types_are_derived_from_how_the_employee_joined_the_line(): void
    {
        [$original, $successor] = $this->replacement();
        $temporary = $this->assignment(Employee::factory()->create(), ['is_emergency_replacement' => true, 'replaced_assignment_id' => $original->id]);
        $cover = $this->assignment(Employee::factory()->create(), ['cover_for_assignment_id' => $successor->id, 'end_date' => now()->addDay()->toDateString()]);

        $this->assertSame('primary', $original->type());
        $this->assertSame('replacement', $successor->type());
        $this->assertSame('temporary', $temporary->type());
        $this->assertSame('cover', $cover->type());

        $this->assertSame([$successor->id], Assignment::ofType('replacement')->pluck('id')->all());
        $this->assertSame([$cover->id], Assignment::ofType('cover')->pluck('id')->all());
    }

    public function test_a_replaced_assignment_says_who_replaced_it_and_when(): void
    {
        [$original, $successor] = $this->replacement();

        $this->assertSame('Replaced by Bob Carer on '.$successor->start_date->format('M d, Y'), $original->fresh()->endReason());
        $this->assertNull($successor->fresh()->endReason());
    }

    public function test_other_end_reasons(): void
    {
        $cover = $this->assignment($this->alice, ['cover_for_assignment_id' => $this->assignment($this->bob)->id, 'is_active' => false, 'end_date' => now()->toDateString()]);
        $this->assertSame('Cover ended', $cover->endReason());

        $ended = $this->assignment(Employee::factory()->create(), ['is_active' => false]);
        $this->assertSame('Ended', $ended->endReason());

        $this->contract->update(['status' => 'closed']);
        $this->assertSame('Contract closed', $ended->fresh()->endReason());
    }

    public function test_the_employee_list_shows_availability(): void
    {
        $this->assignment($this->alice);
        Employee::factory()->create(['first_name' => 'Carol', 'last_name' => 'Free']);

        $this->actingAs($this->admin)->get('/admin/employees')
            ->assertOk()
            ->assertSee('Availability')
            ->assertSee('Assigned')
            ->assertSee('Available');
    }

    public function test_the_employee_list_can_be_filtered_by_availability(): void
    {
        $this->assignment($this->alice);
        Employee::factory()->create(['first_name' => 'Carol', 'last_name' => 'Free']);
        Employee::factory()->pending()->create(['first_name' => 'Dave', 'last_name' => 'Pending']);

        $this->actingAs($this->admin)->get('/admin/employees?availability=available')
            ->assertOk()
            ->assertSee('Carol Free')
            ->assertSee('Bob Carer')
            ->assertDontSee('Alice Nurse')
            ->assertDontSee('Dave Pending');

        $this->actingAs($this->admin)->get('/admin/employees?availability=placed')
            ->assertOk()
            ->assertSee('Alice Nurse')
            ->assertDontSee('Carol Free');
    }

    public function test_the_employee_page_shows_type_and_outcome(): void
    {
        [$original] = $this->replacement();

        $this->actingAs($this->admin)->get("/admin/employees/{$this->alice->id}")
            ->assertOk()
            ->assertSee('Assignment History')
            ->assertSee('Primary')
            ->assertSee('Replaced by Bob Carer on', false);

        $this->actingAs($this->admin)->get("/admin/employees/{$this->bob->id}")
            ->assertOk()
            ->assertSee('Replacement')
            ->assertSee('Replaced: Alice Nurse', false);
    }

    public function test_the_contract_page_labels_replacements_both_ways(): void
    {
        $this->replacement();

        $this->actingAs($this->admin)->get("/admin/contracts/{$this->contract->id}")
            ->assertOk()
            ->assertSee('Replaced by Bob Carer on', false)
            ->assertSee('Replaced: Alice Nurse', false)
            ->assertSee('Alice relocated');
    }

    public function test_the_assignment_report_includes_history_columns_and_type_filter(): void
    {
        $this->replacement();
        $report = app(ReportService::class);

        $rows = $report->getAssignmentReport([]);
        $aliceRow = $rows->firstWhere('Employee Name', 'Alice Nurse');
        $bobRow = $rows->firstWhere('Employee Name', 'Bob Carer');

        $this->assertSame('Primary', $aliceRow['Type']);
        $this->assertSame('Bob Carer', $aliceRow['Replaced By']);
        $this->assertSame('Alice relocated', $aliceRow['Replacement Note']);
        $this->assertStringStartsWith('Replaced by Bob Carer', $aliceRow['Ended Reason']);

        $this->assertSame('Replacement', $bobRow['Type']);
        $this->assertSame('Alice Nurse', $bobRow['Replaced / Covering For']);
        $this->assertSame('Active', $bobRow['Status']);

        $this->assertSame(['Bob Carer'], $report->getAssignmentReport(['type' => 'replacement'])->pluck('Employee Name')->all());
        $this->assertSame(['Alice Nurse'], $report->getAssignmentReport(['status' => 'ended'])->pluck('Employee Name')->all());
    }
}
