<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('e.g. SCI7-1, MATH8-2');
            $table->string('name', 150);
            $table->text('description')->nullable();

            // Load unit configuration
            $table->unsignedTinyInteger('credit_units')->default(3)
                  ->comment('Credit units used for grading / load computation');
            $table->decimal('lecture_hours', 4, 1)->default(3)
                  ->comment('Lecture contact hours per week');
            $table->decimal('lab_hours', 4, 1)->default(0)
                  ->comment('Laboratory contact hours per week');
            $table->decimal('load_units', 4, 2)->default(3)
                  ->comment('Load units counted toward faculty loading');

            // Classification
            $table->enum('subject_type', ['lecture', 'laboratory', 'lecture_lab', 'research', 'elective'])
                  ->default('lecture');
            $table->unsignedTinyInteger('grade_level')
                  ->comment('7 to 12; 0 = cross-grade elective');
            $table->enum('semester', ['first', 'second', 'both'])->default('both');

            // Scheduling constraint
            $table->unsignedTinyInteger('sessions_per_week')->default(1)
                  ->comment('Number of class sessions per week');
            $table->unsignedSmallInteger('minutes_per_session')->default(60)
                  ->comment('Duration of each session in minutes');

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['grade_level', 'semester', 'is_active']);
            $table->index('subject_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
