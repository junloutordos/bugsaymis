<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * faculty_loads — one record per faculty per academic term.
     * Aggregates all assignment types into a single load summary.
     * Per BOT Resolution No. 2025-05-80:
     *   Full Load = 18 units; Underload < 18; Overload > 18
     *   Max admin load = 15 units
     *   Max research advisory = 5 units
     */
    public function up(): void
    {
        Schema::create('faculty_loads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()
                  ->comment('Faculty member');
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();

            // Load unit breakdown
            $table->decimal('teaching_units', 5, 2)->default(0);
            $table->decimal('research_units', 5, 2)->default(0);
            $table->decimal('admin_units', 5, 2)->default(0);
            $table->decimal('cocurricular_units', 5, 2)->default(0);
            $table->decimal('committee_units', 5, 2)->default(0);
            $table->decimal('total_units', 5, 2)->default(0)
                  ->comment('Sum of all load types; updated on each assignment change');

            // Thresholds (per resolution; stored for audit trail)
            $table->decimal('full_load_threshold', 5, 2)->default(18)
                  ->comment('BOT-defined full load (normally 18 units)');

            // Status flags
            $table->enum('load_status', ['underload', 'full_load', 'overload'])->default('underload');
            $table->boolean('is_locked')->default(false)
                  ->comment('Lock prevents further assignment changes after term approval');

            // Overload approval
            $table->boolean('overload_approved')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_remarks')->nullable();

            $table->text('remarks')->nullable();
            $table->timestamps();

            // One load record per faculty per term
            $table->unique(['user_id', 'academic_term_id']);
            $table->index(['school_year_id', 'academic_term_id', 'load_status']);
            $table->index(['user_id', 'school_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_loads');
    }
};
