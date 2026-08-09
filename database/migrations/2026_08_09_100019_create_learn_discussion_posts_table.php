<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_discussion_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_discussion_id')->constrained('learn_discussions')->cascadeOnDelete();
            $table->foreignId('parent_post_id')->nullable()
                  ->constrained('learn_discussion_posts')->nullOnDelete();
            $table->enum('author_type', ['student', 'faculty']);
            $table->unsignedInteger('author_id')
                  ->comment('student_id (students table, no FK) or user_id (users table) depending on author_type');
            $table->longText('body');
            $table->boolean('is_deleted')->default(false);
            $table->enum('deleted_by_type', ['student', 'faculty'])->nullable();
            $table->unsignedInteger('deleted_by_id')->nullable();
            $table->timestamps();

            $table->index(['learn_discussion_id', 'parent_post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_discussion_posts');
    }
};
