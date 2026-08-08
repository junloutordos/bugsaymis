<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_course_id')->constrained('learn_courses')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['learn_course_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_modules');
    }
};
