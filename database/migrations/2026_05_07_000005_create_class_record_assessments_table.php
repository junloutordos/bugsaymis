<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_record_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_record_quarter_id')->constrained('class_record_quarters')->cascadeOnDelete();
            $table->foreignId('grading_category_id')->constrained('grading_categories');
            $table->unsignedTinyInteger('assessment_number')->comment('e.g. 1 for AA1, 2 for AA2');
            $table->string('title')->comment('e.g. Titration Curve, Definition of Acid and Bases');
            $table->date('activity_date')->nullable();
            $table->decimal('max_score', 8, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(
                ['class_record_quarter_id', 'grading_category_id', 'assessment_number'],
                'crq_category_assessment_unique'
            );
            $table->index(['class_record_quarter_id', 'sort_order'], 'cra_quarter_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_record_assessments');
    }
};
