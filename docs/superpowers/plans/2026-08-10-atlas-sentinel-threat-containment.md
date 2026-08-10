# Atlas Sentinel Threat Detection & Network Containment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Atlas Sentinel the ability to detect malware activity (via Windows Defender signals) and SYN-flood/scan-shaped network behavior (via local TCP connection-table heuristics) on enrolled devices, and — for high-confidence local detections — immediately isolate the device from the network with a firewall rule set that preserves its own management channel, while the server tracks, alerts on, confirms, and can release every incident.

**Architecture:** Detection and the containment decision happen entirely on the agent (server round-trips are too slow — checkin is a ~20 min cadence). The server is the audit/config/override layer: it pushes thresholds + exemption + kill-switch state down via the existing checkin response, receives an immediate out-of-band incident report the moment the agent acts, and lets IT confirm/manually-isolate/release. Manual isolate/release reuses the existing "Fix Now" manual-remediation delivery path (`IctEquipmentManualRemediationRequest` → `remediations` checkin field → `RemediationExecutor`); only the *decision to auto-contain* is new agent-local logic.

**Tech Stack:** Laravel 12 / PHP 8.4 (server), .NET 8 self-contained win-x64 Windows Service + WinForms Tray (agent, C#), Vue 3 `<script setup>` + Inertia.js (dashboard), xUnit (new — no test project exists yet in the agent repo), PHPUnit (existing, server).

## Global Constraints

- Migrations must be additive/backward-compatible with currently-deployed code (blue-green deploy — see project CLAUDE.md). No destructive column changes in this plan; none are needed.
- All new mutating web routes gated with `permission:it.equipment.manage`; read-only panel data may use `it.equipment.view` where noted.
- Agent DTOs use `System.Text.Json` with `[property: JsonPropertyName("snake_case")]` on every field (existing convention — STJ does not auto-convert casing in this codebase).
- Agent shells out to Windows tools (`netsh`) via `ProcessStartInfo`, matching the existing pattern used for `sc`/`netsh wlan`/DNS flush — not a new pattern.
- `RemediationExecutor.Execute()` is a **static** method dispatching on a switch statement (`Worker.cs:483`) — new remediation actions are added as new switch cases, not new classes registered via DI.
- No new Laravel permission — every new server-side action reuses `it.equipment.manage` / `it.equipment.view`, per the approved design.
- No file uploads in this feature — the base64/S3 upload rules don't apply here.
- Server timestamps: use `Carbon::parse($value)->format(...)` if ever coercing a date-cast Eloquent attribute — never `new DateTime()` (project-wide rule).

---

## File Structure

**Server (Laravel, `/Users/junlou/bugsaymis-docker/src/bugsaymis`):**
- `database/migrations/2026_08_10_100000_add_containment_fields_to_ict_equipment_devices.php` — new
- `database/migrations/2026_08_10_100100_create_ict_equipment_containment_incidents_table.php` — new
- `database/migrations/2026_08_10_100200_create_atlas_sentinel_containment_settings_table.php` — new
- `app/Models/IctEquipmentContainmentIncident.php` — new
- `app/Models/AtlasSentinelContainmentSetting.php` — new
- `app/Models/IctEquipmentDevice.php` — modify (relation + fillable)
- `app/Services/AtlasSentinelContainmentService.php` — new (core business logic)
- `app/Http/Controllers/Api/AtlasSentinelController.php` — modify (`checkin()` response, new `reportSecurityIncident()`)
- `app/Http/Controllers/ICTEquipmentController.php` — modify (`REMEDIATION_ACTIONS` const, new `security()`, `confirmSecurityIncident()`, `toggleContainmentExempt()`)
- `app/Http/Controllers/AtlasSentinelSecuritySettingsController.php` — new
- `app/Console/Commands/AtlasSentinelAutoReleaseContainments.php` — new
- `routes/api.php` — modify (new `security-incident` route)
- `routes/web.php` — modify (new security/exempt/settings routes)
- `routes/console.php` — modify (schedule the new command)
- `resources/js/Pages/ITJobRequests/SecurityPanel.vue` — new
- `resources/js/Pages/ITJobRequests/ICTEquipments.vue` — modify (mount panel)
- `tests/Feature/AtlasSentinelContainmentTest.php` — new
- `tests/Unit/AtlasSentinelContainmentServiceTest.php` — new

**Agent (`~/bugsaymis-ict-agent`):**
- `src/BugsaymisIctAgent.Tests/BugsaymisIctAgent.Tests.csproj` — new (scaffolded project)
- `src/BugsaymisIctAgent.Service/Security/ITcpConnectionTableReader.cs` — new
- `src/BugsaymisIctAgent.Service/Security/Win32TcpConnectionTableReader.cs` — new
- `src/BugsaymisIctAgent.Service/Security/NetworkAnomalyDetector.cs` — new
- `src/BugsaymisIctAgent.Service/Security/DefenderThreatMonitor.cs` — new
- `src/BugsaymisIctAgent.Service/Security/IFirewallCommandRunner.cs` — new
- `src/BugsaymisIctAgent.Service/Security/Win32FirewallCommandRunner.cs` — new
- `src/BugsaymisIctAgent.Service/Security/NetworkContainmentService.cs` — new
- `src/BugsaymisIctAgent.Service/Security/SecurityMonitor.cs` — new (orchestrator)
- `src/BugsaymisIctAgent.Service/IctAgentApiClient.cs` — modify (new method + DTO field)
- `src/BugsaymisIctAgent.Service/RemediationExecutor.cs` — modify (2 new switch cases)
- `src/BugsaymisIctAgent.Service/Worker.cs` — modify (wire up `SecurityMonitor`, handle containment instruction)
- `src/BugsaymisIctAgent.Tray/ContainmentWarningWatcher.cs` — new

---

## Task 1: Migration — containment fields on `ict_equipment_devices`

**Files:**
- Create: `database/migrations/2026_08_10_100000_add_containment_fields_to_ict_equipment_devices.php`
- Test: `tests/Feature/AtlasSentinelContainmentTest.php` (schema assertion added here, expanded in later tasks)

**Interfaces:**
- Produces: columns `containment_exempt` (bool, default false), `containment_status` (string, default `'none'`, values `none|contained`), `containment_incident_id` (nullable unsignedBigInteger) on `ict_equipment_devices`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/AtlasSentinelContainmentTest.php
namespace Tests\Feature;

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
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_ict_equipment_devices_has_containment_columns"`
Expected: FAIL — columns don't exist yet.

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_10_100000_add_containment_fields_to_ict_equipment_devices.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ict_equipment_devices', function (Blueprint $table) {
            $table->boolean('containment_exempt')->default(false)->after('risk_tier');
            $table->string('containment_status', 20)->default('none')->after('containment_exempt');
            $table->unsignedBigInteger('containment_incident_id')->nullable()->after('containment_status');
        });
    }

    public function down(): void
    {
        Schema::table('ict_equipment_devices', function (Blueprint $table) {
            $table->dropColumn(['containment_exempt', 'containment_status', 'containment_incident_id']);
        });
    }
};
```

- [ ] **Step 4: Run migration in dev**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_10_100000_add_containment_fields_to_ict_equipment_devices.php"`
Expected: migration applied without error.

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_ict_equipment_devices_has_containment_columns"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_10_100000_add_containment_fields_to_ict_equipment_devices.php tests/Feature/AtlasSentinelContainmentTest.php
git commit -m "feat(atlas-sentinel): add containment columns to ict_equipment_devices"
```

---

## Task 2: Migration + Model — `ict_equipment_containment_incidents`

**Files:**
- Create: `database/migrations/2026_08_10_100100_create_ict_equipment_containment_incidents_table.php`
- Create: `app/Models/IctEquipmentContainmentIncident.php`
- Modify: `app/Models/IctEquipmentDevice.php` (add `containmentIncident()` relation)
- Test: `tests/Unit/AtlasSentinelContainmentServiceTest.php` (new file)

**Interfaces:**
- Produces: `IctEquipmentContainmentIncident` model with fillable `device_id, reason, detail, triggered_at, confirmed_by, confirmed_at, released_at, released_by`; casts `detail` to `array`, `triggered_at`/`confirmed_at`/`released_at` to `datetime`.
- Produces: `IctEquipmentDevice::containmentIncident()` — `belongsTo(IctEquipmentContainmentIncident::class, 'containment_incident_id')`.

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/AtlasSentinelContainmentServiceTest.php
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
        $device = IctEquipmentDevice::factory()->create();

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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_incident_belongs_to_device_and_casts_detail_to_array"`
Expected: FAIL — table/model don't exist.

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_10_100100_create_ict_equipment_containment_incidents_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ict_equipment_containment_incidents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('device_id');
            $table->string('reason', 40); // 'network_anomaly' | 'av_signal' | 'manual'
            $table->json('detail')->nullable();
            $table->timestamp('triggered_at');
            $table->unsignedBigInteger('confirmed_by')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('released_by')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->foreign('device_id')->references('id')->on('ict_equipment_devices')->cascadeOnDelete();
            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('released_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['device_id', 'released_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ict_equipment_containment_incidents');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php
// app/Models/IctEquipmentContainmentIncident.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IctEquipmentContainmentIncident extends Model
{
    protected $fillable = [
        'device_id', 'reason', 'detail', 'triggered_at',
        'confirmed_by', 'confirmed_at', 'released_by', 'released_at',
    ];

    protected $casts = [
        'detail' => 'array',
        'triggered_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function device()
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'device_id');
    }

    public function isActive(): bool
    {
        return is_null($this->released_at);
    }
}
```

- [ ] **Step 5: Add the inverse relation to `IctEquipmentDevice`**

Open `app/Models/IctEquipmentDevice.php`, add near the other relations:

```php
public function containmentIncident()
{
    return $this->belongsTo(IctEquipmentContainmentIncident::class, 'containment_incident_id');
}
```

- [ ] **Step 6: Run migration and test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_10_100100_create_ict_equipment_containment_incidents_table.php && php artisan test --filter=test_incident_belongs_to_device_and_casts_detail_to_array"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_10_100100_create_ict_equipment_containment_incidents_table.php app/Models/IctEquipmentContainmentIncident.php app/Models/IctEquipmentDevice.php tests/Unit/AtlasSentinelContainmentServiceTest.php
git commit -m "feat(atlas-sentinel): add containment incident model and table"
```

---

## Task 3: Migration + Model — `atlas_sentinel_containment_settings` (global kill switch)

**Files:**
- Create: `database/migrations/2026_08_10_100200_create_atlas_sentinel_containment_settings_table.php`
- Create: `app/Models/AtlasSentinelContainmentSetting.php`
- Test: `tests/Unit/AtlasSentinelContainmentServiceTest.php` (add test)

**Interfaces:**
- Produces: `AtlasSentinelContainmentSetting::current(): AtlasSentinelContainmentSetting` — singleton row accessor (`firstOrCreate(['id' => 1], [...defaults])`), fields `auto_contain_enabled` (bool, default false), `auto_release_minutes` (int, default 30), `max_half_open_connections` (int, default 100), `max_distinct_ips_per_minute` (int, default 50).

- [ ] **Step 1: Write the failing test**

```php
// add to tests/Unit/AtlasSentinelContainmentServiceTest.php
use App\Models\AtlasSentinelContainmentSetting;

public function test_containment_setting_current_creates_singleton_with_safe_defaults(): void
{
    $setting = AtlasSentinelContainmentSetting::current();

    $this->assertFalse($setting->auto_contain_enabled);
    $this->assertSame(30, $setting->auto_release_minutes);
    $this->assertSame(1, AtlasSentinelContainmentSetting::count());

    $again = AtlasSentinelContainmentSetting::current();
    $this->assertEquals($setting->id, $again->id);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_containment_setting_current_creates_singleton_with_safe_defaults"`
Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Write the migration**

```php
<?php
// database/migrations/2026_08_10_100200_create_atlas_sentinel_containment_settings_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('atlas_sentinel_containment_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('auto_contain_enabled')->default(false);
            $table->unsignedSmallInteger('auto_release_minutes')->default(30);
            $table->unsignedInteger('max_half_open_connections')->default(100);
            $table->unsignedInteger('max_distinct_ips_per_minute')->default(50);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atlas_sentinel_containment_settings');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php
// app/Models/AtlasSentinelContainmentSetting.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AtlasSentinelContainmentSetting extends Model
{
    protected $fillable = [
        'auto_contain_enabled', 'auto_release_minutes',
        'max_half_open_connections', 'max_distinct_ips_per_minute',
    ];

    protected $casts = [
        'auto_contain_enabled' => 'boolean',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'auto_contain_enabled' => false,
            'auto_release_minutes' => 30,
            'max_half_open_connections' => 100,
            'max_distinct_ips_per_minute' => 50,
        ]);
    }
}
```

- [ ] **Step 5: Run migration and test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_10_100200_create_atlas_sentinel_containment_settings_table.php && php artisan test --filter=test_containment_setting_current_creates_singleton_with_safe_defaults"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_10_100200_create_atlas_sentinel_containment_settings_table.php app/Models/AtlasSentinelContainmentSetting.php tests/Unit/AtlasSentinelContainmentServiceTest.php
git commit -m "feat(atlas-sentinel): add global auto-contain kill switch settings"
```

---

## Task 4: `AtlasSentinelContainmentService` — core business logic

**Files:**
- Create: `app/Services/AtlasSentinelContainmentService.php`
- Test: `tests/Unit/AtlasSentinelContainmentServiceTest.php` (add tests)

**Interfaces:**
- Consumes: `IctEquipmentDevice`, `IctEquipmentContainmentIncident`, `AtlasSentinelContainmentSetting::current()`.
- Produces (used by Tasks 5, 7, 8):
  - `recordIncident(IctEquipmentDevice $device, string $reason, array $detail): IctEquipmentContainmentIncident`
  - `confirmIncident(IctEquipmentContainmentIncident $incident, User $confirmedBy): void`
  - `releaseIncident(IctEquipmentContainmentIncident $incident, ?User $releasedBy): void`
  - `autoReleaseExpired(): int` (returns count released)
  - `toggleExempt(IctEquipmentDevice $device, bool $exempt): void`

- [ ] **Step 1: Write the failing tests**

```php
// add to tests/Unit/AtlasSentinelContainmentServiceTest.php
use App\Services\AtlasSentinelContainmentService;
use App\Models\User;
use App\Models\IctEquipmentAlert;
use Illuminate\Support\Carbon;

public function test_record_incident_creates_row_marks_device_contained_and_writes_alert(): void
{
    $device = IctEquipmentDevice::factory()->create(['containment_status' => 'none']);
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
    $device = IctEquipmentDevice::factory()->create();
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
    $device = IctEquipmentDevice::factory()->create();
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
    $device = IctEquipmentDevice::factory()->create();
    $service = app(AtlasSentinelContainmentService::class);

    $expired = $service->recordIncident($device, 'network_anomaly', []);
    $expired->forceFill(['triggered_at' => now()->subMinutes(31)])->save();

    $device2 = IctEquipmentDevice::factory()->create();
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
    $device = IctEquipmentDevice::factory()->create(['containment_exempt' => false]);
    $service = app(AtlasSentinelContainmentService::class);

    $service->toggleExempt($device, true);

    $this->assertTrue($device->fresh()->containment_exempt);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=AtlasSentinelContainmentServiceTest"`
Expected: FAIL — service class doesn't exist.

- [ ] **Step 3: Write the service**

```php
<?php
// app/Services/AtlasSentinelContainmentService.php
namespace App\Services;

use App\Models\AtlasSentinelContainmentSetting;
use App\Models\IctEquipmentAlert;
use App\Models\IctEquipmentContainmentIncident;
use App\Models\IctEquipmentDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AtlasSentinelContainmentService
{
    public function recordIncident(IctEquipmentDevice $device, string $reason, array $detail): IctEquipmentContainmentIncident
    {
        return DB::transaction(function () use ($device, $reason, $detail) {
            $incident = IctEquipmentContainmentIncident::create([
                'device_id' => $device->id,
                'reason' => $reason,
                'detail' => $detail,
                'triggered_at' => now(),
            ]);

            $device->update([
                'containment_status' => 'contained',
                'containment_incident_id' => $incident->id,
            ]);

            IctEquipmentAlert::create([
                'device_id' => $device->id,
                'equipment_id' => $device->equipment_id,
                'code' => $reason === 'av_signal' ? 'malware_detected' : ($reason === 'network_anomaly' ? 'network_anomaly' : 'device_contained'),
                'issue' => 'Device automatically contained: ' . $reason,
                'severity' => 'critical',
                'status' => 'open',
                'probable_cause' => json_encode($detail),
            ]);

            return $incident;
        });
    }

    public function confirmIncident(IctEquipmentContainmentIncident $incident, User $confirmedBy): void
    {
        $incident->update([
            'confirmed_by' => $confirmedBy->id,
            'confirmed_at' => now(),
        ]);
    }

    public function releaseIncident(IctEquipmentContainmentIncident $incident, ?User $releasedBy): void
    {
        DB::transaction(function () use ($incident, $releasedBy) {
            $incident->update([
                'released_at' => now(),
                'released_by' => $releasedBy?->id,
            ]);

            $incident->device()->update([
                'containment_status' => 'none',
                'containment_incident_id' => null,
            ]);

            IctEquipmentAlert::create([
                'device_id' => $incident->device_id,
                'equipment_id' => $incident->device?->equipment_id,
                'code' => 'device_released',
                'issue' => 'Device released from containment',
                'severity' => 'info',
                'status' => 'resolved',
            ]);
        });
    }

    public function autoReleaseExpired(): int
    {
        $minutes = AtlasSentinelContainmentSetting::current()->auto_release_minutes;
        $cutoff = now()->subMinutes($minutes);

        $expired = IctEquipmentContainmentIncident::whereNull('released_at')
            ->whereNull('confirmed_at')
            ->where('triggered_at', '<=', $cutoff)
            ->get();

        foreach ($expired as $incident) {
            $this->releaseIncident($incident, null);
        }

        return $expired->count();
    }

    public function toggleExempt(IctEquipmentDevice $device, bool $exempt): void
    {
        $device->update(['containment_exempt' => $exempt]);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=AtlasSentinelContainmentServiceTest"`
Expected: PASS (all 5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/AtlasSentinelContainmentService.php tests/Unit/AtlasSentinelContainmentServiceTest.php
git commit -m "feat(atlas-sentinel): add containment service with incident lifecycle"
```

---

## Task 5: Endpoint — `POST /api/ict-agent/security-incident`

**Files:**
- Modify: `app/Http/Controllers/Api/AtlasSentinelController.php` (add `reportSecurityIncident()`)
- Modify: `routes/api.php`
- Test: `tests/Feature/AtlasSentinelContainmentTest.php` (add test)

**Interfaces:**
- Consumes: `AtlasSentinelContainmentService::recordIncident()` (Task 4), `NotificationService::notifyUser(User $user, string $requestType, string $referenceNo, string $newStatus, string $url, ?string $remarks = null): void` (existing, `app/Services/NotificationService.php:18-25`).
- Produces: `POST /api/ict-agent/security-incident` — request `{reason: 'network_anomaly'|'av_signal', detail: object, triggered_at: ISO8601 string}`, response `{status: 'ok', incident_id: int}`.

- [ ] **Step 1: Write the failing test**

```php
// add to tests/Feature/AtlasSentinelContainmentTest.php
use App\Models\IctEquipmentDevice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

public function test_agent_can_report_security_incident_and_it_creates_incident_and_notifies(): void
{
    $device = IctEquipmentDevice::factory()->create();
    Sanctum::actingAs($device, ['ict-agent']);

    $manager = User::factory()->create();
    $manager->givePermissionTo('it.equipment.manage');

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
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_agent_can_report_security_incident_and_it_creates_incident_and_notifies"`
Expected: FAIL — 404, route doesn't exist.

- [ ] **Step 3: Add the route**

In `routes/api.php`, alongside the existing `ict-agent` middleware group that has `checkin`/`inventory-checkin`:

```php
Route::post('/ict-agent/security-incident', [\App\Http\Controllers\Api\AtlasSentinelController::class, 'reportSecurityIncident']);
```

(Read the existing group wrapper first — `checkin()` and `inventoryCheckin()` are already registered under `auth:sanctum` + `ict-agent` ability middleware; place the new route inside the same group so it inherits identical auth.)

- [ ] **Step 4: Write the controller method**

In `app/Http/Controllers/Api/AtlasSentinelController.php`, add:

```php
public function reportSecurityIncident(Request $request)
{
    $validated = $request->validate([
        'reason' => 'required|string|in:network_anomaly,av_signal',
        'detail' => 'nullable|array',
        'triggered_at' => 'required|date',
    ]);

    /** @var \App\Models\IctEquipmentDevice $device */
    $device = $request->user();

    $service = app(\App\Services\AtlasSentinelContainmentService::class);
    $incident = $service->recordIncident($device, $validated['reason'], $validated['detail'] ?? []);

    $url = route('ict-equipments.index'); // existing named route for the equipment index page
    \App\Models\User::permission('it.equipment.manage')->get()->each(function ($user) use ($device, $incident, $url) {
        \App\Services\NotificationService::notifyUser(
            $user,
            'Atlas Sentinel Security',
            (string) $incident->id,
            'Device Contained',
            $url,
            "{$device->hostname} was automatically isolated ({$incident->reason})."
        );
    });

    return response()->json(['status' => 'ok', 'incident_id' => $incident->id]);
}
```

Note: `User::permission(...)` assumes a local scope exists on the `User` model for querying by permission (matches the `hasPermission()`/`hasAnyPermission()` RBAC helpers already documented in project conventions) — if no such query scope exists yet, replace with the equivalent pattern already used by `AtlasSentinelNotifyStaleDevices.php` (which already resolves "everyone with `it.equipment.manage`" for its bell notification) — read that command first and copy its exact user-resolution line.

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_agent_can_report_security_incident_and_it_creates_incident_and_notifies"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/AtlasSentinelController.php routes/api.php tests/Feature/AtlasSentinelContainmentTest.php
git commit -m "feat(atlas-sentinel): add security-incident report endpoint"
```

---

## Task 6: Extend `checkin()` response with containment config

**Files:**
- Modify: `app/Http/Controllers/Api/AtlasSentinelController.php` (`checkin()` method, response block ~line 360-407)
- Test: `tests/Feature/AtlasSentinelContainmentTest.php` (add test)

**Interfaces:**
- Produces: checkin JSON response gains a `containment` key:
  ```json
  {
    "containment": {
      "exempt": false,
      "auto_contain_enabled": false,
      "thresholds": {"max_half_open_connections": 100, "max_distinct_ips_per_minute": 50},
      "server_host": "mis.crc.pshs.edu.ph",
      "confirmed": false
    }
  }
  ```

- [ ] **Step 1: Write the failing test**

```php
// add to tests/Feature/AtlasSentinelContainmentTest.php
public function test_checkin_response_includes_containment_config(): void
{
    $device = IctEquipmentDevice::factory()->create();
    Sanctum::actingAs($device, ['ict-agent']);

    $response = $this->postJson('/api/ict-agent/checkin', [
        'cpu' => 10, 'ram_total_mb' => 8000, 'ram_free_mb' => 4000,
        'uptime_seconds' => 100, 'os_version' => 'Windows 11', 'agent_version' => '1.2.0',
    ]);

    $response->assertOk()->assertJsonStructure([
        'containment' => ['exempt', 'auto_contain_enabled', 'thresholds', 'server_host', 'confirmed'],
    ]);
    $this->assertFalse($response->json('containment.exempt'));
    $this->assertFalse($response->json('containment.auto_contain_enabled'));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_checkin_response_includes_containment_config"`
Expected: FAIL — this test may also need adjusting once the exact required fields of `checkin()`'s validation are confirmed by reading the controller (line 271-313 per prior research) — read the method first, this test payload uses only the fields already known to be required.

- [ ] **Step 3: Add the containment block to the response**

Open `app/Http/Controllers/Api/AtlasSentinelController.php`, find the response-building section of `checkin()` (around where `remediations`/`update` keys are assembled, ~line 360-407), and add:

```php
$settings = \App\Models\AtlasSentinelContainmentSetting::current();
$confirmed = false;
if ($device->containment_status === 'contained' && $device->containmentIncident) {
    $confirmed = (bool) $device->containmentIncident->confirmed_at;
}

$responsePayload['containment'] = [
    'exempt' => (bool) $device->containment_exempt,
    'auto_contain_enabled' => (bool) $settings->auto_contain_enabled,
    'thresholds' => [
        'max_half_open_connections' => $settings->max_half_open_connections,
        'max_distinct_ips_per_minute' => $settings->max_distinct_ips_per_minute,
    ],
    'server_host' => parse_url(config('app.url'), PHP_URL_HOST),
    'confirmed' => $confirmed,
];
```

(Read the method first to find the exact variable name holding the assembled response array — it may not literally be `$responsePayload`; match whatever variable is actually `return`ed/`response()->json(...)`'d at the end of `checkin()`.)

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_checkin_response_includes_containment_config"`
Expected: PASS

- [ ] **Step 5: Run full existing Atlas Sentinel test suite to check for regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=AtlasSentinel"`
Expected: PASS, no regressions in existing checkin tests.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/AtlasSentinelController.php tests/Feature/AtlasSentinelContainmentTest.php
git commit -m "feat(atlas-sentinel): include containment config in checkin response"
```

---

## Task 7: Web routes/controller — manual isolate/release, confirm, exempt toggle

**Files:**
- Modify: `app/Http/Controllers/ICTEquipmentController.php` (`REMEDIATION_ACTIONS` const, new methods)
- Modify: `routes/web.php`
- Modify (agent-adjacent, server-only in this task): none — the agent side of manual isolate/release is Task 15 (`RemediationExecutor` cases)
- Test: `tests/Feature/AtlasSentinelContainmentTest.php` (add tests)

**Interfaces:**
- Consumes: `AtlasSentinelContainmentService` (Task 4).
- Produces:
  - `POST /ict-equipments/{ictEquipment}/security-incidents/{incident}/confirm` — confirms an incident.
  - `PATCH /ict-equipments/{ictEquipment}/security-exempt` — body `{exempt: bool}`.
  - `GET /ict-equipments/{ictEquipment}/security` — JSON `{status, incident_id, incidents: [...]}` for the Vue panel (Task 9).
  - Existing `POST /ict-equipments/{ictEquipment}/remediate` gains two accepted `action` values: `network_containment`, `network_release` (delivered to the agent via the existing manual-remediation channel — no new delivery mechanism).

- [ ] **Step 1: Write the failing tests**

```php
// add to tests/Feature/AtlasSentinelContainmentTest.php
use App\Models\ICTEquipment;

public function test_manager_can_confirm_an_incident(): void
{
    $manager = User::factory()->create();
    $manager->givePermissionTo('it.equipment.manage');
    $device = IctEquipmentDevice::factory()->create();
    $service = app(\App\Services\AtlasSentinelContainmentService::class);
    $incident = $service->recordIncident($device, 'network_anomaly', []);
    $equipment = ICTEquipment::factory()->create(['id' => $device->equipment_id]);

    $this->actingAs($manager)
        ->post("/ict-equipments/{$equipment->id}/security-incidents/{$incident->id}/confirm")
        ->assertRedirect();

    $this->assertNotNull($incident->fresh()->confirmed_at);
}

public function test_manager_can_toggle_containment_exempt(): void
{
    $manager = User::factory()->create();
    $manager->givePermissionTo('it.equipment.manage');
    $device = IctEquipmentDevice::factory()->create(['containment_exempt' => false]);
    $equipment = ICTEquipment::factory()->create(['id' => $device->equipment_id]);

    $this->actingAs($manager)
        ->patch("/ict-equipments/{$equipment->id}/security-exempt", ['exempt' => true])
        ->assertRedirect();

    $this->assertTrue($device->fresh()->containment_exempt);
}

public function test_remediate_endpoint_accepts_network_containment_action(): void
{
    $manager = User::factory()->create();
    $manager->givePermissionTo('it.equipment.manage');
    $device = IctEquipmentDevice::factory()->create();
    $equipment = ICTEquipment::factory()->create(['id' => $device->equipment_id]);

    $this->actingAs($manager)
        ->post("/ict-equipments/{$equipment->id}/remediate", ['action' => 'network_containment'])
        ->assertRedirect();

    $this->assertDatabaseHas('ict_equipment_manual_remediation_requests', [
        'action' => 'network_containment',
    ]);
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=AtlasSentinelContainmentTest"`
Expected: FAIL — routes/actions don't exist yet.

- [ ] **Step 3: Extend `REMEDIATION_ACTIONS`**

Open `app/Http/Controllers/ICTEquipmentController.php`, find the `REMEDIATION_ACTIONS` const array (~line 466-473), add `'network_containment'` and `'network_release'` to it.

- [ ] **Step 4: Add the new controller methods**

```php
// app/Http/Controllers/ICTEquipmentController.php — add methods
public function security(\App\Models\ICTEquipment $ictEquipment)
{
    $device = \App\Models\IctEquipmentDevice::where('equipment_id', $ictEquipment->id)->firstOrFail();
    $incidents = \App\Models\IctEquipmentContainmentIncident::where('device_id', $device->id)
        ->latest('triggered_at')->limit(20)->get();

    return response()->json([
        'status' => $device->containment_status,
        'incident_id' => $device->containment_incident_id,
        'exempt' => (bool) $device->containment_exempt,
        'incidents' => $incidents,
    ]);
}

public function confirmSecurityIncident(
    \App\Models\ICTEquipment $ictEquipment,
    \App\Models\IctEquipmentContainmentIncident $incident,
    \App\Services\AtlasSentinelContainmentService $service,
) {
    $service->confirmIncident($incident, auth()->user());

    return back()->with('success', 'Incident confirmed.');
}

public function toggleContainmentExempt(
    \Illuminate\Http\Request $request,
    \App\Models\ICTEquipment $ictEquipment,
    \App\Services\AtlasSentinelContainmentService $service,
) {
    $validated = $request->validate(['exempt' => 'required|boolean']);
    $device = \App\Models\IctEquipmentDevice::where('equipment_id', $ictEquipment->id)->firstOrFail();
    $service->toggleExempt($device, $validated['exempt']);

    return back()->with('success', 'Containment exemption updated.');
}
```

- [ ] **Step 5: Add the routes**

In `routes/web.php`, right after the existing `remediate` route (~line 1009-1011):

```php
Route::get('/ict-equipments/{ictEquipment}/security', [ICTEquipmentController::class, 'security'])
    ->middleware('permission:it.equipment.view')
    ->name('ict-equipments.security');

Route::post('/ict-equipments/{ictEquipment}/security-incidents/{incident}/confirm', [ICTEquipmentController::class, 'confirmSecurityIncident'])
    ->middleware('permission:it.equipment.manage')
    ->name('ict-equipments.security-incidents.confirm');

Route::patch('/ict-equipments/{ictEquipment}/security-exempt', [ICTEquipmentController::class, 'toggleContainmentExempt'])
    ->middleware('permission:it.equipment.manage')
    ->name('ict-equipments.security-exempt');
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=AtlasSentinelContainmentTest"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ICTEquipmentController.php routes/web.php tests/Feature/AtlasSentinelContainmentTest.php
git commit -m "feat(atlas-sentinel): add manual isolate/release, confirm, and exempt-toggle routes"
```

---

## Task 8: Scheduled command — auto-release expired containments

**Files:**
- Create: `app/Console/Commands/AtlasSentinelAutoReleaseContainments.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/AtlasSentinelContainmentTest.php` (add test)

**Interfaces:**
- Consumes: `AtlasSentinelContainmentService::autoReleaseExpired()` (Task 4).
- Produces: `php artisan atlas-sentinel:auto-release-containments`, scheduled every 5 minutes.

- [ ] **Step 1: Write the failing test**

```php
// add to tests/Feature/AtlasSentinelContainmentTest.php
use App\Models\IctEquipmentContainmentIncident;

public function test_auto_release_command_releases_expired_unconfirmed_incidents(): void
{
    $device = IctEquipmentDevice::factory()->create();
    $service = app(\App\Services\AtlasSentinelContainmentService::class);
    $incident = $service->recordIncident($device, 'network_anomaly', []);
    $incident->forceFill(['triggered_at' => now()->subMinutes(45)])->save();

    $this->artisan('atlas-sentinel:auto-release-containments')->assertExitCode(0);

    $this->assertSame('none', $device->fresh()->containment_status);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_auto_release_command_releases_expired_unconfirmed_incidents"`
Expected: FAIL — command not found.

- [ ] **Step 3: Write the command**

```php
<?php
// app/Console/Commands/AtlasSentinelAutoReleaseContainments.php
namespace App\Console\Commands;

use App\Services\AtlasSentinelContainmentService;
use Illuminate\Console\Command;

class AtlasSentinelAutoReleaseContainments extends Command
{
    protected $signature = 'atlas-sentinel:auto-release-containments';
    protected $description = 'Auto-release contained devices whose incident timed out without confirmation';

    public function handle(AtlasSentinelContainmentService $service): int
    {
        $count = $service->autoReleaseExpired();
        $this->info("Auto-released {$count} expired containment incident(s).");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Register the schedule**

In `routes/console.php`, near the existing `atlas-sentinel:notify-stale-devices` schedule entry:

```php
Schedule::command('atlas-sentinel:auto-release-containments')->everyFiveMinutes();
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_auto_release_command_releases_expired_unconfirmed_incidents"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/AtlasSentinelAutoReleaseContainments.php routes/console.php tests/Feature/AtlasSentinelContainmentTest.php
git commit -m "feat(atlas-sentinel): add scheduled auto-release command for expired containments"
```

---

## Task 9: Vue — Security panel

**Files:**
- Create: `resources/js/Pages/ITJobRequests/SecurityPanel.vue`
- Modify: `resources/js/Pages/ITJobRequests/ICTEquipments.vue`

**Interfaces:**
- Consumes: `GET /ict-equipments/{id}/security`, `POST /ict-equipments/{id}/security-incidents/{incident}/confirm`, `PATCH /ict-equipments/{id}/security-exempt`, existing `POST /ict-equipments/{id}/remediate` with `action=network_containment|network_release`.
- Produces: `<SecurityPanel :equipment-id="..." />` component, mountable from `ICTEquipments.vue`.

- [ ] **Step 1: Write the component**

```vue
<!-- resources/js/Pages/ITJobRequests/SecurityPanel.vue -->
<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'
import Swal from 'sweetalert2'
import { ShieldExclamationIcon, ShieldCheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ equipmentId: { type: Number, required: true } })

const loading = ref(true)
const status = ref('none')
const exempt = ref(false)
const incidents = ref([])

async function load() {
  loading.value = true
  const { data } = await axios.get(`/ict-equipments/${props.equipmentId}/security`)
  status.value = data.status
  exempt.value = data.exempt
  incidents.value = data.incidents
  loading.value = false
}

async function isolateNow() {
  const confirm = await Swal.fire({
    title: 'Isolate this device?',
    text: 'It will be blocked from the network except for reporting back to this server.',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Isolate',
  })
  if (!confirm.isConfirmed) return
  await axios.post(`/ict-equipments/${props.equipmentId}/remediate`, { action: 'network_containment' })
  await load()
}

async function releaseNow() {
  await axios.post(`/ict-equipments/${props.equipmentId}/remediate`, { action: 'network_release' })
  await load()
}

async function confirmIncident(incidentId) {
  await axios.post(`/ict-equipments/${props.equipmentId}/security-incidents/${incidentId}/confirm`)
  await load()
}

async function toggleExempt() {
  await axios.patch(`/ict-equipments/${props.equipmentId}/security-exempt`, { exempt: !exempt.value })
  await load()
}

onMounted(load)
</script>

<template>
  <div class="rounded-lg border border-slate-200 bg-white p-4">
    <div class="flex items-center justify-between">
      <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Security</h3>
      <span
        class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium"
        :class="status === 'contained' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'"
      >
        <ShieldExclamationIcon v-if="status === 'contained'" class="h-4 w-4" />
        <ShieldCheckIcon v-else class="h-4 w-4" />
        {{ status === 'contained' ? 'Contained' : 'Healthy' }}
      </span>
    </div>

    <div class="mt-3 flex gap-2">
      <button
        v-if="status !== 'contained'"
        @click="isolateNow"
        class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium"
      >
        Isolate Now
      </button>
      <button
        v-else
        @click="releaseNow"
        class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-sm font-medium"
      >
        Release
      </button>
      <button
        @click="toggleExempt"
        class="border border-slate-200 px-3 py-1.5 rounded-lg text-sm font-medium"
      >
        {{ exempt ? 'Remove Exemption' : 'Exempt from Auto-Containment' }}
      </button>
    </div>

    <div v-if="!loading" class="mt-4 space-y-2">
      <div v-for="incident in incidents" :key="incident.id" class="text-sm border-t border-slate-100 pt-2">
        <div class="flex items-center justify-between">
          <span>{{ incident.reason }} — {{ new Date(incident.triggered_at).toLocaleString('en-PH') }}</span>
          <button
            v-if="!incident.confirmed_at && !incident.released_at"
            @click="confirmIncident(incident.id)"
            class="text-indigo-600 text-xs font-medium"
          >
            Confirm
          </button>
        </div>
      </div>
      <p v-if="incidents.length === 0" class="text-sm text-slate-400">No incidents recorded.</p>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Mount it in `ICTEquipments.vue`**

Open `resources/js/Pages/ITJobRequests/ICTEquipments.vue`, import `SecurityPanel` and mount it in the equipment detail/expanded-row area (matching wherever `DeviceBackups`-style per-device panels are already shown), passing the device's `equipment_id` prop.

- [ ] **Step 3: Build and manually verify**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"` (or use the project's `build` skill)
Then open the ICT Equipment page in a browser, confirm the panel renders, "Isolate Now"/"Release" round-trip against the endpoints from Task 7 without console errors.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/ITJobRequests/SecurityPanel.vue resources/js/Pages/ITJobRequests/ICTEquipments.vue
git commit -m "feat(atlas-sentinel): add Security panel to ICT Equipment page"
```

---

## Task 10: Scaffold `BugsaymisIctAgent.Tests` (xUnit)

**Files:**
- Create: `src/BugsaymisIctAgent.Tests/BugsaymisIctAgent.Tests.csproj`
- Create: `src/BugsaymisIctAgent.Tests/PlaceholderTests.cs` (deleted in Task 11 once real tests exist)
- Modify: solution file (add project reference)

**Interfaces:**
- Produces: a working `dotnet test` target referencing `BugsaymisIctAgent.Service`.

- [ ] **Step 1: Create the test project**

Run (from `~/bugsaymis-ict-agent`):
```bash
dotnet new xunit -o src/BugsaymisIctAgent.Tests
dotnet add src/BugsaymisIctAgent.Tests reference src/BugsaymisIctAgent.Service
```

- [ ] **Step 2: Add it to the solution**

Run: `dotnet sln add src/BugsaymisIctAgent.Tests/BugsaymisIctAgent.Tests.csproj` (if a `.sln` file exists at repo root — check with `ls *.sln` first; if none exists, skip this step, the project will still build/test standalone).

- [ ] **Step 3: Write a trivial sanity test**

```csharp
// src/BugsaymisIctAgent.Tests/PlaceholderTests.cs
using Xunit;

namespace BugsaymisIctAgent.Tests;

public class PlaceholderTests
{
    [Fact]
    public void Sanity_check_test_project_builds_and_runs()
    {
        Assert.True(true);
    }
}
```

- [ ] **Step 4: Run it**

Run: `dotnet test src/BugsaymisIctAgent.Tests`
Expected: 1 test, PASS. (This confirms `EnableWindowsTargeting`/build works for the test project the same way it does for the Service project on this macOS dev machine per the module's established build gotchas.)

- [ ] **Step 5: Commit**

```bash
git add src/BugsaymisIctAgent.Tests
git commit -m "chore: scaffold BugsaymisIctAgent.Tests xUnit project"
```

---

## Task 11: `NetworkAnomalyDetector` — connection-table heuristics

**Files:**
- Create: `src/BugsaymisIctAgent.Service/Security/ITcpConnectionTableReader.cs`
- Create: `src/BugsaymisIctAgent.Service/Security/Win32TcpConnectionTableReader.cs`
- Create: `src/BugsaymisIctAgent.Service/Security/NetworkAnomalyDetector.cs`
- Test: `src/BugsaymisIctAgent.Tests/NetworkAnomalyDetectorTests.cs` — new

**Interfaces:**
- Produces:
  - `enum TcpState { Unknown, SynSent, SynReceived, Established, Other }`
  - `record TcpConnectionInfo(TcpState State, int ProcessId, string RemoteAddress)`
  - `interface ITcpConnectionTableReader { IReadOnlyList<TcpConnectionInfo> GetConnections(); }`
  - `record NetworkAnomalyThresholds(int MaxHalfOpenConnections, int MaxDistinctRemoteIps)`
  - `record NetworkAnomalyResult(bool IsAnomalous, int HalfOpenCount, int DistinctRemoteIpCount, int? TopProcessId)`
  - `class NetworkAnomalyDetector { public NetworkAnomalyDetector(ITcpConnectionTableReader reader); public NetworkAnomalyResult Evaluate(NetworkAnomalyThresholds thresholds); }` — requires **2 consecutive breaches** before flagging `IsAnomalous`, to reduce single-sample noise.

- [ ] **Step 1: Write the failing tests**

```csharp
// src/BugsaymisIctAgent.Tests/NetworkAnomalyDetectorTests.cs
using System.Collections.Generic;
using BugsaymisIctAgent.Service.Security;
using Xunit;

namespace BugsaymisIctAgent.Tests;

public class FakeTcpConnectionTableReader : ITcpConnectionTableReader
{
    public List<TcpConnectionInfo> Connections { get; set; } = new();
    public IReadOnlyList<TcpConnectionInfo> GetConnections() => Connections;
}

public class NetworkAnomalyDetectorTests
{
    private static TcpConnectionInfo HalfOpen(int pid, string ip) => new(TcpState.SynSent, pid, ip);
    private static readonly NetworkAnomalyThresholds Thresholds = new(MaxHalfOpenConnections: 5, MaxDistinctRemoteIps: 5);

    [Fact]
    public void Evaluate_below_threshold_is_not_anomalous()
    {
        var reader = new FakeTcpConnectionTableReader { Connections = new() { HalfOpen(100, "1.2.3.4") } };
        var detector = new NetworkAnomalyDetector(reader);

        var result = detector.Evaluate(Thresholds);

        Assert.False(result.IsAnomalous);
        Assert.Equal(1, result.HalfOpenCount);
    }

    [Fact]
    public void Evaluate_requires_two_consecutive_breaches_before_flagging()
    {
        var connections = new List<TcpConnectionInfo>();
        for (var i = 0; i < 10; i++) connections.Add(HalfOpen(100, $"10.0.0.{i}"));
        var reader = new FakeTcpConnectionTableReader { Connections = connections };
        var detector = new NetworkAnomalyDetector(reader);

        var first = detector.Evaluate(Thresholds);
        Assert.False(first.IsAnomalous); // first breach alone doesn't flag

        var second = detector.Evaluate(Thresholds);
        Assert.True(second.IsAnomalous); // second consecutive breach flags
        Assert.Equal(10, second.HalfOpenCount);
        Assert.Equal(10, second.DistinctRemoteIpCount);
    }

    [Fact]
    public void Evaluate_resets_consecutive_counter_after_a_clean_sample()
    {
        var breach = new List<TcpConnectionInfo>();
        for (var i = 0; i < 10; i++) breach.Add(HalfOpen(100, $"10.0.0.{i}"));
        var reader = new FakeTcpConnectionTableReader { Connections = breach };
        var detector = new NetworkAnomalyDetector(reader);

        detector.Evaluate(Thresholds); // breach 1
        reader.Connections = new() { HalfOpen(100, "1.2.3.4") }; // clean sample
        detector.Evaluate(Thresholds);
        reader.Connections = breach; // breach again — counter should have reset
        var result = detector.Evaluate(Thresholds);

        Assert.False(result.IsAnomalous);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `dotnet test src/BugsaymisIctAgent.Tests --filter NetworkAnomalyDetectorTests`
Expected: FAIL — types don't exist.

- [ ] **Step 3: Write `ITcpConnectionTableReader.cs`**

```csharp
// src/BugsaymisIctAgent.Service/Security/ITcpConnectionTableReader.cs
using System.Collections.Generic;

namespace BugsaymisIctAgent.Service.Security;

public enum TcpState
{
    Unknown = 0,
    SynSent = 3,
    SynReceived = 4,
    Established = 5,
    Other = 99,
}

public record TcpConnectionInfo(TcpState State, int ProcessId, string RemoteAddress);

public interface ITcpConnectionTableReader
{
    IReadOnlyList<TcpConnectionInfo> GetConnections();
}
```

- [ ] **Step 4: Write `Win32TcpConnectionTableReader.cs`**

```csharp
// src/BugsaymisIctAgent.Service/Security/Win32TcpConnectionTableReader.cs
using System;
using System.Collections.Generic;
using System.Net;
using System.Runtime.InteropServices;

namespace BugsaymisIctAgent.Service.Security;

public class Win32TcpConnectionTableReader : ITcpConnectionTableReader
{
    private const int AF_INET = 2;
    private const int TCP_TABLE_OWNER_PID_ALL = 5;

    [DllImport("iphlpapi.dll", SetLastError = true)]
    private static extern uint GetExtendedTcpTable(
        IntPtr pTcpTable, ref int dwOutBufLen, bool sort, int ipVersion, int tblClass, uint reserved);

    [StructLayout(LayoutKind.Sequential)]
    private struct MIB_TCPROW_OWNER_PID
    {
        public uint state;
        public uint localAddr;
        public uint localPort; // first 4 bytes valid, big-endian
        public uint remoteAddr;
        public uint remotePort;
        public uint owningPid;
    }

    public IReadOnlyList<TcpConnectionInfo> GetConnections()
    {
        var results = new List<TcpConnectionInfo>();
        int bufSize = 0;
        GetExtendedTcpTable(IntPtr.Zero, ref bufSize, sort: true, AF_INET, TCP_TABLE_OWNER_PID_ALL, 0);

        var buffer = Marshal.AllocHGlobal(bufSize);
        try
        {
            var ret = GetExtendedTcpTable(buffer, ref bufSize, true, AF_INET, TCP_TABLE_OWNER_PID_ALL, 0);
            if (ret != 0) return results;

            var numEntries = Marshal.ReadInt32(buffer);
            var rowPtr = IntPtr.Add(buffer, 4);
            var rowSize = Marshal.SizeOf<MIB_TCPROW_OWNER_PID>();

            for (var i = 0; i < numEntries; i++)
            {
                var row = Marshal.PtrToStructure<MIB_TCPROW_OWNER_PID>(rowPtr);
                results.Add(new TcpConnectionInfo(
                    State: MapState(row.state),
                    ProcessId: (int)row.owningPid,
                    RemoteAddress: new IPAddress(row.remoteAddr).ToString()
                ));
                rowPtr = IntPtr.Add(rowPtr, rowSize);
            }
        }
        finally
        {
            Marshal.FreeHGlobal(buffer);
        }

        return results;
    }

    private static TcpState MapState(uint state) => state switch
    {
        3 => TcpState.SynSent,
        4 => TcpState.SynReceived,
        5 => TcpState.Established,
        _ => TcpState.Other,
    };
}
```

- [ ] **Step 5: Write `NetworkAnomalyDetector.cs`**

```csharp
// src/BugsaymisIctAgent.Service/Security/NetworkAnomalyDetector.cs
using System.Linq;

namespace BugsaymisIctAgent.Service.Security;

public record NetworkAnomalyThresholds(int MaxHalfOpenConnections, int MaxDistinctRemoteIps);
public record NetworkAnomalyResult(bool IsAnomalous, int HalfOpenCount, int DistinctRemoteIpCount, int? TopProcessId);

public class NetworkAnomalyDetector
{
    private readonly ITcpConnectionTableReader _reader;
    private int _consecutiveBreaches;

    public NetworkAnomalyDetector(ITcpConnectionTableReader reader)
    {
        _reader = reader;
    }

    public NetworkAnomalyResult Evaluate(NetworkAnomalyThresholds thresholds)
    {
        var connections = _reader.GetConnections();
        var halfOpen = connections.Where(c => c.State == TcpState.SynSent || c.State == TcpState.SynReceived).ToList();
        var halfOpenCount = halfOpen.Count;
        var distinctIps = halfOpen.Select(c => c.RemoteAddress).Distinct().Count();
        var topProcessId = halfOpen
            .GroupBy(c => c.ProcessId)
            .OrderByDescending(g => g.Count())
            .Select(g => (int?)g.Key)
            .FirstOrDefault();

        var breached = halfOpenCount >= thresholds.MaxHalfOpenConnections
            || distinctIps >= thresholds.MaxDistinctRemoteIps;

        if (breached)
        {
            _consecutiveBreaches++;
        }
        else
        {
            _consecutiveBreaches = 0;
        }

        var isAnomalous = _consecutiveBreaches >= 2;

        return new NetworkAnomalyResult(isAnomalous, halfOpenCount, distinctIps, topProcessId);
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `dotnet test src/BugsaymisIctAgent.Tests --filter NetworkAnomalyDetectorTests`
Expected: PASS (3 tests)

- [ ] **Step 7: Commit**

```bash
git add src/BugsaymisIctAgent.Service/Security/ITcpConnectionTableReader.cs src/BugsaymisIctAgent.Service/Security/Win32TcpConnectionTableReader.cs src/BugsaymisIctAgent.Service/Security/NetworkAnomalyDetector.cs src/BugsaymisIctAgent.Tests/NetworkAnomalyDetectorTests.cs
git commit -m "feat(security): add network anomaly detector with consecutive-breach debounce"
```

---

## Task 12: `DefenderThreatMonitor` — Windows Defender signal ingestion

**Files:**
- Create: `src/BugsaymisIctAgent.Service/Security/DefenderThreatMonitor.cs`
- Test: `src/BugsaymisIctAgent.Tests/DefenderThreatMonitorTests.cs` — new

**Interfaces:**
- Produces:
  - `record DefenderThreatResult(bool ThreatDetected, string? ThreatName, string? Severity)`
  - `interface IDefenderThreatSource { DefenderThreatResult Check(); }`
  - `class WmiDefenderThreatSource : IDefenderThreatSource` (real WMI implementation against `root\Microsoft\Windows\Defender` → `MSFT_MpThreatDetection`, following the existing `ManagementScope` pattern already used in `InventoryInfo.cs:282` for `root\SecurityCenter2`)
  - `class DefenderThreatMonitor { public DefenderThreatMonitor(IDefenderThreatSource source); public DefenderThreatResult Check(); }` — thin wrapper that also swallows/logs WMI exceptions (Defender WMI namespace is absent on machines where Defender is disabled by a third-party AV like Kaspersky) and returns `ThreatDetected = false` rather than throwing.

- [ ] **Step 1: Write the failing tests**

```csharp
// src/BugsaymisIctAgent.Tests/DefenderThreatMonitorTests.cs
using BugsaymisIctAgent.Service.Security;
using Xunit;

namespace BugsaymisIctAgent.Tests;

public class FakeDefenderThreatSource : IDefenderThreatSource
{
    public DefenderThreatResult Result { get; set; } = new(false, null, null);
    public bool ThrowOnCheck { get; set; }

    public DefenderThreatResult Check()
    {
        if (ThrowOnCheck) throw new System.Management.ManagementException("namespace unavailable");
        return Result;
    }
}

public class DefenderThreatMonitorTests
{
    [Fact]
    public void Check_passes_through_a_real_detection()
    {
        var source = new FakeDefenderThreatSource { Result = new(true, "Trojan:Win32/Test", "Severe") };
        var monitor = new DefenderThreatMonitor(source);

        var result = monitor.Check();

        Assert.True(result.ThreatDetected);
        Assert.Equal("Trojan:Win32/Test", result.ThreatName);
    }

    [Fact]
    public void Check_swallows_wmi_exception_and_reports_no_threat()
    {
        var source = new FakeDefenderThreatSource { ThrowOnCheck = true };
        var monitor = new DefenderThreatMonitor(source);

        var result = monitor.Check();

        Assert.False(result.ThreatDetected);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `dotnet test src/BugsaymisIctAgent.Tests --filter DefenderThreatMonitorTests`
Expected: FAIL — types don't exist.

- [ ] **Step 3: Write the implementation**

```csharp
// src/BugsaymisIctAgent.Service/Security/DefenderThreatMonitor.cs
using System;
using System.Management;

namespace BugsaymisIctAgent.Service.Security;

public record DefenderThreatResult(bool ThreatDetected, string? ThreatName, string? Severity);

public interface IDefenderThreatSource
{
    DefenderThreatResult Check();
}

public class WmiDefenderThreatSource : IDefenderThreatSource
{
    public DefenderThreatResult Check()
    {
        var scope = new ManagementScope(@"root\Microsoft\Windows\Defender");
        scope.Connect();

        using var searcher = new ManagementObjectSearcher(scope, new ObjectQuery("SELECT * FROM MSFT_MpThreatDetection"));
        foreach (ManagementObject result in searcher.Get())
        {
            var name = result["ThreatName"]?.ToString();
            var severity = result["SeverityID"]?.ToString();
            if (!string.IsNullOrEmpty(name))
            {
                return new DefenderThreatResult(true, name, severity);
            }
        }

        return new DefenderThreatResult(false, null, null);
    }
}

public class DefenderThreatMonitor
{
    private readonly IDefenderThreatSource _source;

    public DefenderThreatMonitor(IDefenderThreatSource source)
    {
        _source = source;
    }

    public DefenderThreatResult Check()
    {
        try
        {
            return _source.Check();
        }
        catch (Exception)
        {
            // Defender WMI namespace absent (e.g. Defender disabled by third-party AV) — known
            // limitation, not an error. Network-anomaly detection still covers these machines.
            return new DefenderThreatResult(false, null, null);
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `dotnet test src/BugsaymisIctAgent.Tests --filter DefenderThreatMonitorTests`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/BugsaymisIctAgent.Service/Security/DefenderThreatMonitor.cs src/BugsaymisIctAgent.Tests/DefenderThreatMonitorTests.cs
git commit -m "feat(security): add Windows Defender threat-signal monitor"
```

---

## Task 13: `NetworkContainmentService` — firewall isolation + persistence

**Files:**
- Create: `src/BugsaymisIctAgent.Service/Security/IFirewallCommandRunner.cs`
- Create: `src/BugsaymisIctAgent.Service/Security/Win32FirewallCommandRunner.cs`
- Create: `src/BugsaymisIctAgent.Service/Security/NetworkContainmentService.cs`
- Test: `src/BugsaymisIctAgent.Tests/NetworkContainmentServiceTests.cs` — new

**Interfaces:**
- Produces:
  - `interface IFirewallCommandRunner { void AddBlockAllExceptRule(string ruleNamePrefix, string allowedHost); void RemoveRules(string ruleNamePrefix); }`
  - `record ContainmentState(bool Contained, string? Reason, DateTime? TriggeredAtUtc, DateTime? ExpiresAtUtc)`
  - `class NetworkContainmentService { public NetworkContainmentService(IFirewallCommandRunner runner, string stateFilePath, string managementHost); public void Apply(string reason, int autoReleaseMinutes); public void Release(); public void ConfirmActive(); public ContainmentState GetState(); public bool ShouldAutoRelease(); }`
  - Rule name prefix constant: `"AtlasSentinelContainment"`.

- [ ] **Step 1: Write the failing tests**

```csharp
// src/BugsaymisIctAgent.Tests/NetworkContainmentServiceTests.cs
using System;
using System.IO;
using BugsaymisIctAgent.Service.Security;
using Xunit;

namespace BugsaymisIctAgent.Tests;

public class FakeFirewallCommandRunner : IFirewallCommandRunner
{
    public bool RuleAdded { get; private set; }
    public bool RulesRemoved { get; private set; }

    public void AddBlockAllExceptRule(string ruleNamePrefix, string allowedHost) => RuleAdded = true;
    public void RemoveRules(string ruleNamePrefix) => RulesRemoved = true;
}

public class NetworkContainmentServiceTests : IDisposable
{
    private readonly string _stateFile = Path.Combine(Path.GetTempPath(), $"containment-test-{Guid.NewGuid()}.json");

    [Fact]
    public void Apply_adds_firewall_rule_and_persists_state()
    {
        var runner = new FakeFirewallCommandRunner();
        var service = new NetworkContainmentService(runner, _stateFile, "mis.crc.pshs.edu.ph");

        service.Apply("network_anomaly", autoReleaseMinutes: 30);

        Assert.True(runner.RuleAdded);
        var state = service.GetState();
        Assert.True(state.Contained);
        Assert.Equal("network_anomaly", state.Reason);
        Assert.True(File.Exists(_stateFile));
    }

    [Fact]
    public void GetState_after_reload_reflects_persisted_containment()
    {
        var runner = new FakeFirewallCommandRunner();
        var first = new NetworkContainmentService(runner, _stateFile, "mis.crc.pshs.edu.ph");
        first.Apply("av_signal", autoReleaseMinutes: 30);

        var second = new NetworkContainmentService(runner, _stateFile, "mis.crc.pshs.edu.ph");
        var state = second.GetState();

        Assert.True(state.Contained);
        Assert.Equal("av_signal", state.Reason);
    }

    [Fact]
    public void Release_removes_rules_and_clears_state()
    {
        var runner = new FakeFirewallCommandRunner();
        var service = new NetworkContainmentService(runner, _stateFile, "mis.crc.pshs.edu.ph");
        service.Apply("network_anomaly", autoReleaseMinutes: 30);

        service.Release();

        Assert.True(runner.RulesRemoved);
        Assert.False(service.GetState().Contained);
    }

    [Fact]
    public void ShouldAutoRelease_true_after_expiry_when_not_confirmed()
    {
        var runner = new FakeFirewallCommandRunner();
        var service = new NetworkContainmentService(runner, _stateFile, "mis.crc.pshs.edu.ph");
        service.Apply("network_anomaly", autoReleaseMinutes: -1); // already expired

        Assert.True(service.ShouldAutoRelease());
    }

    [Fact]
    public void ShouldAutoRelease_false_once_confirmed_even_past_expiry()
    {
        var runner = new FakeFirewallCommandRunner();
        var service = new NetworkContainmentService(runner, _stateFile, "mis.crc.pshs.edu.ph");
        service.Apply("network_anomaly", autoReleaseMinutes: -1);

        service.ConfirmActive();

        Assert.False(service.ShouldAutoRelease());
    }

    public void Dispose()
    {
        if (File.Exists(_stateFile)) File.Delete(_stateFile);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `dotnet test src/BugsaymisIctAgent.Tests --filter NetworkContainmentServiceTests`
Expected: FAIL — types don't exist.

- [ ] **Step 3: Write `IFirewallCommandRunner.cs`**

```csharp
// src/BugsaymisIctAgent.Service/Security/IFirewallCommandRunner.cs
namespace BugsaymisIctAgent.Service.Security;

public interface IFirewallCommandRunner
{
    void AddBlockAllExceptRule(string ruleNamePrefix, string allowedHost);
    void RemoveRules(string ruleNamePrefix);
}
```

- [ ] **Step 4: Write `Win32FirewallCommandRunner.cs`**

```csharp
// src/BugsaymisIctAgent.Service/Security/Win32FirewallCommandRunner.cs
using System.Diagnostics;

namespace BugsaymisIctAgent.Service.Security;

public class Win32FirewallCommandRunner : IFirewallCommandRunner
{
    public void AddBlockAllExceptRule(string ruleNamePrefix, string allowedHost)
    {
        // Allow rules first (evaluated alongside block-all — Windows Firewall applies
        // the most specific/allow-wins-on-tie-for-same-rule-type semantics per profile;
        // block-all is added as a low-priority catch-all).
        RunNetsh($"advfirewall firewall add rule name=\"{ruleNamePrefix}-AllowManagement\" dir=out action=allow remoteip={allowedHost} protocol=TCP remoteport=443");
        RunNetsh($"advfirewall firewall add rule name=\"{ruleNamePrefix}-AllowLoopback\" dir=out action=allow remoteip=127.0.0.1");
        RunNetsh($"advfirewall firewall add rule name=\"{ruleNamePrefix}-BlockAllOut\" dir=out action=block");
        RunNetsh($"advfirewall firewall add rule name=\"{ruleNamePrefix}-BlockAllIn\" dir=in action=block");
    }

    public void RemoveRules(string ruleNamePrefix)
    {
        RunNetsh($"advfirewall firewall delete rule name=\"{ruleNamePrefix}-AllowManagement\"");
        RunNetsh($"advfirewall firewall delete rule name=\"{ruleNamePrefix}-AllowLoopback\"");
        RunNetsh($"advfirewall firewall delete rule name=\"{ruleNamePrefix}-BlockAllOut\"");
        RunNetsh($"advfirewall firewall delete rule name=\"{ruleNamePrefix}-BlockAllIn\"");
    }

    private static void RunNetsh(string arguments)
    {
        var psi = new ProcessStartInfo("netsh", arguments)
        {
            UseShellExecute = false,
            CreateNoWindow = true,
            RedirectStandardOutput = true,
            RedirectStandardError = true,
        };
        using var process = Process.Start(psi);
        process?.WaitForExit(15000);
    }
}
```

- [ ] **Step 5: Write `NetworkContainmentService.cs`**

```csharp
// src/BugsaymisIctAgent.Service/Security/NetworkContainmentService.cs
using System;
using System.IO;
using System.Text.Json;
using System.Text.Json.Serialization;

namespace BugsaymisIctAgent.Service.Security;

public record ContainmentState(bool Contained, string? Reason, DateTime? TriggeredAtUtc, DateTime? ExpiresAtUtc);

internal class ContainmentFileModel
{
    [JsonPropertyName("contained")] public bool Contained { get; set; }
    [JsonPropertyName("reason")] public string? Reason { get; set; }
    [JsonPropertyName("triggered_at_utc")] public DateTime? TriggeredAtUtc { get; set; }
    [JsonPropertyName("expires_at_utc")] public DateTime? ExpiresAtUtc { get; set; }
    [JsonPropertyName("confirmed")] public bool Confirmed { get; set; }
}

public class NetworkContainmentService
{
    private const string RuleNamePrefix = "AtlasSentinelContainment";

    private readonly IFirewallCommandRunner _runner;
    private readonly string _stateFilePath;
    private readonly string _managementHost;

    public NetworkContainmentService(IFirewallCommandRunner runner, string stateFilePath, string managementHost)
    {
        _runner = runner;
        _stateFilePath = stateFilePath;
        _managementHost = managementHost;
    }

    public void Apply(string reason, int autoReleaseMinutes)
    {
        _runner.AddBlockAllExceptRule(RuleNamePrefix, _managementHost);

        var model = new ContainmentFileModel
        {
            Contained = true,
            Reason = reason,
            TriggeredAtUtc = DateTime.UtcNow,
            ExpiresAtUtc = DateTime.UtcNow.AddMinutes(autoReleaseMinutes),
            Confirmed = false,
        };
        Persist(model);
    }

    public void Release()
    {
        _runner.RemoveRules(RuleNamePrefix);
        if (File.Exists(_stateFilePath)) File.Delete(_stateFilePath);
    }

    public void ConfirmActive()
    {
        var model = Load();
        if (model is null) return;
        model.Confirmed = true;
        Persist(model);
    }

    public ContainmentState GetState()
    {
        var model = Load();
        if (model is null || !model.Contained) return new ContainmentState(false, null, null, null);
        return new ContainmentState(true, model.Reason, model.TriggeredAtUtc, model.ExpiresAtUtc);
    }

    public bool ShouldAutoRelease()
    {
        var model = Load();
        if (model is null || !model.Contained || model.Confirmed) return false;
        return model.ExpiresAtUtc.HasValue && DateTime.UtcNow >= model.ExpiresAtUtc.Value;
    }

    private void Persist(ContainmentFileModel model)
    {
        var dir = Path.GetDirectoryName(_stateFilePath);
        if (!string.IsNullOrEmpty(dir)) Directory.CreateDirectory(dir);
        File.WriteAllText(_stateFilePath, JsonSerializer.Serialize(model));
    }

    private ContainmentFileModel? Load()
    {
        if (!File.Exists(_stateFilePath)) return null;
        var json = File.ReadAllText(_stateFilePath);
        return JsonSerializer.Deserialize<ContainmentFileModel>(json);
    }
}
```

- [ ] **Step 6: Run tests to verify they pass**

Run: `dotnet test src/BugsaymisIctAgent.Tests --filter NetworkContainmentServiceTests`
Expected: PASS (5 tests)

- [ ] **Step 7: Commit**

```bash
git add src/BugsaymisIctAgent.Service/Security/IFirewallCommandRunner.cs src/BugsaymisIctAgent.Service/Security/Win32FirewallCommandRunner.cs src/BugsaymisIctAgent.Service/Security/NetworkContainmentService.cs src/BugsaymisIctAgent.Tests/NetworkContainmentServiceTests.cs
git commit -m "feat(security): add network containment service with persisted, reboot-surviving state"
```

---

## Task 14: `IctAgentApiClient` — report incident + extend checkin response DTO

**Files:**
- Modify: `src/BugsaymisIctAgent.Service/IctAgentApiClient.cs`

**Interfaces:**
- Consumes: existing `PostAsJsonAsync`/`EnsureSuccessStatusCode` pattern (`IctAgentApiClient.cs:236` example), existing `[JsonPropertyName]` DTO convention.
- Produces:
  - `Task ReportSecurityIncidentAsync(string reason, object detail, string deviceToken, CancellationToken ct)`
  - `CheckinResponse.Containment` (new property, `ContainmentConfig?`)
  - `record ContainmentConfig([property: JsonPropertyName("exempt")] bool Exempt, [property: JsonPropertyName("auto_contain_enabled")] bool AutoContainEnabled, [property: JsonPropertyName("thresholds")] ContainmentThresholds Thresholds, [property: JsonPropertyName("server_host")] string ServerHost, [property: JsonPropertyName("confirmed")] bool Confirmed)`
  - `record ContainmentThresholds([property: JsonPropertyName("max_half_open_connections")] int MaxHalfOpenConnections, [property: JsonPropertyName("max_distinct_ips_per_minute")] int MaxDistinctIpsPerMinute)`

- [ ] **Step 1: Read the existing file to confirm current structure before editing**

Read `src/BugsaymisIctAgent.Service/IctAgentApiClient.cs` in full — confirm the exact current `CheckinResponse` record definition (lines ~26-30 per prior research) and the exact signature of `SendInventoryCheckinAsync` (line ~236) to match its calling pattern precisely (this file may have shifted line numbers since the research pass).

- [ ] **Step 2: Extend `CheckinResponse`**

Add a `Containment` property to the existing `CheckinResponse` record, following the exact same `[property: JsonPropertyName(...)]` style already used on `Update`/`Remediations`/`Backup`:

```csharp
[property: JsonPropertyName("containment")] ContainmentConfig? Containment
```

Add the two new supporting records (`ContainmentConfig`, `ContainmentThresholds`) shown in Interfaces above, placed near the other response DTOs in this file.

- [ ] **Step 3: Add `ReportSecurityIncidentAsync`**

Following the exact pattern of the existing simple POST method (e.g. `SendInventoryCheckinAsync`):

```csharp
public async Task ReportSecurityIncidentAsync(string reason, object detail, string deviceToken, CancellationToken ct)
{
    _http.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", deviceToken);
    var payload = new
    {
        reason,
        detail,
        triggered_at = DateTime.UtcNow.ToString("o"),
    };
    var response = await _http.PostAsJsonAsync("security-incident", payload, ct);
    response.EnsureSuccessStatusCode();
}
```

(If this method could ever run concurrently with another call mutating `_http.DefaultRequestHeaders` — e.g. alongside a backup upload — use the file's existing `BuildAuthedJsonRequest` helper instead, matching how backup calls avoid the shared-header race per the file's established pattern.)

- [ ] **Step 4: Build to verify it compiles**

Run: `dotnet build src/BugsaymisIctAgent.Service`
Expected: build succeeds, no compile errors.

- [ ] **Step 5: Commit**

```bash
git add src/BugsaymisIctAgent.Service/IctAgentApiClient.cs
git commit -m "feat(security): add security-incident reporting and containment config to checkin DTO"
```

---

## Task 15: `RemediationExecutor` — manual `network_containment`/`network_release` actions

**Files:**
- Modify: `src/BugsaymisIctAgent.Service/RemediationExecutor.cs`
- Test: `src/BugsaymisIctAgent.Tests/RemediationExecutorSecurityActionsTests.cs` — new

**Interfaces:**
- Consumes: `NetworkContainmentService` (Task 13).
- Produces: `RemediationExecutor.Execute()` handles `action == "network_containment"` and `action == "network_release"` without throwing `NotSupportedException`.

- [ ] **Step 1: Read the existing file first**

Read `src/BugsaymisIctAgent.Service/RemediationExecutor.cs` in full (185 lines) to confirm the exact current switch statement shape (line ~22-31) and the exact signature `RemediationExecutor.Execute(RemediationInstruction instruction)` before editing — this determines exactly how to wire in `NetworkContainmentService`, since `Execute` is currently static and the new action needs an instance of the service (construct it inline with the same `%ProgramData%\BugsaymisIctAgent\containment.json` path and management host used elsewhere, or thread a static/singleton instance through if `RemediationExecutor` already holds shared static state for other actions — match whatever pattern the file already uses).

- [ ] **Step 2: Write the failing tests**

```csharp
// src/BugsaymisIctAgent.Tests/RemediationExecutorSecurityActionsTests.cs
using BugsaymisIctAgent.Service.Security;
using Xunit;

namespace BugsaymisIctAgent.Tests;

public class RemediationExecutorSecurityActionsTests
{
    [Fact]
    public void NetworkContainmentService_apply_then_release_is_idempotent_and_safe_to_call_twice()
    {
        var runner = new FakeFirewallCommandRunner();
        var stateFile = System.IO.Path.Combine(System.IO.Path.GetTempPath(), $"remediation-test-{System.Guid.NewGuid()}.json");
        var service = new NetworkContainmentService(runner, stateFile, "mis.crc.pshs.edu.ph");

        service.Apply("manual", autoReleaseMinutes: 30);
        service.Apply("manual", autoReleaseMinutes: 30); // calling Apply again must not throw
        service.Release();
        service.Release(); // calling Release again (already released) must not throw

        Assert.False(service.GetState().Contained);

        if (System.IO.File.Exists(stateFile)) System.IO.File.Delete(stateFile);
    }
}
```

(This test exercises the exact idempotency contract `RemediationExecutor`'s new switch cases will rely on — a manual "Isolate Now" click on an already-contained device, or "Release" on an already-released one, must be safe no-ops, since the admin UI doesn't track fine-grained button-disable state.)

- [ ] **Step 3: Run test to verify it fails or passes as a spec check**

Run: `dotnet test src/BugsaymisIctAgent.Tests --filter RemediationExecutorSecurityActionsTests`
Expected: PASS if `Apply`/`Release` were already written idempotently in Task 13 (`Apply` just re-adds rules and rewrites state; `Release` just no-ops on a missing state file since `File.Exists` guards the delete, and `RemoveRules` on already-removed netsh rules exits non-zero but the process call doesn't throw). If it fails, fix `NetworkContainmentService` to be idempotent before proceeding — do not add special-casing in `RemediationExecutor` to work around non-idempotent lower-level behavior.

- [ ] **Step 4: Add the switch cases**

In `RemediationExecutor.cs`, add two new cases to the existing switch statement, following the file's existing static-method style:

```csharp
case "network_containment":
    ExecuteNetworkContainment(instruction);
    break;
case "network_release":
    ExecuteNetworkRelease();
    break;
```

And the two handler methods (placed alongside the other private static handler methods in this file):

```csharp
private static void ExecuteNetworkContainment(RemediationInstruction instruction)
{
    var service = BuildContainmentService();
    service.Apply("manual", autoReleaseMinutes: 30);
}

private static void ExecuteNetworkRelease()
{
    var service = BuildContainmentService();
    service.Release();
}

private static NetworkContainmentService BuildContainmentService()
{
    var stateFile = System.IO.Path.Combine(
        System.Environment.GetFolderPath(System.Environment.SpecialFolder.CommonApplicationData),
        "BugsaymisIctAgent", "containment.json");
    return new NetworkContainmentService(new Win32FirewallCommandRunner(), stateFile, "mis.crc.pshs.edu.ph");
}
```

(The hardcoded `"mis.crc.pshs.edu.ph"` management host should be read from the same `IConfiguration`/`IctAgent:ApiBaseUrl` config value the rest of the agent uses, not duplicated as a literal — read `Program.cs`/`Worker.cs` to see how config is threaded into static/non-DI'd classes elsewhere in this file, e.g. `software_uninstall`'s existing handling, and match that pattern rather than hardcoding.)

- [ ] **Step 5: Run tests**

Run: `dotnet test src/BugsaymisIctAgent.Tests --filter RemediationExecutorSecurityActionsTests`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/BugsaymisIctAgent.Service/RemediationExecutor.cs src/BugsaymisIctAgent.Tests/RemediationExecutorSecurityActionsTests.cs
git commit -m "feat(security): wire manual isolate/release into RemediationExecutor"
```

---

## Task 16: `SecurityMonitor` orchestrator + `Worker.cs` wiring

**Files:**
- Create: `src/BugsaymisIctAgent.Service/Security/SecurityMonitor.cs`
- Modify: `src/BugsaymisIctAgent.Service/Worker.cs`
- Test: `src/BugsaymisIctAgent.Tests/SecurityMonitorTests.cs` — new

**Interfaces:**
- Consumes: `NetworkAnomalyDetector` (Task 11), `DefenderThreatMonitor` (Task 12), `NetworkContainmentService` (Task 13), `IctAgentApiClient.ReportSecurityIncidentAsync` (Task 14), `CheckinResponse.Containment` (Task 14).
- Produces: `class SecurityMonitor { public SecurityMonitor(NetworkAnomalyDetector networkDetector, DefenderThreatMonitor defenderMonitor, NetworkContainmentService containment); public SecurityMonitorTick Tick(ContainmentConfig? serverConfig); }` where `record SecurityMonitorTick(bool Contained, string? Reason, object? Detail)` (non-null `Reason`/`Detail` only when this tick just transitioned into containment — used by `Worker.cs` to decide whether to fire `ReportSecurityIncidentAsync`).

- [ ] **Step 1: Write the failing tests**

```csharp
// src/BugsaymisIctAgent.Tests/SecurityMonitorTests.cs
using System.Collections.Generic;
using BugsaymisIctAgent.Service.Security;
using Xunit;

namespace BugsaymisIctAgent.Tests;

public class SecurityMonitorTests
{
    private static (SecurityMonitor monitor, FakeTcpConnectionTableReader tcp, FakeDefenderThreatSource defender, FakeFirewallCommandRunner firewall, string stateFile) Build()
    {
        var tcp = new FakeTcpConnectionTableReader();
        var defender = new FakeDefenderThreatSource();
        var firewall = new FakeFirewallCommandRunner();
        var stateFile = System.IO.Path.Combine(System.IO.Path.GetTempPath(), $"sm-test-{System.Guid.NewGuid()}.json");

        var networkDetector = new NetworkAnomalyDetector(tcp);
        var defenderMonitor = new DefenderThreatMonitor(defender);
        var containment = new NetworkContainmentService(firewall, stateFile, "mis.crc.pshs.edu.ph");

        return (new SecurityMonitor(networkDetector, defenderMonitor, containment), tcp, defender, firewall, stateFile);
    }

    [Fact]
    public void Tick_does_not_contain_when_kill_switch_disabled_even_on_defender_hit()
    {
        var (monitor, _, defender, firewall, stateFile) = Build();
        defender.Result = new DefenderThreatResult(true, "Trojan:Test", "Severe");
        var config = new ContainmentConfig(Exempt: false, AutoContainEnabled: false,
            Thresholds: new ContainmentThresholds(100, 50), ServerHost: "mis.crc.pshs.edu.ph", Confirmed: false);

        var tick = monitor.Tick(config);

        Assert.False(tick.Contained);
        Assert.False(firewall.RuleAdded);
        System.IO.File.Delete(stateFile);
    }

    [Fact]
    public void Tick_does_not_contain_when_device_is_exempt()
    {
        var (monitor, _, defender, firewall, stateFile) = Build();
        defender.Result = new DefenderThreatResult(true, "Trojan:Test", "Severe");
        var config = new ContainmentConfig(Exempt: true, AutoContainEnabled: true,
            Thresholds: new ContainmentThresholds(100, 50), ServerHost: "mis.crc.pshs.edu.ph", Confirmed: false);

        var tick = monitor.Tick(config);

        Assert.False(tick.Contained);
        Assert.False(firewall.RuleAdded);
        System.IO.File.Delete(stateFile);
    }

    [Fact]
    public void Tick_contains_and_returns_reason_on_confirmed_defender_threat()
    {
        var (monitor, _, defender, firewall, stateFile) = Build();
        defender.Result = new DefenderThreatResult(true, "Trojan:Test", "Severe");
        var config = new ContainmentConfig(Exempt: false, AutoContainEnabled: true,
            Thresholds: new ContainmentThresholds(100, 50), ServerHost: "mis.crc.pshs.edu.ph", Confirmed: false);

        var tick = monitor.Tick(config);

        Assert.True(tick.Contained);
        Assert.Equal("av_signal", tick.Reason);
        Assert.True(firewall.RuleAdded);
        System.IO.File.Delete(stateFile);
    }

    [Fact]
    public void Tick_second_call_after_containment_reports_contained_but_null_reason_no_duplicate_report()
    {
        var (monitor, _, defender, firewall, stateFile) = Build();
        defender.Result = new DefenderThreatResult(true, "Trojan:Test", "Severe");
        var config = new ContainmentConfig(Exempt: false, AutoContainEnabled: true,
            Thresholds: new ContainmentThresholds(100, 50), ServerHost: "mis.crc.pshs.edu.ph", Confirmed: false);

        monitor.Tick(config); // first tick contains
        var second = monitor.Tick(config); // already contained

        Assert.True(second.Contained);
        Assert.Null(second.Reason); // no new incident to report
        System.IO.File.Delete(stateFile);
    }

    [Fact]
    public void Tick_falls_back_to_network_anomaly_when_defender_clean()
    {
        var (monitor, tcp, defender, firewall, stateFile) = Build();
        defender.Result = new DefenderThreatResult(false, null, null);
        var connections = new System.Collections.Generic.List<TcpConnectionInfo>();
        for (var i = 0; i < 10; i++) connections.Add(new TcpConnectionInfo(TcpState.SynSent, 100, $"10.0.0.{i}"));
        tcp.Connections = connections;
        var config = new ContainmentConfig(Exempt: false, AutoContainEnabled: true,
            Thresholds: new ContainmentThresholds(5, 5), ServerHost: "mis.crc.pshs.edu.ph", Confirmed: false);

        monitor.Tick(config); // 1st breach, not yet flagged (consecutive-breach debounce)
        var second = monitor.Tick(config); // 2nd consecutive breach — flags

        Assert.True(second.Contained);
        Assert.Equal("network_anomaly", second.Reason);
        Assert.True(firewall.RuleAdded);
        System.IO.File.Delete(stateFile);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `dotnet test src/BugsaymisIctAgent.Tests --filter SecurityMonitorTests`
Expected: FAIL — `SecurityMonitor` doesn't exist.

- [ ] **Step 3: Write `SecurityMonitor.cs`**

```csharp
// src/BugsaymisIctAgent.Service/Security/SecurityMonitor.cs
namespace BugsaymisIctAgent.Service.Security;

public record SecurityMonitorTick(bool Contained, string? Reason, object? Detail);

public class SecurityMonitor
{
    private readonly NetworkAnomalyDetector _networkDetector;
    private readonly DefenderThreatMonitor _defenderMonitor;
    private readonly NetworkContainmentService _containment;

    public SecurityMonitor(NetworkAnomalyDetector networkDetector, DefenderThreatMonitor defenderMonitor, NetworkContainmentService containment)
    {
        _networkDetector = networkDetector;
        _defenderMonitor = defenderMonitor;
        _containment = containment;
    }

    public SecurityMonitorTick Tick(ContainmentConfig? serverConfig)
    {
        var alreadyContained = _containment.GetState().Contained;

        if (alreadyContained)
        {
            // Already contained — nothing new to decide locally here. Auto-release timing
            // and server-driven manual release are handled elsewhere (Worker.cs loop /
            // RemediationExecutor respectively).
            return new SecurityMonitorTick(true, null, null);
        }

        if (serverConfig is null || serverConfig.Exempt || !serverConfig.AutoContainEnabled)
        {
            return new SecurityMonitorTick(false, null, null);
        }

        var defenderResult = _defenderMonitor.Check();
        if (defenderResult.ThreatDetected)
        {
            var detail = new { threat_name = defenderResult.ThreatName, severity = defenderResult.Severity };
            _containment.Apply("av_signal", autoReleaseMinutes: 30);
            return new SecurityMonitorTick(true, "av_signal", detail);
        }

        var thresholds = new NetworkAnomalyThresholds(
            serverConfig.Thresholds.MaxHalfOpenConnections,
            serverConfig.Thresholds.MaxDistinctIpsPerMinute);
        var networkResult = _networkDetector.Evaluate(thresholds);
        if (networkResult.IsAnomalous)
        {
            var detail = new
            {
                half_open_count = networkResult.HalfOpenCount,
                distinct_remote_ip_count = networkResult.DistinctRemoteIpCount,
                process_id = networkResult.TopProcessId,
            };
            _containment.Apply("network_anomaly", autoReleaseMinutes: 30);
            return new SecurityMonitorTick(true, "network_anomaly", detail);
        }

        return new SecurityMonitorTick(false, null, null);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `dotnet test src/BugsaymisIctAgent.Tests --filter SecurityMonitorTests`
Expected: PASS (5 tests)

- [ ] **Step 5: Wire into `Worker.cs`**

Read `src/BugsaymisIctAgent.Service/Worker.cs` in full first (541 lines) to confirm the exact current constructor and main loop shape before editing. Then:

1. Add a `SecurityMonitor _securityMonitor` field, constructed in the constructor alongside the existing DI'd dependencies (build its own `NetworkAnomalyDetector`/`DefenderThreatMonitor`/`NetworkContainmentService` internally the same way `BuildContainmentService()` does in Task 15, or accept them via constructor injection and register in `Program.cs` — match whichever composition style `Program.cs` already uses for `BackupExecutor`).
2. Add a periodic call to `_securityMonitor.Tick(_lastContainmentConfig)` on a short interval (e.g. every 30-60s), similar in spirit to the existing `WatchdogAsync` timer-driven task — run it independently of the main checkin loop's thread, same reasoning as the watchdog (a hang elsewhere must not block security monitoring).
3. Store the most recent `response.Containment` from each checkin (`Worker.cs:104-123` handling block) into a field the security-monitor timer reads (`_lastContainmentConfig`).
4. When `Tick()` returns a non-null `Reason` (a fresh containment just happened), call `await _api.ReportSecurityIncidentAsync(tick.Reason, tick.Detail, deviceToken, ct)` immediately — do not wait for the next checkin.
5. While `_containment.GetState().Contained` is true, shorten the checkin polling interval to ~60s (read however the existing interval is currently computed/configured, and branch on containment state) so confirm/release instructions arrive quickly; restore the normal interval once released.
6. In the timer loop, also check `_containment.ShouldAutoRelease()` each tick — if true, call `_containment.Release()` locally (the 30-minute default timeout firing without a server round-trip is intentional per the design's safety-net requirement — the agent must not depend on connectivity to release itself).
7. When a checkin response's `Containment.Confirmed` is `true` and local state is contained-but-unconfirmed, call `_containment.ConfirmActive()` to stop the local auto-release timer.

- [ ] **Step 6: Build to verify it compiles**

Run: `dotnet build src/BugsaymisIctAgent.Service`
Expected: build succeeds.

- [ ] **Step 7: Commit**

```bash
git add src/BugsaymisIctAgent.Service/Security/SecurityMonitor.cs src/BugsaymisIctAgent.Service/Worker.cs src/BugsaymisIctAgent.Tests/SecurityMonitorTests.cs
git commit -m "feat(security): add SecurityMonitor orchestrator and wire into Worker loop"
```

---

## Task 17: Tray — containment warning toast

**Files:**
- Create: `src/BugsaymisIctAgent.Tray/ContainmentWarningWatcher.cs`
- Modify: `src/BugsaymisIctAgent.Service/Security/NetworkContainmentService.cs` (write a warning-marker file before applying rules)

**Interfaces:**
- Consumes: existing Tray balloon-notification infrastructure (the same mechanism already used for update/status balloons, per the module's history of "one balloon per state transition").
- Produces: `%LocalAppData%\AtlasSentinel\pending-containment-warning.json` marker (`{"message": "...", "show_at_utc": "..."}`), written by the Service ~30s before applying containment rules, picked up by the Tray's existing local poll loop (the same one that already reads `last-checkin.json` every 15s per this module's established pattern).

- [ ] **Step 1: Add the warning-marker write to `NetworkContainmentService.Apply()`**

Modify `Apply()` in `src/BugsaymisIctAgent.Service/Security/NetworkContainmentService.cs` to write the warning marker to a `%LocalAppData%`-equivalent path *before* the netsh rules are applied. Since the Service runs as SYSTEM and the Tray runs as the logged-in user, the marker must be written to a location both can reach — use `%ProgramData%\BugsaymisIctAgent\pending-containment-warning.json` (SYSTEM-writable, matches the existing `containment.json` location) rather than `%LocalAppData%`, and have the Tray poll that shared path instead. Add a `WriteWarningMarker(string reason)` private method:

```csharp
private void WriteWarningMarker(string reason)
{
    var dir = System.IO.Path.GetDirectoryName(_stateFilePath)!;
    var markerPath = System.IO.Path.Combine(dir, "pending-containment-warning.json");
    var marker = new
    {
        message = $"Atlas Sentinel detected a security issue ({reason}) and will isolate this device from the network.",
        show_at_utc = System.DateTime.UtcNow.ToString("o"),
    };
    System.IO.File.WriteAllText(markerPath, System.Text.Json.JsonSerializer.Serialize(marker));
}
```

Call `WriteWarningMarker(reason)` as the first line of `Apply()`, before `_runner.AddBlockAllExceptRule(...)`. Note: per the approved design, this is a **heads-up, not a delay gate** — containment still applies immediately; there's no 30-second wait built into `Apply()` itself, since a real attack shouldn't be given a 30-second head start. The Tray showing the toast concurrently with (not before) containment taking effect is an accepted, deliberate trade-off — confirmed against the design doc's "informational only" framing.

- [ ] **Step 2: Write `ContainmentWarningWatcher.cs`**

```csharp
// src/BugsaymisIctAgent.Tray/ContainmentWarningWatcher.cs
using System;
using System.IO;
using System.Text.Json;
using System.Windows.Forms;

namespace BugsaymisIctAgent.Tray;

public class ContainmentWarningWatcher
{
    private readonly string _markerPath;
    private readonly NotifyIcon _notifyIcon;
    private DateTime _lastShownUtc = DateTime.MinValue;

    public ContainmentWarningWatcher(string markerPath, NotifyIcon notifyIcon)
    {
        _markerPath = markerPath;
        _notifyIcon = notifyIcon;
    }

    public void PollOnce()
    {
        if (!File.Exists(_markerPath)) return;

        try
        {
            var json = File.ReadAllText(_markerPath);
            var marker = JsonSerializer.Deserialize<WarningMarker>(json);
            if (marker is null) return;

            if (marker.ShowAtUtc <= _lastShownUtc) return; // already shown this one

            _notifyIcon.ShowBalloonTip(
                10000,
                "Atlas Sentinel — Device Isolated",
                marker.Message,
                ToolTipIcon.Warning);

            _lastShownUtc = marker.ShowAtUtc;
        }
        catch (Exception)
        {
            // Best-effort UX notification — never crash the tray over a malformed marker file.
        }
    }

    private class WarningMarker
    {
        public string Message { get; set; } = string.Empty;
        public DateTime ShowAtUtc { get; set; }
    }
}
```

- [ ] **Step 3: Wire `PollOnce()` into the Tray's existing poll timer**

Read the Tray project's existing 15s poll loop (the one already reading `last-checkin.json` per this module's history) and add a `ContainmentWarningWatcher` instance whose `PollOnce()` is called on the same tick, pointed at `%ProgramData%\BugsaymisIctAgent\pending-containment-warning.json`. No cancel button — this is deliberately informational-only per the approved design.

- [ ] **Step 4: Build to verify it compiles**

Run: `dotnet build src/BugsaymisIctAgent.Tray`
Expected: build succeeds. (Full functional verification of the balloon appearing requires a Windows machine/CI per this module's established WPF/Tray testing constraint — cannot be verified on the macOS dev machine.)

- [ ] **Step 5: Commit**

```bash
git add src/BugsaymisIctAgent.Tray/ContainmentWarningWatcher.cs src/BugsaymisIctAgent.Service/Security/NetworkContainmentService.cs
git commit -m "feat(security): add tray warning toast on containment"
```

---

## After all tasks: end-to-end verification (manual, requires Windows VM/CI)

Per the design doc's testing section, this cannot run on the macOS dev machine. On a Windows VM/pilot device:
1. Flip `atlas_sentinel_containment_settings.auto_contain_enabled` to `true` for that one device only (server-side, via tinker or the settings endpoint once built) — do not enable fleet-wide yet.
2. Simulate a connection-rate spike (e.g. a small script opening many outbound connections) and confirm: local detection fires → firewall rules applied → device still reachable via the dashboard → toast appears → `security-incident` POST lands → alert + notification fire.
3. Confirm the device auto-releases after the configured timeout without any admin action.
4. Confirm manual "Isolate Now" / "Release" buttons round-trip correctly via the existing remediation delivery channel.
5. Reboot the device while contained; confirm firewall rules re-apply on Service start.
6. Only after this passes on the pilot device, flip the kill switch fleet-wide.
