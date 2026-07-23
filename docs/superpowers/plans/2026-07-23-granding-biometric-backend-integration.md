# Granding Biometric Backend Integration — Implementation Plan (Plan 1 of 2)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give Atlas Sentinel devices a way to relay guardhouse biometric punches to the backend in real time, so `biometric_logs` fills automatically (no more manual USB→upload), DTR regenerates immediately, and HR/Administrator/OCD see punches live.

**Architecture:** A new Sanctum-authenticated endpoint (`POST /api/ict-agent/biometric-punches`), reusing the exact auth already protecting Atlas Sentinel's `checkin`/`inventory-checkin` routes, accepts raw attlog-format text from a registered "biometric bridge" device. Parsing logic is extracted from the existing manual-upload path into a shared `AttlogLineParser` so both paths agree on format knowledge. New punches trigger `DTRService::generate()` immediately and broadcast a `BiometricPunchRecorded` event (mirroring the existing `AttendanceScanEvent`/`'attendance'` channel pattern used by Student Attendance) to a new private `biometric-feed` channel, consumed by a live panel on the existing `HR/Biometric/Index.vue` page.

This plan produces working, testable software on its own — every step is verifiable with `curl`/feature tests, with no dependency on the physical Granding device or the C# Atlas Sentinel agent (that's Plan 2, which only needs to know the wire contract this plan establishes: `POST /api/ict-agent/biometric-punches` with `{"raw_body": "<attlog text>"}`, and the `biometric_bridge` field this plan adds to the `checkin` response).

**Tech Stack:** Laravel 12 / PHP 8.4, MySQL, Vue 3 + Inertia, Laravel Echo + Soketi (already configured), Sanctum (already configured for Atlas Sentinel devices).

## Global Constraints

- Docker service name for PHP is `php`, not `app`. Run artisan via: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan COMMAND"`.
- All migrations must be additive/backward-compatible (blue-green deploy) — new nullable/defaulted columns and new tables only, no drops/renames.
- Never use `Storage::disk('public')`, never use `FormData`/multipart for uploads (not applicable to this plan — payload is JSON), never use `git add -A`/`git add .`.
- Follow existing test conventions: `RefreshDatabase` trait, real factories/DB writes (no mocking framework is used anywhere in this codebase — do not introduce one).
- Permission strings follow `module.submodule.action`; middleware pipe (`|`) = ANY, comma = ALL.
- Redirect pattern after mutation: `back()->with('success', ...)` (not applicable here — this plan's new controller is a JSON API endpoint, not an Inertia page).
- Only create commits when the plan step says to commit; never `--no-verify`, never force-push.

---

### Task 1: `biometric_devices` migration

**Files:**
- Create: `database/migrations/2026_07_23_100000_create_biometric_devices_table.php`

**Interfaces:**
- Produces: `biometric_devices` table — `id`, `ict_equipment_device_id` (FK, unique), `device_key` (string, unique — matches `biometric_logs.device_id`), `label` (string), `receiver_port` (nullable unsigned smallint), `is_active` (bool, default true), `last_relay_at` (nullable timestamp), timestamps.

- [ ] **Step 1: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_devices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ict_equipment_device_id')
                ->unique()
                ->constrained('ict_equipment_devices')
                ->cascadeOnDelete();

            $table->string('device_key')->unique()
                ->comment('Matches biometric_logs.device_id for records relayed by this bridge');

            $table->string('label')
                ->comment('Human-readable name shown on the live feed, e.g. "Main Gate Guardhouse"');

            $table->unsignedSmallInteger('receiver_port')->nullable()
                ->comment('LAN port the Atlas Sentinel agent listens on for the device ADMS push');

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_relay_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_devices');
    }
};
```

- [ ] **Step 2: Run the migration**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_07_23_100000_create_biometric_devices_table.php"`
Expected: `Migrating: ... create_biometric_devices_table` then `Migrated:` with no errors.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_07_23_100000_create_biometric_devices_table.php
git commit -m "feat(biometric): add biometric_devices table"
```

---

### Task 2: `BiometricDevice` model + bridge-registration Artisan command

**Files:**
- Create: `app/Models/BiometricDevice.php`
- Create: `app/Console/Commands/RegisterBiometricBridge.php`
- Test: `tests/Feature/Console/RegisterBiometricBridgeTest.php`

**Interfaces:**
- Consumes: `ict_equipment_devices` table (Task 1's FK target), already exists.
- Produces: `App\Models\BiometricDevice` with fillable `['ict_equipment_device_id', 'device_key', 'label', 'receiver_port', 'is_active', 'last_relay_at']`, casts `is_active:boolean`, `last_relay_at:datetime`. Artisan command `biometric:register-bridge {ict_equipment_device_id} {device_key} {label} {--port=8090}`.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Console/RegisterBiometricBridgeTest.php"`
Expected: FAIL — class `App\Models\BiometricDevice` (or the command) not found.

- [ ] **Step 3: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricDevice extends Model
{
    protected $table = 'biometric_devices';

    protected $fillable = [
        'ict_equipment_device_id',
        'device_key',
        'label',
        'receiver_port',
        'is_active',
        'last_relay_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_relay_at' => 'datetime',
    ];

    public function bridgeEquipment(): BelongsTo
    {
        return $this->belongsTo(IctEquipmentDevice::class, 'ict_equipment_device_id');
    }
}
```

- [ ] **Step 4: Write the Artisan command**

```php
<?php

namespace App\Console\Commands;

use App\Models\BiometricDevice;
use App\Models\IctEquipmentDevice;
use Illuminate\Console\Command;

class RegisterBiometricBridge extends Command
{
    protected $signature = 'biometric:register-bridge
        {ict_equipment_device_id : ID from ict_equipment_devices for the guardhouse PC running Atlas Sentinel}
        {device_key : Identifier stored on biometric_logs.device_id, e.g. guardhouse-gt200}
        {label : Human-readable name shown on the live feed}
        {--port=8090 : LAN port the agent should listen on for the device ADMS push}';

    protected $description = 'Register (or update) an Atlas Sentinel device as a biometric bridge for a physical Granding terminal.';

    public function handle(): int
    {
        $equipmentDeviceId = (int) $this->argument('ict_equipment_device_id');

        $equipmentDevice = IctEquipmentDevice::find($equipmentDeviceId);
        if (! $equipmentDevice) {
            $this->error("No ict_equipment_devices row with id {$equipmentDeviceId}.");

            return self::FAILURE;
        }

        $bridge = BiometricDevice::updateOrCreate(
            ['ict_equipment_device_id' => $equipmentDeviceId],
            [
                'device_key' => $this->argument('device_key'),
                'label' => $this->argument('label'),
                'receiver_port' => (int) $this->option('port'),
                'is_active' => true,
            ]
        );

        $this->info("Registered biometric bridge #{$bridge->id} ({$bridge->label}) on equipment device #{$equipmentDeviceId}.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Console/RegisterBiometricBridgeTest.php"`
Expected: `PASS` — 3 tests, 0 failures.

- [ ] **Step 6: Commit**

```bash
git add app/Models/BiometricDevice.php app/Console/Commands/RegisterBiometricBridge.php tests/Feature/Console/RegisterBiometricBridgeTest.php
git commit -m "feat(biometric): add BiometricDevice model and bridge-registration command"
```

---

### Task 3: `hr.biometric.monitor` permission

**Files:**
- Modify: `database/seeders/PermissionsSeeder.php:241`
- Modify: `database/seeders/RolePermissionSeeder.php:94` (HR role block)
- Modify: `database/seeders/RolePermissionSeeder.php:184-199` (OCD role block)

**Interfaces:**
- Produces: permission string `hr.biometric.monitor`, granted to roles `HR` and `OCD`. `Administrator` role bypasses all permission checks via `isSuperAdmin()` (no seeder change needed for it).

- [ ] **Step 1: Add the permission definition**

In `database/seeders/PermissionsSeeder.php`, right after the existing line 241 (`'hr.biometric.manage'`), add:

```php
            ['module' => 'HR',     'name' => 'hr.biometric.manage',    'description' => 'Upload and resolve biometric logs'],
            ['module' => 'HR',     'name' => 'hr.biometric.monitor',   'description' => 'View the live biometric punch feed (read-only)'],
```

- [ ] **Step 2: Grant it to the HR role**

In `database/seeders/RolePermissionSeeder.php`, in the `$assign('HR', [...])` block, right after the existing `'hr.biometric.manage',` line (currently line 94), add:

```php
            'hr.dtr.view', 'hr.dtr.manage',
            'hr.biometric.manage',
            'hr.biometric.monitor',
            'dtr.view_own',
```

- [ ] **Step 3: Grant it to the OCD role**

In the `$assign('OCD', [...])` block (currently lines 184-199), add `'hr.biometric.monitor',` after `'dtr.view_own',`:

```php
            'hr.online-punch.record', 'hr.online-punch.monitor', 'hr.face-enrollment.self',
            'dtr.view_own',
            'hr.biometric.monitor',
            'ipcr.view', 'ipcr.monitor', 'ipcr.admin',
```

- [ ] **Step 4: Re-run the affected seeders**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan db:seed --class=PermissionsSeeder && php artisan db:seed --class=RolePermissionSeeder"`
Expected: both commands complete with no errors.

- [ ] **Step 5: Verify in tinker**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan tinker --execute=\"echo App\Models\Permission::where('name','hr.biometric.monitor')->exists() ? 'OK' : 'MISSING';\""`
Expected: `OK`

- [ ] **Step 6: Commit**

```bash
git add database/seeders/PermissionsSeeder.php database/seeders/RolePermissionSeeder.php
git commit -m "feat(biometric): add hr.biometric.monitor permission for HR and OCD"
```

---

### Task 4: Extract `AttlogLineParser`

**Files:**
- Create: `app/Services/HR/AttlogLineParser.php`
- Test: `tests/Unit/Services/HR/AttlogLineParserTest.php`

**Interfaces:**
- Produces: `AttlogLineParser::parseText(string $text): array{rows: array<int, array{device_employee_id: string, log_datetime: string, log_type: string}>, skipped: int}` — pure text-in, structured-data-out, no DB access. `log_type` is one of `'time_in'|'time_out'|'auto'`.
- Consumed by: Task 5 (`BiometricImportService`).

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Unit\Services\HR;

use App\Services\HR\AttlogLineParser;
use Tests\TestCase;

class AttlogLineParserTest extends TestCase
{
    private AttlogLineParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AttlogLineParser();
    }

    public function test_four_field_check_in_line_resolves_to_time_in(): void
    {
        $result = $this->parser->parseText("101\t2026-07-23 07:58:03\t1\t0");

        $this->assertSame(0, $result['skipped']);
        $this->assertSame([
            ['device_employee_id' => '101', 'log_datetime' => '2026-07-23 07:58:03', 'log_type' => 'time_in'],
        ], $result['rows']);
    }

    public function test_four_field_check_out_line_resolves_to_time_out(): void
    {
        $result = $this->parser->parseText("101\t2026-07-23 17:04:31\t1\t1");

        $this->assertSame('time_out', $result['rows'][0]['log_type']);
    }

    public function test_two_field_line_defaults_to_time_in(): void
    {
        $result = $this->parser->parseText("101\t2026-07-23 07:58:03");

        $this->assertSame(0, $result['skipped']);
        $this->assertSame('time_in', $result['rows'][0]['log_type']);
    }

    public function test_slash_delimited_date_is_normalized(): void
    {
        $result = $this->parser->parseText("101\t2026/07/23 07:58:03\t1\t0");

        $this->assertSame('2026-07-23 07:58:03', $result['rows'][0]['log_datetime']);
    }

    public function test_letter_check_type_codes_are_mapped(): void
    {
        $in     = $this->parser->parseText("101\t2026-07-23 07:58:03\t1\tI");
        $out    = $this->parser->parseText("101\t2026-07-23 17:00:00\t1\tO");
        $breakOut = $this->parser->parseText("101\t2026-07-23 12:00:00\t1\tOO");
        $breakIn  = $this->parser->parseText("101\t2026-07-23 13:00:00\t1\tOI");

        $this->assertSame('time_in', $in['rows'][0]['log_type']);
        $this->assertSame('time_out', $out['rows'][0]['log_type']);
        $this->assertSame('time_out', $breakOut['rows'][0]['log_type']);
        $this->assertSame('time_in', $breakIn['rows'][0]['log_type']);
    }

    public function test_header_lines_are_ignored_without_counting_as_skipped(): void
    {
        $result = $this->parser->parseText("PIN\tName\tDate/Time\n101\t2026-07-23 07:58:03\t1\t0");

        $this->assertSame(0, $result['skipped']);
        $this->assertCount(1, $result['rows']);
    }

    public function test_blank_lines_are_ignored_without_counting_as_skipped(): void
    {
        $result = $this->parser->parseText("101\t2026-07-23 07:58:03\t1\t0\n\n\n");

        $this->assertSame(0, $result['skipped']);
        $this->assertCount(1, $result['rows']);
    }

    public function test_leading_bom_is_stripped(): void
    {
        $result = $this->parser->parseText("\xEF\xBB\xBF101\t2026-07-23 07:58:03\t1\t0");

        $this->assertSame('101', $result['rows'][0]['device_employee_id']);
    }

    public function test_unparseable_datetime_is_counted_as_skipped(): void
    {
        $result = $this->parser->parseText("101\tnot-a-date\t1\t0");

        $this->assertSame(1, $result['skipped']);
        $this->assertSame([], $result['rows']);
    }

    public function test_multiline_text_aggregates_rows_and_skipped_correctly(): void
    {
        $text = implode("\n", [
            'PIN	Name	Date/Time',
            '101	2026-07-23 07:58:03	1	0',
            '',
            '102	garbage	1	0',
            '103	2026-07-23 08:01:15	1	1',
        ]);

        $result = $this->parser->parseText($text);

        $this->assertSame(1, $result['skipped']);
        $this->assertCount(2, $result['rows']);
        $this->assertSame('101', $result['rows'][0]['device_employee_id']);
        $this->assertSame('103', $result['rows'][1]['device_employee_id']);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Unit/Services/HR/AttlogLineParserTest.php"`
Expected: FAIL — class `App\Services\HR\AttlogLineParser` not found.

- [ ] **Step 3: Write the parser**

```php
<?php

namespace App\Services\HR;

use Carbon\Carbon;

/**
 * Decodes the Granding biometric attlog text format into normalized punch
 * rows. Shared by the manual .dat file upload path
 * (BiometricImportService::parse) and the live Atlas Sentinel bridge ingest
 * path (BiometricImportService::ingestApiPunches) so both agree on the
 * exact same format knowledge.
 *
 * Confirmed format:
 *   Column 1 — badge / employee number
 *   Column 2 — date and time  (YYYY-MM-DD HH:MM:SS  or  YYYY/MM/DD HH:MM:SS)
 *   Column 3 — check-type code (optional)
 *
 * Delimiter: tab. When single-space-delimited, the date and time arrive as
 * two separate tokens — not handled here (matches prior behavior).
 *
 * check_type codes:
 *   0 / "I"  = Check In        1 / "O"  = Check Out
 *   2 / "OO" = Break Out        3 / "OI" = Break In
 *   4        = Overtime In      5        = Overtime Out
 */
class AttlogLineParser
{
    /** @return array{rows: array<int, array{device_employee_id: string, log_datetime: string, log_type: string}>, skipped: int} */
    public function parseText(string $text): array
    {
        $rows    = [];
        $skipped = 0;
        $lines   = preg_split('/\r\n|\r|\n/', $text) ?: [];

        foreach ($lines as $lineRaw) {
            $line = ltrim($lineRaw, "\xEF\xBB\xBF");
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if ($this->isHeaderLine($line)) {
                continue;
            }

            $parsed = $this->parseLine($line);
            if ($parsed === null) {
                $skipped++;
                continue;
            }

            $rows[] = $parsed;
        }

        return ['rows' => $rows, 'skipped' => $skipped];
    }

    private function isHeaderLine(string $line): bool
    {
        $lower = strtolower($line);

        if (str_starts_with($lower, 'no.')
            || str_starts_with($lower, 'enrollment')
            || str_starts_with($lower, 'userid')
            || str_starts_with($lower, 'pin')
            || str_starts_with($lower, 'employee')
            || str_starts_with($lower, 'date')
            || str_starts_with($lower, 'name')
        ) {
            return true;
        }

        return false;
    }

    /** @return array{device_employee_id: string, log_datetime: string, log_type: string}|null */
    private function parseLine(string $line): ?array
    {
        $fields = array_map('trim', explode("\t", $line));

        if (count($fields) < 2) {
            return null;
        }

        $badgeNo = $fields[0];
        $dtRaw   = str_replace('/', '-', $fields[1]);

        if (! preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}(?::\d{2})?)$/', $dtRaw, $dtMatch)) {
            return null;
        }
        $logDatetime = $dtMatch[1] . ' ' . $dtMatch[2];

        if (isset($fields[3]) && $fields[3] !== '') {
            $rawLogCode = $fields[3];
        } elseif (isset($fields[2]) && $fields[2] !== '') {
            $rawLogCode = $fields[2];
        } else {
            $rawLogCode = '0';
        }

        try {
            $fmt = substr_count($logDatetime, ':') === 1 ? 'Y-m-d H:i' : 'Y-m-d H:i:s';
            $dt  = Carbon::createFromFormat($fmt, $logDatetime);
            if (! $dt) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        return [
            'device_employee_id' => $badgeNo,
            'log_datetime'       => $dt->format('Y-m-d H:i:s'),
            'log_type'           => $this->resolveLogType($rawLogCode),
        ];
    }

    private function resolveLogType(mixed $raw): string
    {
        $s = strtoupper(trim((string) $raw));

        if (in_array($s, ['0', 'I', 'CI', 'IN', '4'], true)) {
            return 'time_in';
        }
        if (in_array($s, ['1', 'O', 'CO', 'OUT', '5'], true)) {
            return 'time_out';
        }
        if (in_array($s, ['2', 'OO', 'BO'], true)) {
            return 'time_out';
        }
        if (in_array($s, ['3', 'OI', 'BI'], true)) {
            return 'time_in';
        }

        return 'auto';
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Unit/Services/HR/AttlogLineParserTest.php"`
Expected: `PASS` — 10 tests, 0 failures.

- [ ] **Step 5: Commit**

```bash
git add app/Services/HR/AttlogLineParser.php tests/Unit/Services/HR/AttlogLineParserTest.php
git commit -m "feat(biometric): extract AttlogLineParser from BiometricImportService"
```

---

### Task 5: Refactor `BiometricImportService` to use the shared parser and add `ingestApiPunches()`

**Files:**
- Modify: `app/Services/HR/BiometricImportService.php` (full rewrite of `parse()`, `parseLine()`, `isHeaderLine()`, `resolveLogType()` removed; `batchInsert()` extended)
- Test: `tests/Feature/HR/BiometricImportServiceTest.php`

**Interfaces:**
- Consumes: `AttlogLineParser::parseText()` (Task 4).
- Produces: `BiometricImportService::parse(string $filePath, string $importBatch, ?string $deviceId = null): array` (unchanged public signature, existing callers in `BiometricLogController::upload` keep working). New method `BiometricImportService::ingestApiPunches(string $rawBody, string $deviceKey): array`. Both return stats shaped `['inserted' => int, 'resolved' => int, 'unresolved' => int, 'duplicates' => int, 'skipped' => int, 'new_rows' => array<int, array{device_employee_id: string, user_id: ?int, device_id: ?string, log_datetime: string, log_type: string, source: string, is_resolved: bool}>]`. `new_rows` is consumed by Task 7.

- [ ] **Step 1: Write the failing tests**

```php
<?php

namespace Tests\Feature\HR;

use App\Models\HR\BiometricLog;
use App\Models\User;
use App\Services\HR\BiometricImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiometricImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_reads_a_file_resolves_known_badges_and_flags_unknown_ones(): void
    {
        $user = User::factory()->create(['badge_id' => '101']);

        $path = tempnam(sys_get_temp_dir(), 'attlog');
        file_put_contents($path, "101\t2026-07-23 07:58:03\t1\t0\n999\t2026-07-23 08:00:00\t1\t0\n");

        $stats = app(BiometricImportService::class)->parse($path, 'batch-1', 'guardhouse-gt200');

        $this->assertSame(2, $stats['inserted']);
        $this->assertSame(1, $stats['resolved']);
        $this->assertSame(1, $stats['unresolved']);
        $this->assertSame(0, $stats['duplicates']);

        $this->assertDatabaseHas('biometric_logs', [
            'device_employee_id' => '101',
            'user_id' => $user->id,
            'device_id' => 'guardhouse-gt200',
            'log_datetime' => '2026-07-23 07:58:03',
            'log_type' => 'time_in',
            'source' => 'biometric',
            'import_batch' => 'batch-1',
            'is_resolved' => 1,
        ]);

        $this->assertDatabaseHas('biometric_logs', [
            'device_employee_id' => '999',
            'user_id' => null,
            'is_resolved' => 0,
        ]);

        unlink($path);
    }

    public function test_parse_deduplicates_against_existing_rows(): void
    {
        $user = User::factory()->create(['badge_id' => '101']);

        BiometricLog::create([
            'device_employee_id' => '101',
            'user_id' => $user->id,
            'device_id' => 'guardhouse-gt200',
            'log_datetime' => '2026-07-23 07:58:03',
            'log_type' => 'time_in',
            'source' => 'biometric',
            'is_resolved' => true,
            'is_duplicate' => false,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'attlog');
        file_put_contents($path, "101\t2026-07-23 07:58:03\t1\t0\n");

        $stats = app(BiometricImportService::class)->parse($path, 'batch-2', 'guardhouse-gt200');

        $this->assertSame(0, $stats['inserted']);
        $this->assertSame(1, $stats['duplicates']);
        $this->assertSame(1, BiometricLog::count());

        unlink($path);
    }

    public function test_ingest_api_punches_writes_rows_with_api_source_and_no_import_batch(): void
    {
        $user = User::factory()->create(['badge_id' => '101']);

        $stats = app(BiometricImportService::class)->ingestApiPunches(
            "101\t2026-07-23 07:58:03\t1\t0",
            'guardhouse-gt200'
        );

        $this->assertSame(1, $stats['inserted']);
        $this->assertCount(1, $stats['new_rows']);
        $this->assertSame($user->id, $stats['new_rows'][0]['user_id']);
        $this->assertSame('time_in', $stats['new_rows'][0]['log_type']);

        $this->assertDatabaseHas('biometric_logs', [
            'device_employee_id' => '101',
            'device_id' => 'guardhouse-gt200',
            'source' => 'api',
            'import_batch' => null,
        ]);
    }

    public function test_ingest_api_punches_deduplicates_against_a_prior_file_import_of_the_same_punch(): void
    {
        $user = User::factory()->create(['badge_id' => '101']);

        BiometricLog::create([
            'device_employee_id' => '101',
            'user_id' => $user->id,
            'device_id' => 'guardhouse-gt200',
            'log_datetime' => '2026-07-23 07:58:03',
            'log_type' => 'time_in',
            'source' => 'biometric',
            'is_resolved' => true,
            'is_duplicate' => false,
        ]);

        $stats = app(BiometricImportService::class)->ingestApiPunches(
            "101\t2026-07-23 07:58:03\t1\t0",
            'guardhouse-gt200'
        );

        $this->assertSame(0, $stats['inserted']);
        $this->assertSame(1, $stats['duplicates']);
        $this->assertSame(1, BiometricLog::count());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/BiometricImportServiceTest.php"`
Expected: FAIL — `ingestApiPunches` method does not exist (the `parse` tests may or may not already pass against the current implementation; either way, run again after Step 3).

- [ ] **Step 3: Rewrite `BiometricImportService`**

Replace the full file contents:

```php
<?php

namespace App\Services\HR;

use App\Models\HR\BiometricLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BiometricImportService
{
    private array $stats = [
        'inserted'   => 0,
        'resolved'   => 0,
        'unresolved' => 0,
        'duplicates' => 0,
        'skipped'    => 0,
        'new_rows'   => [],
    ];

    public function __construct(private readonly AttlogLineParser $parser)
    {
    }

    /**
     * Parse a Granding biometric .dat export file (manual upload path).
     */
    public function parse(string $filePath, string $importBatch, ?string $deviceId = null): array
    {
        if (! file_exists($filePath)) {
            Log::warning('BiometricImportService: file not found', ['path' => $filePath]);

            return $this->resetStats();
        }

        $text = file_get_contents($filePath);

        Log::info('BiometricImport sample lines', [
            'lines' => array_slice(preg_split('/\r\n|\r|\n/', $text) ?: [], 0, 3),
            'batch' => $importBatch,
        ]);

        return $this->ingest($text, $importBatch, $deviceId, 'biometric');
    }

    /**
     * Ingest punches relayed live by an Atlas Sentinel biometric bridge.
     */
    public function ingestApiPunches(string $rawBody, string $deviceKey): array
    {
        return $this->ingest($rawBody, null, $deviceKey, 'api');
    }

    private function ingest(string $text, ?string $importBatch, ?string $deviceId, string $source): array
    {
        $this->resetStats();

        $userMap = User::whereNotNull('badge_id')
            ->pluck('id', 'badge_id')
            ->toArray();

        $parsed = $this->parser->parseText($text);
        $this->stats['skipped'] = $parsed['skipped'];

        $rows = [];
        foreach ($parsed['rows'] as $p) {
            $userId = $userMap[$p['device_employee_id']] ?? null;

            $rows[] = [
                'device_employee_id' => $p['device_employee_id'],
                'user_id'            => $userId,
                'device_id'          => $deviceId,
                'log_datetime'       => $p['log_datetime'],
                'log_type'           => $p['log_type'],
                'source'             => $source,
                'is_resolved'        => $userId !== null,
                'is_duplicate'       => false,
                'import_batch'       => $importBatch,
                'imported_at'        => now()->format('Y-m-d H:i:s'),
                'created_at'         => now()->format('Y-m-d H:i:s'),
                'updated_at'         => now()->format('Y-m-d H:i:s'),
            ];

            if (count($rows) >= 500) {
                $this->batchInsert($rows);
                $rows = [];
            }
        }

        if (! empty($rows)) {
            $this->batchInsert($rows);
        }

        return $this->stats;
    }

    private function resetStats(): array
    {
        return $this->stats = [
            'inserted'   => 0,
            'resolved'   => 0,
            'unresolved' => 0,
            'duplicates' => 0,
            'skipped'    => 0,
            'new_rows'   => [],
        ];
    }

    private function batchInsert(array $rows): void
    {
        $existing = DB::table('biometric_logs')
            ->where(function ($q) use ($rows) {
                foreach ($rows as $r) {
                    $q->orWhere(function ($q2) use ($r) {
                        $q2->where('device_employee_id', $r['device_employee_id'])
                           ->where('log_datetime', $r['log_datetime'])
                           ->where(fn ($q3) => $r['device_id']
                               ? $q3->where('device_id', $r['device_id'])
                               : $q3->whereNull('device_id'));
                    });
                }
            })
            ->select('device_employee_id', 'log_datetime')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->device_employee_id . '|' . $r->log_datetime => true])
            ->toArray();

        $toInsert = [];

        foreach ($rows as $row) {
            $dupKey = $row['device_employee_id'] . '|' . $row['log_datetime'];

            if (isset($existing[$dupKey])) {
                $this->stats['duplicates']++;
                continue;
            }

            $toInsert[] = $row;

            if ($row['is_resolved']) {
                $this->stats['resolved']++;
            } else {
                $this->stats['unresolved']++;
            }
        }

        if (! empty($toInsert)) {
            DB::table('biometric_logs')->insertOrIgnore($toInsert);
            $this->stats['inserted'] += count($toInsert);
            $this->stats['new_rows'] = array_merge($this->stats['new_rows'], $toInsert);
        }
    }

    public function resolveByDeviceId(string $deviceEmployeeId, int $userId): int
    {
        return BiometricLog::where('device_employee_id', $deviceEmployeeId)
            ->where('is_resolved', false)
            ->update(['user_id' => $userId, 'is_resolved' => true]);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/HR/BiometricImportServiceTest.php tests/Unit/Services/HR/AttlogLineParserTest.php"`
Expected: `PASS` — all tests green (both the new tests and the parser tests from Task 4, confirming the refactor didn't break format handling).

- [ ] **Step 5: Commit**

```bash
git add app/Services/HR/BiometricImportService.php tests/Feature/HR/BiometricImportServiceTest.php
git commit -m "refactor(biometric): use AttlogLineParser and add ingestApiPunches for live bridge ingest"
```

---

### Task 6: `BiometricPunchRecorded` broadcast event + channel authorization

**Files:**
- Create: `app/Events/BiometricPunchRecorded.php`
- Modify: `routes/channels.php`

**Interfaces:**
- Produces: `App\Events\BiometricPunchRecorded` (implements `ShouldBroadcastNow`), constructed with `array $payload`, broadcasts on `PrivateChannel('biometric-feed')` as `'biometric.punch.recorded'`. Private channel `biometric-feed` authorized for `isSuperAdmin()` or `hasAnyPermission(['hr.biometric.monitor', 'hr.biometric.manage'])`.
- Consumed by: Task 7 (dispatch), Task 9 (frontend `Echo.private('biometric-feed').listen('.biometric.punch.recorded', ...)`).

- [ ] **Step 1: Write the event**

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired immediately after a biometric punch is ingested (file import or
 * live Atlas Sentinel bridge). Broadcasts on the private 'biometric-feed'
 * channel so the HR/Administrator/OCD live monitor updates in real time.
 */
class BiometricPunchRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly array $payload,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('biometric-feed'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'biometric.punch.recorded';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
```

- [ ] **Step 2: Authorize the channel**

In `routes/channels.php`, add after the existing `'attendance'` channel:

```php
Broadcast::channel('biometric-feed', function ($user) {
    return $user->isSuperAdmin() || $user->hasAnyPermission(['hr.biometric.monitor', 'hr.biometric.manage']);
});
```

- [ ] **Step 3: Commit**

```bash
git add app/Events/BiometricPunchRecorded.php routes/channels.php
git commit -m "feat(biometric): add BiometricPunchRecorded broadcast event and channel auth"
```

---

### Task 7: `POST /api/ict-agent/biometric-punches` endpoint

**Files:**
- Create: `app/Http/Controllers/Api/BiometricPunchIngestController.php`
- Modify: `routes/api.php` (add route inside the existing `ict-agent` `auth:sanctum` group)
- Test: `tests/Feature/Api/BiometricPunchIngestTest.php`

**Interfaces:**
- Consumes: `BiometricImportService::ingestApiPunches()` (Task 5), `BiometricDevice` (Task 2), `BiometricPunchRecorded` (Task 6), `DTRService::generate(int $userId, string $dateFrom, string $dateTo): void` (existing, unchanged).
- Produces: the wire contract Plan 2's agent will call — `POST /api/ict-agent/biometric-punches` with header `Authorization: Bearer <device_token>` and JSON body `{"raw_body": "<attlog text>"}`; responds `200 {"status":"ok","inserted":N,"resolved":N,"unresolved":N,"duplicates":N,"skipped":N}`; responds `403` if the authenticated device has no active `BiometricDevice` row.

- [ ] **Step 1: Write the failing tests**

```php
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
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Api/BiometricPunchIngestTest.php"`
Expected: FAIL — route `ict-agent.biometric-punches.store` (or the controller class) does not exist / 404.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Events\BiometricPunchRecorded;
use App\Http\Controllers\Controller;
use App\Models\BiometricDevice;
use App\Models\User;
use App\Services\HR\BiometricImportService;
use App\Services\HR\DTRService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BiometricPunchIngestController extends Controller
{
    public function store(Request $request, BiometricImportService $importService, DTRService $dtrService): JsonResponse
    {
        $bridge = BiometricDevice::where('ict_equipment_device_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if (! $bridge) {
            abort(403, 'This Atlas Sentinel device is not registered as a biometric bridge.');
        }

        $validated = $request->validate([
            'raw_body' => ['required', 'string', 'max:100000'],
        ]);

        $stats = $importService->ingestApiPunches($validated['raw_body'], $bridge->device_key);

        $bridge->update(['last_relay_at' => now()]);

        $userNames = User::whereIn(
            'id',
            array_values(array_filter(array_column($stats['new_rows'], 'user_id')))
        )->pluck('name', 'id');

        foreach ($stats['new_rows'] as $row) {
            if ($row['user_id']) {
                $date = Carbon::parse($row['log_datetime'])->toDateString();
                $dtrService->generate((int) $row['user_id'], $date, $date);
            }

            rescue(fn () => event(new BiometricPunchRecorded([
                'user_id'            => $row['user_id'],
                'user_name'          => $row['user_id'] ? ($userNames[$row['user_id']] ?? null) : null,
                'device_employee_id' => $row['device_employee_id'],
                'device_label'       => $bridge->label,
                'log_type'           => $row['log_type'],
                'log_datetime'       => $row['log_datetime'],
                'is_resolved'        => $row['is_resolved'],
            ])));
        }

        return response()->json(['status' => 'ok'] + collect($stats)->except('new_rows')->all());
    }
}
```

- [ ] **Step 4: Add the route**

In `routes/api.php`, add the import near the top (alongside the other `Api\AtlasSentinel*` imports):

```php
use App\Http\Controllers\Api\BiometricPunchIngestController;
```

Then inside the existing `Route::middleware(['auth:sanctum', 'ict-agent'])->group(function () { ... })` block (the one starting at line 150), add:

```php
        Route::post('/biometric-punches', [BiometricPunchIngestController::class, 'store'])->name('biometric-punches.store');
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Api/BiometricPunchIngestTest.php"`
Expected: `PASS` — 5 tests, 0 failures.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/BiometricPunchIngestController.php routes/api.php tests/Feature/Api/BiometricPunchIngestTest.php
git commit -m "feat(biometric): add POST /api/ict-agent/biometric-punches ingest endpoint"
```

---

### Task 8: Expose `biometric_bridge` info on the Atlas Sentinel `checkin` response

**Files:**
- Modify: `app/Http/Controllers/Api/AtlasSentinelController.php` (in `checkin()`, after the existing backup-dispatch `try`/`catch` block, before `$latestRelease = ...`)
- Test: `tests/Feature/Api/AtlasSentinelCheckinBiometricBridgeTest.php`

**Interfaces:**
- Consumes: `BiometricDevice` (Task 2).
- Produces: when the checking-in device has an active `BiometricDevice` row, the `checkin` JSON response gains `"biometric_bridge": {"device_key": string, "label": string, "receiver_port": int|null}`. This is the exact field Plan 2's agent reads to activate its ADMS receiver — no other part of this plan depends on it.

- [ ] **Step 1: Write the failing test**

```php
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
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Api/AtlasSentinelCheckinBiometricBridgeTest.php"`
Expected: FAIL on `test_checkin_includes_biometric_bridge_for_a_registered_bridge_device` (key `biometric_bridge` missing from the response).

- [ ] **Step 3: Add the import**

At the top of `app/Http/Controllers/Api/AtlasSentinelController.php`, add:

```php
use App\Models\BiometricDevice;
```

- [ ] **Step 4: Add the response field**

In `checkin()`, immediately after the existing block:

```php
        // A backup hiccup must never take health monitoring down with it.
        try {
            $backup = $this->backupService->pendingBackup($device);
            if ($backup) {
                $response['backup'] = $backup;
            }
        } catch (\Throwable $e) {
            logger()->error('Atlas Sentinel: backup dispatch failed during checkin', [
                'device_id' => $device->id,
                'error' => $e->getMessage(),
            ]);
        }
```

insert:

```php
        $biometricBridge = BiometricDevice::where('ict_equipment_device_id', $device->id)
            ->where('is_active', true)
            ->first();
        if ($biometricBridge) {
            $response['biometric_bridge'] = [
                'device_key'    => $biometricBridge->device_key,
                'label'         => $biometricBridge->label,
                'receiver_port' => $biometricBridge->receiver_port,
            ];
        }
```

(This goes before the existing `$latestRelease = AtlasSentinelRelease::latestRelease();` line.)

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Api/AtlasSentinelCheckinBiometricBridgeTest.php"`
Expected: `PASS` — 3 tests, 0 failures.

- [ ] **Step 6: Run the full Atlas Sentinel + biometric test suite to catch regressions**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/AtlasSentinelEnrollmentTest.php tests/Feature/Api/ tests/Feature/HR/BiometricImportServiceTest.php tests/Feature/Console/RegisterBiometricBridgeTest.php tests/Unit/Services/HR/AttlogLineParserTest.php"`
Expected: `PASS` — all green.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Api/AtlasSentinelController.php tests/Feature/Api/AtlasSentinelCheckinBiometricBridgeTest.php
git commit -m "feat(biometric): surface biometric_bridge instruction on Atlas Sentinel checkin"
```

---

### Task 9: Live punch feed panel on `HR/Biometric/Index.vue`

**Files:**
- Modify: `resources/js/Pages/HR/Biometric/Index.vue`

**Interfaces:**
- Consumes: `window.Echo.private('biometric-feed').listen('.biometric.punch.recorded', payload)` where `payload` matches `BiometricPunchRecorded::broadcastWith()` from Task 6/7: `{user_id, user_name, device_employee_id, device_label, log_type, log_datetime, is_resolved}`.
- No new props/route needed — this is purely a client-side real-time panel added to the existing page.

- [ ] **Step 1: Read the current script setup block**

Open `resources/js/Pages/HR/Biometric/Index.vue` and locate the `<script setup>` imports and the top of the component state (where `uploadForm`, `resolveModal`, etc. are declared).

- [ ] **Step 2: Add live-feed state and Echo subscription**

Add to the imports:

```js
import { ref, onMounted, onUnmounted } from 'vue'
```

(merge with any existing Vue import line rather than duplicating it — if `ref` etc. are already imported, just add `onMounted, onUnmounted` to that same line.)

Add near the other `ref()` declarations:

```js
const livePunches = ref([])
const MAX_LIVE_PUNCHES = 25

function subscribeToLiveFeed() {
  if (!window.Echo) return
  window.Echo.private('biometric-feed')
    .listen('.biometric.punch.recorded', (payload) => {
      livePunches.value.unshift({
        key: `${payload.device_employee_id}-${payload.log_datetime}-${Date.now()}`,
        ...payload,
      })
      if (livePunches.value.length > MAX_LIVE_PUNCHES) {
        livePunches.value.pop()
      }
    })
}

onMounted(() => {
  subscribeToLiveFeed()
})

onUnmounted(() => {
  window.Echo?.leaveChannel('private-biometric-feed')
})

function formatLiveTime(iso) {
  return new Date(iso.replace(' ', 'T')).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' })
}
```

- [ ] **Step 3: Add the panel to the template**

In the `<template>`, right after the `<AppPageHeader ... />` line, add:

```html
      <div class="rounded-lg border border-slate-200 bg-white p-4 mb-6">
        <h3 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-3">Live Punch Feed</h3>
        <p v-if="livePunches.length === 0" class="text-sm text-slate-400">
          Waiting for punches from the guardhouse device…
        </p>
        <ul v-else class="divide-y divide-slate-100">
          <li v-for="p in livePunches" :key="p.key" class="py-2 flex items-center justify-between text-sm">
            <div class="flex items-center gap-2">
              <span
                class="inline-block w-2 h-2 rounded-full"
                :class="p.is_resolved ? 'bg-indigo-600' : 'bg-amber-500'"
              ></span>
              <span class="font-medium text-slate-700">
                {{ p.is_resolved ? p.user_name : `Unresolved badge ${p.device_employee_id}` }}
              </span>
              <span class="text-slate-400">{{ p.log_type === 'time_in' ? 'Time In' : p.log_type === 'time_out' ? 'Time Out' : 'Punch' }}</span>
            </div>
            <div class="text-slate-400">
              {{ p.device_label }} · {{ formatLiveTime(p.log_datetime) }}
            </div>
          </li>
        </ul>
      </div>
```

- [ ] **Step 4: Build and manually verify in the browser**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build completes with no errors.

Then, per this project's convention for UI changes, start the dev server and open `/hr/biometric` as a user with `hr.biometric.manage` or `hr.biometric.monitor`, confirm the "Live Punch Feed" panel renders with the "Waiting for punches…" placeholder and no console errors. Full end-to-end confirmation (a real punch actually appearing) happens in Task 10, since it requires triggering the Task 7 endpoint.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/HR/Biometric/Index.vue
git commit -m "feat(biometric): add live punch feed panel to Biometric Logs page"
```

---

### Task 10: End-to-end manual verification

No new files — this task confirms Tasks 1-9 work together before calling Plan 1 done.

- [ ] **Step 1: Register a test bridge device**

Run (adjust the `ict_equipment_device_id` to any existing enrolled Atlas Sentinel device in dev, or create one first via the ICT Equipments enrollment flow):

```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan biometric:register-bridge <ict_equipment_device_id> guardhouse-gt200 'Main Gate Guardhouse' --port=8090"
```

Expected: `Registered biometric bridge #... (Main Gate Guardhouse) on equipment device #...`

- [ ] **Step 2: Mint a device token for that device and confirm checkin returns the bridge instruction**

```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan tinker --execute=\"echo App\Models\IctEquipmentDevice::find(<ict_equipment_device_id>)->createToken('bridge-test')->plainTextToken;\""
```

Copy the printed token, then:

```bash
curl -s -X POST http://localhost:8080/api/ict-agent/checkin \
  -H "Authorization: Bearer <token>" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{}' | python3 -m json.tool
```

Expected: JSON response includes `"biometric_bridge": {"device_key": "guardhouse-gt200", "label": "Main Gate Guardhouse", "receiver_port": 8090}`.

- [ ] **Step 3: Simulate a punch push and confirm end-to-end wiring**

Ensure a real employee has a `badge_id` set (or set one via tinker: `App\Models\User::find(<id>)->update(['badge_id' => 'TESTBADGE1'])`), then:

```bash
curl -s -X POST http://localhost:8080/api/ict-agent/biometric-punches \
  -H "Authorization: Bearer <token>" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d "{\"raw_body\": \"TESTBADGE1\t$(date '+%Y-%m-%d %H:%M:%S')\t1\t0\"}" | python3 -m json.tool
```

Expected: `{"status": "ok", "inserted": 1, "resolved": 1, "unresolved": 0, "duplicates": 0, "skipped": 0}`.

- [ ] **Step 4: Confirm the DTR and live feed**

- Check `dtr_records` for that user/today has a `time_in_am` populated.
- With the `/hr/biometric` page open in a browser (logged in as HR/Administrator/OCD), re-run Step 3's curl with a new timestamp and confirm the punch appears in the "Live Punch Feed" panel within a second or two, without a page refresh.

- [ ] **Step 5: Revoke the test token**

```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan tinker --execute=\"App\Models\IctEquipmentDevice::find(<ict_equipment_device_id>)->tokens()->delete();\""
```

This task has no commit — it's a verification checkpoint. Plan 1 is complete once Steps 1-4 all match expected output.

---

## Self-Review Notes

- **Spec coverage:** data flow (Tasks 5-8), Phase 0 prerequisite (out of scope for this plan — it's Plan 2's concern, called out explicitly), agent-side changes (Plan 2), backend changes (Tasks 1-8), frontend (Task 9), edge cases — unresolved badge (Task 7 test), duplicate punches (Task 7 test), agent restart/dedup (covered by the existing unique index, exercised in Task 5/7 tests) — all covered. Device-health "online/stale" badge beyond `last_relay_at` timestamp was explicitly out of scope in the design doc and is not built here.
- **Placeholder scan:** none found — every step has complete code or an exact command with expected output.
- **Type consistency:** `BiometricImportService::ingestApiPunches(string $rawBody, string $deviceKey): array` (Task 5) matches the call in `BiometricPunchIngestController::store()` (Task 7). `AttlogLineParser::parseText(string $text): array{rows, skipped}` (Task 4) matches its only consumer in `BiometricImportService::ingest()` (Task 5). `BiometricPunchRecorded::__construct(array $payload)` (Task 6) matches the payload shape constructed in Task 7 and read in Task 9's frontend listener.
