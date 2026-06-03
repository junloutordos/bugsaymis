<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();

            // students table is MyISAM — cannot be an FK target.
            // Constraint is enforced at application level.
            $table->unsignedInteger('student_id')
                  ->comment('references students.id — app-level constraint (MyISAM cannot be FK target)');

            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();

            // sections.id is INT (not BIGINT) — raw unsigned int
            $table->unsignedInteger('section_id')
                  ->comment('references sections.id (int PK)');

            $table->unsignedTinyInteger('grade_level')
                  ->comment('7–12, denormalized from section for fast filtering');

            $table->enum('enrollment_type', ['new', 'returning', 'transferee', 'returnee'])
                  ->default('returning');

            $table->enum('status', ['enrolled', 'dropped', 'transferred_out', 'on_leave', 'completed'])
                  ->default('enrolled');

            $table->date('enrollment_date');
            $table->text('notes')->nullable();

            $table->foreignId('encoded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One enrollment record per student per school year
            $table->unique(['student_id', 'school_year_id'], 'uq_enrollment_student_year');

            $table->index(['section_id', 'school_year_id']);
            $table->index(['student_id', 'status']);
            $table->index(['grade_level', 'school_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};
