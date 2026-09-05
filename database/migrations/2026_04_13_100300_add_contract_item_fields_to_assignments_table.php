<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('contract_item_id')->nullable()->after('contract_id')->constrained('contract_items')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
            $table->decimal('line_amount', 10, 2)->nullable()->after('service_id');
        });

        $contracts = DB::table('contracts')->select(['id', 'service_id', 'amount'])->get();

        foreach ($contracts as $contract) {
            $itemId = DB::table('contract_items')->insertGetId([
                'contract_id' => $contract->id,
                'service_id' => $contract->service_id,
                'amount' => $contract->amount,
                'max_assignments' => 10,
                'assignment_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $assignmentCount = DB::table('assignments')
                ->where('contract_id', $contract->id)
                ->update([
                    'contract_item_id' => $itemId,
                    'service_id' => $contract->service_id,
                    'line_amount' => $contract->amount,
                ]);

            DB::table('contract_items')->where('id', $itemId)->update([
                'assignment_count' => max(1, (int) $assignmentCount),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_item_id');
            $table->dropConstrainedForeignId('service_id');
            $table->dropColumn('line_amount');
        });
    }
};
