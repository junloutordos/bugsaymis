<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_form_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('pisaysystemID', 20)->index();
            $table->unsignedBigInteger('school_year_id');
            $table->tinyInteger('grade_level')->unsigned();
            $table->string('section', 50)->comment('academic, activities, social, career, residence, health, allergies, immunizations, medical_history, vitamins');
            $table->timestamp('submitted_at')->useCurrent()->useCurrentOnUpdate();

            $table->unique(['pisaysystemID', 'school_year_id', 'section'], 'student_section_sy_unique');
            $table->foreign('school_year_id')->references('id')->on('school_years')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_form_submissions');
    }
};
