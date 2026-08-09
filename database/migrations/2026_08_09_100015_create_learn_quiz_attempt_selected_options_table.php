<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quiz_attempt_selected_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learn_quiz_attempt_answer_id');
            $table->foreign('learn_quiz_attempt_answer_id', 'lqaso_answer_fk')
                  ->references('id')->on('learn_quiz_attempt_answers')->cascadeOnDelete();
            $table->unsignedBigInteger('learn_quiz_question_option_id');
            $table->foreign('learn_quiz_question_option_id', 'lqaso_option_fk')
                  ->references('id')->on('learn_quiz_question_options')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['learn_quiz_attempt_answer_id', 'learn_quiz_question_option_id'], 'lqaso_answer_option_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quiz_attempt_selected_options');
    }
};
