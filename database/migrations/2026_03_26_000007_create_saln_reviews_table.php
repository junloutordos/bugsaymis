<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SALN Review Audit Trail.
     *
     * Immutable log of every action taken on a SALN record:
     * submission, review, approval, return, and filing.
     * Each row is append-only — never updated or deleted.
     */
    public function up(): void
    {
        Schema::create('saln_reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('saln_record_id')
                ->constrained('saln_records')
                ->cascadeOnDelete();

            // Who performed the action
            $table->foreignId('actor_id')
                ->constrained('users')
                ->comment('User who performed this action');

            // What action was taken
            $table->enum('action', [
                'created',      // Employee created a draft
                'updated',      // Employee edited a draft
                'submitted',    // Employee submitted for review
                'reviewed',     // Committee member reviewed
                'approved',     // Committee approved
                'returned',     // Committee returned for revision
                'filed',        // HR marked as officially filed
                'reopened',     // HR/Admin reopened a returned SALN
            ])->index();

            // Optional remarks (required for 'returned'; optional for others)
            $table->text('remarks')->nullable();

            // Snapshot of status before and after the action
            $table->string('status_from', 20)->nullable();
            $table->string('status_to',   20)->nullable();

            // created_at only — no updated_at (audit rows are immutable)
            $table->timestamp('created_at')->useCurrent();

            $table->index(['saln_record_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saln_reviews');
    }
};
