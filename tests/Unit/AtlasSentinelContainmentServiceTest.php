<?php

namespace Tests\Unit;

use App\Models\AtlasSentinelContainmentSetting;
use App\Models\IctEquipmentContainmentIncident;
use App\Models\IctEquipmentDevice;
use App\Models\User;
use App\Services\AtlasSentinelContainmentService;
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

    public function test_containment_setting_current_creates_singleton_with_safe_defaults(): void
    {
        $setting = AtlasSentinelContainmentSetting::current();

        $this->assertFalse($setting->auto_contain_enabled);
        $this->assertSame(30, $setting->auto_release_minutes);
        $this->assertSame(1, AtlasSentinelContainmentSetting::count());

        $again = AtlasSentinelContainmentSetting::current();
        $this->assertEquals($setting->id, $again->id);
    }

    public function test_record_incident_creates_row_marks_device_contained_and_writes_alert(): void
    {
        $device = IctEquipmentDevice::create(['hostname' => 'TEST-PC-'.uniqid()]);
        $service = app(AtlasSentinelContainmentService::class);

        $incident = $service->recordIncident($device, 'network_anomaly', ['half_open_count' => 200]);

        $device->refresh();
        $this->assertSame('contained', $device->containment_status);
        $this->assertEquals($incident->id, $device->containment_incident_id);
        $this->assertDatabaseHas('ict_equipment_alerts', [
            'device_id' => $device->id,
            'code' => 'network_anomaly',
        ]);
    }

    public function test_confirm_incident_stamps_confirmed_by_and_at(): void
    {
        $device = IctEquipmentDevice::create(['hostname' => 'TEST-PC-'.uniqid()]);
        $user = User::factory()->create();
        $service = app(AtlasSentinelContainmentService::class);
        $incident = $service->recordIncident($device, 'av_signal', []);

        $service->confirmIncident($incident, $user);

        $incident->refresh();
        $this->assertEquals($user->id, $incident->confirmed_by);
        $this->assertNotNull($incident->confirmed_at);
    }

    public function test_release_incident_clears_device_containment_state(): void
    {
        $device = IctEquipmentDevice::create(['hostname' => 'TEST-PC-'.uniqid()]);
        $service = app(AtlasSentinelContainmentService::class);
        $incident = $service->recordIncident($device, 'manual', []);

        $service->releaseIncident($incident, null);

        $device->refresh();
        $incident->refresh();
        $this->assertSame('none', $device->containment_status);
        $this->assertNull($device->containment_incident_id);
        $this->assertNotNull($incident->released_at);
        $this->assertNull($incident->released_by);
    }

    public function test_auto_release_expired_only_releases_unconfirmed_incidents_past_timeout(): void
    {
        $device = IctEquipmentDevice::create(['hostname' => 'TEST-PC-1-'.uniqid()]);
        $service = app(AtlasSentinelContainmentService::class);

        $expired = $service->recordIncident($device, 'network_anomaly', []);
        $expired->forceFill(['triggered_at' => now()->subMinutes(31)])->save();

        $device2 = IctEquipmentDevice::create(['hostname' => 'TEST-PC-2-'.uniqid()]);
        $confirmedButExpired = $service->recordIncident($device2, 'network_anomaly', []);
        $confirmedButExpired->forceFill(['triggered_at' => now()->subMinutes(31)])->save();
        $service->confirmIncident($confirmedButExpired, User::factory()->create());

        $released = $service->autoReleaseExpired();

        $this->assertSame(1, $released);
        $this->assertSame('none', $device->fresh()->containment_status);
        $this->assertSame('contained', $device2->fresh()->containment_status);
    }

    public function test_toggle_exempt_updates_device_flag(): void
    {
        $device = IctEquipmentDevice::create(['hostname' => 'TEST-PC-'.uniqid(), 'containment_exempt' => false]);
        $service = app(AtlasSentinelContainmentService::class);

        $service->toggleExempt($device, true);

        $this->assertTrue($device->fresh()->containment_exempt);
    }
}
