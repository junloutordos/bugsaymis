<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * faculty_vacancies — tracks open teaching/SST positions per term.
     *
     * A vacancy represents a slot that needs to be staffed within a given
     * school year, academic term, and academic unit. It links to an SST
     * position level (the rank/grade required) and optionally to a specific
     * subject that needs coverage.
     *
     * Lifecycle:
     *   open      — slot identified, not yet assigned
     *   filled    — a faculty member has been assigned (filled_by_user_id set)
     *   cancelled — vacancy withdrawn (position eliminated or redistributed)
     *
     * academic_term_id is nullable for vacancies that span a full school year
     * (e.g. a new permanent hire) rather than a single term.
     *
     * subject_id is nullable — some vacancies are for a position level
     * without a specific subject yet determined.
     */
    public function up(): void
    {
        Schema::create('faculty_vacancies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('school_year_id')
                  ->constrained('school_years')
                  ->cascadeOnDelete();

            $table->foreignId('academic_term_id')
                  ->nullable()
                  ->constrained('academic_terms')
                  ->nullOnDelete()
                  ->comment('Null = full-year vacancy');

            $table->foreignId('academic_unit_id')
                  ->constrained('academic_units')
                  ->cascadeOnDelete();

            $table->foreignId('sst_position_id')
                  ->nullable()
                  ->constrained('sst_positions')
                  ->nullOnDelete()
                  ->comment('Required position level; null = any level');

            $table->foreignId('subject_id')
                  ->nullable()
                  ->constrained('subjects')
                  ->nullOnDelete()
                  ->comment('Specific subject needing coverage; null = TBD');

            $table->enum('status', ['open', 'filled', 'cancelled'])->default('open');

            // Resolution
            $table->foreignId('filled_by_user_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete()
                  ->comment('Faculty member who filled this vacancy');
            $table->timestamp('filled_at')->nullable();

            $table->text('notes')->nullable()
                  ->comment('Context: why the vacancy exists, any constraints');

            $table->foreignId('created_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index(['school_year_id', 'status']);
            $table->index(['academic_unit_id', 'status']);
            $table->index(['school_year_id', 'academic_term_id', 'academic_unit_id'], 'fv_sy_term_unit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_vacancies');
    }
};
