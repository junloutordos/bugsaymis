<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('nomination_id')
                  ->constrained('reward_nominations')
                  ->cascadeOnDelete();

            $table->foreignId('approver_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->enum('decision', ['approved', 'rejected', 'deferred'])->nullable();

            $table->text('remarks')->nullable();

            // PRAISE workflow levels
            $table->enum('level', ['committee', 'head_of_office'])->default('committee');

            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            // One approval record per nomination per level
            $table->unique(['nomination_id', 'level']);

            $table->index(['nomination_id', 'decision']);
            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_approvals');
    }
};
