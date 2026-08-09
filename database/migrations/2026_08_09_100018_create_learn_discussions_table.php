<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_discussions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('prompt');
            $table->decimal('points_possible', 6, 2)->nullable();
            $table->foreignId('class_record_assessment_id')->nullable()
                  ->constrained('class_record_assessments')->nullOnDelete();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_discussions');
    }
};
