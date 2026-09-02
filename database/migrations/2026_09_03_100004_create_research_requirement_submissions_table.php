<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_requirement_submissions', function (Blueprint $table) {
            $table->id();
            // Explicit short FK name — the default auto-generated name
            // ("research_requirement_submissions_research_requirement_assignment_id_foreign")
            // exceeds MySQL's 64-character identifier limit.
            $table->foreignId('research_requirement_assignment_id')
                ->constrained(table: 'research_requirement_assignments', indexName: 'fk_rr_submissions_assignment')
                ->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->dateTime('submitted_at');
            $table->boolean('is_late')->default(false);
            $table->enum('review_status', ['pending', 'accepted', 'returned'])->default('pending');
            $table->text('review_comment')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['research_requirement_assignment_id', 'submitted_at'], 'idx_rr_submissions_assignment_submitted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_requirement_submissions');
    }
};
