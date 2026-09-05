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
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('subcaste')->constrained('users')->nullOnDelete();
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('subcaste')->constrained('users')->nullOnDelete();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('created_by')->nullable()->after('service_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
