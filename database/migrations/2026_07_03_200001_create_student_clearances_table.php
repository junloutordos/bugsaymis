<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_clearance_period_id')
                ->constrained('student_clearance_periods')
                ->cascadeOnDelete();
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->unsignedInteger('student_id')->comment('references students.id; app-level constraint');
            $table->string('pisaysystem_id', 80)->nullable();
            $table->foreignId('student_enrollment_id')->nullable()->constrained('student_enrollments')->nullOnDelete();
            $table->unsignedInteger('section_id')->nullable()->comment('references sections.id; app-level constraint');
            $table->unsignedTinyInteger('grade_level')->nullable();
            $table->foreignId('adviser_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('open');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('adviser_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('adviser_reviewed_at')->nullable();
            $table->foreignId('registrar_received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registrar_received_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['student_clearance_period_id', 'student_id'], 'uq_clearance_period_student');
            $table->index(['school_year_id', 'grade_level', 'section_id'], 'idx_clearance_sy_grade_section');
            $table->index(['status', 'student_clearance_period_id'], 'idx_clearance_status_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_clearances');
    }
};
