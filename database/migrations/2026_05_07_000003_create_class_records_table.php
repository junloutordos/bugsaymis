<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_records', function (Blueprint $table) {
            $table->id();

            // subjects.id is bigint (id()); sections.id is unsigned int (increments())
            $table->unsignedBigInteger('subject_id')->nullable()->comment('Soft ref → subjects.id');
            // sections.id uses increments() (unsigned int, legacy) — formal FK skipped to avoid
            // MySQL 8 type-mismatch error; relationship enforced at application level.
            $table->unsignedInteger('section_id')->nullable()->comment('Soft ref → sections.id');
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('grading_option_id')->constrained('grading_options');

            $table->string('school_year', 20)->comment('e.g. 2024-2025');
            $table->string('subject_name')->comment('Denormalized copy for display/export');
            $table->string('year_level_section')->comment('e.g. G-10 Graviton');

            $table->enum('status', ['draft', 'submitted', 'checked'])->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->foreignId('checked_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // subject_id → subjects.id (both bigint — FK safe)
            $table->foreign('subject_id')->references('id')->on('subjects')->nullOnDelete();

            $table->index(['teacher_id', 'school_year']);
            $table->index(['section_id', 'school_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_records');
    }
};
