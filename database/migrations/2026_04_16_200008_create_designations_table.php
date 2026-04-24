<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * designations — specific administrative/academic roles a faculty member
     * can hold, contributing to their non-teaching load.
     *
     * Examples:
     *   - Department Head (Science)     → 6 load units, max 1 holder per unit
     *   - Grade Level Adviser           → 3 load units, requires_unit = true
     *   - PRAISE Committee Chair        → 2 load units
     *   - Research Coordinator          → 5 load units
     *
     * load_units here is the default contribution for this designation;
     * it can be overridden per load_assignment if needed.
     *
     * requires_unit = true means the designation is scoped to an
     * academic_unit (e.g., "Department Head" is per-unit, not campus-wide).
     *
     * max_holders = max concurrent holders campus-wide (or per-unit when
     * requires_unit = true). NULL means unlimited.
     */
    public function up(): void
    {
        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('designation_category_id')
                  ->constrained('designation_categories')
                  ->cascadeOnDelete();

            $table->string('code', 40)->unique()->comment('e.g. DEPT_HEAD_SCI, GRADE_ADVISER');
            $table->string('name', 150);
            $table->text('description')->nullable();

            // Load contribution
            $table->decimal('load_units', 5, 2)->default(0)
                  ->comment('Default load units this designation contributes');

            // Scoping
            $table->boolean('requires_unit')->default(false)
                  ->comment('If true, holder must be associated with a specific academic_unit');

            // Concurrency cap
            $table->unsignedTinyInteger('max_holders')->nullable()
                  ->comment('Max simultaneous holders; NULL = unlimited');

            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['designation_category_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designations');
    }
};
