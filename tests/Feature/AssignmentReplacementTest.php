<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Employee;
use App\Models\Service;
use App\Models\User;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Replacing the employee on a contract service line.
 *
 * This is the operationally busiest path in the app - staff leave mid-contract
 * and have to be swapped without losing the placement history the agreement
 * PDF is built from.
 */
class AssignmentReplacementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Service $service;

    private Contract $contract;

    private ContractItem $item;

    private Assignment $original;

    protected function setUp(): void
    {
        parent::setUp();

        // The agreement PDF is re-rendered through mPDF on every replacement.
        // Stubbed here so these tests exercise the placement logic, not the
        // Tamil shaping engine.
        $pdf = Mockery::mock(ContractService::class)->makePartial();
        $pdf->shouldReceive('generateContractPDF')->andReturn('contracts/stub.pdf');
        $this->app->instance(ContractService::class, $pdf);

        $this->admin = User::factory()->superAdmin()->create();
        $this->service = Service::factory()->create();

        $this->contract = Contract::factory()->worth(10000)->create([
            'service_id' => $this->service->id,
        ]);

        $this->item = ContractItem::factory()->create([
            'contract_id' => $this->contract->id,
            'service_id' => $this->service->id,
            'max_assignments' => 3,
            'assignment_count' => 1,
        ]);

        $this->original = Assignment::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_item_id' => $this->item->id,
            'service_id' => $this->service->id,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * An employee is only eligible for a line if they carry that service.
     */
    private function eligibleEmployee(): Employee
    {
        $employee = Employee::factory()->create();
        $employee->services()->attach($this->service->id);

        return $employee;
    }

    private function replaceWith(Employee $employee, array $overrides = [])
    {
        return $this->actingAs($this->admin)->post(
            "/admin/contracts/{$this->contract->id}/assignments/{$this->original->id}/replace",
            array_merge([
                'employee_id' => $employee->id,
                'replacement_start_date' => now()->toDateString(),
            ], $overrides)
        );
    }

    public function test_a_replacement_deactivates_the_outgoing_assignment(): void
    {
        $this->replaceWith($this->eligibleEmployee());

        $this->original->refresh();
        $this->assertFalse((bool) $this->original->is_active);
        $this->assertNotNull($this->original->end_date);
    }

    public function test_the_replacement_links_back_to_who_it_replaced(): void
    {
        $incoming = $this->eligibleEmployee();

        $this->replaceWith($incoming);

        $this->assertDatabaseHas('assignments', [
            'contract_item_id' => $this->item->id,
            'employee_id' => $incoming->id,
            'replaced_assignment_id' => $this->original->id,
            'is_active' => 1,
        ]);
    }

    public function test_only_one_assignment_stays_active_on_the_line(): void
    {
        $this->replaceWith($this->eligibleEmployee());

        $active = Assignment::where('contract_item_id', $this->item->id)
            ->where('is_active', true)
            ->count();

        $this->assertSame(1, $active);
    }

    public function test_each_replacement_counts_against_the_line_limit(): void
    {
        $this->replaceWith($this->eligibleEmployee());

        $this->assertSame(2, $this->item->fresh()->assignment_count);
    }

    /**
     * The churn cap is what stops a single line being rewritten indefinitely.
     */
    public function test_replacement_is_refused_once_the_limit_is_reached(): void
    {
        $this->item->update(['assignment_count' => 3, 'max_assignments' => 3]);

        $this->replaceWith($this->eligibleEmployee())
            ->assertSessionHas('error', 'Maximum employee changes reached for this service line.');

        $this->assertTrue((bool) $this->original->fresh()->is_active);
        $this->assertSame(3, $this->item->fresh()->assignment_count);
    }

    public function test_an_employee_without_the_service_is_refused(): void
    {
        $wrongSkill = Employee::factory()->create();

        $this->replaceWith($wrongSkill)
            ->assertSessionHas('error', 'Replacement employee does not match this service category.');

        $this->assertTrue((bool) $this->original->fresh()->is_active);
    }

    public function test_an_inactive_employee_is_refused(): void
    {
        $employee = Employee::factory()->pending()->create();
        $employee->services()->attach($this->service->id);

        $this->replaceWith($employee)
            ->assertSessionHas('error', 'Replacement employee must be active.');

        $this->assertTrue((bool) $this->original->fresh()->is_active);
    }

    public function test_a_temporary_swap_is_flagged_as_an_emergency_replacement(): void
    {
        $incoming = $this->eligibleEmployee();

        $this->replaceWith($incoming, ['is_temporary' => 1]);

        $this->assertDatabaseHas('assignments', [
            'employee_id' => $incoming->id,
            'is_emergency_replacement' => 1,
        ]);
    }

    /**
     * Assignment ids are guessable, so a replacement must not be applied
     * across contracts.
     */
    public function test_an_assignment_from_another_contract_is_rejected(): void
    {
        $foreign = Assignment::factory()->create();

        $this->actingAs($this->admin)->post(
            "/admin/contracts/{$this->contract->id}/assignments/{$foreign->id}/replace",
            [
                'employee_id' => $this->eligibleEmployee()->id,
                'replacement_start_date' => now()->toDateString(),
            ]
        )->assertNotFound();
    }

    public function test_replacement_does_not_change_what_the_contract_is_worth(): void
    {
        $this->replaceWith($this->eligibleEmployee());

        $this->assertSame('10000.00', $this->contract->fresh()->amount);
    }
}
