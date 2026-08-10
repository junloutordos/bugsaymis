<?php

namespace Tests\Unit;

use App\Models\IctEquipmentContainmentIncident;
use App\Models\IctEquipmentDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AtlasSentinelContainmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_incident_belongs_to_device_and_casts_detail_to_array(): void
    {
        $device = IctEquipmentDevice::create(['hostname' => 'TEST-PC-'.uniqid()]);

        $incident = IctEquipmentContainmentIncident::create([
            'device_id' => $device->id,
            'reason' => 'network_anomaly',
            'detail' => ['half_open_count' => 150, 'process_name' => 'svchost'],
            'triggered_at' => now(),
        ]);

        $this->assertIsArray($incident->fresh()->detail);
        $this->assertEquals(150, $incident->fresh()->detail['half_open_count']);
        $this->assertTrue($device->fresh()->is($incident->device));
    }
}
