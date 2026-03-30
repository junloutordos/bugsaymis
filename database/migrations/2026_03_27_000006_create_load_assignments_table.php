<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * load_assignments — each row is one line item that contributes
     * to a faculty's total load for the term.
     *
     * Types:
     *   teaching     — subject assigned to a section
     *   research     — research advisory (mirrors research_advisories)
     *   admin        — administrative duty (max 15 units per resolution)
     *   cocurricular — co/extra-curricular activity
     *   committee    — committee membership/chairpersonship
     */
    public function up(): void
    {
        Schema::create('load_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculty_load_id')->constrained('faculty_loads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()
                  ->comment('Denormalized faculty FK for faster queries');
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();

            $table->enum('assignment_type', [
                'teaching',
                'research',
                'admin',
                'cocurricular',
                'committee',
            ])->default('teaching');

            // Teaching-specific (nullable for non-teaching assignments)
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->nullOnDelete();
            $table->unsignedInteger('section_id')->nullable()
                  ->comment('FK to sections.id (int PK, not bigint)');

            // Load contribution
            $table->decimal('load_units', 5, 2)->default(0);

            // Description for non-teaching assignments
            $table->string('description', 255)->nullable()
                  ->comment('e.g. "Research Adviser – STEM Research 1", "PRAISE Committee – Chair"');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['faculty_load_id', 'assignment_type']);
            $table->index(['user_id', 'school_year_id', 'academic_term_id']);
            $table->index(['subject_id', 'section_id', 'academic_term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('load_assignments');
    }
};
