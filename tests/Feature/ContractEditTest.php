<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Contract;
use App\Models\ContractItem;
use App\Models\Employee;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\ContractService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Creating and editing contracts through the admin forms.
 *
 * Covers the rules the forms have to hold: dates, amounts, who can be placed,
 * and what an edit may change without going through Replace or Close.
 */
class ContractEditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $pdf = Mockery::mock(ContractService::class)->makePartial();
        $pdf->shouldReceive('generateContractPDF')->andReturn('contracts/stub.pdf');
        $this->app->instance(ContractService::class, $pdf);

        $this->admin = User::factory()->superAdmin()->create();
        $this->service = Service::factory()->create();
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

    private function payload(array $overrides = [], ?array $lines = null): array
    {
        return array_merge([
            'patient_id' => Patient::factory()->create()->id,
            'duration_type' => 'monthly',
            'duration_value' => 1,
            'start_date' => '2026-10-01',
            'payment_method' => 'cash',
            'line_items' => $lines ?? [[
                'service_id' => $this->service->id,
                'employee_id' => $this->eligibleEmployee()->id,
                'amount' => 10000,
            ]],
        ], $overrides);
    }

    private function store(array $payload)
    {
        return $this->actingAs($this->admin)->post(route('admin.contracts.store'), $payload);
    }

    private function update(Contract $contract, array $payload)
    {
        return $this->actingAs($this->admin)->put(route('admin.contracts.update', $contract), $payload);
    }

    /**
     * A staffed single-line contract, the way createContract leaves one.
     *
     * @return array{0: Contract, 1: ContractItem, 2: Assignment}
     */
    private function staffedContract(float $amount = 10000): array
    {
        $contract = Contract::factory()->worth($amount)->create([
            'service_id' => $this->service->id,
            'status' => 'active',
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
        ]);
        $item = ContractItem::factory()->create([
            'contract_id' => $contract->id,
            'service_id' => $this->service->id,
            'amount' => $amount,
            'max_assignments' => 3,
            'assignment_count' => 3,
        ]);
        $assignment = Assignment::factory()->create([
            'contract_id' => $contract->id,
            'contract_item_id' => $item->id,
            'service_id' => $this->service->id,
            'employee_id' => $this->eligibleEmployee()->id,
            'is_active' => true,
        ]);

        return [$contract, $item, $assignment];
    }

    private function editPayload(Contract $contract, ContractItem $item, int $employeeId, float $amount): array
    {
        return [
            'patient_id' => $contract->patient_id,
            'duration_type' => 'monthly',
            'duration_value' => 1,
            'start_date' => $contract->start_date->toDateString(),
            'payment_method' => 'cash',
            'line_items' => [[
                'item_id' => $item->id,
                'service_id' => $this->service->id,
                'employee_id' => $employeeId,
                'amount' => $amount,
            ]],
        ];
    }

    // ---- contracts-5: custom duration ---------------------------------------

    public function test_a_custom_duration_stores_the_end_date_it_was_given(): void
    {
        $this->store($this->payload([
            'duration_type' => 'custom',
            'duration_value' => null,
            'custom_end_date' => '2026-10-10',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('2026-10-10', Contract::sole()->end_date->toDateString());
    }

    public function test_a_custom_duration_requires_an_end_date_on_or_after_the_start(): void
    {
        $this->store($this->payload(['duration_type' => 'custom', 'duration_value' => null]))
            ->assertSessionHasErrors('custom_end_date');

        $this->store($this->payload([
            'duration_type' => 'custom',
            'duration_value' => null,
            'custom_end_date' => '2026-09-30',
        ]))->assertSessionHasErrors('custom_end_date');

        $this->assertSame(0, Contract::count());
    }

    public function test_editing_a_custom_contract_can_change_its_end_date(): void
    {
        [$contract, $item, $assignment] = $this->staffedContract();
        $payload = $this->editPayload($contract, $item, $assignment->employee_id, 10000);
        $payload['duration_type'] = 'custom';
        $payload['duration_value'] = null;
        $payload['custom_end_date'] = now()->addDays(40)->toDateString();

        $this->update($contract, $payload)->assertSessionHasNoErrors();

        $this->assertSame(now()->addDays(40)->toDateString(), $contract->fresh()->end_date->toDateString());
    }

    // ---- contracts-6: month-end overflow ------------------------------------

    public function test_a_monthly_contract_from_the_31st_ends_at_the_end_of_the_next_month(): void
    {
        $this->store($this->payload(['start_date' => '2027-01-31']))->assertSessionHasNoErrors();

        $this->assertSame('2027-02-28', Contract::sole()->end_date->toDateString());
    }

    public function test_a_yearly_contract_from_a_leap_day_ends_on_the_28th(): void
    {
        $this->store($this->payload(['start_date' => '2028-02-29', 'duration_type' => 'yearly']))->assertSessionHasNoErrors();

        $this->assertSame('2029-02-28', Contract::sole()->end_date->toDateString());
    }

    // ---- contracts-7: double booking ----------------------------------------

    public function test_an_employee_placed_on_another_contract_cannot_be_put_on_a_new_one(): void
    {
        [, , $assignment] = $this->staffedContract();

        $this->store($this->payload([], [[
            'service_id' => $this->service->id,
            'employee_id' => $assignment->employee_id,
            'amount' => 5000,
        ]]))->assertSessionHasErrors('line_items');

        $this->assertSame(1, Contract::count());
    }

    public function test_one_employee_cannot_staff_two_lines_of_a_contract(): void
    {
        $employee = $this->eligibleEmployee();

        $this->store($this->payload([], [
            ['service_id' => $this->service->id, 'employee_id' => $employee->id, 'amount' => 5000],
            ['service_id' => $this->service->id, 'employee_id' => $employee->id, 'amount' => 5000],
        ]))->assertSessionHasErrors('line_items');

        $this->assertSame(0, Contract::count());
    }

    public function test_a_new_line_on_edit_cannot_take_a_placed_employee(): void
    {
        [$contract, $item, $assignment] = $this->staffedContract();
        $busyElsewhere = $this->staffedContract()[2]->employee_id;

        $payload = $this->editPayload($contract, $item, $assignment->employee_id, 10000);
        $payload['line_items'][] = ['service_id' => $this->service->id, 'employee_id' => $busyElsewhere, 'amount' => 1000];

        $this->update($contract, $payload)->assertSessionHasErrors('line_items');

        $this->assertSame(1, $contract->items()->count());
    }

    /**
     * Staff on a line removed in the same save are released by it, so moving
     * them to a new line in that save is not double booking.
     */
    public function test_an_employee_from_a_removed_line_may_move_to_a_new_line_in_the_same_save(): void
    {
        [$contract, , $assignment] = $this->staffedContract();
        $otherService = Service::factory()->create();
        $assignment->employee->services()->attach($otherService->id);

        $this->update($contract, [
            'patient_id' => $contract->patient_id,
            'duration_type' => 'monthly',
            'duration_value' => 1,
            'start_date' => $contract->start_date->toDateString(),
            'payment_method' => 'cash',
            'line_items' => [[
                'service_id' => $otherService->id,
                'employee_id' => $assignment->employee_id,
                'amount' => 10000,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, Assignment::where('employee_id', $assignment->employee_id)->where('is_active', true)->count());
    }

    public function test_the_create_form_only_lists_employees_who_are_free(): void
    {
        [, , $assignment] = $this->staffedContract();
        $free = $this->eligibleEmployee();

        $this->actingAs($this->admin)->get(route('admin.contracts.create'))
            ->assertOk()
            ->assertViewHas('employees', fn ($employees) => $employees->contains('id', $free->id)
                && ! $employees->contains('id', $assignment->employee_id));
    }

    public function test_the_edit_form_lists_free_employees_and_this_contracts_own_staff(): void
    {
        [$contract, , $assignment] = $this->staffedContract();
        $busyElsewhere = $this->staffedContract()[2]->employee_id;
        $free = $this->eligibleEmployee();

        $this->actingAs($this->admin)->get(route('admin.contracts.edit', $contract))
            ->assertOk()
            ->assertViewHas('employees', fn ($employees) => $employees->contains('id', $free->id)
                && $employees->contains('id', $assignment->employee_id)
                && ! $employees->contains('id', $busyElsewhere));
    }

    // ---- contracts-8: no in-place employee swap ------------------------------

    /**
     * Swapping in place skipped the line's change limit and erased the
     * outgoing employee's placement; that is Replace's job.
     */
    public function test_the_edit_form_cannot_swap_the_employee_on_a_staffed_line(): void
    {
        [$contract, $item, $assignment] = $this->staffedContract();
        $originalEmployee = $assignment->employee_id;

        $this->update($contract, $this->editPayload($contract, $item, $this->eligibleEmployee()->id, 10000))
            ->assertSessionHasErrors('line_items');

        $this->assertSame($originalEmployee, $assignment->fresh()->employee_id);
        $this->assertSame(3, $item->fresh()->assignment_count);
    }

    public function test_the_edit_form_still_updates_a_line_that_keeps_its_employee(): void
    {
        [$contract, $item, $assignment] = $this->staffedContract();

        $this->update($contract, $this->editPayload($contract, $item, $assignment->employee_id, 12000))
            ->assertSessionHasNoErrors();

        $this->assertSame('12000.00', $item->fresh()->amount);
        $this->assertSame('12000.00', $assignment->fresh()->line_amount);
        $this->assertSame('12000.00', $contract->fresh()->amount);
    }

    // ---- payments-5: total cannot drop below what is collected ---------------

    public function test_the_total_cannot_be_edited_below_what_was_collected(): void
    {
        [$contract, $item, $assignment] = $this->staffedContract(10000);
        Payment::factory()->of(10000)->create(['contract_id' => $contract->id]);

        $this->update($contract, $this->editPayload($contract, $item, $assignment->employee_id, 6000))
            ->assertSessionHasErrors('line_items');

        $this->assertSame('10000.00', $contract->fresh()->amount);
    }

    public function test_the_total_cannot_be_edited_below_collected_plus_open_orders(): void
    {
        [$contract, $item, $assignment] = $this->staffedContract(10000);
        Payment::factory()->of(4000)->create(['contract_id' => $contract->id]);
        Payment::factory()->pending()->of(5000)->create(['contract_id' => $contract->id]);

        $this->update($contract, $this->editPayload($contract, $item, $assignment->employee_id, 8000))
            ->assertSessionHasErrors('line_items');

        $this->update($contract, $this->editPayload($contract, $item, $assignment->employee_id, 9000))
            ->assertSessionHasNoErrors();

        $this->assertSame('9000.00', $contract->fresh()->amount);
    }

    // ---- contracts-17: amounts the columns can hold ---------------------------

    public function test_a_line_amount_too_large_for_the_column_is_a_validation_error(): void
    {
        $this->store($this->payload([], [[
            'service_id' => $this->service->id,
            'employee_id' => $this->eligibleEmployee()->id,
            'amount' => 100000000,
        ]]))->assertSessionHasErrors('line_items.0.amount');
    }

    public function test_a_total_too_large_for_the_column_is_a_validation_error(): void
    {
        $this->store($this->payload([], [
            ['service_id' => $this->service->id, 'employee_id' => $this->eligibleEmployee()->id, 'amount' => 60000000],
            ['service_id' => $this->service->id, 'employee_id' => $this->eligibleEmployee()->id, 'amount' => 60000000],
        ]))->assertSessionHasErrors('line_items');

        $this->assertSame(0, Contract::count());
    }

    // ---- deleted patients ------------------------------------------------------

    public function test_a_new_contract_cannot_be_created_for_a_deleted_patient(): void
    {
        $patient = Patient::factory()->create();
        $patient->delete();

        $this->store($this->payload(['patient_id' => $patient->id]))->assertSessionHasErrors('patient_id');
    }

    public function test_a_contract_whose_patient_was_deleted_can_still_be_edited(): void
    {
        [$contract, $item, $assignment] = $this->staffedContract();
        $contract->patient->delete();

        $this->actingAs($this->admin)->get(route('admin.contracts.edit', $contract))
            ->assertOk()
            ->assertViewHas('patients', fn ($patients) => $patients->contains('id', $contract->patient_id));

        $this->update($contract, $this->editPayload($contract, $item, $assignment->employee_id, 11000))
            ->assertSessionHasNoErrors();

        $this->assertSame('11000.00', $contract->fresh()->amount);
    }

    public function test_an_edit_cannot_move_a_contract_to_another_deleted_patient(): void
    {
        [$contract, $item, $assignment] = $this->staffedContract();
        $deleted = Patient::factory()->create();
        $deleted->delete();

        $payload = $this->editPayload($contract, $item, $assignment->employee_id, 10000);
        $payload['patient_id'] = $deleted->id;

        $this->update($contract, $payload)->assertSessionHasErrors('patient_id');
    }
}
