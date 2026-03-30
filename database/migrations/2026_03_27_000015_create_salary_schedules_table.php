<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * salary_schedules — DBM Salary Standardization Law pay schedule.
     *
     * Stores monthly and annual rates per salary grade (SG 1–33)
     * and step (1–8).  Multiple effective-date entries are supported
     * so historical rates can be preserved when a new SSL is released.
     *
     * Used by the Overload Pay module to auto-fill the annual_rate
     * field of overload_computations from a faculty member's position.
     *
     * Formula reference (BOT Res. 2025-05-80):
     *   PHTR = (annual_rate / 1,600) × 1.25
     */
    public function up(): void
    {
        Schema::create('salary_schedules', function (Blueprint $table) {
            $table->id();

            $table->unsignedTinyInteger('salary_grade')
                  ->comment('DBM Salary Grade (1–33)');

            $table->unsignedTinyInteger('step')->default(1)
                  ->comment('Within-grade step (1–8)');

            $table->decimal('monthly_rate', 10, 2)
                  ->comment('Monthly salary rate in PHP');

            $table->decimal('annual_rate', 12, 2)
                  ->comment('Annual salary = monthly_rate × 12 (stored for quick lookup)');

            $table->date('effective_date')
                  ->comment('Date from which this rate schedule is in force');

            $table->boolean('is_current')->default(false)
                  ->comment('True for the currently applicable schedule');

            $table->string('schedule_name', 80)->nullable()
                  ->comment('e.g., "SSL V 2023", "SSL VI 2025"');

            $table->text('remarks')->nullable();

            $table->timestamps();

            // One unique rate per grade/step/effective date
            $table->unique(['salary_grade', 'step', 'effective_date'], 'uq_sg_step_date');

            $table->index(['salary_grade', 'step', 'is_current'], 'idx_sg_step_current');
            $table->index('is_current', 'idx_is_current');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_schedules');
    }
};
