<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_year_id')
                  ->constrained('school_years')
                  ->cascadeOnDelete();
            $table->string('name', 50)->comment('e.g. 1st Semester, 2nd Semester, Summer');
            $table->enum('term_type', ['1st_semester', '2nd_semester', 'summer', 'trimester', 'quarter'])->default('1st_semester');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['school_year_id', 'term_type']);
            $table->index(['school_year_id', 'is_current']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_terms');
    }
};
