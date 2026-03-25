<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_user', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('user_id')->index();

            // Tracks the last time this participant read the conversation.
            // Used to compute unread message counts efficiently.
            $table->timestamp('last_read_at')->nullable();

            // Allow a participant to "leave" a group without deleting the row
            $table->timestamp('left_at')->nullable();

            $table->timestamps();

            // Prevent duplicate participants in the same conversation
            $table->unique(['conversation_id', 'user_id']);

            $table->foreign('conversation_id')
                ->references('id')->on('conversations')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_user');
    }
};
