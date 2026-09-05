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
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `contracts` MODIFY `payment_method` ENUM('cash','razorpay') NOT NULL DEFAULT 'cash'");
    }
};
