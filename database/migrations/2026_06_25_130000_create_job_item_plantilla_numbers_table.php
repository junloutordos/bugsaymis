<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('job_item_plantilla_numbers')) {
            return;
        }

        Schema::create('job_item_plantilla_numbers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_item_id')->constrained('job_items')->cascadeOnDelete();
            $table->string('plantilla_item_no', 50);
            $table->enum('status', ['vacant', 'filled'])->default('vacant');
            $table->foreignId('placement_id')->nullable()->unique()->constrained('placements')->nullOnDelete();
            $table->timestamps();

            $table->unique(['job_item_id', 'plantilla_item_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_item_plantilla_numbers');
    }
};
