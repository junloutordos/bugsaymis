<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * faculty_committee_assignments — tracks committee membership per faculty per term.
     * Per BOT Resolution: every faculty must have at least 1 committee.
     * Chairpersonships are flagged and tracked separately for compliance.
     * References the existing `committees` table (committees.id bigint unsigned).
     */
    public function up(): void
    {
        Schema::create('faculty_committee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()
                  ->comment('Faculty member');
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();

            // Link to committees table (FK added in migration 000014 after committees is created)
            $table->unsignedBigInteger('committee_id')->nullable();
            $table->string('committee_name', 200)
                  ->comment('Stored explicitly for denormalization and legacy committee names');

            $table->enum('role', ['chairperson', 'co_chair', 'member', 'secretary'])
                  ->default('member');
            $table->decimal('load_units', 4, 2)->default(0)
                  ->comment('Load units credited for this committee role');

            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('remarks')->nullable();

            // Optional back-link to the load_assignments row
            $table->foreignId('load_assignment_id')
                  ->nullable()
                  ->constrained('load_assignments')
                  ->nullOnDelete();

            $table->timestamps();

            $table->index(['user_id', 'academic_term_id', 'status'], 'idx_fca_user_term_status');
            $table->index(['school_year_id', 'academic_term_id'], 'idx_fca_sy_term');
            $table->index('committee_id', 'idx_fca_committee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_committee_assignments');
    }
};
