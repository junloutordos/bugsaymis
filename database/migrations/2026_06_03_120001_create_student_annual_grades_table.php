<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_annual_grades', function (Blueprint $table) {
            $table->id();

            // students.id — app-level constraint (MyISAM cannot be FK target)
            $table->unsignedInteger('student_id');
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();

            // Source class record (the teacher's gradebook)
            $table->foreignId('class_record_id')->constrained('class_records')->cascadeOnDelete();

            $table->unsignedBigInteger('subject_id')->nullable()
                  ->comment('Soft ref → subjects.id (denormalized for easy joins)');
            $table->string('subject_name', 150)
                  ->comment('Denormalized subject name for display without joining');

            // Per-quarter running grade equivalents (GE scale: 1.0 best, 5.0 = Failed)
            // Q running grade = floor((currentQGE × 2/3) + (prevRunning × 1/3))
            $table->decimal('q1_ge', 5, 3)->nullable();
            $table->decimal('q2_ge', 5, 3)->nullable();
            $table->decimal('q3_ge', 5, 3)->nullable();
            $table->decimal('q4_ge', 5, 3)->nullable();

            // Final grade = average of Q1–Q4 running GEs (round to 3 dp)
            $table->decimal('final_ge', 5, 3)->nullable();

            // Remarks: Passed (final_ge ≤ 3.0) | Failed | Incomplete | Dropped
            $table->string('remarks', 20)->default('Incomplete');

            // Once locked, grades cannot be changed
            $table->boolean('is_locked')->default(false);
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('computed_at')->nullable();

            $table->timestamps();

            // One grade record per student per subject per school year
            $table->unique(['student_id', 'school_year_id', 'class_record_id'], 'uq_annual_grade');
            $table->index(['student_id', 'school_year_id']);
            $table->index('class_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_annual_grades');
    }
};
