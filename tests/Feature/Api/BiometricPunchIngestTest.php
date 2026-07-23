<?php

namespace Tests\Feature\Api;

use App\Events\BiometricPunchRecorded;
use App\Models\BiometricDevice;
use App\Models\ICTEquipment;
use App\Models\IctEquipmentDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BiometricPunchIngestTest extends TestCase
{
    use RefreshDatabase;

    private function makeBridgeDevice(string $deviceKey = 'guardhouse-gt200'): IctEquipmentDevice
    {
        $equipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'GUARDHOUSE-PC-' . str()->random(6),
            'status' => 'Good Working',
            'description' => 'Guardhouse PC running Atlas Sentinel',
        ]);

        $equipmentDevice = IctEquipmentDevice::create([
            'equipment_id' => $equipment->id,
            'hostname' => 'GUARDHOUSE-PC',
        ]);

        BiometricDevice::create([
            'ict_equipment_device_id' => $equipmentDevice->id,
            'device_key' => $deviceKey,
            'label' => 'Main Gate Guardhouse',
            'receiver_port' => 8090,
            'is_active' => true,
        ]);

        return $equipmentDevice;
    }

    public function test_a_device_without_an_active_bridge_registration_is_rejected(): void
    {
        $equipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'NON-BRIDGE-PC',
            'status' => 'Good Working',
            'description' => 'Random non-bridge PC',
        ]);
        $device = IctEquipmentDevice::create(['equipment_id' => $equipment->id, 'hostname' => 'RANDOM-PC']);

        Sanctum::actingAs($device, ['*']);

        $response = $this->postJson('/api/ict-agent/biometric-punches', [
            'raw_body' => "101\t2026-07-23 07:58:03\t1\t0",
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('biometric_logs', 0);
    }

    public function test_a_regular_user_token_is_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/ict-agent/biometric-punches', [
            'raw_body' => "101\t2026-07-23 07:58:03\t1\t0",
        ]);

        $response->assertForbidden();
    }

    public function test_a_valid_punch_is_ingested_triggers_dtr_generation_and_broadcasts(): void
    {
        Event::fake([BiometricPunchRecorded::class]);

        $employee = User::factory()->create(['badge_id' => '101']);
        $bridgeDevice = $this->makeBridgeDevice();
        Sanctum::actingAs($bridgeDevice, ['*']);

        $response = $this->postJson('/api/ict-agent/biometric-punches', [
            'raw_body' => "101\t2026-07-23 07:58:03\t1\t0",
        ]);

        $response->assertOk()->assertJson([
            'status' => 'ok',
            'inserted' => 1,
            'resolved' => 1,
        ]);

        $this->assertDatabaseHas('biometric_logs', [
            'device_employee_id' => '101',
            'user_id' => $employee->id,
            'device_id' => 'guardhouse-gt200',
            'source' => 'api',
        ]);

        $this->assertDatabaseHas('dtr_records', [
            'user_id' => $employee->id,
            'work_date' => '2026-07-23',
        ]);

        $this->assertDatabaseHas('biometric_devices', [
            'device_key' => 'guardhouse-gt200',
        ]);
        $bridge = BiometricDevice::where('device_key', 'guardhouse-gt200')->first();
        $this->assertNotNull($bridge->last_relay_at);

        Event::assertDispatched(BiometricPunchRecorded::class, function (BiometricPunchRecorded $event) use ($employee) {
            return $event->payload['device_employee_id'] === '101'
                && $event->payload['user_id'] === $employee->id
                && $event->payload['is_resolved'] === true;
        });
    }

    public function test_an_unresolved_badge_is_ingested_and_broadcast_without_dtr_generation(): void
    {
        Event::fake([BiometricPunchRecorded::class]);

        $bridgeDevice = $this->makeBridgeDevice();
        Sanctum::actingAs($bridgeDevice, ['*']);

        $response = $this->postJson('/api/ict-agent/biometric-punches', [
            'raw_body' => "999\t2026-07-23 07:58:03\t1\t0",
        ]);

        $response->assertOk()->assertJson(['inserted' => 1, 'unresolved' => 1]);

        $this->assertDatabaseCount('dtr_records', 0);

        Event::assertDispatched(BiometricPunchRecorded::class, function (BiometricPunchRecorded $event) {
            return $event->payload['is_resolved'] === false && $event->payload['user_id'] === null;
        });
    }

    public function test_duplicate_punches_are_not_reinserted_or_rebroadcast(): void
    {
        Event::fake([BiometricPunchRecorded::class]);

        $employee = User::factory()->create(['badge_id' => '101']);
        $bridgeDevice = $this->makeBridgeDevice();
        Sanctum::actingAs($bridgeDevice, ['*']);

        $this->postJson('/api/ict-agent/biometric-punches', ['raw_body' => "101\t2026-07-23 07:58:03\t1\t0"])
            ->assertOk();

        Event::fake([BiometricPunchRecorded::class]);

        $response = $this->postJson('/api/ict-agent/biometric-punches', ['raw_body' => "101\t2026-07-23 07:58:03\t1\t0"]);

        $response->assertOk()->assertJson(['inserted' => 0, 'duplicates' => 1]);
        $this->assertSame(1, \App\Models\HR\BiometricLog::count());
        Event::assertNotDispatched(BiometricPunchRecorded::class);
    }
}
