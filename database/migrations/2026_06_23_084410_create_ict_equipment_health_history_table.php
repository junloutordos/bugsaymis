<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ict_equipment_health_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('ict_equipment_devices')->onDelete('cascade');
            $table->index(['device_id', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ict_equipment_health_history');
    }
};
