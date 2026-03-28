<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_schedule_jobs', function (Blueprint $table) {
            $table->id();

            // Scope
            $table->unsignedBigInteger('school_year_id');
            $table->unsignedBigInteger('academic_term_id');

            // Lifecycle
            $table->enum('status', ['pending', 'running', 'completed', 'failed'])
                  ->default('pending');

            // GA configuration used for this run
            $table->json('parameters')->nullable();
            // e.g. { population_size: 30, mutation_rate: 0.05, max_generations: 100 }

            // Outcome summary
            $table->integer('fitness_score')->nullable();
            $table->integer('hard_conflicts')->default(0);
            $table->integer('schedules_generated')->default(0);

            // Full generated schedule (JSON array of proposed class_schedule rows)
            $table->longText('generated_schedules')->nullable();

            // Failure info
            $table->text('error_message')->nullable();

            // Timestamps for the run itself
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['academic_term_id', 'status']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_schedule_jobs');
    }
};
