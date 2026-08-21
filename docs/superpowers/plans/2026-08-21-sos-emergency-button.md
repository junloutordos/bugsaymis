# SOS Emergency Button Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship Phase A of the SOS Emergency Button — backend alert lifecycle/escalation engine, staff (Atlas web) and student (Student Portal) triggers, a real-time responder Command Center, and admin-configurable escalation tiers/external contacts.

**Architecture:** New `Sos` domain (migrations, models, service-layer state machine) reused by two thin controllers (staff web, Student Portal). Trigger creates a `SosAlert` row, broadcasts synchronously on a permission-gated `sos-responders` Echo channel, and queues notification jobs (SMS/email/push) so a gateway outage never blocks the alert. A minute-by-minute cron sweep (`sos:process-escalations`) advances unacknowledged alerts through admin-configured tiers, mirroring the proven `AtlasSentinelAutoReleaseContainments` pattern.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 (`<script setup>`) + Inertia.js 2, Laravel Echo + Soketi (Pusher driver), existing `SmsGateService` / `FcmService` / `CampusPresenceService`.

**Spec:** `docs/superpowers/specs/2026-08-21-sos-emergency-button-design.md`

## Global Constraints

- Additive migrations only — no destructive schema changes (per this project's blue-green migration discipline).
- File uploads (none in this feature) would need base64 JSON per project convention — N/A here, no file uploads in Phase A.
- `Storage::disk('s3')` only if storage were needed — N/A, no file storage in this feature.
- Controllers return `Inertia::render(...)` for full pages; mutation/action endpoints consumed by live JS (Command Center, floating button) return JSON, matching the existing `api/notifications` pattern.
- Never use `new DateTime()` with Eloquent date-cast attributes — use `Carbon::parse($value)->format(...)`.
- Permission strings follow `module.submodule.action` (`sos.trigger`, `sos.respond`, `sos.manage`).
- New seeders are **manual-run only** in production (not registered in `DatabaseSeeder.php`), matching `LostFoundPermissionSeeder`/`ErrorReportPermissionSeeder` precedent — run via ECS exec after deploy.
- Philippine locale (`en-PH`) for any displayed dates in the frontend.

---

## Task 1: Migrations — full `Sos` schema + `employee_profiles.mobile_number`

**Files:**
- Create: `database/migrations/2026_08_21_170000_create_sos_alerts_table.php`
- Create: `database/migrations/2026_08_21_170100_create_sos_alert_events_table.php`
- Create: `database/migrations/2026_08_21_170200_create_sos_notification_logs_table.php`
- Create: `database/migrations/2026_08_21_170300_create_sos_escalation_tiers_table.php`
- Create: `database/migrations/2026_08_21_170400_create_sos_escalation_tier_users_table.php`
- Create: `database/migrations/2026_08_21_170500_create_sos_external_contacts_table.php`
- Create: `database/migrations/2026_08_21_170600_add_mobile_number_to_employee_profiles_table.php`
- Test: `tests/Feature/Sos/SosSchemaTest.php`

**Interfaces:**
- Produces tables: `sos_alerts` (`id, triggerable_type, triggerable_id, alert_type, is_silent, status, lat, lng, accuracy, geofence_zone_id, current_tier_order, triggered_at, resolved_at, resolved_by, resolution_notes, timestamps`), `sos_alert_events` (`id, sos_alert_id, type, actor_type, actor_id, payload, created_at`), `sos_notification_logs` (`id, sos_alert_id, channel, recipient_type, recipient_id, recipient_label, sent, sent_at, timestamps`), `sos_escalation_tiers` (`id, alert_type, order, role_id, timeout_minutes, channels, notify_external, timestamps`), `sos_escalation_tier_users` (`id, sos_escalation_tier_id, user_id, timestamps`), `sos_external_contacts` (`id, name, org, phone, email, alert_types, channel, active, timestamps`); `employee_profiles.mobile_number` (nullable string).

- [ ] **Step 1: Write the failing schema test**

```php
<?php

namespace Tests\Feature\Sos;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SosSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_sos_tables_and_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('sos_alerts', [
            'triggerable_type', 'triggerable_id', 'alert_type', 'is_silent', 'status',
            'lat', 'lng', 'accuracy', 'geofence_zone_id', 'current_tier_order',
            'triggered_at', 'resolved_at', 'resolved_by', 'resolution_notes',
        ]));

        $this->assertTrue(Schema::hasColumns('sos_alert_events', [
            'sos_alert_id', 'type', 'actor_type', 'actor_id', 'payload', 'created_at',
        ]));

        $this->assertTrue(Schema::hasColumns('sos_notification_logs', [
            'sos_alert_id', 'channel', 'recipient_type', 'recipient_id', 'recipient_label', 'sent', 'sent_at',
        ]));

        $this->assertTrue(Schema::hasColumns('sos_escalation_tiers', [
            'alert_type', 'order', 'role_id', 'timeout_minutes', 'channels', 'notify_external',
        ]));

        $this->assertTrue(Schema::hasColumns('sos_escalation_tier_users', [
            'sos_escalation_tier_id', 'user_id',
        ]));

        $this->assertTrue(Schema::hasColumns('sos_external_contacts', [
            'name', 'org', 'phone', 'email', 'alert_types', 'channel', 'active',
        ]));

        $this->assertTrue(Schema::hasColumn('employee_profiles', 'mobile_number'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosSchemaTest.php"`
Expected: FAIL — tables/columns don't exist yet.

- [ ] **Step 3: Write the migrations**

`create_sos_alerts_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('triggerable_type');
            $table->unsignedBigInteger('triggerable_id');
            $table->string('alert_type');
            $table->boolean('is_silent')->default(false);
            $table->string('status')->default('triggered');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->unsignedBigInteger('geofence_zone_id')->nullable();
            $table->unsignedInteger('current_tier_order')->default(1);
            $table->timestamp('triggered_at');
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['triggerable_type', 'triggerable_id']);
            $table->index('status');
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_alerts');
    }
};
```

`create_sos_alert_events_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_alert_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_alert_id')->constrained('sos_alerts')->cascadeOnDelete();
            $table->string('type');
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_alert_events');
    }
};
```

`create_sos_notification_logs_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_alert_id')->constrained('sos_alerts')->cascadeOnDelete();
            $table->string('channel');
            $table->string('recipient_type')->nullable();
            $table->unsignedBigInteger('recipient_id')->nullable();
            $table->string('recipient_label')->nullable();
            $table->boolean('sent')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_notification_logs');
    }
};
```

`create_sos_escalation_tiers_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_escalation_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('alert_type');
            $table->unsignedInteger('order');
            $table->unsignedBigInteger('role_id')->nullable();
            $table->unsignedInteger('timeout_minutes')->nullable();
            $table->json('channels');
            $table->boolean('notify_external')->default(false);
            $table->timestamps();

            $table->unique(['alert_type', 'order']);
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_escalation_tiers');
    }
};
```

`create_sos_escalation_tier_users_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_escalation_tier_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sos_escalation_tier_id')->constrained('sos_escalation_tiers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sos_escalation_tier_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_escalation_tier_users');
    }
};
```

`create_sos_external_contacts_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sos_external_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('org')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->json('alert_types');
            $table->string('channel')->default('sms');
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sos_external_contacts');
    }
};
```

`add_mobile_number_to_employee_profiles_table.php`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            // Own work contact number, used by the SOS module to SMS this
            // employee when they're an assigned responder. Distinct from
            // emergency_contact_phone, which is someone ELSE'S number.
            $table->string('mobile_number')->nullable()->after('emergency_contact_phone');
        });
    }

    public function down(): void
    {
        Schema::table('employee_profiles', function (Blueprint $table) {
            $table->dropColumn('mobile_number');
        });
    }
};
```

- [ ] **Step 4: Run migrations in dev**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_21_170000_create_sos_alerts_table.php --path=database/migrations/2026_08_21_170100_create_sos_alert_events_table.php --path=database/migrations/2026_08_21_170200_create_sos_notification_logs_table.php --path=database/migrations/2026_08_21_170300_create_sos_escalation_tiers_table.php --path=database/migrations/2026_08_21_170400_create_sos_escalation_tier_users_table.php --path=database/migrations/2026_08_21_170500_create_sos_external_contacts_table.php --path=database/migrations/2026_08_21_170600_add_mobile_number_to_employee_profiles_table.php"`

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosSchemaTest.php"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_21_170000_create_sos_alerts_table.php database/migrations/2026_08_21_170100_create_sos_alert_events_table.php database/migrations/2026_08_21_170200_create_sos_notification_logs_table.php database/migrations/2026_08_21_170300_create_sos_escalation_tiers_table.php database/migrations/2026_08_21_170400_create_sos_escalation_tier_users_table.php database/migrations/2026_08_21_170500_create_sos_external_contacts_table.php database/migrations/2026_08_21_170600_add_mobile_number_to_employee_profiles_table.php tests/Feature/Sos/SosSchemaTest.php
git commit -m "feat(sos): add SOS alert schema migrations"
```

---

## Task 2: Eloquent models

**Files:**
- Create: `app/Models/Sos/SosAlert.php`
- Create: `app/Models/Sos/SosAlertEvent.php`
- Create: `app/Models/Sos/SosNotificationLog.php`
- Create: `app/Models/Sos/SosEscalationTier.php`
- Create: `app/Models/Sos/SosExternalContact.php`
- Modify: `app/Models/HR/EmployeeProfile.php` (add `mobile_number` to `$fillable`)
- Test: `tests/Feature/Sos/SosModelsTest.php`

**Interfaces:**
- Consumes: tables from Task 1.
- Produces: `SosAlert::triggerable()` (MorphTo), `SosAlert::events()` (HasMany `SosAlertEvent`), `SosAlert::resolvedBy()` (BelongsTo `User`), `SosAlert::falseAlarmCount(string $triggerableType, int $triggerableId, int $windowDays): int`; `SosAlertEvent::alert()` (BelongsTo); `SosNotificationLog::alert()` (BelongsTo); `SosEscalationTier::role()` (BelongsTo `Role`), `SosEscalationTier::users()` (BelongsToMany `User` via `sos_escalation_tier_users`); `SosExternalContact` plain model with `alert_types`/`active` casts.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Role;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosAlertEvent;
use App\Models\Sos\SosEscalationTier;
use App\Models\Sos\SosExternalContact;
use App\Models\Sos\SosNotificationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sos_alert_relations_and_false_alarm_count(): void
    {
        $user = User::factory()->create();

        $alert = SosAlert::create([
            'triggerable_type'   => User::class,
            'triggerable_id'     => $user->id,
            'alert_type'         => 'medical',
            'is_silent'          => false,
            'status'             => 'false_alarm',
            'current_tier_order' => 1,
            'triggered_at'       => now(),
        ]);

        SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'triggered',
            'actor_type'   => User::class,
            'actor_id'     => $user->id,
            'payload'      => ['alert_type' => 'medical'],
        ]);

        $this->assertTrue($alert->triggerable->is($user));
        $this->assertCount(1, $alert->events);
        $this->assertSame(1, SosAlert::falseAlarmCount(User::class, $user->id, 30));
    }

    public function test_escalation_tier_role_and_explicit_users(): void
    {
        $role = Role::create(['name' => 'Security Guard']);
        $tier = SosEscalationTier::create([
            'alert_type'      => 'security',
            'order'           => 1,
            'role_id'         => $role->id,
            'timeout_minutes' => 10,
            'channels'        => ['in_app', 'sms'],
            'notify_external' => false,
        ]);
        $user = User::factory()->create();
        $tier->users()->attach($user->id);

        $this->assertTrue($tier->role->is($role));
        $this->assertTrue($tier->users->contains($user));
        $this->assertSame(['in_app', 'sms'], $tier->channels);
    }

    public function test_external_contact_and_notification_log(): void
    {
        $contact = SosExternalContact::create([
            'name'        => 'Butuan City BFP',
            'phone'       => '+639170000000',
            'alert_types' => ['fire_disaster'],
            'channel'     => 'sms',
            'active'      => true,
        ]);

        $this->assertSame(['fire_disaster'], $contact->alert_types);
        $this->assertTrue($contact->active);

        $alert = SosAlert::create([
            'triggerable_type'   => User::class,
            'triggerable_id'     => User::factory()->create()->id,
            'alert_type'         => 'fire_disaster',
            'status'             => 'triggered',
            'current_tier_order' => 1,
            'triggered_at'       => now(),
        ]);

        $log = SosNotificationLog::create([
            'sos_alert_id'    => $alert->id,
            'channel'         => 'sms',
            'recipient_type'  => 'external_contact',
            'recipient_id'    => $contact->id,
            'recipient_label' => $contact->name,
            'sent'            => true,
            'sent_at'         => now(),
        ]);

        $this->assertTrue($log->alert->is($alert));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosModelsTest.php"`
Expected: FAIL — model classes don't exist.

- [ ] **Step 3: Write the models**

`app/Models/Sos/SosAlert.php`:
```php
<?php

namespace App\Models\Sos;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SosAlert extends Model
{
    protected $fillable = [
        'triggerable_type', 'triggerable_id', 'alert_type', 'is_silent', 'status',
        'lat', 'lng', 'accuracy', 'geofence_zone_id', 'current_tier_order',
        'triggered_at', 'resolved_at', 'resolved_by', 'resolution_notes',
    ];

    protected $casts = [
        'is_silent'    => 'boolean',
        'lat'          => 'float',
        'lng'          => 'float',
        'accuracy'     => 'float',
        'triggered_at' => 'datetime',
        'resolved_at'  => 'datetime',
    ];

    public function triggerable(): MorphTo
    {
        return $this->morphTo();
    }

    public function events(): HasMany
    {
        return $this->hasMany(SosAlertEvent::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public static function falseAlarmCount(string $triggerableType, int $triggerableId, int $windowDays): int
    {
        return static::where('triggerable_type', $triggerableType)
            ->where('triggerable_id', $triggerableId)
            ->where('status', 'false_alarm')
            ->where('triggered_at', '>=', now()->subDays($windowDays))
            ->count();
    }
}
```

`app/Models/Sos/SosAlertEvent.php`:
```php
<?php

namespace App\Models\Sos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosAlertEvent extends Model
{
    const UPDATED_AT = null;

    // 'created_at' is intentionally fillable: this append-only timeline is
    // only ever written by trusted service/cron code (never raw user input),
    // and the escalation sweep's own tests need to backdate events to
    // simulate an alert that has sat in a tier past its timeout.
    protected $fillable = ['sos_alert_id', 'type', 'actor_type', 'actor_id', 'payload', 'created_at'];

    protected $casts = ['payload' => 'array', 'created_at' => 'datetime'];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(SosAlert::class, 'sos_alert_id');
    }
}
```

`app/Models/Sos/SosNotificationLog.php`:
```php
<?php

namespace App\Models\Sos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SosNotificationLog extends Model
{
    protected $fillable = [
        'sos_alert_id', 'channel', 'recipient_type', 'recipient_id',
        'recipient_label', 'sent', 'sent_at',
    ];

    protected $casts = ['sent' => 'boolean', 'sent_at' => 'datetime'];

    public function alert(): BelongsTo
    {
        return $this->belongsTo(SosAlert::class, 'sos_alert_id');
    }
}
```

`app/Models/Sos/SosEscalationTier.php`:
```php
<?php

namespace App\Models\Sos;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SosEscalationTier extends Model
{
    protected $fillable = ['alert_type', 'order', 'role_id', 'timeout_minutes', 'channels', 'notify_external'];

    protected $casts = ['channels' => 'array', 'notify_external' => 'boolean'];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'sos_escalation_tier_users');
    }
}
```

`app/Models/Sos/SosExternalContact.php`:
```php
<?php

namespace App\Models\Sos;

use Illuminate\Database\Eloquent\Model;

class SosExternalContact extends Model
{
    protected $fillable = ['name', 'org', 'phone', 'email', 'alert_types', 'channel', 'active'];

    protected $casts = ['alert_types' => 'array', 'active' => 'boolean'];
}
```

Modify `app/Models/HR/EmployeeProfile.php` — add `'mobile_number',` to `$fillable`, right after `'emergency_contact_phone',`.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosModelsTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Sos app/Models/HR/EmployeeProfile.php tests/Feature/Sos/SosModelsTest.php
git commit -m "feat(sos): add Sos domain models"
```

---

## Task 3: Roles + permissions seeders

**Files:**
- Modify: `database/seeders/RolesSeeder.php` (add `DRRM Coordinator`, `Security Guard`)
- Create: `database/seeders/SosPermissionSeeder.php`
- Test: `tests/Feature/Sos/SosPermissionSeederTest.php`

**Interfaces:**
- Produces: roles `DRRM Coordinator`, `Security Guard` (idempotent); permissions `sos.trigger`, `sos.respond`, `sos.manage`; `sos.respond` granted to `DRRM Coordinator`, `Security Guard`, `Administrator`, `Nurse`; `sos.manage` granted to `Administrator`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SosPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_roles_and_grant_sos_respond(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(SosPermissionSeeder::class);

        $this->assertDatabaseHas('roles', ['name' => 'DRRM Coordinator']);
        $this->assertDatabaseHas('roles', ['name' => 'Security Guard']);

        $security = Role::where('name', 'Security Guard')->first();
        $user = User::factory()->create();
        $user->roles()->attach($security->id);

        $this->assertTrue($user->fresh()->hasPermission('sos.respond'));
        $this->assertFalse($user->fresh()->hasPermission('sos.manage'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosPermissionSeederTest.php"`
Expected: FAIL — `SosPermissionSeeder` doesn't exist, roles missing.

- [ ] **Step 3: Modify RolesSeeder and create SosPermissionSeeder**

In `database/seeders/RolesSeeder.php`, add to the `$roles` array (inside the "Non-staff" / general block, e.g. right after the `'Nurse'` entry):

```php
            ['name' => 'DRRM Coordinator', 'description' => 'Disaster Risk Reduction & Management Coordinator. Responds to SOS alerts and coordinates campus emergency response.'],
```

And, near `'Dorm Manager'`, add (only if not already present in another seeder — verify via `grep -n "Security Guard" database/seeders/*.php` before adding, to avoid a duplicate `updateOrCreate` call elsewhere):

```php
            ['name' => 'Security Guard', 'description' => 'Campus security personnel. Monitors gate attendance and responds to SOS alerts.'],
```

`database/seeders/SosPermissionSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * SOS Emergency Button permissions.
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\SosPermissionSeeder --force
 */
class SosPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $trigger = Permission::firstOrCreate(['name' => 'sos.trigger'], [
            'module'      => 'SOS',
            'description' => 'Trigger an SOS emergency alert.',
        ]);

        $respond = Permission::firstOrCreate(['name' => 'sos.respond'], [
            'module'      => 'SOS',
            'description' => 'View the SOS Command Center and acknowledge/triage/resolve alerts.',
        ]);

        $manage = Permission::firstOrCreate(['name' => 'sos.manage'], [
            'module'      => 'SOS',
            'description' => 'Configure SOS escalation tiers, external contacts, and thresholds.',
        ]);

        foreach (['DRRM Coordinator', 'Security Guard', 'Administrator', 'Nurse'] as $roleName) {
            Role::where('name', $roleName)->first()
                ?->permissions()->syncWithoutDetaching([$respond->id]);
        }

        Role::where('name', 'Administrator')->first()
            ?->permissions()->syncWithoutDetaching([$manage->id, $trigger->id]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosPermissionSeederTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/seeders/RolesSeeder.php database/seeders/SosPermissionSeeder.php tests/Feature/Sos/SosPermissionSeederTest.php
git commit -m "feat(sos): add DRRM/Security roles and sos.* permissions"
```

---

## Task 4: `config/sos.php`

**Files:**
- Create: `config/sos.php`

**Interfaces:**
- Produces: `config('sos.emergency_hotline_number')`, `config('sos.off_campus_message')`, `config('sos.false_alarm_threshold')`, `config('sos.false_alarm_window_days')`, `config('sos.hold_confirm_seconds')`, `config('sos.countdown_seconds')` — consumed by `SosAlertController`, `StudentPortal\SosAlertController`, `SosAlertService`, and the frontend (via an Inertia shared prop added in Task 18).

- [ ] **Step 1: Write the config file**

```php
<?php

return [
    // Shown to the user when an SOS trigger is blocked for being off-campus
    // or unable to verify location — this is a real phone number the person
    // should call directly, not a dead end.
    'emergency_hotline_number' => env('SOS_EMERGENCY_HOTLINE_NUMBER', '911'),

    'off_campus_message' => 'SOS is for on-campus emergencies. If this is a real emergency, please call the number below directly.',

    // Repeat false-alarm detection window/threshold — crossing this flags
    // the account for admin review, it does not block future triggers.
    'false_alarm_threshold'   => (int) env('SOS_FALSE_ALARM_THRESHOLD', 3),
    'false_alarm_window_days' => (int) env('SOS_FALSE_ALARM_WINDOW_DAYS', 30),

    // Anti-prank friction on the normal (non-silent) trigger flow.
    'hold_confirm_seconds' => 3,
    'countdown_seconds'    => 8,
];
```

- [ ] **Step 2: Commit**

```bash
git add config/sos.php
git commit -m "feat(sos): add SOS configuration"
```

---

## Task 5: `sos-responders` broadcast channel

**Files:**
- Modify: `routes/channels.php`
- Test: `tests/Feature/Sos/SosChannelAuthTest.php`

**Interfaces:**
- Produces: `Broadcast::channel('sos-responders', ...)` — authorized for `isSuperAdmin()` or `hasPermission('sos.respond')`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_sos_respond_permission_can_join_channel(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'sos.respond'], ['module' => 'SOS', 'description' => 'x']);
        $role = Role::create(['name' => 'DRRM Coordinator']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        $this->actingAs($user);

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-sos-responders',
        ]);

        $response->assertOk();
    }

    public function test_user_without_permission_is_denied(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/broadcasting/auth', [
            'channel_name' => 'private-sos-responders',
        ]);

        $response->assertForbidden();
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosChannelAuthTest.php"`
Expected: FAIL — channel not registered, first test 403s.

- [ ] **Step 3: Add the channel**

In `routes/channels.php`, after the `biometric-feed` channel:

```php
Broadcast::channel('sos-responders', function ($user) {
    return $user->isSuperAdmin() || $user->hasPermission('sos.respond');
});
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosChannelAuthTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add routes/channels.php tests/Feature/Sos/SosChannelAuthTest.php
git commit -m "feat(sos): add sos-responders broadcast channel"
```

---

## Task 6: Broadcast events

**Files:**
- Create: `app/Events/Sos/SosAlertTriggered.php`
- Create: `app/Events/Sos/SosAlertUpdated.php`
- Test: `tests/Feature/Sos/SosBroadcastEventsTest.php`

**Interfaces:**
- Produces: `new SosAlertTriggered(array $payload)` broadcasting as `sos.alert.triggered`; `new SosAlertUpdated(array $payload)` broadcasting as `sos.alert.updated`; both on `PrivateChannel('sos-responders')`. Consumed by `SosAlertService` (Task 7-9).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Events\Sos\SosAlertTriggered;
use App\Events\Sos\SosAlertUpdated;
use Illuminate\Broadcasting\PrivateChannel;
use Tests\TestCase;

class SosBroadcastEventsTest extends TestCase
{
    public function test_sos_alert_triggered_broadcasts_correctly(): void
    {
        $event = new SosAlertTriggered(['id' => 1, 'status' => 'triggered']);

        $channels = $event->broadcastOn();
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertSame('private-sos-responders', $channels[0]->name);
        $this->assertSame('sos.alert.triggered', $event->broadcastAs());
        $this->assertSame(['id' => 1, 'status' => 'triggered'], $event->broadcastWith());
    }

    public function test_sos_alert_updated_broadcasts_correctly(): void
    {
        $event = new SosAlertUpdated(['id' => 1, 'status' => 'resolved']);

        $this->assertSame('sos.alert.updated', $event->broadcastAs());
        $this->assertSame(['id' => 1, 'status' => 'resolved'], $event->broadcastWith());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosBroadcastEventsTest.php"`
Expected: FAIL — classes don't exist.

- [ ] **Step 3: Write the events**

`app/Events/Sos/SosAlertTriggered.php`:
```php
<?php

namespace App\Events\Sos;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SosAlertTriggered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('sos-responders')];
    }

    public function broadcastAs(): string
    {
        return 'sos.alert.triggered';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
```

`app/Events/Sos/SosAlertUpdated.php`:
```php
<?php

namespace App\Events\Sos;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SosAlertUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('sos-responders')];
    }

    public function broadcastAs(): string
    {
        return 'sos.alert.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosBroadcastEventsTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Events/Sos tests/Feature/Sos/SosBroadcastEventsTest.php
git commit -m "feat(sos): add SosAlertTriggered/SosAlertUpdated broadcast events"
```

---

## Task 7: `SosAlertService::trigger()`

**Files:**
- Create: `app/Services/Sos/SosAlertService.php` (this task implements `trigger()`; Tasks 8-9 extend the same file)
- Test: `tests/Feature/Sos/SosAlertServiceTriggerTest.php`

**Interfaces:**
- Consumes: `App\Services\CampusPresenceService::resolveLocationGate(?float $lat, ?float $lng, ?float $accuracy, ?string $ip): array` (existing).
- Produces: `SosAlertService::trigger(Model $triggerable, string $alertType, bool $isSilent, ?float $lat, ?float $lng, ?float $accuracy, ?string $ip): array` returning `['blocked' => bool, 'reason' => ?string, 'alert' => ?SosAlert]`. Fires `SosAlertTriggered`. Dispatches `App\Jobs\Sos\NotifySosResponders` and `App\Jobs\Sos\NotifySosEmergencyContact` (stubbed as `ShouldQueue` no-op classes in this task — full implementation in Task 12; declare them now so `trigger()` compiles and can be tested with `Queue::fake()`).

- [ ] **Step 1: Write minimal job stubs `trigger()` depends on**

`app/Jobs/Sos/NotifySosResponders.php`:
```php
<?php

namespace App\Jobs\Sos;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifySosResponders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $sosAlertId,
        public readonly int $tierId,
    ) {}

    public function handle(): void
    {
        // Implemented in Task 12.
    }
}
```

`app/Jobs/Sos/NotifySosEmergencyContact.php`:
```php
<?php

namespace App\Jobs\Sos;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifySosEmergencyContact implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $sosAlertId) {}

    public function handle(): void
    {
        // Implemented in Task 12.
    }
}
```

- [ ] **Step 2: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Events\Sos\SosAlertTriggered;
use App\Jobs\Sos\NotifySosEmergencyContact;
use App\Jobs\Sos\NotifySosResponders;
use App\Models\Sos\SosEscalationTier;
use App\Models\User;
use App\Services\Sos\SosAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SosAlertServiceTriggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_trigger_creates_alert_broadcasts_and_dispatches_jobs_when_geofencing_unconfigured(): void
    {
        // No OnlinePunchGeofenceZone rows => CampusPresenceService resolves 'unconfigured',
        // which this feature deliberately allows through rather than blocking everyone.
        Event::fake([SosAlertTriggered::class]);
        Queue::fake();

        SosEscalationTier::create([
            'alert_type' => 'medical', 'order' => 1, 'timeout_minutes' => 10,
            'channels' => ['in_app'], 'notify_external' => false,
        ]);

        $user = User::factory()->create();
        $service = app(SosAlertService::class);

        $result = $service->trigger(
            triggerable: $user,
            alertType: 'medical',
            isSilent: false,
            lat: null,
            lng: null,
            accuracy: null,
            ip: '127.0.0.1',
        );

        $this->assertFalse($result['blocked']);
        $this->assertNotNull($result['alert']);
        $this->assertSame('triggered', $result['alert']->status);
        $this->assertDatabaseHas('sos_alert_events', ['sos_alert_id' => $result['alert']->id, 'type' => 'triggered']);

        Event::assertDispatched(SosAlertTriggered::class);
        Queue::assertPushed(NotifySosResponders::class);
        Queue::assertPushed(NotifySosEmergencyContact::class);
    }

    public function test_trigger_is_blocked_when_geofence_reports_no_permission(): void
    {
        \App\Models\HR\OnlinePunchGeofenceZone::create([
            'name' => 'Main Campus', 'latitude' => 8.9475, 'longitude' => 125.5406,
            'radius_meters' => 200, 'is_active' => true,
        ]);

        $user = User::factory()->create();
        $service = app(SosAlertService::class);

        $result = $service->trigger(
            triggerable: $user, alertType: 'general', isSilent: false,
            lat: null, lng: null, accuracy: null, ip: '127.0.0.1',
        );

        $this->assertTrue($result['blocked']);
        $this->assertSame('no_permission', $result['reason']);
        $this->assertDatabaseCount('sos_alerts', 0);
    }

    public function test_silent_trigger_defaults_alert_type_flag_is_stored(): void
    {
        $user = User::factory()->create();
        $service = app(SosAlertService::class);

        $result = $service->trigger(
            triggerable: $user, alertType: 'security', isSilent: true,
            lat: null, lng: null, accuracy: null, ip: '127.0.0.1',
        );

        $this->assertTrue($result['alert']->is_silent);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertServiceTriggerTest.php"`
Expected: FAIL — `SosAlertService` doesn't exist.

- [ ] **Step 4: Write `SosAlertService::trigger()`**

`app/Services/Sos/SosAlertService.php`:
```php
<?php

namespace App\Services\Sos;

use App\Events\Sos\SosAlertTriggered;
use App\Events\Sos\SosAlertUpdated;
use App\Jobs\Sos\NotifySosEmergencyContact;
use App\Jobs\Sos\NotifySosResponders;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosAlertEvent;
use App\Models\Sos\SosEscalationTier;
use App\Models\User;
use App\Services\CampusPresenceService;
use Illuminate\Database\Eloquent\Model;

class SosAlertService
{
    public function __construct(private readonly CampusPresenceService $campusPresence) {}

    /**
     * @return array{blocked: bool, reason: ?string, alert: ?SosAlert}
     */
    public function trigger(
        Model $triggerable,
        string $alertType,
        bool $isSilent,
        ?float $lat,
        ?float $lng,
        ?float $accuracy,
        ?string $ip,
    ): array {
        $gate = $this->campusPresence->resolveLocationGate($lat, $lng, $accuracy, $ip);

        // A false "we notified someone" is worse than an honest redirect to
        // real emergency services — see spec decision #4. 'coarse' and
        // 'unconfigured' are allowed through deliberately: blocking a real
        // emergency over GPS imprecision or an unset-up geofence is worse
        // than the alternative.
        if (in_array($gate['status'], ['outside', 'no_permission'], true)) {
            return ['blocked' => true, 'reason' => $gate['status'], 'alert' => null];
        }

        $alert = SosAlert::create([
            'triggerable_type'   => get_class($triggerable),
            'triggerable_id'     => $triggerable->getKey(),
            'alert_type'         => $alertType,
            'is_silent'          => $isSilent,
            'status'             => 'triggered',
            'lat'                => $lat,
            'lng'                => $lng,
            'accuracy'           => $accuracy,
            'geofence_zone_id'   => $gate['geofence']['zoneId'] ?? null,
            'current_tier_order' => 1,
            'triggered_at'       => now(),
        ]);

        SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'triggered',
            'actor_type'   => get_class($triggerable),
            'actor_id'     => $triggerable->getKey(),
            'payload'      => ['alert_type' => $alertType, 'is_silent' => $isSilent],
        ]);

        event(new SosAlertTriggered($this->broadcastPayload($alert)));

        $firstTier = SosEscalationTier::where('alert_type', $alertType)->where('order', 1)->first();
        if ($firstTier) {
            NotifySosResponders::dispatch($alert->id, $firstTier->id);
        }
        NotifySosEmergencyContact::dispatch($alert->id);

        return ['blocked' => false, 'reason' => null, 'alert' => $alert];
    }

    private function broadcastPayload(SosAlert $alert): array
    {
        return [
            'id'           => $alert->id,
            'alert_type'   => $alert->alert_type,
            'is_silent'    => $alert->is_silent,
            'status'       => $alert->status,
            'lat'          => $alert->lat,
            'lng'          => $alert->lng,
            'triggered_at' => $alert->triggered_at->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertServiceTriggerTest.php"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Services/Sos/SosAlertService.php app/Jobs/Sos tests/Feature/Sos/SosAlertServiceTriggerTest.php
git commit -m "feat(sos): implement SosAlertService::trigger() with campus geofence gate"
```

---

## Task 8: `SosAlertService` lifecycle — acknowledge/verify/falseAlarm/resolve

**Files:**
- Modify: `app/Services/Sos/SosAlertService.php`
- Test: `tests/Feature/Sos/SosAlertServiceLifecycleTest.php`

**Interfaces:**
- Consumes: `App\Services\NotificationService::notifyUser(User $user, string $requestType, string $referenceNo, string $newStatus, string $url, ?string $remarks = null): void` (existing).
- Produces: `acknowledge(SosAlert $alert, User $responder): SosAlertEvent`, `verify(SosAlert $alert, User $responder, ?string $note = null): SosAlertEvent`, `markFalseAlarm(SosAlert $alert, User $responder, string $reason): SosAlertEvent`, `resolve(SosAlert $alert, User $responder, ?string $notes = null): SosAlertEvent`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Role;
use App\Models\Sos\SosAlert;
use App\Models\User;
use App\Services\Sos\SosAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosAlertServiceLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function alert(array $overrides = []): SosAlert
    {
        return SosAlert::create(array_merge([
            'triggerable_type'   => User::class,
            'triggerable_id'     => User::factory()->create()->id,
            'alert_type'         => 'medical',
            'status'             => 'triggered',
            'current_tier_order' => 1,
            'triggered_at'       => now(),
        ], $overrides));
    }

    public function test_acknowledge_sets_status_and_logs_event(): void
    {
        $alert = $this->alert();
        $responder = User::factory()->create();

        app(SosAlertService::class)->acknowledge($alert, $responder);

        $this->assertSame('acknowledged', $alert->fresh()->status);
        $this->assertDatabaseHas('sos_alert_events', ['sos_alert_id' => $alert->id, 'type' => 'acknowledged', 'actor_id' => $responder->id]);
    }

    public function test_verify_keeps_alert_active_for_further_escalation(): void
    {
        $alert = $this->alert();
        $responder = User::factory()->create();

        app(SosAlertService::class)->verify($alert, $responder, 'Confirmed real emergency');

        $this->assertSame('verified', $alert->fresh()->status);
    }

    public function test_false_alarm_requires_reason_and_records_it(): void
    {
        $alert = $this->alert();
        $responder = User::factory()->create();

        app(SosAlertService::class)->markFalseAlarm($alert, $responder, 'Accidental tap, confirmed with student');

        $this->assertSame('false_alarm', $alert->fresh()->status);
        $this->assertDatabaseHas('sos_alert_events', [
            'sos_alert_id' => $alert->id, 'type' => 'false_alarm', 'actor_id' => $responder->id,
        ]);
    }

    public function test_repeat_false_alarms_notify_administrators(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::create(['name' => 'Administrator'])->id);
        $triggerable = User::factory()->create();
        $responder = User::factory()->create();

        // 2 prior false alarms + this one crosses the default threshold of 3
        for ($i = 0; $i < 2; $i++) {
            $this->alert(['triggerable_id' => $triggerable->id, 'status' => 'false_alarm'])->save();
        }
        $alert = $this->alert(['triggerable_id' => $triggerable->id]);

        app(SosAlertService::class)->markFalseAlarm($alert, $responder, 'Another accidental trigger');

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $admin->id]);
    }

    public function test_resolve_sets_resolution_fields(): void
    {
        $alert = $this->alert(['status' => 'verified']);
        $responder = User::factory()->create();

        app(SosAlertService::class)->resolve($alert, $responder, 'Handled by campus nurse');

        $fresh = $alert->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertSame($responder->id, $fresh->resolved_by);
        $this->assertSame('Handled by campus nurse', $fresh->resolution_notes);
        $this->assertNotNull($fresh->resolved_at);
    }

    public function test_resolve_rejects_already_false_alarm_alerts(): void
    {
        $alert = $this->alert(['status' => 'false_alarm']);
        $responder = User::factory()->create();

        $this->expectException(\RuntimeException::class);
        app(SosAlertService::class)->resolve($alert, $responder);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertServiceLifecycleTest.php"`
Expected: FAIL — methods don't exist.

- [ ] **Step 3: Add the lifecycle methods**

Add to `app/Services/Sos/SosAlertService.php` (new imports: `App\Models\Sos\SosAlert`, `App\Services\NotificationService`, `App\Models\Role` not needed — query via `roles()`):

```php
    public function acknowledge(SosAlert $alert, User $responder): SosAlertEvent
    {
        $alert->update(['status' => 'acknowledged']);

        $event = SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'acknowledged',
            'actor_type'   => User::class,
            'actor_id'     => $responder->id,
            'payload'      => null,
        ]);

        event(new SosAlertUpdated($this->broadcastPayload($alert->fresh())));

        return $event;
    }

    public function verify(SosAlert $alert, User $responder, ?string $note = null): SosAlertEvent
    {
        $alert->update(['status' => 'verified']);

        $event = SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'verified',
            'actor_type'   => User::class,
            'actor_id'     => $responder->id,
            'payload'      => $note ? ['note' => $note] : null,
        ]);

        event(new SosAlertUpdated($this->broadcastPayload($alert->fresh())));

        return $event;
    }

    public function markFalseAlarm(SosAlert $alert, User $responder, string $reason): SosAlertEvent
    {
        $alert->update(['status' => 'false_alarm']);

        $event = SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'false_alarm',
            'actor_type'   => User::class,
            'actor_id'     => $responder->id,
            'payload'      => ['reason' => $reason],
        ]);

        event(new SosAlertUpdated($this->broadcastPayload($alert->fresh())));

        $count = SosAlert::falseAlarmCount(
            $alert->triggerable_type,
            $alert->triggerable_id,
            (int) config('sos.false_alarm_window_days'),
        );

        if ($count >= (int) config('sos.false_alarm_threshold')) {
            $admins = User::whereHas('roles', fn ($q) => $q->where('name', 'Administrator'))->get();
            foreach ($admins as $admin) {
                \App\Services\NotificationService::notifyUser(
                    user: $admin,
                    requestType: 'SOS Repeat False Alarm',
                    referenceNo: "SOS-{$alert->id}",
                    newStatus: 'Needs Review',
                    url: route('sos.show', $alert->id),
                );
            }
        }

        return $event;
    }

    public function resolve(SosAlert $alert, User $responder, ?string $notes = null): SosAlertEvent
    {
        if ($alert->status === 'false_alarm') {
            throw new \RuntimeException("Alert #{$alert->id} is already closed as a false alarm.");
        }

        $alert->update([
            'status'           => 'resolved',
            'resolved_at'      => now(),
            'resolved_by'      => $responder->id,
            'resolution_notes' => $notes,
        ]);

        $event = SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'resolved',
            'actor_type'   => User::class,
            'actor_id'     => $responder->id,
            'payload'      => $notes ? ['notes' => $notes] : null,
        ]);

        event(new SosAlertUpdated($this->broadcastPayload($alert->fresh())));

        return $event;
    }
```

Add `use App\Models\Sos\SosAlert;` and `use App\Models\Sos\SosAlertEvent;` if not already imported (they are, from Task 7).

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertServiceLifecycleTest.php"`
Expected: PASS (note: `test_repeat_false_alarms_notify_administrators` and `test_resolve_sets_resolution_fields` reference `route('sos.show', ...)`, which is added in Task 14 — if this task is executed before Task 14, temporarily run this test after Task 14, or add a minimal `sos.show` route stub now. Recommended: implement Task 14's routes file skeleton first if executing tasks out of order; the dependency is noted here for the executor's awareness.)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Sos/SosAlertService.php tests/Feature/Sos/SosAlertServiceLifecycleTest.php
git commit -m "feat(sos): add SOS alert lifecycle transitions and repeat-offender notification"
```

---

## Task 9: `SosAlertService::processEscalations()`

**Files:**
- Modify: `app/Services/Sos/SosAlertService.php`
- Test: `tests/Feature/Sos/SosAlertServiceEscalationTest.php`

**Interfaces:**
- Produces: `processEscalations(): int` — returns count of alerts advanced.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Jobs\Sos\NotifySosResponders;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Models\User;
use App\Services\Sos\SosAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SosAlertServiceEscalationTest extends TestCase
{
    use RefreshDatabase;

    public function test_alert_past_tier_timeout_advances_to_next_tier(): void
    {
        Queue::fake();

        SosEscalationTier::create(['alert_type' => 'security', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        $tier2 = SosEscalationTier::create(['alert_type' => 'security', 'order' => 2, 'timeout_minutes' => null, 'channels' => ['in_app', 'sms'], 'notify_external' => true]);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'security', 'status' => 'triggered', 'current_tier_order' => 1,
            'triggered_at' => now()->subMinutes(15),
        ]);
        $alert->events()->create(['type' => 'triggered', 'actor_type' => null, 'actor_id' => null, 'payload' => null, 'created_at' => now()->subMinutes(15)]);

        $count = app(SosAlertService::class)->processEscalations();

        $this->assertSame(1, $count);
        $fresh = $alert->fresh();
        $this->assertSame('escalated', $fresh->status);
        $this->assertSame(2, $fresh->current_tier_order);
        $this->assertDatabaseHas('sos_alert_events', ['sos_alert_id' => $alert->id, 'type' => 'escalated']);
        Queue::assertPushed(NotifySosResponders::class, fn ($job) => $job->tierId === $tier2->id);
    }

    public function test_alert_within_timeout_is_not_escalated(): void
    {
        SosEscalationTier::create(['alert_type' => 'medical', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1,
            'triggered_at' => now()->subMinutes(2),
        ]);
        $alert->events()->create(['type' => 'triggered', 'actor_type' => null, 'actor_id' => null, 'payload' => null, 'created_at' => now()->subMinutes(2)]);

        $count = app(SosAlertService::class)->processEscalations();

        $this->assertSame(0, $count);
        $this->assertSame('triggered', $alert->fresh()->status);
    }

    public function test_resolved_alerts_are_never_escalated(): void
    {
        SosEscalationTier::create(['alert_type' => 'general', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'general', 'status' => 'resolved', 'current_tier_order' => 1,
            'triggered_at' => now()->subHours(1), 'resolved_at' => now(),
        ]);

        $count = app(SosAlertService::class)->processEscalations();

        $this->assertSame(0, $count);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertServiceEscalationTest.php"`
Expected: FAIL — `processEscalations()` doesn't exist.

- [ ] **Step 3: Add `processEscalations()`**

Add to `app/Services/Sos/SosAlertService.php`:

```php
    public function processEscalations(): int
    {
        $count = 0;

        $alerts = SosAlert::whereNotIn('status', ['resolved', 'false_alarm'])->get();

        foreach ($alerts as $alert) {
            $currentTier = SosEscalationTier::where('alert_type', $alert->alert_type)
                ->where('order', $alert->current_tier_order)
                ->first();

            // No tier configured for this order, or this is the final tier
            // (null timeout) — nothing further to advance to.
            if (! $currentTier || $currentTier->timeout_minutes === null) {
                continue;
            }

            // ->first()?->created_at (not ->value()) — value() returns the raw
            // DB scalar, bypassing model hydration, so it would NOT be a Carbon
            // instance and ->copy() below would fatal.
            $enteredAt = $alert->events()
                ->whereIn('type', ['triggered', 'escalated'])
                ->latest('created_at')
                ->first()?->created_at ?? $alert->triggered_at;

            if (now()->lessThan($enteredAt->copy()->addMinutes($currentTier->timeout_minutes))) {
                continue;
            }

            if ($this->escalate($alert)) {
                $count++;
            }
        }

        return $count;
    }

    private function escalate(SosAlert $alert): bool
    {
        $nextTier = SosEscalationTier::where('alert_type', $alert->alert_type)
            ->where('order', $alert->current_tier_order + 1)
            ->first();

        if (! $nextTier) {
            return false;
        }

        $alert->update(['status' => 'escalated', 'current_tier_order' => $nextTier->order]);

        SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'escalated',
            'actor_type'   => null,
            'actor_id'     => null,
            'payload'      => ['tier_order' => $nextTier->order],
        ]);

        event(new SosAlertUpdated($this->broadcastPayload($alert->fresh())));

        NotifySosResponders::dispatch($alert->id, $nextTier->id);

        return true;
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertServiceEscalationTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Sos/SosAlertService.php tests/Feature/Sos/SosAlertServiceEscalationTest.php
git commit -m "feat(sos): add cron-driven escalation sweep to SosAlertService"
```

---

## Task 10: `SosNotificationDispatchService::notifyTier()`

**Files:**
- Create: `app/Services/Sos/SosNotificationDispatchService.php` (this task: `notifyTier()`; Task 11 adds `notifyEmergencyContact()`)
- Test: `tests/Feature/Sos/SosNotificationDispatchServiceTest.php`

**Interfaces:**
- Consumes: `App\Services\StudentAttendance\SmsGateService::send(string $phone, string $message): bool` (existing).
- Produces: `SosNotificationDispatchService::notifyTier(SosAlert $alert, SosEscalationTier $tier): void` — resolves responders (role + explicit users), sends SMS/email per `$tier->channels`, logs an `in_app` entry for audit, and notifies configured external contacts when `$tier->notify_external`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Role;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Models\Sos\SosExternalContact;
use App\Models\HR\EmployeeProfile;
use App\Models\User;
use App\Services\Sos\SosNotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SosNotificationDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_tier_sms_and_emails_role_and_explicit_responders_and_external_contacts(): void
    {
        config(['services.sms_gate.url' => 'https://sms.test/send', 'services.sms_gate.username' => 'u', 'services.sms_gate.password' => 'p']);
        Http::fake(['sms.test/*' => Http::response(['ok' => true], 200)]);
        Mail::fake();

        $role = Role::create(['name' => 'Security Guard']);
        $roleUser = User::factory()->create(['email' => 'guard@crc.pshs.edu.ph']);
        $roleUser->roles()->attach($role->id);
        EmployeeProfile::create(['user_id' => $roleUser->id, 'mobile_number' => '09171234567']);

        $explicitUser = User::factory()->create(['email' => 'drrm@crc.pshs.edu.ph']);
        EmployeeProfile::create(['user_id' => $explicitUser->id, 'mobile_number' => '09179876543']);

        $tier = SosEscalationTier::create([
            'alert_type' => 'fire_disaster', 'order' => 1, 'role_id' => $role->id,
            'timeout_minutes' => 10, 'channels' => ['sms', 'email', 'in_app'], 'notify_external' => true,
        ]);
        $tier->users()->attach($explicitUser->id);

        SosExternalContact::create([
            'name' => 'Butuan BFP', 'phone' => '09170000001', 'alert_types' => ['fire_disaster'], 'channel' => 'sms', 'active' => true,
        ]);
        SosExternalContact::create([
            'name' => 'Wrong Type Contact', 'phone' => '09170000002', 'alert_types' => ['medical'], 'channel' => 'sms', 'active' => true,
        ]);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'fire_disaster', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        app(SosNotificationDispatchService::class)->notifyTier($alert, $tier);

        Http::assertSentCount(3); // roleUser + explicitUser + the matching external contact
        Mail::assertSent(\App\Mail\SosAlertMail::class, 2); // roleUser + explicitUser

        $this->assertDatabaseHas('sos_notification_logs', ['sos_alert_id' => $alert->id, 'channel' => 'sms', 'recipient_id' => $roleUser->id]);
        $this->assertDatabaseHas('sos_notification_logs', ['sos_alert_id' => $alert->id, 'channel' => 'in_app', 'recipient_id' => $explicitUser->id]);
        $this->assertDatabaseHas('sos_notification_logs', ['sos_alert_id' => $alert->id, 'recipient_type' => 'external_contact']);
        $this->assertDatabaseMissing('sos_notification_logs', ['sos_alert_id' => $alert->id, 'recipient_label' => 'Wrong Type Contact']);
    }

    public function test_notify_tier_skips_sms_for_responder_without_mobile_number(): void
    {
        $role = Role::create(['name' => 'Nurse']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        EmployeeProfile::create(['user_id' => $user->id]); // no mobile_number

        $tier = SosEscalationTier::create([
            'alert_type' => 'medical', 'order' => 1, 'role_id' => $role->id,
            'timeout_minutes' => 10, 'channels' => ['sms'], 'notify_external' => false,
        ]);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        app(SosNotificationDispatchService::class)->notifyTier($alert, $tier);

        $this->assertDatabaseMissing('sos_notification_logs', ['sos_alert_id' => $alert->id, 'channel' => 'sms']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosNotificationDispatchServiceTest.php"`
Expected: FAIL — service and `SosAlertMail` don't exist.

- [ ] **Step 3: Write `SosAlertMail` + blade + `SosNotificationDispatchService::notifyTier()`**

`app/Mail/SosAlertMail.php`:
```php
<?php

namespace App\Mail;

use App\Models\Sos\SosAlert;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SosAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SosAlert $alert,
        public readonly ?User $recipient,
    ) {}

    public function build()
    {
        $label = ucfirst(str_replace('_', ' ', $this->alert->alert_type));

        return $this->subject("PSHS-CRC SOS Alert — {$label}")
            ->view('emails.sos_alert')
            ->with(['alert' => $this->alert, 'recipient' => $this->recipient]);
    }
}
```

`resources/views/emails/sos_alert.blade.php`:
```blade
@extends('emails.layouts.base')

@section('header-title')SOS Alert — {{ ucfirst(str_replace('_',' ', $alert->alert_type)) }}@endsection
@section('header-subtitle','Atlas — Emergency Response')

@section('content')
<p class="greeting">Hello{{ $recipient ? ' ' . $recipient->name : '' }},</p>
<p class="lead">An SOS emergency alert has been triggered on campus. Please respond immediately.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Alert ID</td><td class="val"><strong>#{{ $alert->id }}</strong></td></tr>
    <tr><td class="lbl">Type</td><td class="val">{{ ucfirst(str_replace('_',' ', $alert->alert_type)) }}</td></tr>
    <tr><td class="lbl">Triggered At</td><td class="val">{{ $alert->triggered_at->timezone('Asia/Manila')->format('F j, Y — g:i:s A') }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-red">{{ ucfirst($alert->status) }}</span></td></tr>
</table>

<p class="lead">Log in to the Atlas SOS Command Center to acknowledge and respond.</p>
@endsection
```

Add to `app/Services/Sos/SosNotificationDispatchService.php`:
```php
<?php

namespace App\Services\Sos;

use App\Mail\SosAlertMail;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Models\Sos\SosExternalContact;
use App\Models\Sos\SosNotificationLog;
use App\Models\User;
use App\Services\StudentAttendance\SmsGateService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SosNotificationDispatchService
{
    public function __construct(private readonly SmsGateService $sms) {}

    public function notifyTier(SosAlert $alert, SosEscalationTier $tier): void
    {
        foreach ($this->resolveTierResponders($tier) as $user) {
            if (in_array('sms', $tier->channels, true)) {
                $mobile = $user->employeeProfile?->mobile_number;
                if ($mobile) {
                    $sent = $this->sms->send($mobile, $this->smsMessageFor($alert));
                    $this->log($alert, 'sms', 'user', $user->id, $user->name, $sent);
                }
            }

            if (in_array('email', $tier->channels, true) && $user->email) {
                $sent = true;
                try {
                    Mail::to($user->email)->send(new SosAlertMail($alert, $user));
                } catch (Throwable $e) {
                    Log::warning('Sos: failed to email responder.', ['user_id' => $user->id, 'error' => $e->getMessage()]);
                    $sent = false;
                }
                $this->log($alert, 'email', 'user', $user->id, $user->name, $sent);
            }

            if (in_array('in_app', $tier->channels, true)) {
                // Real delivery is the SosAlertTriggered/SosAlertUpdated broadcast
                // itself — logged here for audit completeness only.
                $this->log($alert, 'in_app', 'user', $user->id, $user->name, true);
            }
        }

        if ($tier->notify_external) {
            $this->notifyExternalContacts($alert);
        }
    }

    private function resolveTierResponders(SosEscalationTier $tier): Collection
    {
        $fromRole = $tier->role_id
            ? User::whereHas('roles', fn ($q) => $q->where('roles.id', $tier->role_id))->get()
            : collect();

        return $fromRole->merge($tier->users)->unique('id');
    }

    private function notifyExternalContacts(SosAlert $alert): void
    {
        $contacts = SosExternalContact::where('active', true)->get()
            ->filter(fn (SosExternalContact $c) => in_array($alert->alert_type, $c->alert_types ?? [], true));

        foreach ($contacts as $contact) {
            if (in_array($contact->channel, ['sms', 'both'], true) && $contact->phone) {
                $sent = $this->sms->send($contact->phone, $this->smsMessageFor($alert));
                $this->log($alert, 'sms', 'external_contact', $contact->id, $contact->name, $sent);
            }

            if (in_array($contact->channel, ['email', 'both'], true) && $contact->email) {
                $sent = true;
                try {
                    Mail::to($contact->email)->send(new SosAlertMail($alert, null));
                } catch (Throwable $e) {
                    Log::warning('Sos: failed to email external contact.', ['contact_id' => $contact->id, 'error' => $e->getMessage()]);
                    $sent = false;
                }
                $this->log($alert, 'email', 'external_contact', $contact->id, $contact->name, $sent);
            }
        }
    }

    private function smsMessageFor(SosAlert $alert): string
    {
        $label = ucfirst(str_replace('_', ' ', $alert->alert_type));
        return "PSHS-CRC SOS Alert #{$alert->id} ({$label}) triggered on campus. Respond via the Atlas SOS Command Center.";
    }

    private function log(SosAlert $alert, string $channel, string $recipientType, ?int $recipientId, ?string $label, bool $sent): void
    {
        SosNotificationLog::create([
            'sos_alert_id'    => $alert->id,
            'channel'         => $channel,
            'recipient_type'  => $recipientType,
            'recipient_id'    => $recipientId,
            'recipient_label' => $label,
            'sent'            => $sent,
            'sent_at'         => $sent ? now() : null,
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosNotificationDispatchServiceTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Sos/SosNotificationDispatchService.php app/Mail/SosAlertMail.php resources/views/emails/sos_alert.blade.php tests/Feature/Sos/SosNotificationDispatchServiceTest.php
git commit -m "feat(sos): notify tier responders and external contacts via SMS/email"
```

---

## Task 11: `SosNotificationDispatchService::notifyEmergencyContact()`

**Files:**
- Modify: `app/Services/Sos/SosNotificationDispatchService.php`
- Test: `tests/Feature/Sos/SosEmergencyContactNotificationTest.php`

**Interfaces:**
- Consumes: `App\Services\StudentAttendance\FcmService::send(string $token, string $title, string $body, array $data = []): bool` (existing), `Student::parentContacts()` (existing).
- Produces: `notifyEmergencyContact(SosAlert $alert): void` — staff → SMS to `EmployeeProfile.emergency_contact_phone`; students → push + SMS to linked `ParentContact` rows per their `notify_push`/`notify_sms` flags.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\HR\EmployeeProfile;
use App\Models\Sos\SosAlert;
use App\Models\Student;
use App\Models\StudentAttendance\ParentContact;
use App\Models\User;
use App\Services\Sos\SosNotificationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SosEmergencyContactNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_trigger_sms_the_employee_emergency_contact(): void
    {
        config(['services.sms_gate.url' => 'https://sms.test/send', 'services.sms_gate.username' => 'u', 'services.sms_gate.password' => 'p']);
        Http::fake(['sms.test/*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->create(['name' => 'Juan Dela Cruz']);
        EmployeeProfile::create([
            'user_id' => $user->id,
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_contact_phone' => '09171112222',
        ]);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => $user->id,
            'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        app(SosNotificationDispatchService::class)->notifyEmergencyContact($alert);

        Http::assertSentCount(1);
        $this->assertDatabaseHas('sos_notification_logs', [
            'sos_alert_id' => $alert->id, 'channel' => 'sms', 'recipient_type' => 'user_emergency_contact', 'recipient_label' => 'Maria Dela Cruz',
        ]);
    }

    public function test_student_trigger_pushes_and_sms_all_linked_parents(): void
    {
        config(['services.sms_gate.url' => 'https://sms.test/send', 'services.sms_gate.username' => 'u', 'services.sms_gate.password' => 'p']);
        Http::fake(['sms.test/*' => Http::response(['ok' => true], 200)]);

        // Student is deliberately guarded (read-only legacy table) — Eloquent
        // mass-assignment is blocked, so seed it the same way the rest of this
        // codebase's tests do: a raw DB insert, then read back via the model.
        $studentId = \Illuminate\Support\Facades\DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-001', 'firstname' => 'Test', 'lastname' => 'Student',
        ]);
        $student = Student::find($studentId);
        $parent = ParentContact::create([
            'name' => 'Parent One', 'email' => 'parent@example.com', 'password' => bcrypt('x'),
            'notify_push' => true, 'notify_sms' => true, 'fcm_device_token' => 'token-abc', 'mobile_phone' => '09173334444',
        ]);
        $parent->students()->attach($student->id, ['relationship' => 'guardian']);

        $alert = SosAlert::create([
            'triggerable_type' => Student::class, 'triggerable_id' => $student->id,
            'alert_type' => 'security', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        app(SosNotificationDispatchService::class)->notifyEmergencyContact($alert);

        $this->assertDatabaseHas('sos_notification_logs', ['sos_alert_id' => $alert->id, 'channel' => 'push', 'recipient_id' => $parent->id]);
        $this->assertDatabaseHas('sos_notification_logs', ['sos_alert_id' => $alert->id, 'channel' => 'sms', 'recipient_id' => $parent->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosEmergencyContactNotificationTest.php"`
Expected: FAIL — `notifyEmergencyContact()` doesn't exist.

- [ ] **Step 3: Add `notifyEmergencyContact()`**

Add to `app/Services/Sos/SosNotificationDispatchService.php` (add constructor param `FcmService $fcm`, imports `App\Models\Student`, `App\Services\StudentAttendance\FcmService`):

```php
    public function __construct(
        private readonly SmsGateService $sms,
        private readonly FcmService $fcm,
    ) {}

    public function notifyEmergencyContact(SosAlert $alert): void
    {
        $triggerable = $alert->triggerable;

        if ($triggerable instanceof User) {
            $profile = $triggerable->employeeProfile;
            if ($profile && $profile->emergency_contact_phone) {
                $sent = $this->sms->send(
                    $profile->emergency_contact_phone,
                    "PSHS-CRC SOS Alert: {$triggerable->name} triggered an emergency alert on campus. Please contact the school immediately.",
                );
                $this->log($alert, 'sms', 'user_emergency_contact', null, $profile->emergency_contact_name, $sent);
            }
            return;
        }

        if ($triggerable instanceof \App\Models\Student) {
            // Student has no `name` attribute — the legacy table only has
            // firstname/lastname (see app/Models/Student.php, read-only/guarded).
            $studentName = trim("{$triggerable->firstname} {$triggerable->lastname}") ?: 'Your child';

            foreach ($triggerable->parentContacts as $parent) {
                if ($parent->wantsPushNotification()) {
                    $sent = $this->fcm->send(
                        $parent->fcm_device_token,
                        'Emergency SOS Alert',
                        "{$studentName} triggered an SOS alert on campus.",
                        ['type' => 'sos_alert', 'alert_id' => (string) $alert->id],
                    );
                    $this->log($alert, 'push', 'parent_contact', $parent->id, $parent->name, $sent);
                }

                if ($parent->notify_sms && ! empty($parent->mobile_phone)) {
                    $sent = $this->sms->send(
                        $parent->mobile_phone,
                        "PSHS-CRC SOS Alert: {$studentName} triggered an emergency alert on campus.",
                    );
                    $this->log($alert, 'sms', 'parent_contact', $parent->id, $parent->name, $sent);
                }
            }
        }
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosEmergencyContactNotificationTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Sos/SosNotificationDispatchService.php tests/Feature/Sos/SosEmergencyContactNotificationTest.php
git commit -m "feat(sos): notify triggering user's own emergency contact/guardian"
```

---

## Task 12: Wire up `NotifySosResponders` / `NotifySosEmergencyContact` jobs

**Files:**
- Modify: `app/Jobs/Sos/NotifySosResponders.php`
- Modify: `app/Jobs/Sos/NotifySosEmergencyContact.php`
- Test: `tests/Feature/Sos/SosNotifyJobsTest.php`

**Interfaces:**
- Consumes: `SosNotificationDispatchService::notifyTier()`, `::notifyEmergencyContact()` (Tasks 10-11).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Jobs\Sos\NotifySosEmergencyContact;
use App\Jobs\Sos\NotifySosResponders;
use App\Models\HR\EmployeeProfile;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SosNotifyJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notify_sos_responders_job_dispatches_tier_notifications(): void
    {
        config(['services.sms_gate.url' => 'https://sms.test/send', 'services.sms_gate.username' => 'u', 'services.sms_gate.password' => 'p']);
        Http::fake(['sms.test/*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->create();
        EmployeeProfile::create(['user_id' => $user->id, 'mobile_number' => '09171234567']);
        $tier = SosEscalationTier::create(['alert_type' => 'medical', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['sms'], 'notify_external' => false]);
        $tier->users()->attach($user->id);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        (new NotifySosResponders($alert->id, $tier->id))->handle(app(\App\Services\Sos\SosNotificationDispatchService::class));

        $this->assertDatabaseHas('sos_notification_logs', ['sos_alert_id' => $alert->id, 'recipient_id' => $user->id]);
    }

    public function test_notify_sos_emergency_contact_job_notifies_guardian(): void
    {
        config(['services.sms_gate.url' => 'https://sms.test/send', 'services.sms_gate.username' => 'u', 'services.sms_gate.password' => 'p']);
        Http::fake(['sms.test/*' => Http::response(['ok' => true], 200)]);

        $user = User::factory()->create();
        EmployeeProfile::create(['user_id' => $user->id, 'emergency_contact_name' => 'Contact', 'emergency_contact_phone' => '09170001111']);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => $user->id,
            'alert_type' => 'general', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        (new NotifySosEmergencyContact($alert->id))->handle(app(\App\Services\Sos\SosNotificationDispatchService::class));

        $this->assertDatabaseHas('sos_notification_logs', ['sos_alert_id' => $alert->id, 'recipient_type' => 'user_emergency_contact']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosNotifyJobsTest.php"`
Expected: FAIL — `handle()` is still a no-op stub from Task 7.

- [ ] **Step 3: Implement the jobs' `handle()`**

`app/Jobs/Sos/NotifySosResponders.php` — replace the `handle()` body:
```php
<?php

namespace App\Jobs\Sos;

use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Services\Sos\SosNotificationDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifySosResponders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $sosAlertId,
        public readonly int $tierId,
    ) {}

    public function handle(SosNotificationDispatchService $dispatch): void
    {
        $alert = SosAlert::find($this->sosAlertId);
        $tier  = SosEscalationTier::find($this->tierId);

        if (! $alert || ! $tier) return;

        $dispatch->notifyTier($alert, $tier);
    }
}
```

`app/Jobs/Sos/NotifySosEmergencyContact.php` — replace the `handle()` body:
```php
<?php

namespace App\Jobs\Sos;

use App\Models\Sos\SosAlert;
use App\Services\Sos\SosNotificationDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifySosEmergencyContact implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $sosAlertId) {}

    public function handle(SosNotificationDispatchService $dispatch): void
    {
        $alert = SosAlert::find($this->sosAlertId);
        if (! $alert) return;

        $dispatch->notifyEmergencyContact($alert);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosNotifyJobsTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/Sos tests/Feature/Sos/SosNotifyJobsTest.php
git commit -m "feat(sos): wire notification jobs to SosNotificationDispatchService"
```

---

## Task 13: `sos:process-escalations` command + schedule

**Files:**
- Create: `app/Console/Commands/ProcessSosEscalations.php`
- Modify: `routes/console.php`
- Test: `tests/Feature/Sos/ProcessSosEscalationsCommandTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProcessSosEscalationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_escalates_overdue_alerts(): void
    {
        Queue::fake();

        SosEscalationTier::create(['alert_type' => 'general', 'order' => 1, 'timeout_minutes' => 5, 'channels' => ['in_app'], 'notify_external' => false]);
        SosEscalationTier::create(['alert_type' => 'general', 'order' => 2, 'timeout_minutes' => null, 'channels' => ['in_app'], 'notify_external' => true]);

        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'general', 'status' => 'triggered', 'current_tier_order' => 1,
            'triggered_at' => now()->subMinutes(10),
        ]);
        $alert->events()->create(['type' => 'triggered', 'actor_type' => null, 'actor_id' => null, 'payload' => null, 'created_at' => now()->subMinutes(10)]);

        $this->artisan('sos:process-escalations')
            ->expectsOutputToContain('Escalated 1 SOS alert(s).')
            ->assertExitCode(0);

        $this->assertSame('escalated', $alert->fresh()->status);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/ProcessSosEscalationsCommandTest.php"`
Expected: FAIL — command doesn't exist.

- [ ] **Step 3: Write the command and register the schedule**

`app/Console/Commands/ProcessSosEscalations.php`:
```php
<?php

namespace App\Console\Commands;

use App\Services\Sos\SosAlertService;
use Illuminate\Console\Command;

class ProcessSosEscalations extends Command
{
    protected $signature = 'sos:process-escalations';

    protected $description = 'Advance SOS alerts past their current tier timeout to the next escalation tier';

    public function handle(SosAlertService $service): int
    {
        $count = $service->processEscalations();
        $this->info("Escalated {$count} SOS alert(s).");

        return self::SUCCESS;
    }
}
```

In `routes/console.php`, add near the other `everyMinute()` entry:
```php
Schedule::command('sos:process-escalations')->everyMinute()->withoutOverlapping();
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/ProcessSosEscalationsCommandTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/ProcessSosEscalations.php routes/console.php tests/Feature/Sos/ProcessSosEscalationsCommandTest.php
git commit -m "feat(sos): add sos:process-escalations scheduled command"
```

---

## Task 14: Staff `SosAlertController` + routes

**Files:**
- Create: `app/Http/Controllers/Sos/SosAlertController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Sos/SosAlertControllerTest.php`

**Interfaces:**
- Produces routes: `POST /sos/trigger` (`sos.trigger`, any authenticated user), `GET /sos` (`sos.index`, `sos.respond`), `GET /sos/{alert}` (`sos.show`, `sos.respond`), `POST /sos/{alert}/acknowledge|verify|false-alarm|resolve` (`sos.respond`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Sos\SosAlert;
use App\Models\Sos\SosEscalationTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosAlertControllerTest extends TestCase
{
    use RefreshDatabase;

    private function responder(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'sos.respond'], ['module' => 'SOS', 'description' => 'x']);
        $role = Role::create(['name' => 'DRRM Coordinator']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        return $user;
    }

    public function test_any_authenticated_user_can_trigger(): void
    {
        SosEscalationTier::create(['alert_type' => 'general', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/sos/trigger', [
            'alert_type' => 'general', 'is_silent' => false,
        ]);

        $response->assertCreated()->assertJson(['blocked' => false]);
        $this->assertDatabaseHas('sos_alerts', ['triggerable_id' => $user->id, 'triggerable_type' => User::class]);
    }

    public function test_trigger_without_permission_still_works_since_sos_trigger_is_not_gated(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->postJson('/sos/trigger', ['alert_type' => 'medical']);
        $response->assertStatus(201);
    }

    public function test_command_center_requires_sos_respond_permission(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/sos')->assertForbidden();

        $responder = $this->responder();
        $this->actingAs($responder)->get('/sos')->assertOk();
    }

    public function test_acknowledge_and_resolve_flow(): void
    {
        $responder = $this->responder();
        $alert = SosAlert::create([
            'triggerable_type' => User::class, 'triggerable_id' => User::factory()->create()->id,
            'alert_type' => 'medical', 'status' => 'triggered', 'current_tier_order' => 1, 'triggered_at' => now(),
        ]);

        $this->actingAs($responder)->postJson("/sos/{$alert->id}/acknowledge")
            ->assertOk()->assertJsonPath('status', 'acknowledged');

        $this->actingAs($responder)->postJson("/sos/{$alert->id}/resolve", ['notes' => 'Handled'])
            ->assertOk()->assertJsonPath('status', 'resolved');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertControllerTest.php"`
Expected: FAIL — controller/routes don't exist.

- [ ] **Step 3: Write the controller and routes**

`app/Http/Controllers/Sos/SosAlertController.php`:
```php
<?php

namespace App\Http\Controllers\Sos;

use App\Http\Controllers\Controller;
use App\Models\Sos\SosAlert;
use App\Services\Sos\SosAlertService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SosAlertController extends Controller
{
    public function trigger(Request $request, SosAlertService $service)
    {
        $validated = $request->validate([
            'alert_type' => 'required|in:medical,security,fire_disaster,general',
            'is_silent'  => 'boolean',
            'lat'        => 'nullable|numeric',
            'lng'        => 'nullable|numeric',
            'accuracy'   => 'nullable|numeric',
        ]);

        $result = $service->trigger(
            triggerable: $request->user(),
            alertType:   $validated['alert_type'],
            isSilent:    $validated['is_silent'] ?? false,
            lat:         $validated['lat'] ?? null,
            lng:         $validated['lng'] ?? null,
            accuracy:    $validated['accuracy'] ?? null,
            ip:          $request->ip(),
        );

        if ($result['blocked']) {
            return response()->json([
                'blocked'           => true,
                'message'           => config('sos.off_campus_message'),
                'emergency_hotline' => config('sos.emergency_hotline_number'),
            ], 422);
        }

        return response()->json(['blocked' => false, 'alert_id' => $result['alert']->id], 201);
    }

    public function index()
    {
        $alerts = SosAlert::with(['events' => fn ($q) => $q->orderByDesc('created_at')])
            ->orderByDesc('triggered_at')
            ->limit(100)
            ->get()
            ->map(fn (SosAlert $alert) => $this->serialize($alert));

        return Inertia::render('Sos/CommandCenter', ['alerts' => $alerts]);
    }

    public function show(SosAlert $alert)
    {
        return response()->json($this->serialize($alert->load('events')));
    }

    public function acknowledge(Request $request, SosAlert $alert, SosAlertService $service)
    {
        $service->acknowledge($alert, $request->user());
        return response()->json($this->serialize($alert->fresh()));
    }

    public function verify(Request $request, SosAlert $alert, SosAlertService $service)
    {
        $validated = $request->validate(['note' => 'nullable|string|max:1000']);
        $service->verify($alert, $request->user(), $validated['note'] ?? null);
        return response()->json($this->serialize($alert->fresh()));
    }

    public function falseAlarm(Request $request, SosAlert $alert, SosAlertService $service)
    {
        $validated = $request->validate(['reason' => 'required|string|max:1000']);
        $service->markFalseAlarm($alert, $request->user(), $validated['reason']);
        return response()->json($this->serialize($alert->fresh()));
    }

    public function resolve(Request $request, SosAlert $alert, SosAlertService $service)
    {
        $validated = $request->validate(['notes' => 'nullable|string|max:2000']);
        $service->resolve($alert, $request->user(), $validated['notes'] ?? null);
        return response()->json($this->serialize($alert->fresh()));
    }

    private function serialize(SosAlert $alert): array
    {
        return [
            'id'           => $alert->id,
            'alert_type'   => $alert->alert_type,
            'is_silent'    => $alert->is_silent,
            'status'       => $alert->status,
            'lat'          => $alert->lat,
            'lng'          => $alert->lng,
            'triggered_at' => $alert->triggered_at->toIso8601String(),
            'resolved_at'  => $alert->resolved_at?->toIso8601String(),
            'events'       => $alert->relationLoaded('events')
                ? $alert->events->map(fn ($e) => [
                    'type'       => $e->type,
                    'payload'    => $e->payload,
                    'created_at' => $e->created_at->toIso8601String(),
                ])->values()
                : [],
        ];
    }
}
```

In `routes/web.php`, add a new block right after the `lost-found` route group:

```php
Route::middleware(['auth'])->prefix('sos')->name('sos.')->group(function () {
    Route::post('/trigger', [\App\Http\Controllers\Sos\SosAlertController::class, 'trigger'])->name('trigger');

    Route::middleware('permission:sos.respond')->group(function () {
        Route::get('/', [\App\Http\Controllers\Sos\SosAlertController::class, 'index'])->name('index');
        Route::get('/{alert}', [\App\Http\Controllers\Sos\SosAlertController::class, 'show'])->name('show')->whereNumber('alert');
        Route::post('/{alert}/acknowledge', [\App\Http\Controllers\Sos\SosAlertController::class, 'acknowledge'])->name('acknowledge')->whereNumber('alert');
        Route::post('/{alert}/verify', [\App\Http\Controllers\Sos\SosAlertController::class, 'verify'])->name('verify')->whereNumber('alert');
        Route::post('/{alert}/false-alarm', [\App\Http\Controllers\Sos\SosAlertController::class, 'falseAlarm'])->name('false-alarm')->whereNumber('alert');
        Route::post('/{alert}/resolve', [\App\Http\Controllers\Sos\SosAlertController::class, 'resolve'])->name('resolve')->whereNumber('alert');
    });
});
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertControllerTest.php"`
Expected: PASS

- [ ] **Step 5: Re-run Task 8's lifecycle test**, which depends on `route('sos.show', ...)`:

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosAlertServiceLifecycleTest.php"`
Expected: PASS (was previously blocked on this route existing).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Sos/SosAlertController.php routes/web.php tests/Feature/Sos/SosAlertControllerTest.php
git commit -m "feat(sos): add staff SOS trigger and Command Center endpoints"
```

---

## Task 15: Student Portal `SosAlertController` + route

**Files:**
- Create: `app/Http/Controllers/StudentPortal/SosAlertController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Sos/StudentPortalSosTriggerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Sos\SosEscalationTier;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPortalSosTriggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_student_can_trigger_sos(): void
    {
        SosEscalationTier::create(['alert_type' => 'security', 'order' => 1, 'timeout_minutes' => 10, 'channels' => ['in_app'], 'notify_external' => false]);
        // Student is guarded (read-only legacy table) — seed via raw insert,
        // matching this codebase's existing test convention.
        $studentId = \Illuminate\Support\Facades\DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-002', 'firstname' => 'Test', 'lastname' => 'Student Two',
        ]);

        $response = $this->withSession(['student_pisaysystemID' => 'TEST-002'])
            ->postJson('/student-portal/sos/trigger', ['alert_type' => 'security', 'is_silent' => true]);

        $response->assertCreated()->assertJson(['blocked' => false]);
        $this->assertDatabaseHas('sos_alerts', [
            'triggerable_type' => Student::class, 'triggerable_id' => $studentId, 'is_silent' => true,
        ]);
    }

    public function test_logged_out_visitor_cannot_trigger(): void
    {
        $this->postJson('/student-portal/sos/trigger', ['alert_type' => 'general'])
            ->assertRedirect(route('student-portal.login'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/StudentPortalSosTriggerTest.php"`
Expected: FAIL — controller/route don't exist.

- [ ] **Step 3: Write the controller and route**

`app/Http/Controllers/StudentPortal/SosAlertController.php`:
```php
<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\Sos\SosAlertService;
use Illuminate\Http\Request;

class SosAlertController extends Controller
{
    public function trigger(Request $request, SosAlertService $service)
    {
        $validated = $request->validate([
            'alert_type' => 'required|in:medical,security,fire_disaster,general',
            'is_silent'  => 'boolean',
            'lat'        => 'nullable|numeric',
            'lng'        => 'nullable|numeric',
            'accuracy'   => 'nullable|numeric',
        ]);

        $student = Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();

        $result = $service->trigger(
            triggerable: $student,
            alertType:   $validated['alert_type'],
            isSilent:    $validated['is_silent'] ?? false,
            lat:         $validated['lat'] ?? null,
            lng:         $validated['lng'] ?? null,
            accuracy:    $validated['accuracy'] ?? null,
            ip:          $request->ip(),
        );

        if ($result['blocked']) {
            return response()->json([
                'blocked'           => true,
                'message'           => config('sos.off_campus_message'),
                'emergency_hotline' => config('sos.emergency_hotline_number'),
            ], 422);
        }

        return response()->json(['blocked' => false, 'alert_id' => $result['alert']->id], 201);
    }
}
```

In `routes/web.php`, inside the existing `Route::middleware('student.portal')->group(function () { ... })` block (the one starting around the `/dashboard` route), add:

```php
        Route::post('/sos/trigger', [\App\Http\Controllers\StudentPortal\SosAlertController::class, 'trigger'])->name('sos.trigger');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/StudentPortalSosTriggerTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/StudentPortal/SosAlertController.php routes/web.php tests/Feature/Sos/StudentPortalSosTriggerTest.php
git commit -m "feat(sos): add Student Portal SOS trigger endpoint"
```

---

## Task 16: `SosSettingsController` (tiers, external contacts, responder mobile) + routes

**Files:**
- Create: `app/Http/Controllers/Sos/SosSettingsController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Sos/SosSettingsControllerTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\HR\EmployeeProfile;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosSettingsControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $permission = Permission::firstOrCreate(['name' => 'sos.manage'], ['module' => 'SOS', 'description' => 'x']);
        $role = Role::create(['name' => 'Administrator']);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);
        return $user;
    }

    public function test_non_admin_cannot_access_settings(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/sos/settings')->assertForbidden();
    }

    public function test_admin_can_create_tier_and_external_contact(): void
    {
        $admin = $this->admin();
        $responder = User::factory()->create();

        $this->actingAs($admin)->post('/sos/settings/tiers', [
            'alert_type' => 'fire_disaster', 'order' => 1, 'timeout_minutes' => 15,
            'channels' => ['in_app', 'sms'], 'notify_external' => true, 'user_ids' => [$responder->id],
        ])->assertRedirect();

        $this->assertDatabaseHas('sos_escalation_tiers', ['alert_type' => 'fire_disaster', 'order' => 1]);
        $this->assertDatabaseHas('sos_escalation_tier_users', ['user_id' => $responder->id]);

        $this->actingAs($admin)->post('/sos/settings/external-contacts', [
            'name' => 'Butuan BFP', 'phone' => '09170000001', 'alert_types' => ['fire_disaster'], 'channel' => 'sms', 'active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('sos_external_contacts', ['name' => 'Butuan BFP']);
    }

    public function test_admin_can_set_responder_mobile_number(): void
    {
        $admin = $this->admin();
        $responder = User::factory()->create();
        EmployeeProfile::create(['user_id' => $responder->id]);

        $this->actingAs($admin)->post("/sos/settings/responders/{$responder->id}/mobile", [
            'mobile_number' => '09171234567',
        ])->assertRedirect();

        $this->assertSame('09171234567', $responder->fresh()->employeeProfile->mobile_number);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosSettingsControllerTest.php"`
Expected: FAIL — controller/routes don't exist.

- [ ] **Step 3: Write the controller and routes**

`app/Http/Controllers/Sos/SosSettingsController.php`:
```php
<?php

namespace App\Http\Controllers\Sos;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Sos\SosEscalationTier;
use App\Models\Sos\SosExternalContact;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SosSettingsController extends Controller
{
    public function index()
    {
        return Inertia::render('Sos/Settings', [
            'tiers'            => SosEscalationTier::with('role', 'users')->orderBy('alert_type')->orderBy('order')->get(),
            'externalContacts' => SosExternalContact::orderBy('name')->get(),
            'roles'            => Role::select('id', 'name')->get(),
            'users'            => User::employees()
                ->with('employeeProfile:id,user_id,mobile_number')
                ->select('id', 'name')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeTier(Request $request)
    {
        $data = $request->validate([
            'alert_type'      => 'required|in:medical,security,fire_disaster,general',
            'order'           => 'required|integer|min:1',
            'role_id'         => 'nullable|exists:roles,id',
            'timeout_minutes' => 'nullable|integer|min:1',
            'channels'        => 'required|array|min:1',
            'channels.*'      => 'in:in_app,sms,email',
            'notify_external' => 'boolean',
            'user_ids'        => 'array',
            'user_ids.*'      => 'exists:users,id',
        ]);

        $tier = SosEscalationTier::create(collect($data)->except('user_ids')->all());
        $tier->users()->sync($data['user_ids'] ?? []);

        return back()->with('success', 'Escalation tier saved.');
    }

    public function updateTier(Request $request, SosEscalationTier $tier)
    {
        $data = $request->validate([
            'order'           => 'required|integer|min:1',
            'role_id'         => 'nullable|exists:roles,id',
            'timeout_minutes' => 'nullable|integer|min:1',
            'channels'        => 'required|array|min:1',
            'channels.*'      => 'in:in_app,sms,email',
            'notify_external' => 'boolean',
            'user_ids'        => 'array',
            'user_ids.*'      => 'exists:users,id',
        ]);

        $tier->update(collect($data)->except('user_ids')->all());
        $tier->users()->sync($data['user_ids'] ?? []);

        return back()->with('success', 'Escalation tier updated.');
    }

    public function destroyTier(SosEscalationTier $tier)
    {
        $tier->delete();
        return back()->with('success', 'Escalation tier removed.');
    }

    public function storeExternalContact(Request $request)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'org'           => 'nullable|string|max:255',
            'phone'         => 'nullable|string|max:32',
            'email'         => 'nullable|email|max:255',
            'alert_types'   => 'required|array|min:1',
            'alert_types.*' => 'in:medical,security,fire_disaster,general',
            'channel'       => 'required|in:sms,email,both',
            'active'        => 'boolean',
        ]);

        SosExternalContact::create($data);
        return back()->with('success', 'External contact added.');
    }

    public function updateExternalContact(Request $request, SosExternalContact $contact)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'org'           => 'nullable|string|max:255',
            'phone'         => 'nullable|string|max:32',
            'email'         => 'nullable|email|max:255',
            'alert_types'   => 'required|array|min:1',
            'alert_types.*' => 'in:medical,security,fire_disaster,general',
            'channel'       => 'required|in:sms,email,both',
            'active'        => 'boolean',
        ]);

        $contact->update($data);
        return back()->with('success', 'External contact updated.');
    }

    public function destroyExternalContact(SosExternalContact $contact)
    {
        $contact->delete();
        return back()->with('success', 'External contact removed.');
    }

    public function updateResponderMobile(Request $request, User $user)
    {
        $data = $request->validate(['mobile_number' => 'nullable|string|max:20']);

        $profile = $user->employeeProfile;
        if (! $profile) {
            return back()->with('error', "{$user->name} has no employee profile yet — mobile number can't be set.");
        }

        $profile->update(['mobile_number' => $data['mobile_number']]);
        return back()->with('success', 'Mobile number updated.');
    }
}
```

In `routes/web.php`, inside the `sos.` route group added in Task 14, add a nested `sos.manage`-gated group:

```php
    Route::middleware('permission:sos.manage')->prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [\App\Http\Controllers\Sos\SosSettingsController::class, 'index'])->name('index');
        Route::post('/tiers', [\App\Http\Controllers\Sos\SosSettingsController::class, 'storeTier'])->name('tiers.store');
        Route::put('/tiers/{tier}', [\App\Http\Controllers\Sos\SosSettingsController::class, 'updateTier'])->name('tiers.update')->whereNumber('tier');
        Route::delete('/tiers/{tier}', [\App\Http\Controllers\Sos\SosSettingsController::class, 'destroyTier'])->name('tiers.destroy')->whereNumber('tier');
        Route::post('/external-contacts', [\App\Http\Controllers\Sos\SosSettingsController::class, 'storeExternalContact'])->name('external-contacts.store');
        Route::put('/external-contacts/{contact}', [\App\Http\Controllers\Sos\SosSettingsController::class, 'updateExternalContact'])->name('external-contacts.update')->whereNumber('contact');
        Route::delete('/external-contacts/{contact}', [\App\Http\Controllers\Sos\SosSettingsController::class, 'destroyExternalContact'])->name('external-contacts.destroy')->whereNumber('contact');
        Route::post('/responders/{user}/mobile', [\App\Http\Controllers\Sos\SosSettingsController::class, 'updateResponderMobile'])->name('responders.mobile')->whereNumber('user');
    });
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosSettingsControllerTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Sos/SosSettingsController.php routes/web.php tests/Feature/Sos/SosSettingsControllerTest.php
git commit -m "feat(sos): add admin SOS settings endpoints"
```

---

## Task 17: Default escalation tier seeder

**Files:**
- Create: `database/seeders/SosDefaultEscalationTierSeeder.php`
- Test: `tests/Feature/Sos/SosDefaultEscalationTierSeederTest.php`

**Interfaces:**
- Produces: two tiers per `alert_type` (`medical`, `security`, `fire_disaster`, `general`) — tier 1 role-routed per spec defaults, 10-minute timeout, `['in_app','sms']`; tier 2 `Administrator`, no timeout (final), `notify_external = true`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Sos\SosEscalationTier;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SosDefaultEscalationTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SosDefaultEscalationTierSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_two_tiers_for_each_alert_type(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(SosDefaultEscalationTierSeeder::class);

        foreach (['medical', 'security', 'fire_disaster', 'general'] as $type) {
            $this->assertSame(2, SosEscalationTier::where('alert_type', $type)->count());
            $this->assertDatabaseHas('sos_escalation_tiers', ['alert_type' => $type, 'order' => 2, 'notify_external' => true]);
        }

        $medicalTier1 = SosEscalationTier::where('alert_type', 'medical')->where('order', 1)->first();
        $this->assertSame('Nurse', $medicalTier1->role->name);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(RolesSeeder::class);
        $this->seed(SosDefaultEscalationTierSeeder::class);
        $this->seed(SosDefaultEscalationTierSeeder::class);

        $this->assertSame(2, SosEscalationTier::where('alert_type', 'general')->count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosDefaultEscalationTierSeederTest.php"`
Expected: FAIL — seeder doesn't exist.

- [ ] **Step 3: Write the seeder**

`database/seeders/SosDefaultEscalationTierSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\Sos\SosEscalationTier;
use Illuminate\Database\Seeder;

/**
 * Default SOS escalation tiers — fully editable afterward via the
 * Admin SOS Settings page. Ships a working default so the feature isn't
 * inert until an admin configures it.
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\SosDefaultEscalationTierSeeder --force
 */
class SosDefaultEscalationTierSeeder extends Seeder
{
    private const TIER_1_ROLES = [
        'medical'       => 'Nurse',
        'security'      => 'Security Guard',
        'fire_disaster' => 'DRRM Coordinator',
        'general'       => 'Security Guard',
    ];

    public function run(): void
    {
        foreach (self::TIER_1_ROLES as $alertType => $roleName) {
            $role = Role::where('name', $roleName)->first();

            SosEscalationTier::updateOrCreate(
                ['alert_type' => $alertType, 'order' => 1],
                [
                    'role_id'         => $role?->id,
                    'timeout_minutes' => 10,
                    'channels'        => ['in_app', 'sms'],
                    'notify_external' => false,
                ],
            );

            $admin = Role::where('name', 'Administrator')->first();

            SosEscalationTier::updateOrCreate(
                ['alert_type' => $alertType, 'order' => 2],
                [
                    'role_id'         => $admin?->id,
                    'timeout_minutes' => null,
                    'channels'        => ['in_app', 'sms'],
                    'notify_external' => true,
                ],
            );
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Sos/SosDefaultEscalationTierSeederTest.php"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/seeders/SosDefaultEscalationTierSeeder.php tests/Feature/Sos/SosDefaultEscalationTierSeederTest.php
git commit -m "feat(sos): seed default escalation tiers"
```

---

## Task 18: `SosFloatingButton.vue` + mount in `AdminLayout.vue`

**Files:**
- Create: `resources/js/Components/Sos/SosFloatingButton.vue`
- Modify: `resources/js/Layouts/AdminLayout.vue`
- Modify: `app/Http/Middleware/HandleInertiaRequests.php` (share `sos` config as an Inertia prop)

**Interfaces:**
- Produces: `<SosFloatingButton :trigger-route="'sos.trigger'" />` — self-contained component: floating action button, category picker, hold-to-confirm + cancellable countdown, and a discreet long-press silent trigger. Posts to the given Ziggy route name via axios.

- [ ] **Step 1: Share SOS config as an Inertia prop**

In `app/Http/Middleware/HandleInertiaRequests.php`, inside the `share()` method's returned array, add:

```php
            'sosConfig' => [
                'holdConfirmSeconds' => config('sos.hold_confirm_seconds'),
                'countdownSeconds'   => config('sos.countdown_seconds'),
            ],
```

- [ ] **Step 2: Write `SosFloatingButton.vue`**

```vue
<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { ExclamationTriangleIcon, XMarkIcon, HeartIcon, ShieldExclamationIcon, FireIcon, HandRaisedIcon } from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  triggerRoute: { type: String, required: true },
})

const page = usePage()
const holdSeconds = computed(() => page.props.sosConfig?.holdConfirmSeconds ?? 3)
const countdownTotal = computed(() => page.props.sosConfig?.countdownSeconds ?? 8)

const CATEGORIES = [
  { value: 'medical', label: 'Medical', icon: HeartIcon },
  { value: 'security', label: 'Security', icon: ShieldExclamationIcon },
  { value: 'fire_disaster', label: 'Fire / Disaster', icon: FireIcon },
  { value: 'general', label: 'General', icon: HandRaisedIcon },
]

const pickerOpen = ref(false)
const selectedCategory = ref(null)
const holding = ref(false)
const holdProgress = ref(0)
const countdownActive = ref(false)
const countdownSeconds = ref(0)
const blockedMessage = ref(null)
const sending = ref(false)

let holdTimer = null
let countdownTimer = null
let silentPressTimer = null

function openPicker() {
  blockedMessage.value = null
  pickerOpen.value = true
}

function closePicker() {
  pickerOpen.value = false
  selectedCategory.value = null
  cancelHold()
}

function selectCategory(value) {
  selectedCategory.value = value
}

function startHold() {
  if (!selectedCategory.value) return
  holding.value = true
  holdProgress.value = 0
  const stepMs = 50
  const totalSteps = (holdSeconds.value * 1000) / stepMs
  let step = 0
  holdTimer = setInterval(() => {
    step++
    holdProgress.value = Math.min(100, (step / totalSteps) * 100)
    if (step >= totalSteps) {
      clearInterval(holdTimer)
      holding.value = false
      startCountdown(selectedCategory.value, false)
    }
  }, stepMs)
}

function cancelHold() {
  if (holdTimer) clearInterval(holdTimer)
  holding.value = false
  holdProgress.value = 0
}

function startCountdown(alertType, isSilent) {
  countdownActive.value = true
  countdownSeconds.value = countdownTotal.value
  countdownTimer = setInterval(() => {
    countdownSeconds.value--
    if (countdownSeconds.value <= 0) {
      clearInterval(countdownTimer)
      countdownActive.value = false
      dispatch(alertType, isSilent)
    }
  }, 1000)
}

function cancelCountdown() {
  if (countdownTimer) clearInterval(countdownTimer)
  countdownActive.value = false
  closePicker()
}

async function dispatch(alertType, isSilent) {
  sending.value = true
  const coords = await captureLocation()

  try {
    const { data } = await axios.post(route(props.triggerRoute), {
      alert_type: alertType,
      is_silent: isSilent,
      lat: coords?.lat ?? null,
      lng: coords?.lng ?? null,
      accuracy: coords?.accuracy ?? null,
    })

    if (!isSilent) {
      pickerOpen.value = false
      selectedCategory.value = null
    }
  } catch (e) {
    if (e.response?.status === 422 && e.response?.data?.blocked) {
      blockedMessage.value = e.response.data
    }
  } finally {
    sending.value = false
  }
}

function captureLocation() {
  return new Promise((resolve) => {
    if (!('geolocation' in navigator)) return resolve(null)
    navigator.geolocation.getCurrentPosition(
      (pos) => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: pos.coords.accuracy }),
      () => resolve(null),
      { enableHighAccuracy: true, timeout: 5000 },
    )
  })
}

// ── Silent/duress: long-press the icon itself, no picker, no visible UI ──
function startSilentPress() {
  silentPressTimer = setTimeout(() => {
    dispatch('security', true)
  }, holdSeconds.value * 1000)
}

function cancelSilentPress() {
  if (silentPressTimer) clearTimeout(silentPressTimer)
}

onBeforeUnmount(() => {
  cancelHold()
  if (countdownTimer) clearInterval(countdownTimer)
  cancelSilentPress()
})
</script>

<template>
  <button
    type="button"
    class="fixed bottom-5 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-red-600 text-white shadow-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-400"
    aria-label="SOS Emergency"
    @click="openPicker"
    @pointerdown="startSilentPress"
    @pointerup="cancelSilentPress"
    @pointerleave="cancelSilentPress"
  >
    <ExclamationTriangleIcon class="h-6 w-6" />
  </button>

  <div v-if="pickerOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
    <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
      <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-slate-900">Emergency SOS</h2>
        <button type="button" class="text-slate-400 hover:text-slate-600" @click="closePicker">
          <XMarkIcon class="h-5 w-5" />
        </button>
      </div>

      <template v-if="blockedMessage">
        <p class="mb-3 text-sm text-red-700">{{ blockedMessage.message }}</p>
        <p class="text-sm text-slate-600">Emergency hotline: <strong>{{ blockedMessage.emergency_hotline }}</strong></p>
        <button type="button" class="mt-4 w-full rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700" @click="closePicker">Close</button>
      </template>

      <template v-else-if="!countdownActive">
        <p class="mb-3 text-sm text-slate-600">What kind of emergency is this?</p>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="cat in CATEGORIES" :key="cat.value" type="button"
            class="flex flex-col items-center gap-1 rounded-xl border px-3 py-3 text-sm font-medium transition-colors"
            :class="selectedCategory === cat.value ? 'border-red-600 bg-red-50 text-red-700' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
            @click="selectCategory(cat.value)"
          >
            <component :is="cat.icon" class="h-5 w-5" />
            {{ cat.label }}
          </button>
        </div>

        <button
          v-if="selectedCategory"
          type="button"
          class="relative mt-5 w-full overflow-hidden rounded-xl bg-red-600 px-4 py-3 text-sm font-semibold text-white"
          @pointerdown="startHold" @pointerup="cancelHold" @pointerleave="cancelHold"
        >
          <span class="absolute inset-y-0 left-0 bg-red-800" :style="{ width: holdProgress + '%' }"></span>
          <span class="relative">{{ holding ? 'Keep holding…' : 'Hold to confirm' }}</span>
        </button>
      </template>

      <template v-else>
        <p class="mb-2 text-sm text-slate-600">Sending SOS alert in</p>
        <p class="mb-4 text-4xl font-bold text-red-600">{{ countdownSeconds }}s</p>
        <button type="button" class="w-full rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700" @click="cancelCountdown">Cancel</button>
      </template>
    </div>
  </div>
</template>
```

- [ ] **Step 3: Mount in `AdminLayout.vue`**

In `resources/js/Layouts/AdminLayout.vue`, add the import near the other component imports:

```js
import SosFloatingButton from '@/Components/Sos/SosFloatingButton.vue';
```

And add `<SosFloatingButton trigger-route="sos.trigger" />` near the end of the template's root, alongside the other always-mounted overlays (e.g. next to `<AppLoadingOverlay />` / `<SessionExpiredOverlay />`).

- [ ] **Step 4: Manual verification**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"` (or start `npm run dev` and check in-browser) — confirm no build errors, and that the SOS button renders bottom-right on any authenticated Atlas page.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Sos/SosFloatingButton.vue resources/js/Layouts/AdminLayout.vue app/Http/Middleware/HandleInertiaRequests.php
git commit -m "feat(sos): add SOS floating trigger button to Atlas web"
```

---

## Task 19: Mount `SosFloatingButton.vue` in `StudentPortalLayout.vue`

**Files:**
- Modify: `resources/js/Layouts/StudentPortalLayout.vue`

- [ ] **Step 1: Mount the component**

In `resources/js/Layouts/StudentPortalLayout.vue`, add the import:

```js
import SosFloatingButton from '@/Components/Sos/SosFloatingButton.vue'
```

Add `<SosFloatingButton trigger-route="student-portal.sos.trigger" />` just before the closing `</div>` of the root layout element (after `<footer>`).

- [ ] **Step 2: Manual verification**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"` — confirm no build errors; log in to `/student-portal` in dev and confirm the SOS button renders.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Layouts/StudentPortalLayout.vue
git commit -m "feat(sos): add SOS floating trigger button to Student Portal"
```

---

## Task 20: `Sos/CommandCenter.vue` page

**Files:**
- Create: `resources/js/Pages/Sos/CommandCenter.vue`

**Interfaces:**
- Consumes: `props.alerts` (from `SosAlertController::index()`), Echo channel `sos-responders` events `.sos.alert.triggered` / `.sos.alert.updated`.

- [ ] **Step 1: Write the page**

```vue
<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import axios from 'axios'

const props = defineProps({ alerts: Array })

const alerts = ref([...props.alerts])
const selected = ref(null)
const falseAlarmReason = ref('')
const resolutionNotes = ref('')

const activeAlerts = computed(() => alerts.value.filter(a => !['resolved', 'false_alarm'].includes(a.status)))
const closedAlerts = computed(() => alerts.value.filter(a => ['resolved', 'false_alarm'].includes(a.status)))

function statusClass(status) {
  return {
    triggered: 'bg-red-100 text-red-700',
    acknowledged: 'bg-amber-100 text-amber-700',
    verified: 'bg-orange-100 text-orange-700',
    escalated: 'bg-red-200 text-red-800',
    resolved: 'bg-emerald-100 text-emerald-700',
    false_alarm: 'bg-slate-100 text-slate-500',
  }[status] ?? 'bg-slate-100 text-slate-600'
}

function upsertAlert(payload) {
  const idx = alerts.value.findIndex(a => a.id === payload.id)
  if (idx === -1) {
    alerts.value.unshift({ ...payload, events: [] })
  } else {
    alerts.value[idx] = { ...alerts.value[idx], ...payload }
  }
}

let channel = null
function subscribe() {
  if (!window.Echo) return
  channel = window.Echo.private('sos-responders')
    .listen('.sos.alert.triggered', (payload) => upsertAlert(payload))
    .listen('.sos.alert.updated', (payload) => upsertAlert(payload))
}

onMounted(subscribe)
onUnmounted(() => { if (window.Echo && channel) window.Echo.leave('sos-responders') })

async function act(alert, action, body = {}) {
  const { data } = await axios.post(route(`sos.${action}`, alert.id), body)
  upsertAlert(data)
  if (selected.value?.id === alert.id) selected.value = data
}
</script>

<template>
  <Head title="SOS Command Center" />
  <AdminLayout title="SOS Command Center">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <div class="lg:col-span-2">
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Active Alerts</h2>
        <div v-if="activeAlerts.length === 0" class="rounded-lg border border-dashed border-slate-200 p-6 text-center text-sm text-slate-400">
          No active SOS alerts.
        </div>
        <div v-for="alert in activeAlerts" :key="alert.id"
             class="mb-3 cursor-pointer rounded-xl border border-slate-200 bg-white p-4 hover:border-indigo-300"
             @click="selected = alert">
          <div class="flex items-center justify-between">
            <div>
              <span class="text-sm font-semibold text-slate-900">#{{ alert.id }} — {{ alert.alert_type.replace('_', ' ') }}</span>
              <span v-if="alert.is_silent" class="ml-2 rounded bg-slate-800 px-2 py-0.5 text-xs font-medium text-white">SILENT</span>
            </div>
            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusClass(alert.status)">{{ alert.status }}</span>
          </div>
          <p class="mt-1 text-xs text-slate-500">Triggered {{ new Date(alert.triggered_at).toLocaleString('en-PH') }}</p>
        </div>

        <h2 class="mb-3 mt-6 text-xs font-semibold uppercase tracking-wide text-slate-500">Closed</h2>
        <div v-for="alert in closedAlerts" :key="alert.id" class="mb-2 rounded-lg border border-slate-100 p-3 text-sm text-slate-500">
          #{{ alert.id }} — {{ alert.alert_type.replace('_', ' ') }} — <span :class="statusClass(alert.status)" class="rounded px-1.5 py-0.5 text-xs">{{ alert.status }}</span>
        </div>
      </div>

      <div v-if="selected" class="rounded-xl border border-slate-200 bg-white p-5">
        <h3 class="text-sm font-semibold text-slate-900">Alert #{{ selected.id }}</h3>
        <p class="mt-1 text-xs text-slate-500">{{ selected.alert_type.replace('_', ' ') }} · {{ selected.status }}</p>

        <div class="mt-4 flex flex-col gap-2">
          <button class="rounded-lg bg-amber-600 px-3 py-2 text-sm font-medium text-white" @click="act(selected, 'acknowledge')">Acknowledge</button>
          <button class="rounded-lg bg-orange-600 px-3 py-2 text-sm font-medium text-white" @click="act(selected, 'verify')">Verify (real emergency)</button>

          <div class="mt-2">
            <input v-model="falseAlarmReason" placeholder="Reason for false alarm" class="mb-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
            <button class="w-full rounded-lg bg-slate-600 px-3 py-2 text-sm font-medium text-white" :disabled="!falseAlarmReason"
                    @click="act(selected, 'false-alarm', { reason: falseAlarmReason })">Mark False Alarm</button>
          </div>

          <div class="mt-2">
            <textarea v-model="resolutionNotes" placeholder="Resolution notes" class="mb-1 w-full rounded-lg border border-slate-200 px-3 py-2 text-sm"></textarea>
            <button class="w-full rounded-lg bg-emerald-600 px-3 py-2 text-sm font-medium text-white"
                    @click="act(selected, 'resolve', { notes: resolutionNotes })">Resolve</button>
          </div>
        </div>

        <div class="mt-5">
          <h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Timeline</h4>
          <ul class="mt-2 space-y-1 text-xs text-slate-600">
            <li v-for="(e, i) in selected.events" :key="i">{{ e.type }} — {{ new Date(e.created_at).toLocaleString('en-PH') }}</li>
          </ul>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 2: Manual verification**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"` — confirm no build errors. Log in as a `sos.respond`-permitted user in dev, visit `/sos`, trigger a test alert from another tab, confirm it appears live.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Sos/CommandCenter.vue
git commit -m "feat(sos): add real-time SOS Command Center page"
```

---

## Task 21: `Sos/Settings.vue` admin page

**Files:**
- Create: `resources/js/Pages/Sos/Settings.vue`

**Interfaces:**
- Consumes: `props.tiers`, `props.externalContacts`, `props.roles`, `props.users` (each with a nested `employee_profile.mobile_number`, from `SosSettingsController::index()` per Task 16's `with('employeeProfile:...')` eager load).

- [ ] **Step 1: Write the page**

```vue
<script setup>
import { useForm } from '@inertiajs/vue3'
import { Head } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({
  tiers: Array,
  externalContacts: Array,
  roles: Array,
  users: Array,
})

const ALERT_TYPES = ['medical', 'security', 'fire_disaster', 'general']
const CHANNELS = ['in_app', 'sms', 'email']

const tierForm = useForm({
  alert_type: 'medical', order: 1, role_id: null, timeout_minutes: 10,
  channels: ['in_app'], notify_external: false, user_ids: [],
})

function submitTier() {
  tierForm.post(route('sos.settings.tiers.store'), { preserveScroll: true, onSuccess: () => tierForm.reset() })
}

function removeTier(tier) {
  tierForm.delete(route('sos.settings.tiers.destroy', tier.id), { preserveScroll: true })
}

const contactForm = useForm({
  name: '', org: '', phone: '', email: '', alert_types: [], channel: 'sms', active: true,
})

function submitContact() {
  contactForm.post(route('sos.settings.external-contacts.store'), { preserveScroll: true, onSuccess: () => contactForm.reset() })
}

function removeContact(contact) {
  contactForm.delete(route('sos.settings.external-contacts.destroy', contact.id), { preserveScroll: true })
}

const mobileForms = Object.fromEntries(
  props.users.map(u => [u.id, useForm({ mobile_number: u.employee_profile?.mobile_number ?? '' })])
)

function saveMobile(user) {
  mobileForms[user.id].post(route('sos.settings.responders.mobile', user.id), { preserveScroll: true })
}
</script>

<template>
  <Head title="SOS Settings" />
  <AdminLayout title="SOS Settings">
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
      <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Escalation Tiers</h2>

        <div v-for="tier in tiers" :key="tier.id" class="mb-2 flex items-center justify-between rounded-lg border border-slate-200 p-3 text-sm">
          <div>
            <strong>{{ tier.alert_type.replace('_', ' ') }}</strong> — tier {{ tier.order }} —
            {{ tier.role?.name ?? 'no role' }} — {{ tier.timeout_minutes ? tier.timeout_minutes + 'min' : 'final' }}
          </div>
          <button class="text-red-600 hover:underline" @click="removeTier(tier)">Remove</button>
        </div>

        <form class="mt-4 space-y-2 rounded-xl border border-slate-200 p-4" @submit.prevent="submitTier">
          <select v-model="tierForm.alert_type" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option v-for="t in ALERT_TYPES" :key="t" :value="t">{{ t.replace('_', ' ') }}</option>
          </select>
          <input v-model.number="tierForm.order" type="number" min="1" placeholder="Order" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <select v-model="tierForm.role_id" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option :value="null">No role (explicit users only)</option>
            <option v-for="r in roles" :key="r.id" :value="r.id">{{ r.name }}</option>
          </select>
          <input v-model.number="tierForm.timeout_minutes" type="number" min="1" placeholder="Timeout minutes (blank = final tier)" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <div class="flex gap-3 text-sm">
            <label v-for="c in CHANNELS" :key="c" class="flex items-center gap-1">
              <input type="checkbox" :value="c" v-model="tierForm.channels" /> {{ c }}
            </label>
          </div>
          <label class="flex items-center gap-1 text-sm"><input type="checkbox" v-model="tierForm.notify_external" /> Notify external contacts</label>
          <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Add Tier</button>
        </form>
      </section>

      <section>
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">External Contacts</h2>

        <div v-for="contact in externalContacts" :key="contact.id" class="mb-2 flex items-center justify-between rounded-lg border border-slate-200 p-3 text-sm">
          <div>
            <strong>{{ contact.name }}</strong> ({{ contact.org }}) — {{ contact.phone }} — {{ contact.alert_types.join(', ') }}
          </div>
          <button class="text-red-600 hover:underline" @click="removeContact(contact)">Remove</button>
        </div>

        <form class="mt-4 space-y-2 rounded-xl border border-slate-200 p-4" @submit.prevent="submitContact">
          <input v-model="contactForm.name" placeholder="Name" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <input v-model="contactForm.org" placeholder="Organization" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <input v-model="contactForm.phone" placeholder="Phone" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <input v-model="contactForm.email" placeholder="Email" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <div class="flex flex-wrap gap-3 text-sm">
            <label v-for="t in ALERT_TYPES" :key="t" class="flex items-center gap-1">
              <input type="checkbox" :value="t" v-model="contactForm.alert_types" /> {{ t.replace('_', ' ') }}
            </label>
          </div>
          <select v-model="contactForm.channel" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            <option value="sms">SMS</option>
            <option value="email">Email</option>
            <option value="both">Both</option>
          </select>
          <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Add Contact</button>
        </form>
      </section>

      <section class="lg:col-span-2">
        <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">Responder Mobile Numbers</h2>
        <p class="mb-3 text-xs text-slate-500">Used for the SMS channel on escalation tiers — a responder with no number set here won't receive SMS, only in-app/email.</p>
        <div v-for="user in users" :key="user.id" class="mb-2 flex items-center gap-2">
          <span class="w-48 truncate text-sm text-slate-700">{{ user.name }}</span>
          <input v-model="mobileForms[user.id].mobile_number" placeholder="09XXXXXXXXX" class="flex-1 rounded-lg border border-slate-200 px-3 py-2 text-sm" />
          <button class="rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200" @click="saveMobile(user)">Save</button>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 2: Manual verification**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"` — confirm no build errors. Log in as Administrator in dev, visit `/sos/settings`, create a tier and an external contact, confirm they persist and appear in the list; set a responder's mobile number and confirm it persists on reload.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Sos/Settings.vue
git commit -m "feat(sos): add admin SOS settings page"
```

---

## Task 22: Full verification pass

**Files:** none (verification only)

- [ ] **Step 1: Run the full PHP test suite**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test"`
Expected: all tests pass, zero regressions in unrelated suites.

- [ ] **Step 2: PHP lint all touched files**

Run the project's `lint` skill (or `docker compose exec php bash -c "cd /var/www/html/bugsaymis && find app/Models/Sos app/Services/Sos app/Events/Sos app/Jobs/Sos app/Http/Controllers/Sos app/Http/Controllers/StudentPortal/SosAlertController.php app/Console/Commands/ProcessSosEscalations.php app/Mail/SosAlertMail.php database/seeders/SosPermissionSeeder.php database/seeders/SosDefaultEscalationTierSeeder.php -name '*.php' -exec php -l {} \;"`).
Expected: `No syntax errors detected` for every file.

- [ ] **Step 3: Build frontend assets**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors.

- [ ] **Step 4: Manual smoke test in dev browser**

Using Chrome dev tools / the app in a browser against the dev Docker stack:
1. Log in as a plain staff user, trigger a test "general" SOS from the floating button (category → hold 3s → cancel during the 8s countdown) — confirm cancel works and no alert row is created (`SELECT * FROM sos_alerts` empty for that trigger).
2. Repeat without cancelling — confirm the alert appears in `/sos` (as a `sos.respond`-permitted user in another session) within seconds via the live Echo update, no page refresh needed.
3. As the responder, click Acknowledge → Verify → Resolve, confirm the status badge and timeline update live in both tabs.
4. Trigger a silent alert (long-press the icon directly) — confirm no visible UI change on the triggering side, and that the resulting alert in the Command Center shows the `SILENT` badge.
5. Visit `/sos/settings` as Administrator, edit a tier's channels, confirm the change persists on reload.

- [ ] **Step 5: Commit (only if any fixes were needed in the prior steps)**

```bash
git add -A -- app resources database tests config routes
git commit -m "fix(sos): address issues found during full verification pass"
```
