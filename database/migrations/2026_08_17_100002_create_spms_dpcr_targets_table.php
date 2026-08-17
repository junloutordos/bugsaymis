<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_dpcr_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dpcr_id')->constrained('spms_dpcrs')->cascadeOnDelete();
            $table->foreignId('spms_performance_indicator_id')->constrained('spms_performance_indicators')->cascadeOnDelete();
            $table->decimal('q1_actual', 5, 2)->nullable();
            $table->decimal('q2_actual', 5, 2)->nullable();
            $table->decimal('q3_actual', 5, 2)->nullable();
            $table->decimal('q4_actual', 5, 2)->nullable();
            $table->decimal('rating_q', 4, 2)->nullable();
            $table->decimal('rating_e', 4, 2)->nullable();
            $table->decimal('rating_t', 4, 2)->nullable();
            $table->decimal('rating_avg', 4, 2)->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_dpcr_targets');
    }
};
