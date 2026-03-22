<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->string('name')->comment('e.g. "Regular Office Hours", "Night Shift"');
            $table->enum('schedule_type', ['fixed', 'shifting'])->default('fixed');

            // Work days stored as JSON array: ["Mon","Tue","Wed","Thu","Fri"]
            $table->json('work_days')->nullable();

            $table->time('time_in');
            $table->time('time_out');

            $table->unsignedSmallInteger('grace_period_minutes')->default(15)
                ->comment('Late tolerance before deduction starts');
            $table->unsignedSmallInteger('late_threshold_minutes')->default(240)
                ->comment('Minutes late that triggers half-day deduction');
            $table->unsignedSmallInteger('half_day_hours')->default(4)
                ->comment('Hours boundary for AM/PM half-day split');

            $table->date('effective_date');
            $table->date('end_date')->nullable()->comment('NULL = currently active');
            $table->boolean('is_default')->default(false)
                ->comment('Default schedule for this employee');

            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'effective_date']);
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_schedules');
    }
};
