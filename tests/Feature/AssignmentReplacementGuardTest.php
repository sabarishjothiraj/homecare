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
 * Replace may only act on a line's current employee on a running contract.
 *
 * Replacing a row that had already been replaced (the page offered it) put a
 * second active employee on the line; replacing on a closed contract created
 * an active placement nothing would ever end.
 */
class AssignmentReplacementGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Service $service;

    private Contract $contract;

    private ContractItem $item;

    private Assignment $original;

    private Assignment $current;

    protected function setUp(): void
    {
        parent::setUp();

        $pdf = Mockery::mock(ContractService::class)->makePartial();
        $pdf->shouldReceive('generateContractPDF')->andReturn('contracts/stub.pdf');
        $this->app->instance(ContractService::class, $pdf);

        $this->admin = User::factory()->superAdmin()->create();
        $this->service = Service::factory()->create();

        $this->contract = Contract::factory()->create([
            'service_id' => $this->service->id,
            'status' => 'active',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);
        $this->item = ContractItem::factory()->create([
            'contract_id' => $this->contract->id,
            'service_id' => $this->service->id,
            'max_assignments' => 5,
            'assignment_count' => 2,
        ]);

        // Nurse X was replaced by nurse Y
        $this->original = Assignment::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_item_id' => $this->item->id,
            'service_id' => $this->service->id,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->subWeek()->toDateString(),
            'is_active' => false,
        ]);
        $this->current = Assignment::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_item_id' => $this->item->id,
            'service_id' => $this->service->id,
            'start_date' => now()->subWeek()->toDateString(),
            'replaced_assignment_id' => $this->original->id,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function eligibleEmployee(): Employee
    {
        $employee = Employee::factory()->create();
        $employee->services()->attach($this->service->id);

        return $employee;
    }

    private function replace(Assignment $assignment, Employee $employee)
    {
        return $this->actingAs($this->admin)->post(
            route('admin.contracts.assignments.replace', [$this->contract, $assignment]),
            ['employee_id' => $employee->id, 'replacement_start_date' => now()->toDateString()]
        );
    }

    private function activeOnLine(): int
    {
        return Assignment::where('contract_item_id', $this->item->id)->where('is_active', true)->count();
    }

    public function test_an_already_replaced_assignment_cannot_be_replaced_again(): void
    {
        $this->replace($this->original, $this->eligibleEmployee())
            ->assertSessionHas('error', 'Only the current employee on a service line can be replaced.');

        $this->assertSame(1, $this->activeOnLine());
        $this->assertSame(now()->subWeek()->toDateString(), $this->original->fresh()->end_date->toDateString());
        $this->assertSame(2, $this->item->fresh()->assignment_count);
    }

    public function test_the_current_assignment_can_still_be_replaced(): void
    {
        $this->replace($this->current, $this->eligibleEmployee())->assertSessionHas('success');

        $this->assertSame(1, $this->activeOnLine());
        $this->assertFalse((bool) $this->current->fresh()->is_active);
        $this->assertSame(3, $this->item->fresh()->assignment_count);
    }

    public function test_replacement_is_refused_on_a_closed_contract(): void
    {
        $this->contract->update(['status' => 'closed']);

        $this->replace($this->current, $this->eligibleEmployee())
            ->assertSessionHas('error', 'Cannot change employees on a closed or expired contract.');

        $this->assertSame(1, $this->activeOnLine());
    }

    public function test_replacement_is_refused_on_an_expired_contract(): void
    {
        $this->contract->update(['status' => 'expired']);

        $this->replace($this->current, $this->eligibleEmployee())
            ->assertSessionHas('error', 'Cannot change employees on a closed or expired contract.');

        $this->assertTrue((bool) $this->current->fresh()->is_active);
    }

    /**
     * The page only offers Replace (and Cover) on the line's current row.
     */
    public function test_the_contract_page_offers_replace_only_on_the_current_row(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.contracts.show', $this->contract))->assertOk();

        $response->assertSee('assignment-'.$this->current->id.'-replace', false);
        $response->assertDontSee('assignment-'.$this->original->id.'-replace', false);
        $response->assertDontSee('assignment-'.$this->original->id.'-cover', false);
    }
}
