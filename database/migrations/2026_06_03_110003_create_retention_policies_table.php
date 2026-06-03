<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);

            // null = global default applied when no school-year-specific policy exists
            $table->foreignId('school_year_id')->nullable()->constrained('school_years')->nullOnDelete()
                  ->comment('null = global default');

            $table->enum('grade_band', ['jhs', 'shs', 'all'])->default('all')
                  ->comment('jhs = Grades 7-10, shs = Grades 11-12, all = both');

            // Retention / exclusion thresholds
            $table->unsignedTinyInteger('failed_subjects_for_retention')->default(3)
                  ->comment('Number of failed subjects that triggers retention (repeat same grade)');
            $table->unsignedTinyInteger('failed_subjects_for_exclusion')->nullable()
                  ->comment('Number of failed subjects that triggers exclusion/dismissal; null = no exclusion rule');

            // GWA-based promotion floor (optional)
            $table->decimal('min_gwa_for_promotion', 5, 2)->nullable()
                  ->comment('Minimum GWA required for promotion; null = no GWA floor');

            // Honors cutoffs (GWA thresholds, all subjects weighted equally)
            $table->decimal('honors_highest_cutoff', 5, 2)->default(98.00)
                  ->comment('GWA >= this → With Highest Honors');
            $table->decimal('honors_high_cutoff', 5, 2)->default(95.00)
                  ->comment('GWA >= this → With High Honors');
            $table->decimal('honors_regular_cutoff', 5, 2)->default(90.00)
                  ->comment('GWA >= this → With Honors');

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_year_id', 'grade_band', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retention_policies');
    }
};
