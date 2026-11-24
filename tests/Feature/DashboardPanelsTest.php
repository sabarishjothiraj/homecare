<?php

namespace Tests\Feature;

use App\Models\ContactQuery;
use App\Models\Contract;
use App\Models\JoinRequest;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardPanelsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->superAdmin()->create();
    }

    private function staff(array $permissionSlugs): User
    {
        $role = Role::create(['name' => 'Editor', 'slug' => 'editor']);

        foreach ($permissionSlugs as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user = User::factory()->create(['role' => 'admin']);
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_panels_are_limited_to_the_modules_a_role_can_open(): void
    {
        Payment::factory()->create(['amount' => 98765]);
        ContactQuery::create(['name' => 'Secret Enquirer', 'email' => 'q@example.com', 'message' => 'Private question', 'status' => 'new']);

        $editor = $this->staff(['dashboard.view', 'cms.manage']);

        $this->actingAs($editor)->get('/admin/dashboard')
            ->assertOk()
            ->assertDontSee('Total Revenue')
            ->assertDontSee('Net Revenue')
            ->assertDontSee('98,765')
            ->assertDontSee('revenueChart')
            ->assertDontSee('Secret Enquirer')
            ->assertDontSee('Recent Queries')
            ->assertViewMissing('totalRevenue')
            ->assertViewMissing('recentQueries');

        // The session is pinned to its user; start a fresh one for the next
        $this->flushSession();
        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Total Revenue')
            ->assertSee('98,765')
            ->assertSee('Secret Enquirer');
    }

    /**
     * From the 29th-31st, subMonths() used to overflow into the next month.
     */
    public function test_the_revenue_chart_lists_six_distinct_months_at_month_end(): void
    {
        $this->travelTo(Carbon::parse('2026-03-31 12:00:00'));
        Payment::factory()->create(['amount' => 500, 'paid_at' => Carbon::parse('2026-02-10 10:00:00')]);

        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertViewHas('monthlyRevenue', function (array $months) {
                $this->assertSame(
                    ['Oct 2025', 'Nov 2025', 'Dec 2025', 'Jan 2026', 'Feb 2026', 'Mar 2026'],
                    array_column($months, 'month')
                );
                $this->assertSame([0.0, 0.0, 0.0, 0.0, 500.0, 0.0], array_column($months, 'revenue'));

                return true;
            });
    }

    public function test_contract_panels_use_the_real_statuses_and_amounts(): void
    {
        Contract::factory()->count(2)->create(['status' => 'not_assigned']);
        Contract::factory()->create(['status' => 'closing_soon']);
        Contract::factory()->create(['status' => 'closed', 'amount' => 45000]);

        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('2 Not Assigned')
            ->assertSee('Closing Soon')
            ->assertSee('Expired')
            ->assertSee('<span class="badge bg-warning">Not Assigned</span>', false)
            ->assertDontSee('Not_assigned')
            ->assertSee('₹45,000');
    }

    public function test_days_left_counts_whole_days_from_today(): void
    {
        $this->travelTo(Carbon::parse('2026-09-23 14:00:00'));
        Contract::factory()->create(['status' => 'active', 'start_date' => '2026-09-01', 'end_date' => '2026-09-24']);
        Contract::factory()->create(['status' => 'active', 'start_date' => '2026-09-01', 'end_date' => '2026-09-26']);

        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('1 day')
            ->assertSee('3 days')
            ->assertDontSee('0 days');
    }

    public function test_join_requests_show_the_applicant_from_field_data(): void
    {
        JoinRequest::create([
            'field_data' => ['first_name' => 'Priya', 'last_name' => 'Raman', 'email' => 'priya@example.com', 'phone' => '9876501234'],
            'status' => 'new',
        ]);

        $this->actingAs($this->admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Priya Raman')
            ->assertSee('priya@example.com')
            ->assertSee('9876501234');
    }
}
