<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Upgrading patients from the free-text address/location columns to the
 * structured address fields.
 *
 * The migration's DDL implicitly commits RefreshDatabase's transaction, so the
 * rows written here are removed by hand in tearDown().
 */
class PatientAddressMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_04_17_163034_update_patients_table_address_fields.php');
    }

    protected function tearDown(): void
    {
        DB::table('patients')->where('name', 'like', 'Legacy %')->delete();

        parent::tearDown();
    }

    /**
     * Put patients back in the pre-migration shape, add rows the old schema
     * allowed, then run the upgrade.
     */
    private function upgradeWith(array $patients): void
    {
        $migration = $this->migration();
        $migration->down();

        try {
            foreach ($patients as $patient) {
                DB::table('patients')->insert($patient + [
                    'phone' => '9876543210',
                    'stay_with_them' => 'no',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } finally {
            // Always restore the current schema so later tests are unaffected.
            $migration->up();
        }
    }

    public function test_the_old_address_and_location_are_carried_across(): void
    {
        $this->upgradeWith([
            ['name' => 'Legacy Chennai', 'email' => 'c@example.com', 'address' => '12 Gandhi Road, T Nagar', 'location' => 'Chennai'],
        ]);

        $patient = DB::table('patients')->where('name', 'Legacy Chennai')->first();
        $this->assertSame('12 Gandhi Road, T Nagar', $patient->address1);
        $this->assertSame('Chennai', $patient->city);
        $this->assertSame('', $patient->state);
        $this->assertSame('', $patient->pincode);
    }

    public function test_patients_saved_without_an_email_do_not_block_the_upgrade(): void
    {
        $this->upgradeWith([
            ['name' => 'Legacy No Email', 'email' => null, 'address' => '5 Anna Salai', 'location' => 'Coimbatore'],
        ]);

        $patient = DB::table('patients')->where('name', 'Legacy No Email')->first();
        $this->assertSame('', $patient->email);
        $this->assertSame('5 Anna Salai', $patient->address1);
    }

    public function test_an_address_too_long_for_address1_is_kept_whole_in_the_notes(): void
    {
        $address = str_repeat('Door 12, Long Street Name, ', 12).'END';

        $this->upgradeWith([
            ['name' => 'Legacy Long', 'email' => 'l@example.com', 'address' => $address, 'location' => 'Madurai', 'notes' => 'Diabetic'],
        ]);

        $patient = DB::table('patients')->where('name', 'Legacy Long')->first();
        $this->assertSame(substr($address, 0, 255), $patient->address1);
        $this->assertStringStartsWith('Diabetic', $patient->notes);
        $this->assertStringContainsString($address, $patient->notes);
    }
}
