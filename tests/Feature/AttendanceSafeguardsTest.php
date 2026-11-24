<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeePayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
        Carbon::setTestNow('2026-09-10 11:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function paidApril(Employee $employee): EmployeePayout
    {
        return EmployeePayout::create([
            'employee_id' => $employee->id, 'rate' => 24000, 'amount' => 24000, 'status' => 'paid',
            'period_start' => '2026-04-01', 'period_end' => '2026-04-30',
        ]);
    }

    public function test_attendance_cannot_be_marked_for_a_future_date(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin)->post('/admin/attendance', [
            'date' => '2026-09-11',
            'records' => [$employee->id => ['status' => 'present', 'version' => '']],
        ])->assertSessionHas('error');
        $this->assertSame(0, Attendance::count());

        $this->actingAs($this->admin)->post('/admin/attendance/bulk', [
            'employee_ids' => [$employee->id],
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => 'present',
        ])->assertSessionHas('error');
        $this->assertSame(0, Attendance::count());

        $this->actingAs($this->admin)->get('/admin/attendance?date=2026-09-11')
            ->assertOk()
            ->assertSee('This date is in the future');
    }

    public function test_a_mark_made_by_mistake_on_a_future_date_can_still_be_cleared(): void
    {
        $employee = Employee::factory()->create();
        $mark = Attendance::create(['employee_id' => $employee->id, 'date' => '2026-09-15', 'status' => 'present']);

        $this->actingAs($this->admin)->post('/admin/attendance', [
            'date' => '2026-09-15',
            'records' => [$employee->id => ['status' => '', 'version' => $mark->version()]],
        ])->assertSessionHas('success');

        $this->assertSame(0, Attendance::count());
    }

    public function test_the_bulk_form_stops_at_today_in_the_current_month(): void
    {
        Employee::factory()->create();

        $this->actingAs($this->admin)->get('/admin/attendance/register?month=2026-09')
            ->assertOk()
            ->assertSee('name="end_date" value="2026-09-10"', false);

        $this->actingAs($this->admin)->get('/admin/attendance/register?month=2026-10')
            ->assertOk()
            ->assertDontSee('Bulk Mark');
    }

    public function test_saving_an_older_copy_of_the_sheet_keeps_marks_made_since(): void
    {
        $nurses = Employee::factory()->count(3)->create();

        // Both coordinators loaded the sheet while all three were unmarked; A saved first
        foreach ($nurses->take(2) as $nurse) {
            Attendance::create(['employee_id' => $nurse->id, 'date' => '2026-09-09', 'status' => 'present']);
        }

        $this->actingAs($this->admin)->post('/admin/attendance', [
            'date' => '2026-09-09',
            'records' => [
                $nurses[0]->id => ['status' => '', 'version' => ''],       // untouched by B
                $nurses[1]->id => ['status' => 'absent', 'version' => ''], // B marked it too
                $nurses[2]->id => ['status' => 'half_day', 'version' => ''],
            ],
        ])->assertSessionHas('error', fn ($message) => str_contains($message, '1 mark changed by someone else'));

        $this->assertSame('present', Attendance::where('employee_id', $nurses[0]->id)->value('status'));
        $this->assertSame('present', Attendance::where('employee_id', $nurses[1]->id)->value('status'));
        $this->assertSame('half_day', Attendance::where('employee_id', $nurses[2]->id)->value('status'));
    }

    public function test_attendance_on_a_paid_payslip_is_locked(): void
    {
        $employee = Employee::factory()->create();
        $mark = Attendance::create(['employee_id' => $employee->id, 'date' => '2026-04-10', 'status' => 'present']);
        $this->paidApril($employee);

        $this->actingAs($this->admin)->post('/admin/attendance', [
            'date' => '2026-04-10',
            'records' => [$employee->id => ['status' => '', 'version' => $mark->version()]],
        ])->assertSessionHas('error', fn ($message) => str_contains($message, 'locked'));
        $this->assertSame('present', $mark->fresh()->status);

        $this->actingAs($this->admin)->post('/admin/attendance/bulk', [
            'employee_ids' => [$employee->id],
            'start_date' => '2026-04-28',
            'end_date' => '2026-05-02',
            'status' => 'absent',
            'overwrite' => 1,
        ])->assertSessionHas('success', fn ($message) => str_contains($message, 'Marked 2 days') && str_contains($message, '3 days on paid payslips'));

        $this->assertSame(0, Attendance::where('employee_id', $employee->id)->whereDate('date', '<=', '2026-04-30')->where('status', 'absent')->count());
        $this->assertSame('present', $mark->fresh()->status);

        // Every row is locked, so there is nothing to save
        $this->actingAs($this->admin)->get('/admin/attendance?date=2026-04-10')
            ->assertSee('This date is on a paid payslip')
            ->assertSee('Every employee listed is on a paid payslip')
            ->assertDontSee('Save Attendance');
    }

    public function test_a_pending_payslip_does_not_lock_attendance(): void
    {
        $employee = Employee::factory()->create();
        EmployeePayout::create([
            'employee_id' => $employee->id, 'rate' => 24000, 'amount' => 24000, 'status' => 'pending',
            'period_start' => '2026-08-01', 'period_end' => '2026-08-31',
        ]);

        $this->actingAs($this->admin)->post('/admin/attendance', [
            'date' => '2026-08-10',
            'records' => [$employee->id => ['status' => 'absent', 'version' => '']],
        ])->assertSessionHas('success')->assertSessionMissing('error');

        $this->assertSame('absent', Attendance::where('employee_id', $employee->id)->value('status'));
        $this->actingAs($this->admin)->get('/admin/attendance?date=2026-08-10')->assertSee('Save Attendance');
    }

    public function test_validation_errors_are_shown_on_the_sheet(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin)
            ->from('/admin/attendance?date=2026-09-09')
            ->followingRedirects()
            ->post('/admin/attendance', [
                'date' => '2026-09-09',
                'records' => [$employee->id => ['status' => 'holiday', 'version' => '']],
            ])
            ->assertOk()
            ->assertSee("The selected records.{$employee->id}.status is invalid.");

        $this->actingAs($this->admin)
            ->from('/admin/attendance/register?month=2026-09')
            ->followingRedirects()
            ->post('/admin/attendance/bulk', ['start_date' => '2026-09-01', 'end_date' => '2026-09-05', 'status' => 'present'])
            ->assertOk()
            ->assertSee('The employee ids field is required.');
    }

    public function test_unknown_or_inactive_employee_ids_are_ignored_not_a_server_error(): void
    {
        $employee = Employee::factory()->create();
        $applicant = Employee::factory()->pending()->create();

        $this->actingAs($this->admin)->post('/admin/attendance', [
            'date' => '2026-09-09',
            'records' => [
                999999 => ['status' => 'present', 'version' => ''],
                'abc' => ['status' => 'present', 'version' => ''],
                $applicant->id => ['status' => 'present', 'version' => ''],
                $employee->id => ['status' => 'present', 'version' => ''],
            ],
        ])->assertRedirect()->assertSessionHas('error', fn ($message) => str_contains($message, '3 rows ignored'));

        $this->assertSame(1, Attendance::count());
        $this->assertSame($employee->id, Attendance::value('employee_id'));
    }
}
