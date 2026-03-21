<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranking_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained('applications')->cascadeOnDelete();
            $table->decimal('total_score', 8, 4)->default(0);
            $table->unsignedInteger('rank')->nullable();
            $table->boolean('is_recommended')->default(false);
            $table->text('deliberation_notes')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'rank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ranking_summaries');
    }
};
