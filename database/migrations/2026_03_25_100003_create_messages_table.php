<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('conversation_id')->index();
            $table->unsignedBigInteger('sender_id')->index();

            // Supports plain text; extendable to markdown / rich text
            $table->text('body');

            // Optional file attachment (stored path)
            $table->string('attachment_path', 500)->nullable();
            $table->string('attachment_type', 50)->nullable();   // image, pdf, etc.

            // For 1-on-1 DM read receipts (set when the other party reads the message)
            $table->timestamp('read_at')->nullable();

            // Soft deletes allow "delete for me" without removing data for other participants
            $table->softDeletes();
            $table->timestamps();

            // Composite index for the most common query:
            // "give me all messages in conversation X ordered by time"
            $table->index(['conversation_id', 'created_at']);

            $table->foreign('conversation_id')
                ->references('id')->on('conversations')
                ->onDelete('cascade');

            $table->foreign('sender_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
