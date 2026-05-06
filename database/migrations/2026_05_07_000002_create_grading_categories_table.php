<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grading_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grading_option_id')->constrained('grading_options')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 10)->comment('e.g. AA, FA, SE, PE, QE, P1, P2');
            $table->decimal('weight', 5, 4)->comment('e.g. 0.7000 for 70%');
            $table->unsignedTinyInteger('max_assessments')->comment('e.g. 3 for AA1-AA3, 7 for FA1-FA7');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['grading_option_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_categories');
    }
};
