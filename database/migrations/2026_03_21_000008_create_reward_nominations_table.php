<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_nominations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reward_type_id')
                  ->constrained('reward_types')
                  ->cascadeOnDelete();

            $table->foreignId('nominee_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('nominated_by')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->text('justification');

            // Comma-separated or JSON paths to uploaded files
            $table->json('supporting_documents')->nullable();

            $table->enum('status', [
                'pending',
                'screened',
                'evaluated',
                'approved',
                'rejected',
            ])->default('pending');

            // Period this nomination covers (e.g. Q1 2026, Jan 2026)
            $table->string('period')->nullable();

            // Optional team name when category = team
            $table->string('team_name')->nullable();

            $table->text('remarks')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index('status');
            $table->index(['nominee_id', 'reward_type_id']);
            $table->index('nominated_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_nominations');
    }
};
