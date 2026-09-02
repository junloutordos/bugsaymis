<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('research_type', ['thesis', 'investigatory', 'science_research', 'feasibility'])->nullable();
            $table->json('grade_levels')->nullable();
            $table->string('accepted_file_types', 255)->nullable();
            $table->tinyInteger('max_files')->default(5);
            $table->dateTime('due_at');
            $table->boolean('allow_late_submission')->default(true);
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();

            $table->index(['academic_term_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_requirements');
    }
};
