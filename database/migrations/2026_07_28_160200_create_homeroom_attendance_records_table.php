<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeroom_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homeroom_attendance_date_id')->constrained('homeroom_attendance_dates')->cascadeOnDelete();
            $table->integer('student_id')->comment('FK to students.id (legacy INT pk); no constraint');
            $table->enum('status', ['present', 'absent', 'tardy', 'cutting']);
            $table->boolean('incomplete_uniform')->default(false);
            $table->enum('excused_status', ['n_a', 'excused', 'unexcused'])->default('n_a');
            $table->foreignId('admission_slip_id')->nullable()->constrained('class_admission_slips')->nullOnDelete();
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['homeroom_attendance_date_id', 'student_id'], 'hmrm_att_date_student_unique');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_attendance_records');
    }
};
