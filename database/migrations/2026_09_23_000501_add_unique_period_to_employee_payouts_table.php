<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One live payslip per employee per period.
     *
     * `not_cancelled` is 1 for pending/paid rows and NULL for cancelled ones;
     * NULLs never collide in a unique index, so cancelled rows can sit beside
     * the payslip that replaced them.
     *
     * Existing duplicates are not resolved here: one of them may have been
     * paid, and picking which to drop is a business decision. The migration
     * stops before changing anything and lists them instead.
     */
    public function up(): void
    {
        $duplicates = DB::table('employee_payouts')
            ->where('status', '!=', 'cancelled')
            ->select('employee_id', 'period_start', 'period_end', DB::raw('GROUP_CONCAT(id ORDER BY id) as ids'))
            ->groupBy('employee_id', 'period_start', 'period_end')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $groups = $duplicates->map(fn ($row) => "employee #{$row->employee_id} {$row->period_start} to {$row->period_end}: payouts {$row->ids}");

            throw new RuntimeException(
                'employee_payouts has more than one live payslip for the same employee and period ('.$groups->implode('; ').'). '
                ."In each group keep the correct payslip (the one actually paid out, if any), set status = 'cancelled' on the others, then run the migration again."
            );
        }

        Schema::table('employee_payouts', function (Blueprint $table) {
            $table->boolean('not_cancelled')->storedAs("CASE WHEN status = 'cancelled' THEN NULL ELSE 1 END")->after('status');

            $table->unique(['employee_id', 'period_start', 'period_end', 'not_cancelled'], 'employee_payouts_employee_period_unique');
        });
    }

    public function down(): void
    {
        $hasEmployeeIndex = Schema::hasIndex('employee_payouts', ['employee_id']);

        Schema::table('employee_payouts', function (Blueprint $table) use ($hasEmployeeIndex) {
            // The unique key starts with employee_id, so MySQL may have dropped the
            // foreign key's own index in its favour; the foreign key needs one back
            if (! $hasEmployeeIndex) {
                $table->index('employee_id', 'employee_payouts_employee_id_foreign');
            }

            $table->dropUnique('employee_payouts_employee_period_unique');
            $table->dropColumn('not_cancelled');
        });
    }
};
