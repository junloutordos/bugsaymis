<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * overload_computations — stores overload pay computations per faculty per term.
     *
     * BOT Resolution No. 2025-05-80 formula:
     *   PHTR (Per Hour Teaching Rate) = (Annual Rate / 1600) × 1.25
     *   Overload Pay = PHTR × overload_hours
     *
     * Workflow: pending → for_approval → approved → paid
     */
    public function up(): void
    {
        Schema::create('overload_computations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()
                  ->comment('Faculty with overload');
            $table->foreignId('faculty_load_id')
                  ->nullable()
                  ->constrained('faculty_loads')
                  ->nullOnDelete();
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();

            // Inputs
            $table->decimal('annual_rate', 12, 2)->comment('Annual salary rate (AR)');
            $table->decimal('overload_units', 5, 2)->comment('Units beyond the 18-unit full load');
            $table->decimal('overload_hours', 6, 2)->comment('Overload contact hours per week');

            // Computed values (stored for audit trail)
            $table->decimal('phtr', 10, 4)->comment('Per Hour Teaching Rate = (AR / 1600) × 1.25');
            $table->decimal('total_overload_pay', 12, 2)->comment('PHTR × overload_hours × weeks in term');
            $table->unsignedSmallInteger('term_weeks')->default(18)
                  ->comment('Number of weeks in the term (for total pay calculation)');

            // Approval workflow
            $table->enum('status', ['pending', 'for_approval', 'approved', 'paid', 'cancelled'])
                  ->default('pending');
            $table->foreignId('requested_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'academic_term_id']);
            $table->index(['school_year_id', 'academic_term_id', 'status'], 'idx_oc_sy_term_status');
            $table->index('status', 'idx_oc_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overload_computations');
    }
};
