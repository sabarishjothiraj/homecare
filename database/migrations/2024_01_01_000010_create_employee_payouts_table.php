<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('employee_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('contract_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('frequency', ['daily', 'weekly', 'monthly', 'yearly'])->default('monthly');
            $table->decimal('rate', 10, 2);
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_payouts');
    }
};

