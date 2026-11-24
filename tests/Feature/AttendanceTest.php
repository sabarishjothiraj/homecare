<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    public function test_the_daily_sheet_lists_active_employees(): void
    {
        Employee::factory()->create(['first_name' => 'Anita', 'last_name' => 'Das']);
        Employee::factory()->pending()->create(['first_name' => 'Pending', 'last_name' => 'Person']);

        $this->actingAs($this->admin)->get('/admin/attendance?date=2026-04-10')
            ->assertOk()
            ->assertSee('Anita Das')
            ->assertDontSee('Pending Person')
            ->assertSee('Not marked: <strong>1</strong>', false);
    }

    public function test_the_daily_sheet_saves_updates_and_clears_marks(): void
    {
        $anita = Employee::factory()->create();
        $bala = Employee::factory()->create();
        $balaMark = Attendance::create(['employee_id' => $bala->id, 'date' => '2026-04-10', 'status' => 'present']);

        // Like the sheet, each row posts the version of the mark it showed ('' when unmarked)
        $this->actingAs($this->admin)->post('/admin/attendance', [
            'date' => '2026-04-10',
            'records' => [
                $anita->id => ['status' => 'half_day', 'notes' => 'Left at 2pm', 'version' => ''],
                $bala->id => ['status' => '', 'version' => $balaMark->version()],
            ],
        ])->assertRedirect()->assertSessionHas('success');

        $mark = Attendance::where('employee_id', $anita->id)->firstOrFail();
        $this->assertSame('half_day', $mark->status);
        $this->assertSame('Left at 2pm', $mark->notes);
        $this->assertSame($this->admin->id, $mark->marked_by);
        $this->assertDatabaseMissing('attendances', ['employee_id' => $bala->id]);

        $this->actingAs($this->admin)->post('/admin/attendance', [
            'date' => '2026-04-10',
            'records' => [$anita->id => ['status' => 'present', 'version' => $mark->version()]],
        ]);
        $this->assertSame(1, Attendance::where('employee_id', $anita->id)->count());
        $this->assertSame('present', Attendance::where('employee_id', $anita->id)->value('status'));
    }

    public function test_invalid_status_is_rejected(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin)->post('/admin/attendance', [
            'date' => '2026-04-10',
            'records' => [$employee->id => ['status' => 'holiday']],
        ])->assertSessionHasErrors('records.'.$employee->id.'.status');
    }

    public function test_bulk_marking_fills_a_range_and_keeps_existing_marks_unless_overwriting(): void
    {
        $anita = Employee::factory()->create();
        $bala = Employee::factory()->create();
        Attendance::create(['employee_id' => $anita->id, 'date' => '2026-04-03', 'status' => 'absent']);

        $this->actingAs($this->admin)->post('/admin/attendance/bulk', [
            'employee_ids' => [$anita->id, $bala->id],
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-05',
            'status' => 'present',
        ])->assertRedirect('/admin/attendance/register?month=2026-04')
            ->assertSessionHas('success', fn ($message) => str_contains($message, '9 days') && str_contains($message, '1 already-marked'));

        $this->assertSame(10, Attendance::count());
        $this->assertSame('absent', Attendance::where('employee_id', $anita->id)->whereDate('date', '2026-04-03')->value('status'));

        $this->actingAs($this->admin)->post('/admin/attendance/bulk', [
            'employee_ids' => [$anita->id],
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-05',
            'status' => 'week_off',
            'overwrite' => 1,
        ]);

        $this->assertSame(5, Attendance::where('employee_id', $anita->id)->where('status', 'week_off')->count());
    }

    public function test_bulk_marking_is_limited_to_about_two_months(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin)->post('/admin/attendance/bulk', [
            'employee_ids' => [$employee->id],
            'start_date' => '2026-01-01',
            'end_date' => '2026-06-30',
            'status' => 'present',
        ])->assertSessionHas('error');

        $this->assertSame(0, Attendance::count());
    }

    public function test_the_monthly_register_shows_marks_and_totals(): void
    {
        $employee = Employee::factory()->create(['first_name' => 'Kala', 'last_name' => 'V']);
        Attendance::create(['employee_id' => $employee->id, 'date' => '2026-04-02', 'status' => 'present']);
        Attendance::create(['employee_id' => $employee->id, 'date' => '2026-04-03', 'status' => 'absent', 'notes' => 'Sick']);

        $this->actingAs($this->admin)->get('/admin/attendance/register?month=2026-04')
            ->assertOk()
            ->assertSee('April 2026')
            ->assertSee('Kala V')
            ->assertSee('Absent — Sick', false)
            ->assertSee('Bulk Mark');
    }
}
