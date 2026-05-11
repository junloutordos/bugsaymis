<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('network_devices', function (Blueprint $table) {
            $table->id();

            // Identity
            $table->string('mac_address', 17)->unique()->comment('Canonical format: aa:bb:cc:dd:ee:ff');
            $table->string('ip_address', 45)->nullable();
            $table->string('hostname', 255)->nullable();
            $table->string('vendor', 255)->nullable()->comment('From OUI lookup, e.g. Apple Inc., HP Enterprise');

            // Classification
            $table->enum('device_type', [
                'computer', 'phone', 'tablet', 'printer',
                'ap', 'switch', 'camera', 'iot', 'unknown',
            ])->default('unknown');
            $table->enum('connection_type', ['wired', 'wireless', 'unknown'])->default('unknown');

            // Network details
            $table->unsignedSmallInteger('vlan_id')->nullable();
            $table->string('vlan_name', 100)->nullable();

            // Wireless details
            $table->string('ap_name', 255)->nullable()->comment('Access point name if wireless');
            $table->string('ssid', 255)->nullable();
            $table->smallInteger('signal_dbm')->nullable()->comment('Signal strength in dBm');

            // Status
            $table->boolean('is_online')->default(false);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            // Admin
            $table->string('label', 255)->nullable()->comment('Admin-assigned friendly name');
            $table->text('notes')->nullable();

            // Source tracking
            $table->enum('data_source', ['snmp', 'ruijie_cloud', 'both'])->default('snmp');
            $table->string('ruijie_cloud_id', 100)->nullable()->comment('Device ID in Ruijie Cloud');
            $table->enum('last_source', ['snmp', 'ruijie_cloud'])->default('snmp');

            $table->timestamps();

            $table->index('ip_address');
            $table->index('is_online');
            $table->index('vlan_id');
            $table->index('last_seen_at');
            $table->index('connection_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_devices');
    }
};
