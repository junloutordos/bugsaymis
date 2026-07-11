<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_tasks', function (Blueprint $table) {
            $table->id();

            // May reference a sub-committee row (committees is self-referential)
            $table->foreignId('committee_id')->constrained('committees')->cascadeOnDelete();
            $table->foreignId('rating_period_id')->nullable()->constrained('ipcr_rating_periods')->nullOnDelete();
            $table->foreignId('work_distribution_plan_id')->nullable()->constrained('work_distribution_plans')->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('not_started'); // not_started|working_on_it|stuck|done
            $table->string('priority', 10)->default('medium');    // low|medium|high|urgent
            $table->date('due_date')->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['committee_id', 'rating_period_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_tasks');
    }
};
