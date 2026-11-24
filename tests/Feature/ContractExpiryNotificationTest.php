<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\Notification;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * notifications:generate, the nightly "contract expiring soon" alert.
 *
 * It used to query a related_id column the table does not have, so it threw
 * on the first matching contract and no alert was ever stored.
 */
class ContractExpiryNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::set('contract_alert_days', 7);
    }

    private function contractEndingIn(int $days, string $status = 'active'): Contract
    {
        return Contract::factory()->create([
            'status' => $status,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addDays($days)->toDateString(),
        ]);
    }

    public function test_a_contract_inside_the_alert_window_is_notified_and_linked(): void
    {
        $contract = $this->contractEndingIn(7);

        $this->artisan('notifications:generate')
            ->expectsOutput('Generated 1 new notification(s).')
            ->assertSuccessful();

        $notification = Notification::sole();
        $this->assertSame('contract_expiring', $notification->type);
        $this->assertSame($contract->id, $notification->contract_id);
        $this->assertSame(route('admin.contracts.show', $contract, false), $notification->link);
        $this->assertStringContainsString($contract->contract_number, $notification->message);
        $this->assertFalse($notification->is_read);
    }

    /**
     * Matching only "exactly alert_days out" missed a contract created inside
     * the window, or any day the scheduler did not run.
     */
    public function test_the_whole_window_is_covered_not_just_its_first_day(): void
    {
        $endsToday = $this->contractEndingIn(0, 'closing_soon');
        $endsInThree = $this->contractEndingIn(3, 'closing_soon');

        $this->artisan('notifications:generate')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$endsToday->id, $endsInThree->id],
            Notification::pluck('contract_id')->all()
        );
    }

    public function test_contracts_outside_the_window_or_not_running_are_skipped(): void
    {
        $this->contractEndingIn(8);
        $this->contractEndingIn(-1);
        $this->contractEndingIn(3, 'closed');
        $this->contractEndingIn(3, 'expired');

        $this->artisan('notifications:generate')
            ->expectsOutput('Generated 0 new notification(s).')
            ->assertSuccessful();

        $this->assertSame(0, Notification::count());
    }

    public function test_each_contract_is_notified_once_per_window(): void
    {
        $this->contractEndingIn(5);

        $this->artisan('notifications:generate')->assertSuccessful();
        $this->travel(1)->days();
        $this->artisan('notifications:generate')
            ->expectsOutput('Generated 0 new notification(s).')
            ->assertSuccessful();

        $this->assertSame(1, Notification::count());
    }

    /**
     * An extended contract has a new end date, so it is worth a new alert
     * when that date comes into range.
     */
    public function test_an_extended_contract_is_notified_again_for_its_new_end_date(): void
    {
        $contract = $this->contractEndingIn(5);
        $this->artisan('notifications:generate')->assertSuccessful();

        $contract->update(['end_date' => now()->addDays(20)->toDateString()]);
        $this->travel(15)->days();
        $this->artisan('notifications:generate')->assertSuccessful();

        $this->assertSame(2, Notification::where('contract_id', $contract->id)->count());
    }

    public function test_a_deleted_patient_does_not_break_the_run(): void
    {
        $contract = $this->contractEndingIn(2);
        $contract->patient->delete();

        $this->artisan('notifications:generate')->assertSuccessful();

        $this->assertSame(1, Notification::count());
    }
}
