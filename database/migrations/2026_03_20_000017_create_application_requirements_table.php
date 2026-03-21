<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master list of document requirements
        Schema::create('application_requirements', function (Blueprint $table) {
            $table->id();
            $table->string('name');                              // e.g. "Application Letter"
            $table->string('description')->nullable();          // e.g. "Addressed to the Campus Director"
            $table->string('accepted_formats')->nullable();     // e.g. "PDF, DOC"
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Pivot: which requirements each job item requires
        Schema::create('job_item_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_item_id')
                  ->constrained('job_items')
                  ->cascadeOnDelete();
            $table->foreignId('requirement_id')
                  ->constrained('application_requirements')
                  ->cascadeOnDelete();
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();

            $table->unique(['job_item_id', 'requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_item_requirements');
        Schema::dropIfExists('application_requirements');
    }
};
