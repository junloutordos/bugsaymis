<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * research_advisories — tracks research advisory loads.
     * Per BOT Resolution No. 2025-05-80: max 5 advisory load units per term.
     * Each advisory row is mirrored in load_assignments (type = research).
     */
    public function up(): void
    {
        Schema::create('research_advisories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()
                  ->comment('Adviser / faculty');
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();

            // Advisee details
            $table->unsignedInteger('student_id')->nullable()
                  ->comment('FK to students.id (int PK); nullable if student not in system');
            $table->string('student_name', 150)->nullable()
                  ->comment('Free-text fallback when student_id is null');
            $table->string('research_title', 500)->nullable();
            $table->unsignedTinyInteger('grade_level')->nullable()
                  ->comment('7–12; identifies the research stage');

            $table->enum('advisory_type', ['lead', 'co_adviser'])->default('lead')
                  ->comment('lead = 1 unit; co_adviser = 0.5 unit (configurable)');
            $table->decimal('load_units', 4, 2)->default(1.00)
                  ->comment('Load units credited toward the faculty research cap (max 5)');

            $table->enum('status', ['active', 'completed', 'dropped'])->default('active');
            $table->text('remarks')->nullable();

            // Optional back-link to the load_assignments row
            $table->foreignId('load_assignment_id')
                  ->nullable()
                  ->constrained('load_assignments')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'academic_term_id', 'status']);
            $table->index(['school_year_id', 'academic_term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_advisories');
    }
};
