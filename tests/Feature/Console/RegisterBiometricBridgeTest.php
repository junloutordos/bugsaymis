<?php

namespace Tests\Feature\Console;

use App\Models\BiometricDevice;
use App\Models\ICTEquipment;
use App\Models\IctEquipmentDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterBiometricBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_new_bridge_registration(): void
    {
        $equipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'GUARDHOUSE-PC-001',
            'status' => 'Good Working',
            'description' => 'Guardhouse PC running Atlas Sentinel',
        ]);

        $device = IctEquipmentDevice::create([
            'equipment_id' => $equipment->id,
            'hostname' => 'GUARDHOUSE-PC',
        ]);

        $this->artisan('biometric:register-bridge', [
            'ict_equipment_device_id' => $device->id,
            'device_key' => 'guardhouse-gt200',
            'label' => 'Main Gate Guardhouse',
            '--port' => 8090,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('biometric_devices', [
            'ict_equipment_device_id' => $device->id,
            'device_key' => 'guardhouse-gt200',
            'label' => 'Main Gate Guardhouse',
            'receiver_port' => 8090,
            'is_active' => 1,
        ]);
    }

    public function test_it_updates_an_existing_bridge_registration_instead_of_duplicating(): void
    {
        $equipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'GUARDHOUSE-PC-002',
            'status' => 'Good Working',
            'description' => 'Guardhouse PC running Atlas Sentinel',
        ]);

        $device = IctEquipmentDevice::create([
            'equipment_id' => $equipment->id,
            'hostname' => 'GUARDHOUSE-PC-2',
        ]);

        BiometricDevice::create([
            'ict_equipment_device_id' => $device->id,
            'device_key' => 'old-key',
            'label' => 'Old Label',
            'receiver_port' => 9000,
            'is_active' => true,
        ]);

        $this->artisan('biometric:register-bridge', [
            'ict_equipment_device_id' => $device->id,
            'device_key' => 'guardhouse-gt200-b',
            'label' => 'Back Gate Guardhouse',
            '--port' => 8091,
        ])->assertExitCode(0);

        $this->assertSame(1, BiometricDevice::where('ict_equipment_device_id', $device->id)->count());
        $this->assertDatabaseHas('biometric_devices', [
            'ict_equipment_device_id' => $device->id,
            'device_key' => 'guardhouse-gt200-b',
            'label' => 'Back Gate Guardhouse',
            'receiver_port' => 8091,
        ]);
    }

    public function test_it_fails_for_an_unknown_equipment_device(): void
    {
        $this->artisan('biometric:register-bridge', [
            'ict_equipment_device_id' => 999999,
            'device_key' => 'guardhouse-gt200',
            'label' => 'Main Gate Guardhouse',
        ])->assertExitCode(1);

        $this->assertDatabaseCount('biometric_devices', 0);
    }
}
