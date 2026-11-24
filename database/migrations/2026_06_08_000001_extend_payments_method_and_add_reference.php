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
        // Allow the same payment methods the contract supports (cash, razorpay, online, cheque, bank_transfer).
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `payments` MODIFY `method` ENUM('cash','razorpay','online','cheque','bank_transfer') NOT NULL DEFAULT 'cash'");
        }

        if (! Schema::hasColumn('payments', 'reference')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('reference')->nullable()->after('notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The method enum is deliberately left wide. Narrowing it back to
        // ('cash','razorpay') fails under strict mode once any online, cheque or
        // bank_transfer payment exists, and remapping those rows would rewrite
        // payment history. The wider enum is a superset, so older code still works.

        if (Schema::hasColumn('payments', 'reference')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('reference');
            });
        }
    }
};
