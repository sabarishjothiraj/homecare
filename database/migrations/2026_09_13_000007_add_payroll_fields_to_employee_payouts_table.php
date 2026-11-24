<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Turn payouts into monthly payroll records: the salary basis and
     * attendance they were calculated from, gross, adjustments and net
     * (`amount`), plus how they were paid.
     */
    public function up(): void
    {
        Schema::table('employee_payouts', function (Blueprint $table) {
            $table->string('pay_type', 20)->nullable()->after('frequency');
            $table->unsignedSmallInteger('days_in_period')->nullable()->after('period_end');
            $table->decimal('payable_days', 5, 1)->default(0)->after('days_in_period');
            $table->json('attendance_summary')->nullable()->after('payable_days');
            $table->decimal('gross_amount', 10, 2)->default(0)->after('attendance_summary');
            $table->decimal('adjustments_total', 10, 2)->default(0)->after('gross_amount');
            $table->string('payment_method', 30)->nullable()->after('status');
            $table->string('transaction_reference')->nullable()->after('payment_method');

            $table->index(['period_start', 'period_end']);
        });

        Schema::create('employee_payout_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_payout_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // earning | deduction
            $table->string('label');
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_payout_items');

        Schema::table('employee_payouts', function (Blueprint $table) {
            $table->dropIndex(['period_start', 'period_end']);
            $table->dropColumn([
                'pay_type', 'days_in_period', 'payable_days', 'attendance_summary',
                'gross_amount', 'adjustments_total', 'payment_method', 'transaction_reference',
            ]);
        });
    }
};
