<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_v2_core_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_v2_id')->constrained('ipcr_v2_records')->cascadeOnDelete();
            $table->foreignId('employee_function_id')->nullable()->constrained('employee_functions')->nullOnDelete();
            $table->string('label', 255);
            $table->decimal('weight_percent', 5, 2)->nullable();
            $table->text('target')->nullable();
            $table->text('actual_accomplishment')->nullable();
            $table->unsignedTinyInteger('student_feedback_rating')->nullable();
            $table->unsignedTinyInteger('supervisor_feedback_rating')->nullable();
            $table->unsignedTinyInteger('im_development_rating')->nullable();
            $table->unsignedTinyInteger('timeliness_rating')->nullable();
            $table->decimal('row_average', 4, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_v2_core_items');
    }
};
