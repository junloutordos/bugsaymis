<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * grade_levels — vocabulary table for Grades 7–12.
     *
     * PK is the grade number itself (7, 8, 9, 10, 11, 12) for
     * easy FK joins without ID indirection.
     */
    public function up(): void
    {
        Schema::create('grade_levels', function (Blueprint $table) {
            $table->unsignedTinyInteger('grade')->primary()
                  ->comment('Grade number: 7, 8, 9, 10, 11, 12');
            $table->string('label', 30)->comment('e.g. "Grade 7", "Grade 11"');
            $table->foreignId('academic_unit_id')->nullable()->constrained('academic_units')->nullOnDelete()
                  ->comment('Which academic unit this grade belongs to');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_levels');
    }
};
