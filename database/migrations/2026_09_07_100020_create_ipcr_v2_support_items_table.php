<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ipcr_v2_support_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ipcr_v2_id')->constrained('ipcr_v2_records')->cascadeOnDelete();
            $table->foreignId('employee_function_id')->nullable()->constrained('employee_functions')->nullOnDelete();
            $table->string('label', 255);
            $table->text('actual_accomplishment')->nullable();
            $table->string('mov_link', 500)->nullable();
            $table->unsignedTinyInteger('quality_rating')->nullable();
            $table->unsignedTinyInteger('efficiency_rating')->nullable();
            $table->unsignedTinyInteger('timeliness_rating')->nullable();
            $table->decimal('row_average', 4, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ipcr_v2_support_items');
    }
};
