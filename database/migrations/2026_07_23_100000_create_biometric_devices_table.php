<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ict_equipment_device_id')
                ->unique()
                ->constrained('ict_equipment_devices')
                ->cascadeOnDelete();

            $table->string('device_key')->unique()
                ->comment('Matches biometric_logs.device_id for records relayed by this bridge');

            $table->string('label')
                ->comment('Human-readable name shown on the live feed, e.g. "Main Gate Guardhouse"');

            $table->unsignedSmallInteger('receiver_port')->nullable()
                ->comment('LAN port the Atlas Sentinel agent listens on for the device ADMS push');

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_relay_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};
