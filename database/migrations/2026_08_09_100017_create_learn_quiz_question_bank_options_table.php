<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_quiz_question_bank_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('learn_quiz_question_bank_item_id');
            $table->foreign('learn_quiz_question_bank_item_id', 'lqqbo_item_fk')
                  ->references('id')->on('learn_quiz_question_bank_items')->cascadeOnDelete();
            $table->string('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_quiz_question_bank_options');
    }
};
