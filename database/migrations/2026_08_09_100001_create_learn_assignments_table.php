<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('instructions')->nullable();
            $table->enum('submission_type', ['text', 'file', 'link']);
            $table->decimal('points_possible', 6, 2)->nullable()
                  ->comment('Ignored when a learn_rubrics row exists for this assignment');
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_assignments');
    }
};
