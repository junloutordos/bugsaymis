<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_performance_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('spms_outcome_id')->constrained('spms_outcomes')->cascadeOnDelete();
            $table->text('description');
            $table->text('target')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->unsignedSmallInteger('fiscal_year');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_performance_indicators');
    }
};
