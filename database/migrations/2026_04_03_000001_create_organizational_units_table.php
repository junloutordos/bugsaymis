<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizational_units', function (Blueprint $table) {
            $table->id();

            // ── Identity ──────────────────────────────────────────────────────
            $table->string('code', 50)->unique()
                ->comment('Short machine-readable code, e.g. PSHS-CRC, ACAD-DIV');
            $table->string('name')
                ->comment('Full official unit name');
            $table->string('short_name', 100)->nullable()
                ->comment('Acronym or display abbreviation');
            $table->text('description')->nullable();

            // ── Classification ────────────────────────────────────────────────
            $table->enum('type', [
                'institution',   // top-level campus / school
                'division',      // major functional division
                'department',    // department within a division
                'section',       // section within a department
                'unit',          // smallest functional unit
                'office',        // administrative office
                'committee',     // cross-cutting committee
            ])->default('unit')->comment('Organizational level classification');

            // ── Adjacency List Hierarchy ──────────────────────────────────────
            $table->unsignedBigInteger('parent_id')->nullable()
                ->comment('NULL for root node; self-referencing adjacency list');
            $table->foreign('parent_id')
                ->references('id')
                ->on('organizational_units')
                ->nullOnDelete();

            // ── Materialized Path (fast subtree queries) ──────────────────────
            $table->string('path', 500)->nullable()
                ->comment('Ancestor chain: /1/4/7/ — used for efficient subtree lookups');
            $table->unsignedTinyInteger('depth')->default(0)
                ->comment('Distance from root (0 = root node)');

            // ── Display & Ordering ────────────────────────────────────────────
            $table->unsignedSmallInteger('order_index')->default(0)
                ->comment('Sort order among siblings');

            // ── Backward Compatibility Links ──────────────────────────────────
            $table->unsignedBigInteger('division_id')->nullable()
                ->comment('FK to legacy divisions table if this unit maps to a division');
            $table->foreign('division_id')
                ->references('id')
                ->on('divisions')
                ->nullOnDelete();

            $table->unsignedBigInteger('office_id')->nullable()
                ->comment('FK to legacy offices table if this unit maps to an office');
            $table->foreign('office_id')
                ->references('id')
                ->on('offices')
                ->nullOnDelete();

            // ── Operational Metadata ──────────────────────────────────────────
            $table->boolean('is_active')->default(true);
            $table->date('established_date')->nullable();
            $table->date('abolished_date')->nullable();
            $table->string('legal_basis')->nullable()
                ->comment('RA / EO / AO / CSC resolution that created this unit');
            $table->text('mandate')->nullable()
                ->comment('Official functions and responsibilities');
            $table->text('remarks')->nullable();

            // ── Audit ─────────────────────────────────────────────────────────
            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // ── Indexes ───────────────────────────────────────────────────────
            $table->index('parent_id');
            $table->index('type');
            $table->index('is_active');
            $table->index('depth');
            $table->index(['parent_id', 'order_index'], 'idx_ou_parent_order');
            // Prefix index — path values follow /id/id/ pattern; 191 chars covers deep hierarchies
            $table->rawIndex('path(191)', 'idx_ou_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizational_units');
    }
};
