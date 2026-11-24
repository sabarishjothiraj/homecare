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
 * Temporary cover while an assigned employee is on leave.
 *
 * A cover must not behave like a replacement: the employee on leave keeps
 * their placement and simply resumes once the cover's last day has passed.
 */
class AssignmentCoverTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Service $service;

    private Contract $contract;

    private ContractItem $item;

    private Assignment $primary;

    protected function setUp(): void
    {
        parent::setUp();

        $pdf = Mockery::mock(ContractService::class)->makePartial();
        $pdf->shouldReceive('generateContractPDF')->andReturn('contracts/stub.pdf');
        $this->app->instance(ContractService::class, $pdf);

        $this->admin = User::factory()->superAdmin()->create();
        $this->service = Service::factory()->create();

        $this->contract = Contract::factory()->worth(10000)->create([
            'service_id' => $this->service->id,
            'status' => 'active',
            'start_date' => now()->subWeek()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
        ]);

        $this->item = ContractItem::factory()->create([
            'contract_id' => $this->contract->id,
            'service_id' => $this->service->id,
            'max_assignments' => 3,
            'assignment_count' => 1,
        ]);

        $this->primary = Assignment::factory()->create([
            'contract_id' => $this->contract->id,
            'contract_item_id' => $this->item->id,
            'service_id' => $this->service->id,
            'start_date' => now()->subWeek()->toDateString(),
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

    private function coverWith(Employee $employee, array $overrides = [], ?Assignment $primary = null)
    {
        $primary ??= $this->primary;

        return $this->actingAs($this->admin)->post(
            "/admin/contracts/{$this->contract->id}/assignments/{$primary->id}/cover",
            array_merge([
                'employee_id' => $employee->id,
                'cover_start_date' => now()->toDateString(),
                'cover_end_date' => now()->addDays(4)->toDateString(),
            ], $overrides)
        );
    }

    private function coverFor(Employee $employee): Assignment
    {
        return Assignment::where('employee_id', $employee->id)->whereNotNull('cover_for_assignment_id')->firstOrFail();
    }

    public function test_a_cover_keeps_the_employee_on_leave_assigned(): void
    {
        $this->coverWith($this->eligibleEmployee())->assertSessionHas('success');

        $this->assertTrue((bool) $this->primary->fresh()->is_active);
        $this->assertNull($this->primary->fresh()->end_date);
    }

    public function test_the_cover_is_active_for_the_leave_dates_and_linked_to_the_primary(): void
    {
        $incoming = $this->eligibleEmployee();

        $this->coverWith($incoming);

        $cover = $this->coverFor($incoming);
        $this->assertSame($this->primary->id, $cover->cover_for_assignment_id);
        $this->assertTrue((bool) $cover->is_active);
        $this->assertSame(now()->toDateString(), $cover->start_date->toDateString());
        $this->assertSame(now()->addDays(4)->toDateString(), $cover->end_date->toDateString());
        $this->assertTrue($this->primary->fresh()->currentCover()?->is($cover));
    }

    public function test_a_cover_does_not_use_up_the_line_change_limit(): void
    {
        $this->coverWith($this->eligibleEmployee());

        $this->assertSame(1, $this->item->fresh()->assignment_count);
    }

    public function test_a_cover_works_even_when_the_change_limit_is_reached(): void
    {
        $this->item->update(['assignment_count' => 3, 'max_assignments' => 3]);

        $this->coverWith($this->eligibleEmployee())->assertSessionHas('success');
    }

    public function test_an_end_date_is_required(): void
    {
        $this->coverWith($this->eligibleEmployee(), ['cover_end_date' => null])
            ->assertSessionHasErrors('cover_end_date');

        $this->assertSame(0, Assignment::coverAssignments()->count());
    }

    public function test_the_employee_on_leave_cannot_cover_themselves(): void
    {
        $self = $this->primary->employee;
        $self->services()->attach($this->service->id);

        $this->coverWith($self)
            ->assertSessionHas('error', 'Cover employee must be different from the employee on leave.');
    }

    public function test_an_employee_already_placed_elsewhere_is_refused(): void
    {
        $busy = $this->eligibleEmployee();
        Assignment::factory()->create(['employee_id' => $busy->id, 'is_active' => true]);

        $this->coverWith($busy)
            ->assertSessionHas('error', 'This employee is already assigned to an active contract.');
    }

    public function test_an_employee_without_the_service_is_refused(): void
    {
        $this->coverWith(Employee::factory()->create())
            ->assertSessionHas('error', 'Cover employee does not match this service category.');
    }

    public function test_overlapping_covers_for_the_same_leave_are_refused(): void
    {
        $this->coverWith($this->eligibleEmployee());

        $this->coverWith($this->eligibleEmployee(), [
            'cover_start_date' => now()->addDays(2)->toDateString(),
            'cover_end_date' => now()->addDays(6)->toDateString(),
        ])->assertSessionHas('error', 'This employee already has a cover arranged for part of these dates.');
    }

    public function test_a_cover_cannot_run_past_the_contract_end(): void
    {
        $this->coverWith($this->eligibleEmployee(), [
            'cover_end_date' => now()->addMonths(3)->toDateString(),
        ])->assertSessionHas('error');

        $this->assertSame(0, Assignment::coverAssignments()->count());
    }

    public function test_a_cover_cannot_itself_be_covered_or_replaced(): void
    {
        $incoming = $this->eligibleEmployee();
        $this->coverWith($incoming);
        $cover = $this->coverFor($incoming);

        $this->coverWith($this->eligibleEmployee(), [], $cover)
            ->assertSessionHas('error', 'A temporary cover cannot itself be covered.');

        $this->actingAs($this->admin)->post(
            "/admin/contracts/{$this->contract->id}/assignments/{$cover->id}/replace",
            ['employee_id' => $this->eligibleEmployee()->id, 'replacement_start_date' => now()->toDateString()]
        )->assertSessionHas('error', 'A temporary cover cannot be replaced. End the cover instead.');
    }

    /**
     * The whole point: nobody has to remember to bring the original back.
     */
    public function test_the_nightly_job_ends_covers_after_their_last_day(): void
    {
        $incoming = $this->eligibleEmployee();
        $this->coverWith($incoming);
        $cover = $this->coverFor($incoming);

        $this->artisan('assignments:end-covers')->assertSuccessful();
        $this->assertTrue((bool) $cover->fresh()->is_active, 'Cover must still run on its last day.');

        $this->travel(5)->days();
        $this->artisan('assignments:end-covers')->assertSuccessful();

        $this->assertFalse((bool) $cover->fresh()->is_active);
        $this->assertTrue((bool) $this->primary->fresh()->is_active);
        $this->assertNull($this->primary->fresh()->currentCover());
    }

    public function test_a_cover_can_be_ended_early(): void
    {
        $incoming = $this->eligibleEmployee();
        $this->coverWith($incoming, ['cover_start_date' => now()->toDateString()]);
        $cover = $this->coverFor($incoming);

        $this->actingAs($this->admin)
            ->post("/admin/contracts/{$this->contract->id}/assignments/{$cover->id}/cover/end")
            ->assertSessionHas('success', 'Temporary cover ended.');

        $cover->refresh();
        $this->assertFalse((bool) $cover->is_active);
        $this->assertSame(now()->toDateString(), $cover->end_date->toDateString());
        $this->assertTrue((bool) $this->primary->fresh()->is_active);
    }

    public function test_a_scheduled_cover_can_be_cancelled(): void
    {
        $incoming = $this->eligibleEmployee();
        $this->coverWith($incoming, [
            'cover_start_date' => now()->addDays(3)->toDateString(),
            'cover_end_date' => now()->addDays(5)->toDateString(),
        ]);
        $cover = $this->coverFor($incoming);

        $this->actingAs($this->admin)
            ->post("/admin/contracts/{$this->contract->id}/assignments/{$cover->id}/cover/end")
            ->assertSessionHas('success', 'Scheduled cover cancelled.');

        $this->assertSoftDeleted($cover);
    }

    public function test_the_cover_employee_can_be_made_the_permanent_replacement(): void
    {
        $incoming = $this->eligibleEmployee();
        $this->coverWith($incoming);
        $cover = $this->coverFor($incoming);

        $this->actingAs($this->admin)->post(
            "/admin/contracts/{$this->contract->id}/assignments/{$this->primary->id}/replace",
            ['employee_id' => $incoming->id, 'replacement_start_date' => now()->toDateString()]
        )->assertSessionHas('success');

        $this->assertFalse((bool) $this->primary->fresh()->is_active);
        $this->assertFalse((bool) $cover->fresh()->is_active);
        $this->assertDatabaseHas('assignments', [
            'employee_id' => $incoming->id,
            'replaced_assignment_id' => $this->primary->id,
            'cover_for_assignment_id' => null,
            'is_active' => 1,
        ]);
    }

    public function test_removing_the_primary_ends_its_cover(): void
    {
        $incoming = $this->eligibleEmployee();
        $this->coverWith($incoming);
        $cover = $this->coverFor($incoming);

        $this->actingAs($this->admin)
            ->delete("/admin/contracts/{$this->contract->id}/assignments/{$this->primary->id}");

        $this->assertFalse((bool) $cover->fresh()->is_active);
    }

    /**
     * The edit form no longer swaps a line's employee in place (that is what
     * Replace is for), so this pins the line's price going to the primary.
     */
    public function test_editing_the_contract_updates_the_primary_not_the_cover(): void
    {
        $incoming = $this->eligibleEmployee();
        $this->coverWith($incoming);
        $cover = $this->coverFor($incoming);
        $this->primary->employee->services()->attach($this->service->id);

        $this->actingAs($this->admin)->put("/admin/contracts/{$this->contract->id}", [
            'patient_id' => $this->contract->patient_id,
            'duration_type' => 'monthly',
            'duration_value' => 2,
            'start_date' => $this->contract->start_date->toDateString(),
            'payment_method' => 'cash',
            'line_items' => [[
                'item_id' => $this->item->id,
                'service_id' => $this->service->id,
                'employee_id' => $this->primary->employee_id,
                'amount' => 12000,
            ]],
        ])->assertSessionHasNoErrors();

        $this->assertSame('12000.00', $this->primary->fresh()->line_amount);
        $this->assertSame($incoming->id, $cover->fresh()->employee_id);
        $this->assertNull($cover->fresh()->line_amount);
        $this->assertTrue((bool) $cover->fresh()->is_active);
    }

    public function test_an_assignment_from_another_contract_is_rejected(): void
    {
        $foreign = Assignment::factory()->create();

        $this->coverWith($this->eligibleEmployee(), [], $foreign)->assertNotFound();
    }
}
