<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_rubric_id')->constrained('learn_rubrics')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('max_points', 6, 2);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['learn_rubric_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_rubric_criteria');
    }
};
