<?php

namespace Tests\Feature\Api;

use App\Events\BiometricPunchRecorded;
use App\Models\BiometricDevice;
use App\Models\HR\DtrRecord;
use App\Models\ICTEquipment;
use App\Models\IctEquipmentDevice;
use App\Models\User;
use App\Services\HR\DTRService;
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

    /**
     * Regression test for the DTRService::generate() bug where 'processed_by'
     * was unconditionally set to Auth::id(). When the actor authenticated via
     * Sanctum is an IctEquipmentDevice (not a User) — as is the case for this
     * device-triggered ingest endpoint — Auth::id() returns the device's own
     * numeric id, which is not necessarily a valid users.id. Deliberately
     * pick the employee's id far away from the device's id so the pre-fix
     * behavior is guaranteed to hit the dtr_records.processed_by FK
     * constraint rather than "getting lucky" because the two auto-increment
     * sequences happened to coincide (which is what made the bug
     * intermittent/order-dependent in the first place).
     */
    public function test_device_authenticated_ingestion_never_sets_dtr_processed_by_to_the_device_id(): void
    {
        Event::fake([BiometricPunchRecorded::class]);

        $bridgeDevice = $this->makeBridgeDevice();
        Sanctum::actingAs($bridgeDevice, ['*']);

        $employee = User::factory()->create([
            'id' => $bridgeDevice->id + 999000,
            'badge_id' => '101',
        ]);

        $response = $this->postJson('/api/ict-agent/biometric-punches', [
            'raw_body' => "101\t2026-07-23 07:58:03\t1\t0",
        ]);

        $response->assertOk()->assertJson(['status' => 'ok', 'inserted' => 1, 'resolved' => 1]);

        $record = DtrRecord::where('user_id', $employee->id)->where('work_date', '2026-07-23')->first();
        $this->assertNotNull($record, 'DTR record should have been generated without an FK violation.');
        $this->assertNull($record->processed_by, 'processed_by must be null when the actor is a device, not a User.');
    }

    /**
     * Companion non-regression check: normal User-authenticated DTR
     * generation must keep setting processed_by to the acting user's id —
     * the fix only nulls it out for non-User actors.
     */
    public function test_user_authenticated_dtr_generation_still_sets_processed_by(): void
    {
        $processor = User::factory()->create();
        $employee = User::factory()->create(['badge_id' => '202']);
        Sanctum::actingAs($processor, ['*']);

        \App\Models\HR\BiometricLog::create([
            'user_id' => $employee->id,
            'device_employee_id' => '202',
            'device_id' => 'guardhouse-gt200',
            'log_datetime' => '2026-07-23 07:58:03',
            'log_type' => 'time_in',
            'source' => 'api',
            'is_resolved' => true,
            'is_duplicate' => false,
        ]);

        app(DTRService::class)->generate($employee->id, '2026-07-23', '2026-07-23');

        $record = DtrRecord::where('user_id', $employee->id)->where('work_date', '2026-07-23')->first();
        $this->assertNotNull($record);
        $this->assertSame($processor->id, $record->processed_by);
    }
}
