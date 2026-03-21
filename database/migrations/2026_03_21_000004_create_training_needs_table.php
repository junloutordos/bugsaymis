<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_needs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            // Supervisor who assessed the need
            $table->foreignId('assessed_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            // Competency gap identified
            $table->string('competency_area');           // e.g. "Planning and Delivering"
            $table->string('competency_gap');            // e.g. "Lacks project management skills"
            $table->enum('current_level', [
                'none',
                'basic',
                'intermediate',
                'advanced',
            ])->default('basic');
            $table->enum('target_level', [
                'basic',
                'intermediate',
                'advanced',
            ])->default('intermediate');

            $table->enum('priority_level', [
                'high',
                'medium',
                'low',
            ])->default('medium');

            $table->text('recommended_training')->nullable(); // Suggested L&D intervention
            $table->string('source')->default('self');       // self | supervisor | tna_form | ipcr

            $table->unsignedSmallInteger('year');            // TNA cycle year e.g. 2026

            $table->enum('status', [
                'pending',    // Submitted, not yet reviewed
                'approved',   // Endorsed for LDP
                'addressed',  // Training completed/intervention done
                'deferred',   // Moved to next cycle
            ])->default('pending');

            $table->foreignId('approved_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Index for TNA consolidation queries
            $table->index(['year', 'priority_level']);
            $table->index(['employee_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_needs');
    }
};
