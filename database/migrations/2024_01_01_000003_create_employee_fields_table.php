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
        Schema::create('employee_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Field name/key
            $table->string('label'); // Display label
            $table->enum('type', ['text', 'textarea', 'email', 'phone', 'number', 'date', 'select', 'file'])->default('text');
            $table->text('options')->nullable(); // JSON for select options or file formats
            $table->boolean('is_required')->default(false);
            $table->boolean('is_mandatory')->default(false); // Cannot be deleted/modified
            $table->boolean('is_document')->default(false); // Is this a document field
            $table->integer('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_fields');
    }
};

