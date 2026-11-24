<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A temporary cover is a short-lived assignment that stands in for a
     * primary assignment (employee on leave) without ending it.
     */
    public function up(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->foreignId('cover_for_assignment_id')
                ->nullable()
                ->after('replaced_assignment_id')
                ->constrained('assignments')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropForeign(['cover_for_assignment_id']);
            $table->dropColumn('cover_for_assignment_id');
        });
    }
};
