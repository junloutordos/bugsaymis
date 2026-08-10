<?php

namespace Tests\Feature;

use App\Models\ICTEquipment;
use App\Models\IctEquipmentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
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

    public function test_agent_can_report_security_incident_and_it_creates_incident_and_notifies(): void
    {
        $equipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'SEC-INCIDENT-PC-'.uniqid(),
            'status' => 'Good Working',
            'description' => 'Test device for security incident reporting',
        ]);
        $device = IctEquipmentDevice::create(['equipment_id' => $equipment->id, 'hostname' => 'SEC-INCIDENT-PC']);
        Sanctum::actingAs($device, ['*']);

        $manager = $this->userWithPermission('it.equipment.manage');

        $response = $this->postJson('/api/ict-agent/security-incident', [
            'reason' => 'network_anomaly',
            'detail' => ['half_open_count' => 240, 'process_name' => 'svchost.exe'],
            'triggered_at' => now()->toIso8601String(),
        ]);

        $response->assertOk()->assertJsonStructure(['status', 'incident_id']);
        $this->assertDatabaseHas('ict_equipment_containment_incidents', [
            'device_id' => $device->id,
            'reason' => 'network_anomaly',
        ]);
        $this->assertSame('contained', $device->fresh()->containment_status);
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $manager->id,
        ]);
    }

    public function test_checkin_response_includes_containment_config(): void
    {
        $device = IctEquipmentDevice::create(['hostname' => 'TEST-PC-'.uniqid()]);
        Sanctum::actingAs($device, ['*']);

        $response = $this->postJson('/api/ict-agent/checkin', []);

        $response->assertOk()->assertJsonStructure([
            'containment' => ['exempt', 'auto_contain_enabled', 'thresholds', 'server_host', 'confirmed'],
        ]);
        $this->assertFalse($response->json('containment.exempt'));
        $this->assertFalse($response->json('containment.auto_contain_enabled'));
    }

    public function test_manager_can_confirm_an_incident(): void
    {
        $manager = $this->userWithPermission('it.equipment.manage');
        $equipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'CONFIRM-PC-'.uniqid(),
            'status' => 'Good Working',
            'description' => 'Test device for confirm',
        ]);
        $device = IctEquipmentDevice::create(['equipment_id' => $equipment->id, 'hostname' => 'CONFIRM-PC']);
        $service = app(\App\Services\AtlasSentinelContainmentService::class);
        $incident = $service->recordIncident($device, 'network_anomaly', []);

        $this->actingAs($manager)
            ->postJson("/ict-equipments/{$equipment->id}/security-incidents/{$incident->id}/confirm")
            ->assertOk();

        $this->assertNotNull($incident->fresh()->confirmed_at);
    }

    public function test_manager_can_toggle_containment_exempt(): void
    {
        $manager = $this->userWithPermission('it.equipment.manage');
        $equipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'EXEMPT-PC-'.uniqid(),
            'status' => 'Good Working',
            'description' => 'Test device for exempt toggle',
        ]);
        $device = IctEquipmentDevice::create(['equipment_id' => $equipment->id, 'hostname' => 'EXEMPT-PC', 'containment_exempt' => false]);

        $this->actingAs($manager)
            ->patchJson("/ict-equipments/{$equipment->id}/security-exempt", ['exempt' => true])
            ->assertOk();

        $this->assertTrue($device->fresh()->containment_exempt);
    }

    public function test_remediate_endpoint_accepts_network_containment_action(): void
    {
        $manager = $this->userWithPermission('it.equipment.manage');
        $equipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'REMEDIATE-PC-'.uniqid(),
            'status' => 'Good Working',
            'description' => 'Test device for manual isolate',
        ]);
        $device = IctEquipmentDevice::create(['equipment_id' => $equipment->id, 'hostname' => 'REMEDIATE-PC']);

        $this->actingAs($manager)
            ->postJson("/ict-equipments/{$equipment->id}/remediate", ['action' => 'network_containment'])
            ->assertOk();

        $this->assertDatabaseHas('ict_equipment_manual_remediation_requests', [
            'device_id' => $device->id,
            'action' => 'network_containment',
        ]);
    }

    public function test_security_panel_endpoint_returns_status_and_incidents(): void
    {
        $manager = $this->userWithPermission('it.equipment.view');
        $equipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'PANEL-PC-'.uniqid(),
            'status' => 'Good Working',
            'description' => 'Test device for security panel',
        ]);
        $device = IctEquipmentDevice::create(['equipment_id' => $equipment->id, 'hostname' => 'PANEL-PC']);
        app(\App\Services\AtlasSentinelContainmentService::class)->recordIncident($device, 'av_signal', ['threat_name' => 'Trojan:Test']);

        $response = $this->actingAs($manager)->getJson("/ict-equipments/{$equipment->id}/security");

        $response->assertOk()->assertJsonStructure(['status', 'incident_id', 'exempt', 'incidents']);
        $this->assertSame('contained', $response->json('status'));
        $this->assertCount(1, $response->json('incidents'));
    }

    public function test_auto_release_command_releases_expired_unconfirmed_incidents(): void
    {
        $device = IctEquipmentDevice::create(['hostname' => 'TEST-PC-'.uniqid()]);
        $service = app(\App\Services\AtlasSentinelContainmentService::class);
        $incident = $service->recordIncident($device, 'network_anomaly', []);
        $incident->forceFill(['triggered_at' => now()->subMinutes(45)])->save();

        $this->artisan('atlas-sentinel:auto-release-containments')->assertExitCode(0);

        $this->assertSame('none', $device->fresh()->containment_status);
    }

    private function userWithPermission(string $permissionName): User
    {
        $permission = \App\Models\Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'ICT Equipment', 'description' => $permissionName],
        );
        $role = \App\Models\Role::create(['name' => 'ContainmentTester_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
