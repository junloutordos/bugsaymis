<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_programs', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();

            // Training classification per CSC / PRIME-HRM
            $table->enum('type', [
                'mandatory',    // CSC-mandated (e.g. PSTE, Values Orientation)
                'technical',    // Job-specific technical skills
                'leadership',   // Leadership & management
                'functional',   // Cross-functional / generic
            ])->default('technical');

            $table->string('competency_area')->nullable();  // e.g. "Strategic Leadership"
            $table->string('target_position')->nullable();  // e.g. "Plantilla Teaching"

            $table->string('provider')->nullable();         // Training provider / organizer
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedSmallInteger('hours')->default(0); // Training hours (for L&D tracking)

            $table->decimal('budget', 12, 2)->default(0);

            $table->enum('status', [
                'planned',
                'ongoing',
                'completed',
                'cancelled',
            ])->default('planned');

            $table->unsignedBigInteger('created_by')->nullable();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_programs');
    }
};
