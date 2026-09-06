<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_functions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('function_type', ['core', 'support']);
            $table->enum('source_type', ['load_assignment', 'wdp', 'manual']);
            $table->foreignId('load_assignment_id')->nullable()
                ->constrained('load_assignments')->nullOnDelete();
            $table->foreignId('work_distribution_plan_id')->nullable()
                ->constrained('work_distribution_plans')->nullOnDelete();
            $table->string('label', 255);
            $table->decimal('weight_percent', 5, 2)->nullable();
            $table->foreignId('academic_term_id')->nullable()
                ->constrained('academic_terms')->nullOnDelete();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'function_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_functions');
    }
};
