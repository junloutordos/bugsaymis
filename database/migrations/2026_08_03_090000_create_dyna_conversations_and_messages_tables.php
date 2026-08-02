<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dyna_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('dyna_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dyna_conversation_id')->constrained('dyna_conversations')->cascadeOnDelete();
            $table->string('role', 20); // 'user' | 'assistant'
            $table->longText('content');
            $table->json('tool_calls')->nullable(); // [{name, input, result}] for audit
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dyna_messages');
        Schema::dropIfExists('dyna_conversations');
    }
};
