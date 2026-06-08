<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_record_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('class_record_attendance_date_id');
            $table->unsignedBigInteger('class_record_student_id');
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->nullable();
            $table->timestamps();

            $table->foreign('class_record_attendance_date_id', 'car_date_fk')
                ->references('id')->on('class_record_attendance_dates')
                ->cascadeOnDelete();
            $table->foreign('class_record_student_id', 'car_student_fk')
                ->references('id')->on('class_record_students')
                ->cascadeOnDelete();

            $table->unique(
                ['class_record_attendance_date_id', 'class_record_student_id'],
                'attendance_record_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_record_attendance_records');
    }
};
