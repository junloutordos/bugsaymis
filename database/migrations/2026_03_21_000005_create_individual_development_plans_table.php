<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('individual_development_plans', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('supervisor_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Links to the TNA entry that generated this IDP item (optional)
            $table->foreignId('training_need_id')
                  ->nullable()
                  ->constrained('training_needs')
                  ->nullOnDelete();

            // Links to a learning program if already mapped
            $table->foreignId('learning_program_id')
                  ->nullable()
                  ->constrained('learning_programs')
                  ->nullOnDelete();

            $table->unsignedSmallInteger('year');            // IDP cycle year
            $table->string('competency');                    // Competency to develop
            $table->enum('current_level', [
                'none', 'basic', 'intermediate', 'advanced',
            ])->default('basic');
            $table->enum('target_level', [
                'basic', 'intermediate', 'advanced',
            ])->default('intermediate');

            $table->string('development_activity');          // e.g. "Attend Project Mgmt Seminar"
            $table->enum('intervention_type', [
                'training',         // Formal L&D program
                'coaching',         // 1-on-1 coaching/mentoring
                'assignment',       // Special assignment / OJT
                'self_study',       // Self-directed learning
                'e_learning',       // Online courses
                'other',
            ])->default('training');

            $table->date('timeline_start')->nullable();
            $table->date('timeline_end')->nullable();

            $table->enum('status', [
                'planned',
                'ongoing',
                'completed',
                'deferred',
                'cancelled',
            ])->default('planned');

            // Supervisor approval
            $table->enum('approval_status', [
                'draft',
                'submitted',
                'approved',
                'returned',
            ])->default('draft');

            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->text('supervisor_remarks')->nullable();
            $table->text('employee_remarks')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['employee_id', 'year']);
            $table->index(['supervisor_id', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('individual_development_plans');
    }
};
