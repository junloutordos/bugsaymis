<?php

namespace Tests\Feature\Api;

use App\Models\BiometricDevice;
use App\Models\ICTEquipment;
use App\Models\IctEquipmentDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AtlasSentinelCheckinBiometricBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkin_omits_biometric_bridge_for_a_plain_device(): void
    {
        $equipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'PLAIN-PC',
            'status' => 'Good Working',
            'description' => 'Plain PC with no biometric bridge',
        ]);
        $device = IctEquipmentDevice::create(['equipment_id' => $equipment->id, 'hostname' => 'PLAIN-PC']);

        Sanctum::actingAs($device, ['*']);

        $response = $this->postJson('/api/ict-agent/checkin', []);

        $response->assertOk();
        $this->assertArrayNotHasKey('biometric_bridge', $response->json());
    }

    public function test_checkin_includes_biometric_bridge_for_a_registered_bridge_device(): void
    {
        $equipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'GUARDHOUSE-PC',
            'status' => 'Good Working',
            'description' => 'Guardhouse PC running Atlas Sentinel',
        ]);
        $device = IctEquipmentDevice::create(['equipment_id' => $equipment->id, 'hostname' => 'GUARDHOUSE-PC']);

        BiometricDevice::create([
            'ict_equipment_device_id' => $device->id,
            'device_key' => 'guardhouse-gt200',
            'label' => 'Main Gate Guardhouse',
            'receiver_port' => 8090,
            'is_active' => true,
        ]);

        Sanctum::actingAs($device, ['*']);

        $response = $this->postJson('/api/ict-agent/checkin', []);

        $response->assertOk()->assertJson([
            'biometric_bridge' => [
                'device_key' => 'guardhouse-gt200',
                'label' => 'Main Gate Guardhouse',
                'receiver_port' => 8090,
            ],
        ]);
    }

    public function test_checkin_omits_biometric_bridge_when_registration_is_inactive(): void
    {
        $equipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'RETIRED-PC',
            'status' => 'Good Working',
            'description' => 'Retired PC with an inactive biometric bridge registration',
        ]);
        $device = IctEquipmentDevice::create(['equipment_id' => $equipment->id, 'hostname' => 'RETIRED-PC']);

        BiometricDevice::create([
            'ict_equipment_device_id' => $device->id,
            'device_key' => 'old-bridge',
            'label' => 'Retired Bridge',
            'is_active' => false,
        ]);

        Sanctum::actingAs($device, ['*']);

        $response = $this->postJson('/api/ict-agent/checkin', []);

        $this->assertArrayNotHasKey('biometric_bridge', $response->json());
    }
}
