<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_routings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->unsignedInteger('sequence')->default(1);
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('receiver_id')->constrained('users')->onDelete('cascade');
            $table->string('status')->default('Pending'); // Pending | Received | Action Taken | Forwarded | Returned
            $table->timestamp('received_at')->nullable();
            $table->timestamp('action_taken_at')->nullable();
            $table->text('action_taken')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('forwarded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_routings');
    }
};
