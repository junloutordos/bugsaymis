<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ams_activity_student_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('ams_activities')->cascadeOnDelete();
            $table->unsignedBigInteger('participant_id'); // student ID
            $table->string('attended', 10)->default('no');
            $table->decimal('hours_attended', 5, 2)->default(0);
            $table->string('certificate_path')->nullable();
            $table->timestamps();

            $table->unique(['activity_id', 'participant_id'], 'ams_student_attendance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ams_activity_student_attendance');
    }
};
