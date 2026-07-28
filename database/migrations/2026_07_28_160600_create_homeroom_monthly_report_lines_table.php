<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeroom_monthly_report_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homeroom_monthly_report_id')->constrained('homeroom_monthly_reports')->cascadeOnDelete();
            $table->integer('student_id')->comment('FK to students.id (legacy INT pk); no constraint');
            $table->decimal('days_present', 5, 2)->default(0)->comment('Weighted: school days minus tardy/cutting/unexcused-absence deductions');
            $table->unsignedSmallInteger('excused_absences')->default(0);
            $table->unsignedSmallInteger('unexcused_absences')->default(0);
            $table->unsignedSmallInteger('cutting_count')->default(0);
            $table->unsignedSmallInteger('tardy_count')->default(0);
            $table->unsignedSmallInteger('inc_uniform_count')->default(0);
            $table->decimal('deduction_points', 5, 2)->default(0);
            $table->string('remarks')->nullable();
            $table->boolean('is_perfect_attendance')->default(false);
            $table->timestamps();

            $table->unique(['homeroom_monthly_report_id', 'student_id'], 'hmrm_report_line_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_monthly_report_lines');
    }
};
