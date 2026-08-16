<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spms_division_performance_indicator', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->foreignId('spms_performance_indicator_id');
            $table->foreign('spms_performance_indicator_id', 'spms_dpi_indicator_fk')
                ->references('id')->on('spms_performance_indicators')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spms_division_performance_indicator');
    }
};
