<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('research_requirement_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_requirement_id')->constrained('research_requirements')->cascadeOnDelete();
            $table->foreignId('research_group_id')->constrained('research_groups')->cascadeOnDelete();
            $table->enum('status', ['pending', 'submitted', 'accepted', 'returned'])->default('pending');
            $table->boolean('excluded')->default(false);
            $table->dateTime('reminder_sent_at')->nullable();
            $table->dateTime('overdue_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['research_requirement_id', 'research_group_id'], 'uq_requirement_group');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_requirement_assignments');
    }
};
