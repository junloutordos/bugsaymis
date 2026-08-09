<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quiz_question_bank_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->enum('question_type', ['multiple_choice', 'true_false', 'multiple_select', 'short_answer', 'essay']);
            $table->longText('prompt');
            $table->decimal('points', 6, 2);
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quiz_question_bank_items');
    }
};
