<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->unsignedInteger('section_id')->comment('FK to sections.id (int PK, not bigint)');
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->longText('syllabus_body')->nullable();
            $table->timestamps();

            $table->unique(
                ['subject_id', 'section_id', 'school_year_id', 'academic_term_id'],
                'learn_courses_tuple_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_courses');
    }
};
