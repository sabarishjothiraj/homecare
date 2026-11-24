<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `contracts` MODIFY `payment_method` ENUM('cash','razorpay','online','cheque','bank_transfer') NOT NULL DEFAULT 'cash'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally a no-op. Narrowing back to ('cash','razorpay') fails under
        // strict mode once any contract uses online, cheque or bank_transfer, and
        // remapping those rows would rewrite how the contract was paid. The wider
        // enum is a superset, so older code still works against it.
    }
};
