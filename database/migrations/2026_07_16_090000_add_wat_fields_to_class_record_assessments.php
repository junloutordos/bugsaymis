<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('class_record_assessments', function (Blueprint $table) {
            // WAT taxonomy: formative | alternative | ila | long_test_1 | long_test_2
            $table->string('assessment_type', 20)->nullable()->after('grading_category_id');
            $table->boolean('is_graded')->default(true)->after('assessment_type');
            // Derived server-side (never client-supplied): type is a long test,
            // or the per-assessment share of the quarterly grade is >= 10%
            $table->boolean('is_major')->default(false)->after('is_graded');
            $table->timestamp('plotted_at')->nullable()->after('activity_date')
                ->comment('When an activity_date was first assigned — audit anchor for the Friday-before rule');
        });

        Schema::create('wat_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('section_id');
            $table->foreignId('school_year_id')->constrained('school_years');
            $table->date('week_start')->comment('Monday of the reviewed week');
            $table->string('status', 20)->default('reviewed');
            $table->text('remarks')->nullable();
            $table->foreignId('reviewed_by_id')->constrained('users');
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->unique(['section_id', 'school_year_id', 'week_start'], 'wat_reviews_section_week_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wat_reviews');

        Schema::table('class_record_assessments', function (Blueprint $table) {
            $table->dropColumn(['assessment_type', 'is_graded', 'is_major', 'plotted_at']);
        });
    }
};
