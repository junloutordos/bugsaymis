<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Calibration Schedule for Laboratory Equipment (PSHS-00-F-CID-09).
 * Schedule approved by Academic Unit Head / CID Chief. Events carry per-month
 * Target/Actual dates, certificate, sticker flag, due date and pass/fail
 * result. Overdue/failed instruments are flagged out of service (§3.5.4/§3.5.7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_calibration_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->nullOnDelete();
            $table->foreignId('lab_equipment_id')->constrained('lab_equipment')->cascadeOnDelete();
            $table->string('frequency', 30);
            $table->foreignId('prepared_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete(); // AUH / CID Chief
            $table->timestamp('approved_at')->nullable();
            $table->string('status', 20)->default('draft'); // draft | submitted | approved
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['school_year_id', 'lab_equipment_id'], 'lab_cal_sched_sy_equip_unq');
        });

        Schema::create('lab_calibration_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_calibration_schedule_id')->constrained('lab_calibration_schedules')->cascadeOnDelete();
            $table->foreignId('lab_equipment_id')->constrained('lab_equipment')->cascadeOnDelete();
            $table->unsignedTinyInteger('month')->nullable(); // 1-12
            $table->date('target_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->string('calibrated_by', 150)->nullable(); // external provider or trained employee
            $table->boolean('is_external')->default(true);
            $table->string('certificate_path')->nullable(); // S3
            $table->boolean('sticker_attached')->default(false);
            $table->date('due_date')->nullable();
            $table->string('result', 15)->default('pending'); // passed | failed | pending
            // scheduled | done | overdue | out_of_service
            $table->string('status', 20)->default('scheduled')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_calibration_events');
        Schema::dropIfExists('lab_calibration_schedules');
    }
};
