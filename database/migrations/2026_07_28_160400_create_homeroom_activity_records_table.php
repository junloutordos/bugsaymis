<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homeroom_activity_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homeroom_activity_log_id')->constrained('homeroom_activity_logs')->cascadeOnDelete();
            $table->integer('student_id')->comment('FK to students.id (legacy INT pk); no constraint');
            $table->enum('am_status', ['present', 'absent'])->default('present');
            $table->enum('pm_status', ['present', 'absent'])->default('present');
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->unique(['homeroom_activity_log_id', 'student_id'], 'hmrm_act_log_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homeroom_activity_records');
    }
};
