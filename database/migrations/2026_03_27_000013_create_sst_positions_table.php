<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * sst_positions — Science and Technology Teacher position levels (SST I–V).
     *
     * Stores load expectations and constraints per position level,
     * aligned with BOT Resolution No. 2025-05-80 and DBM Salary Grades.
     *
     * Used by the ScheduleValidationService and LoadComputationService
     * to enforce position-appropriate load limits.
     */
    public function up(): void
    {
        Schema::create('sst_positions', function (Blueprint $table) {
            $table->id();

            // Position code: 'SST-I', 'SST-II', 'SST-III', 'SST-IV', 'SST-V'
            $table->string('code', 20)->unique();

            // Full plantilla title
            $table->string('name', 120);

            // DBM Salary Grade (SG 12 → SG 17 for SST I–V)
            $table->unsignedTinyInteger('salary_grade')->nullable();

            // ── Load expectations per BOT Resolution 2025-05-80 ──────────────

            // Full load (18 units for all SST levels per resolution)
            $table->decimal('full_load_threshold', 5, 2)->default(18.00)
                  ->comment('Units for full load — 18 for all SST per BOT Res. 2025-05-80');

            // Minimum teaching units required (classroom instruction)
            $table->decimal('min_teaching_units', 5, 2)->default(6.00)
                  ->comment('Minimum units that must be classroom instruction');

            // Caps on non-teaching loads
            $table->decimal('max_admin_units', 5, 2)->default(15.00)
                  ->comment('Max administrative load (BOT resolution cap)');
            $table->decimal('max_research_units', 5, 2)->default(5.00)
                  ->comment('Max research advisory units (BOT resolution cap)');

            // Overload threshold (same as full_load in most cases)
            $table->decimal('overload_threshold', 5, 2)->default(18.00);

            // Maximum overload allowed before Campus Director approval required
            $table->decimal('max_overload_units', 5, 2)->default(6.00)
                  ->comment('Units beyond full load before approval required');

            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sst_positions');
    }
};
