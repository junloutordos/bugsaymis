<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollment_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();

            // null = applies to all grade levels for this school year
            $table->unsignedTinyInteger('grade_level')->nullable()
                  ->comment('7–12; null = all grade levels');

            $table->string('label', 100)
                  ->comment('e.g. "Grade 7 New Student Enrollment 2025-2026"');

            $table->dateTime('open_at');
            $table->dateTime('close_at');

            // Manual overrides take precedence over date-based open/close
            $table->boolean('is_manually_open')->default(false)
                  ->comment('Force-open regardless of open_at/close_at');
            $table->boolean('is_manually_closed')->default(false)
                  ->comment('Force-close regardless of open_at/close_at');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_year_id', 'grade_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollment_periods');
    }
};
