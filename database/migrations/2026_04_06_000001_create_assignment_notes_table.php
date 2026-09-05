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
        Schema::create('assignment_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note');
            $table->timestamps();
        });

        $now = now();
        $existingNotes = DB::table('assignments')
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->get(['id', 'notes']);

        if ($existingNotes->isNotEmpty()) {
            DB::table('assignment_notes')->insert(
                $existingNotes->map(function ($assignment) use ($now) {
                    return [
                        'assignment_id' => $assignment->id,
                        'created_by' => null,
                        'note' => $assignment->notes,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->toArray()
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignment_notes');
    }
};
