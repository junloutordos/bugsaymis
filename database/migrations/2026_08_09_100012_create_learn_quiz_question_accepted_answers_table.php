<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quiz_question_accepted_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learn_quiz_question_id');
            $table->foreign('learn_quiz_question_id', 'lqqaa_question_fk')
                  ->references('id')->on('learn_quiz_questions')->cascadeOnDelete();
            $table->string('answer_text');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quiz_question_accepted_answers');
    }
};
