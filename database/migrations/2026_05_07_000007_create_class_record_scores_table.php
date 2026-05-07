<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_record_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_record_student_id')->constrained('class_record_students')->cascadeOnDelete();
            $table->foreignId('class_record_assessment_id')->constrained('class_record_assessments')->cascadeOnDelete();
            $table->decimal('score', 8, 2)->nullable()->comment('null = not yet entered');
            $table->timestamps();

            $table->unique(
                ['class_record_student_id', 'class_record_assessment_id'],
                'crs_student_assessment_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_record_scores');
    }
};
