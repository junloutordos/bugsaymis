<?php

namespace Tests\Feature;

use App\Models\IctEquipmentDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AtlasSentinelContainmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_ict_equipment_devices_has_containment_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('ict_equipment_devices', [
            'containment_exempt',
            'containment_status',
            'containment_incident_id',
        ]));
    }

    public function test_new_device_defaults_to_not_exempt_and_no_containment(): void
    {
        $device = IctEquipmentDevice::create(['hostname' => 'TEST-PC-'.uniqid()])->fresh();

        $this->assertFalse((bool) $device->containment_exempt);
        $this->assertSame('none', $device->containment_status);
        $this->assertNull($device->containment_incident_id);
    }
}
