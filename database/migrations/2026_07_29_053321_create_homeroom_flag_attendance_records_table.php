<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeroom_flag_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homeroom_flag_attendance_date_id');
            $table->foreign('homeroom_flag_attendance_date_id', 'hmrm_flag_att_record_date_fk')
                ->references('id')->on('homeroom_flag_attendance_dates')->cascadeOnDelete();
            $table->integer('student_id')->comment('FK to students.id (legacy INT pk); no constraint');
            $table->enum('status', ['present', 'absent', 'tardy']);
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['homeroom_flag_attendance_date_id', 'student_id'], 'hmrm_flag_att_date_student_unique');
            $table->index('student_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_flag_attendance_records');
    }
};
