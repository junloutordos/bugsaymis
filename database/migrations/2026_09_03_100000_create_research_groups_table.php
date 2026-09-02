<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->tinyInteger('grade_level');
            $table->string('title', 500);
            $table->enum('research_type', ['thesis', 'investigatory', 'science_research', 'feasibility'])->nullable();
            $table->timestamps();

            $table->index(['academic_term_id', 'grade_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_groups');
    }
};
