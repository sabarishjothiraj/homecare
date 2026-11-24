<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JoinRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class JoinRequestAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    /**
     * The routes used {request} while the controller took $joinRequest, so
     * approve/reject updated an empty model and reported success.
     */
    public function test_approve_and_reject_change_the_bound_request(): void
    {
        $approved = $this->makeRequest();
        $rejected = $this->makeRequest(['email' => 'second@example.com', 'phone' => '9000000002']);

        $this->actingAs($this->admin)
            ->post("/admin/join-requests/{$approved->id}/approve", ['admin_notes' => 'Good references.'])
            ->assertSessionHas('success');

        $this->actingAs($this->admin)
            ->post("/admin/join-requests/{$rejected->id}/reject", ['admin_notes' => 'No licence.'])
            ->assertSessionHas('success');

        $approved->refresh();
        $this->assertSame('approved', $approved->status);
        $this->assertSame('Good references.', $approved->admin_notes);
        $this->assertSame($this->admin->id, $approved->reviewed_by);
        $this->assertNotNull($approved->reviewed_at);

        $this->assertSame('rejected', $rejected->fresh()->status);
    }

    public function test_converting_an_approved_request_creates_one_employee(): void
    {
        $request = $this->makeRequest(['status' => 'approved']);

        $response = $this->actingAs($this->admin)
            ->post("/admin/join-requests/{$request->id}/convert");

        $employee = Employee::sole();
        $response->assertRedirect(route('admin.employees.show', $employee));

        $this->assertSame('Asha', $employee->first_name);
        $this->assertSame('Kumar', $employee->last_name);
        $this->assertSame('asha@example.com', $employee->email);
        $this->assertSame('9000000001', $employee->phone);
        $this->assertSame('pending', $employee->status);
        $this->assertSame('join_form', $employee->source);
        $this->assertSame($this->admin->id, $employee->created_by);

        $request->refresh();
        $this->assertSame('converted', $request->status);
        $this->assertSame($employee->id, $request->employee_id);

        // A second submit (double click, back button) must not convert again.
        $this->actingAs($this->admin)
            ->from("/admin/join-requests/{$request->id}")
            ->post("/admin/join-requests/{$request->id}/convert")
            ->assertRedirect("/admin/join-requests/{$request->id}")
            ->assertSessionHas('error');

        $this->assertSame(1, Employee::count());

        $this->actingAs($this->admin)->get("/admin/join-requests/{$request->id}")
            ->assertOk()
            ->assertSee('Converted')
            ->assertSee(route('admin.employees.show', $employee), false);
    }

    public function test_rejected_or_new_requests_cannot_be_converted(): void
    {
        foreach (['rejected', 'new'] as $i => $status) {
            $request = $this->makeRequest([
                'status' => $status,
                'email' => "applicant{$i}@example.com",
                'phone' => "900000010{$i}",
            ]);

            $this->actingAs($this->admin)
                ->from("/admin/join-requests/{$request->id}")
                ->post("/admin/join-requests/{$request->id}/convert")
                ->assertSessionHas('error');

            $this->assertSame($status, $request->fresh()->status);
        }

        $this->assertSame(0, Employee::count());
    }

    public function test_conversion_refuses_an_email_or_phone_that_already_belongs_to_an_employee(): void
    {
        Employee::factory()->create(['email' => 'asha@example.com']);
        $request = $this->makeRequest(['status' => 'approved']);

        $this->actingAs($this->admin)
            ->from("/admin/join-requests/{$request->id}")
            ->post("/admin/join-requests/{$request->id}/convert")
            ->assertRedirect("/admin/join-requests/{$request->id}")
            ->assertSessionHas('error');

        $this->assertSame(1, Employee::count());
        $this->assertSame('approved', $request->fresh()->status);
    }

    /**
     * A concurrent convert (or admin create) can insert the same email after
     * the duplicate check ran; the unique index must surface as a message,
     * not a 500.
     */
    public function test_a_unique_index_race_during_conversion_returns_the_friendly_error(): void
    {
        $request = $this->makeRequest(['status' => 'approved']);

        // Slip a conflicting row in between the duplicate check and the insert.
        Employee::creating(function (Employee $employee) {
            if ($employee->source === 'join_form') {
                DB::table('employees')->insert([
                    'first_name' => 'Racer',
                    'email' => $employee->email,
                    'field_data' => '{}',
                    'status' => 'pending',
                    'source' => 'admin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        $this->actingAs($this->admin)
            ->from("/admin/join-requests/{$request->id}")
            ->post("/admin/join-requests/{$request->id}/convert")
            ->assertRedirect("/admin/join-requests/{$request->id}")
            ->assertSessionHas('error', 'An employee with this email or phone already exists.');

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertNull($request->employee_id);
        $this->assertSame(0, Employee::where('source', 'join_form')->count());
    }

    public function test_a_converted_request_can_no_longer_be_reviewed(): void
    {
        $employee = Employee::factory()->create();
        $request = $this->makeRequest(['status' => 'converted', 'employee_id' => $employee->id]);

        $this->actingAs($this->admin)
            ->post("/admin/join-requests/{$request->id}/reject", ['admin_notes' => 'Changed our mind.'])
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->put("/admin/join-requests/{$request->id}", ['status' => 'approved'])
            ->assertSessionHas('error');

        $this->assertSame('converted', $request->fresh()->status);
    }

    public function test_index_shows_applicant_names_and_every_status(): void
    {
        $this->makeRequest();
        $employee = Employee::factory()->create();
        $this->makeRequest([
            'first_name' => 'Ravi',
            'email' => 'ravi@example.com',
            'phone' => '9000000003',
            'status' => 'converted',
            'employee_id' => $employee->id,
        ]);

        $this->actingAs($this->admin)->get('/admin/join-requests')
            ->assertOk()
            ->assertSee('Asha Kumar')
            ->assertSee('Ravi Kumar')
            ->assertSee('Converted to Employee');
    }

    private function makeRequest(array $overrides = []): JoinRequest
    {
        $status = $overrides['status'] ?? 'new';
        $employeeId = $overrides['employee_id'] ?? null;
        unset($overrides['status'], $overrides['employee_id']);

        return JoinRequest::create([
            'field_data' => array_merge([
                'first_name' => 'Asha',
                'last_name' => 'Kumar',
                'email' => 'asha@example.com',
                'phone' => '9000000001',
                'address' => '4 Temple Street',
            ], $overrides),
            'status' => $status,
            'employee_id' => $employeeId,
        ]);
    }
}
