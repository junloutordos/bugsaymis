<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * designation_categories — top-level groupings for designations.
     *
     * Examples:
     *   - Academic        (Department Head, Grade Level Chair)
     *   - Administrative  (Division Head, Unit Coordinator)
     *   - Committee       (PRAISE Committee Chair, BAC Member)
     *   - Research        (Research Coordinator, Adviser)
     *
     * Categories determine the default load accounting and UI grouping
     * when assigning designations to faculty in load_assignments.
     */
    public function up(): void
    {
        Schema::create('designation_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique()->comment('e.g. ACADEMIC, ADMIN, COMMITTEE');
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('designation_categories');
    }
};
