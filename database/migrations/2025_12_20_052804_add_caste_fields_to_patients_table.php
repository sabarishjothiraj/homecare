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
        Schema::table('patients', function (Blueprint $table) {
            // Drop the community column
            $table->dropColumn('community');

            // Add caste_id and subcaste columns
            $table->foreignId('caste_id')->nullable()->after('address')->constrained('castes')->onDelete('set null');
            $table->string('subcaste', 100)->nullable()->after('caste_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            // Drop the new columns
            $table->dropForeign(['caste_id']);
            $table->dropColumn(['caste_id', 'subcaste']);

            // Restore the community column
            $table->string('community')->after('address');
        });
    }
};
