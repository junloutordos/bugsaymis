<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ict_equipment_containment_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->string('reason', 40); // 'network_anomaly' | 'av_signal' | 'manual'
            $table->json('detail')->nullable();
            $table->timestamp('triggered_at');
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('released_by')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('ict_equipment_devices')->cascadeOnDelete();
            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('released_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['device_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ict_equipment_containment_incidents');
    }
};
