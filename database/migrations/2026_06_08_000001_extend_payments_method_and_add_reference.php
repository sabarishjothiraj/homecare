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

        if (!Schema::hasColumn('payments', 'reference')) {
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
        if (Schema::hasColumn('payments', 'reference')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('reference');
            });
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `payments` MODIFY `method` ENUM('cash','razorpay') NOT NULL DEFAULT 'cash'");
        }
    }
};
