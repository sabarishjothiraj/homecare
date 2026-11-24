<?php

namespace Tests\Feature;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The one-off UTC → IST conversion of stored date-times.
 *
 * It rewrites every row in the database exactly once, so what it must and
 * must not touch is pinned down here rather than trusted.
 */
class TimestampShiftMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_09_23_000100_shift_stored_timestamps_to_ist.php');
    }

    /**
     * A contract whose date-times were written while the app ran on UTC.
     */
    private function legacyContract(): Contract
    {
        $contract = Contract::factory()->create(['start_date' => '2026-09-30', 'end_date' => '2026-10-31']);

        DB::table('contracts')->where('id', $contract->id)->update([
            'created_at' => '2026-09-30 20:00:00',
            'updated_at' => '2026-01-15 18:45:00',
            'closed_at' => null,
        ]);

        return $contract;
    }

    private function row(Contract $contract): object
    {
        return DB::table('contracts')->where('id', $contract->id)->first();
    }

    public function test_date_times_move_forward_five_and_a_half_hours_and_back(): void
    {
        $contract = $this->legacyContract();

        $this->migration()->up();

        $row = $this->row($contract);
        $this->assertSame('2026-10-01 01:30:00', $row->created_at);
        $this->assertSame('2026-01-16 00:15:00', $row->updated_at);

        $this->migration()->down();

        $row = $this->row($contract);
        $this->assertSame('2026-09-30 20:00:00', $row->created_at);
        $this->assertSame('2026-01-15 18:45:00', $row->updated_at);
    }

    public function test_nulls_and_calendar_dates_are_left_alone(): void
    {
        $contract = $this->legacyContract();

        $this->migration()->up();

        $row = $this->row($contract);
        $this->assertNull($row->closed_at);
        $this->assertSame('2026-09-30', $row->start_date);
        $this->assertSame('2026-10-31', $row->end_date);
    }

    public function test_the_jobs_table_keeps_its_unix_timestamps(): void
    {
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => '{}',
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => 1790000000,
            'created_at' => 1790000000,
        ]);

        $this->migration()->up();

        $job = DB::table('jobs')->first();
        $this->assertSame(1790000000, (int) $job->available_at);
        $this->assertSame(1790000000, (int) $job->created_at);
    }

    public function test_a_publish_time_typed_by_an_admin_is_already_ist_and_is_not_shifted(): void
    {
        $blogId = DB::table('blogs')->insertGetId([
            'title' => 'Monsoon care tips',
            'slug' => 'monsoon-care-tips',
            'content' => '<p>...</p>',
            'author_id' => User::factory()->create()->id,
            'status' => 'published',
            'published_at' => '2026-10-01 10:00:00',
            'created_at' => '2026-09-30 20:00:00',
            'updated_at' => '2026-09-30 20:00:00',
        ]);
        $blog = fn () => DB::table('blogs')->where('id', $blogId)->first();

        $this->migration()->up();

        $this->assertSame('2026-10-01 10:00:00', $blog()->published_at);
        // The rest of the row is app-stamped UTC and still converts.
        $this->assertSame('2026-10-01 01:30:00', $blog()->created_at);

        $this->migration()->down();

        $this->assertSame('2026-10-01 10:00:00', $blog()->published_at);
        $this->assertSame('2026-09-30 20:00:00', $blog()->created_at);
    }

    public function test_nothing_is_converted_when_the_app_is_pinned_to_another_timezone(): void
    {
        config(['app.timezone' => 'UTC']);
        $contract = $this->legacyContract();

        $this->migration()->up();

        $this->assertSame('2026-09-30 20:00:00', $this->row($contract)->created_at);
    }
}
