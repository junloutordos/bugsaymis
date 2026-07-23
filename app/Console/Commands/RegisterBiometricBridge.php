<?php

namespace App\Console\Commands;

use App\Models\BiometricDevice;
use App\Models\IctEquipmentDevice;
use Illuminate\Console\Command;

class RegisterBiometricBridge extends Command
{
    protected $signature = 'biometric:register-bridge
        {ict_equipment_device_id : ID from ict_equipment_devices for the guardhouse PC running Atlas Sentinel}
        {device_key : Identifier stored on biometric_logs.device_id, e.g. guardhouse-gt200}
        {label : Human-readable name shown on the live feed}
        {--port=8090 : LAN port the agent should listen on for the device ADMS push}';

    protected $description = 'Register (or update) an Atlas Sentinel device as a biometric bridge for a physical Granding terminal.';

    public function handle(): int
    {
        $equipmentDeviceId = (int) $this->argument('ict_equipment_device_id');

        $equipmentDevice = IctEquipmentDevice::find($equipmentDeviceId);
        if (! $equipmentDevice) {
            $this->error("No ict_equipment_devices row with id {$equipmentDeviceId}.");

            return self::FAILURE;
        }

        $bridge = BiometricDevice::updateOrCreate(
            ['ict_equipment_device_id' => $equipmentDeviceId],
            [
                'device_key' => $this->argument('device_key'),
                'label' => $this->argument('label'),
                'receiver_port' => (int) $this->option('port'),
                'is_active' => true,
            ]
        );

        $this->info("Registered biometric bridge #{$bridge->id} ({$bridge->label}) on equipment device #{$equipmentDeviceId}.");

        return self::SUCCESS;
    }
}
