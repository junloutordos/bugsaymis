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
        Schema::create('ict_equipment_hardware_inventory', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id')->unique();
            $table->string('cpu_model')->nullable();
            $table->unsignedSmallInteger('cpu_cores')->nullable();
            $table->json('ram_modules')->nullable();
            $table->json('disks')->nullable();
            $table->json('gpu')->nullable();
            $table->json('peripherals')->nullable();
            $table->json('battery')->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('ict_equipment_devices')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ict_equipment_hardware_inventory');
    }
};
