<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruitment_type_id')->constrained('recruitment_types')->cascadeOnDelete();
            $table->string('name'); // e.g. "Education", "Experience", "Exam Score", "Interview"
            $table->decimal('weight_percentage', 5, 2); // must sum to 100 per type
            $table->string('scoring_guide')->nullable(); // optional description/rubric
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('recruitment_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_criteria');
    }
};
