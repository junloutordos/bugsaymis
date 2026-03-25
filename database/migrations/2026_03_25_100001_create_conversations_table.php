<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();

            // 'direct' = 1-on-1 DM  |  'group' = group chat
            $table->enum('type', ['direct', 'group'])->default('direct')->index();

            // Only used for group conversations
            $table->string('name', 150)->nullable();

            // The user who created/owns this conversation
            $table->unsignedBigInteger('created_by')->index();

            // Soft-delete so conversation history is preserved
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('created_by')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
