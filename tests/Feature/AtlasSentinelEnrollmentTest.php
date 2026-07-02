<?php

namespace Tests\Feature;

use App\Models\ICTEquipment;
use App\Models\IctEquipmentDevice;
use App\Models\IctEquipmentEnrollmentToken;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AtlasSentinelEnrollmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createTestSchema();
    }

    public function test_duplicate_serial_with_different_identity_creates_pending_setup_equipment(): void
    {
        $existingEquipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'GENERIC-SERIAL-001',
            'description' => 'Original desktop',
            'status' => 'Good Working',
        ]);

        IctEquipmentDevice::create([
            'equipment_id' => $existingEquipment->id,
            'hostname' => 'CRC-LAB-01',
            'mac_address' => '00:11:22:33:44:55',
            'last_checkin_at' => now(),
        ]);

        $response = $this->postJson('/api/ict-agent/enroll', [
            'enrollment_token' => $this->validEnrollmentToken(),
            'serial_no' => 'GENERIC-SERIAL-001',
            'hostname' => 'CRC-LAB-CLONE',
            'mac_address' => '00:11:22:33:44:66',
            'model' => 'Generic Desktop',
        ]);

        $response->assertCreated()
            ->assertJson([
                'linked' => false,
                'match_source' => 'clone_collision_pending_setup',
            ]);

        $this->assertDatabaseHas('ict_equipments', [
            'id' => $existingEquipment->id,
            'description' => 'Original desktop',
            'status' => 'Good Working',
        ]);

        $this->assertDatabaseHas('ict_equipments', [
            'serial_no' => 'GENERIC-SERIAL-001',
            'description' => 'Generic Desktop (MAC 00:11:22:33:44:66)',
            'status' => 'Pending Setup',
        ]);

        $this->assertDatabaseHas('ict_equipment_devices', [
            'hostname' => 'CRC-LAB-CLONE',
            'mac_address' => '00:11:22:33:44:66',
        ]);
    }

    public function test_duplicate_mac_with_different_hostname_creates_pending_setup_equipment(): void
    {
        $existingEquipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'REAL-SERIAL-001',
            'description' => 'Original desktop',
            'status' => 'Good Working',
        ]);

        IctEquipmentDevice::create([
            'equipment_id' => $existingEquipment->id,
            'hostname' => 'CRC-LAB-01',
            'mac_address' => '00:11:22:33:44:55',
            'last_checkin_at' => now(),
        ]);

        $response = $this->postJson('/api/ict-agent/enroll', [
            'enrollment_token' => $this->validEnrollmentToken(),
            'serial_no' => 'UNKNOWN-SERIAL',
            'hostname' => 'CRC-LAB-02',
            'mac_address' => '00:11:22:33:44:55',
            'model' => 'Cloned Desktop',
        ]);

        $response->assertCreated()
            ->assertJson([
                'linked' => false,
                'match_source' => 'clone_collision_pending_setup',
            ]);

        $this->assertDatabaseHas('ict_equipments', [
            'serial_no' => 'UNKNOWN-SERIAL',
            'description' => 'Cloned Desktop (MAC 00:11:22:33:44:55)',
            'status' => 'Pending Setup',
        ]);

        $this->assertDatabaseHas('ict_equipment_devices', [
            'hostname' => 'CRC-LAB-01',
            'mac_address' => '00:11:22:33:44:55',
        ]);

        $this->assertDatabaseHas('ict_equipment_devices', [
            'hostname' => 'CRC-LAB-02',
            'mac_address' => '00:11:22:33:44:55',
        ]);
    }

    public function test_same_machine_reenrollment_reuses_existing_equipment(): void
    {
        $existingEquipment = ICTEquipment::create([
            'category' => 'CPU/System Unit',
            'serial_no' => 'REAL-SERIAL-001',
            'description' => 'Original desktop',
            'status' => 'Good Working',
        ]);

        IctEquipmentDevice::create([
            'equipment_id' => $existingEquipment->id,
            'hostname' => 'CRC-LAB-01',
            'mac_address' => '00:11:22:33:44:55',
            'last_checkin_at' => now(),
        ]);

        $response = $this->postJson('/api/ict-agent/enroll', [
            'enrollment_token' => $this->validEnrollmentToken(),
            'serial_no' => 'REAL-SERIAL-001',
            'hostname' => 'CRC-LAB-01',
            'mac_address' => '00:11:22:33:44:55',
            'model' => 'Original Desktop',
        ]);

        $response->assertCreated()
            ->assertJson([
                'equipment_id' => $existingEquipment->id,
                'linked' => true,
                'match_source' => 'serial',
            ]);

        $this->assertSame(1, ICTEquipment::count());
        $this->assertSame(1, IctEquipmentDevice::count());
    }

    private function validEnrollmentToken(): string
    {
        $token = 'test-token-' . str()->random(16);

        IctEquipmentEnrollmentToken::create([
            'token' => $token,
            'created_by' => User::create([
                'name' => 'MIS Tester',
                'email' => 'mis-tester-' . str()->random(8) . '@crc.pshs.edu.ph',
                'password' => 'password',
            ])->id,
            'expires_at' => now()->addHour(),
            'max_uses' => 10,
            'uses_count' => 0,
        ]);

        return $token;
    }

    private function createTestSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->timestamps();
        });

        Schema::create('ict_equipments', function (Blueprint $table) {
            $table->id();
            $table->string('category')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('property_no')->nullable();
            $table->string('serial_no');
            $table->text('description')->nullable();
            $table->date('date_acquired')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('room_id')->nullable();
            $table->text('remarks')->nullable();
            $table->string('qr_code_path')->nullable();
            $table->date('warranty_expires_at')->nullable();
            $table->string('warranty_provider')->nullable();
            $table->date('decommissioned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ict_equipment_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('equipment_id')->nullable();
            $table->string('hostname')->nullable();
            $table->string('mac_address', 20)->nullable();
            $table->string('os_version')->nullable();
            $table->string('agent_version', 20)->nullable();
            $table->timestamp('last_checkin_at')->nullable();
            $table->timestamp('stale_notified_at')->nullable();
            $table->string('network_location')->nullable();
            $table->timestamp('network_location_changed_at')->nullable();
            $table->timestamp('last_update_attempted_at')->nullable();
            $table->string('last_update_result')->nullable();
            $table->text('last_update_details')->nullable();
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->unsignedTinyInteger('risk_score')->nullable();
            $table->string('risk_tier')->nullable();
            $table->timestamp('last_full_inventory_at')->nullable();
            $table->timestamp('last_diagnostics_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ict_equipment_enrollment_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->string('label', 100)->nullable();
            $table->unsignedBigInteger('created_by')->index();
            $table->timestamp('expires_at');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
}
