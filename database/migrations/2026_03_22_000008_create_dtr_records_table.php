<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dtr_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('employee_schedules')->nullOnDelete();

            $table->date('work_date')->index();

            // Raw punch times (from biometric_logs or manual)
            $table->time('time_in_am')->nullable();
            $table->time('time_out_am')->nullable();
            $table->time('time_in_pm')->nullable();
            $table->time('time_out_pm')->nullable();

            // Computed fields (set by DTRService)
            $table->decimal('hours_worked', 5, 2)->nullable()
                ->comment('Net hours worked, excluding lunch break');
            $table->decimal('late_minutes', 6, 2)->default(0)
                ->comment('Minutes arrived late past grace period');
            $table->decimal('undertime_minutes', 6, 2)->default(0)
                ->comment('Minutes left early');
            $table->decimal('overtime_minutes', 6, 2)->default(0)
                ->comment('Approved overtime minutes beyond official hours');

            $table->enum('day_type', [
                'regular',              // Normal working day
                'holiday_regular',      // Regular holiday
                'holiday_special',      // Special non-working holiday
                'rest_day',             // Weekend / scheduled rest day
                'rest_day_holiday',     // Rest day that falls on holiday
            ])->default('regular');

            $table->enum('attendance_status', [
                'present',
                'absent',
                'half_day',
                'on_leave',
                'on_official_business',
                'suspended',            // Gov work suspension
                'holiday',
            ])->default('present')->index();

            // Leave linkage
            $table->foreignId('leave_application_id')->nullable()
                ->constrained('leave_applications')->nullOnDelete();

            $table->boolean('is_posted')->default(false)->index()
                ->comment('True once included in a payroll run');
            $table->boolean('is_locked')->default(false)
                ->comment('Locked after payroll finalization — no further edits');

            // Audit
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['user_id', 'work_date'], 'dtr_records_unique');
            $table->index(['user_id', 'work_date', 'attendance_status']);
            $table->index(['is_posted', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dtr_records');
    }
};
