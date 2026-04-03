<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizational_versions', function (Blueprint $table) {
            $table->id();

            // ── Version Identity ──────────────────────────────────────────────
            $table->string('version_number', 20)
                ->comment('Semantic version string, e.g. v2026.1, v2026.2');
            $table->string('name')
                ->comment('Human-readable version name, e.g. Q1 2026 Organizational Structure');

            $table->enum('status', [
                'draft',     // being built; not yet effective
                'approved',  // approved but not yet in effect
                'active',    // currently effective version
                'archived',  // superseded by a newer version
            ])->default('draft');

            // ── Effectivity ───────────────────────────────────────────────────
            $table->date('effective_date')
                ->comment('Date this version becomes the official structure');
            $table->date('end_date')->nullable()
                ->comment('NULL = currently active; set when superseded');

            // ── Structural Snapshot ───────────────────────────────────────────
            $table->json('snapshot')->nullable()
                ->comment('Full JSON snapshot of the org tree at this version — enables historical replay');

            // ── Approval Workflow ─────────────────────────────────────────────
            $table->unsignedBigInteger('approved_by')->nullable()
                ->comment('HR/Admin user who approved this version');
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            // ── Change Summary ────────────────────────────────────────────────
            $table->text('change_summary')->nullable()
                ->comment('Summary of structural changes from the previous version');
            $table->string('basis_document')->nullable()
                ->comment('Resolution / Order that authorizes this structure');

            // ── Audit ─────────────────────────────────────────────────────────
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ───────────────────────────────────────────────────────
            $table->index('status');
            $table->index('effective_date');
            $table->index(['status', 'effective_date'], 'idx_ov_status_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizational_versions');
    }
};
