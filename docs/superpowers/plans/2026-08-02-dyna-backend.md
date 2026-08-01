# Dyna Backend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the Laravel/Bedrock backend that lets an authenticated, permission-scoped Atlas
user ask Dyna an analytics question and get back an answer grounded in real, live tool calls
against existing Atlas data.

**Architecture:** A Sanctum-authenticated `POST /api/dyna/chat` endpoint feeds the user's
message plus conversation history to Amazon Bedrock's Converse API (Claude Sonnet 5) along
with a schema of "Dyna Tools." When the model requests a tool, `DynaOrchestratorService`
executes it as the requesting user — so every tool inherits that user's existing permission
scoping for free — feeds the result back to the model, and repeats until the model returns a
final answer. Every exchange is persisted for history and audit.

**Tech Stack:** Laravel 12 / PHP 8.4, `aws/aws-sdk-php` (already vendored, `BedrockRuntimeClient`),
Laravel Sanctum (personal access tokens, mirrors the existing mobile API pattern), MySQL.

## Global Constraints

- Controllers stay thin; logic lives in `app/Services/Atlas/Dyna/`.
- Permissions follow `module.submodule.action`; this feature's permission is
  `atlas.dyna.access`, seeded via a migration (the codebase's current idiom — see
  `2026_08_01_190100_seed_hr_document_request_catalog_and_permissions.php`), granted to
  `Administrator`, `OCD`, `DivisionChief` — the same role set as
  `ExecutiveDashboardPermissionSeeder`.
- Migrations: `database/migrations/YYYY_MM_DD_HHMMSS_description.php`, always additive, always
  a working `down()`. All new columns/tables here are additive-only — no destructive changes.
- AWS access: **no new IAM user, no plaintext API keys.** Mirror
  `PdfTextExtractorService::makeTextractClient()` — pass explicit credentials only when
  `config('filesystems.disks.s3.key')` is set (local dev), otherwise let the AWS SDK fall back
  to the ECS task role.
- Every Bedrock `Converse` call MUST set `inferenceConfig.maxTokens` explicitly — never leave
  it unset (silent over-reservation of quota, per Bedrock best practice).
- Every Dyna Tool executes as the requesting `User` and MUST apply the same scoping the web
  app already uses (Division Chiefs see their own division only, unless `Administrator`/`OCD`)
  — no tool queries with elevated privilege beyond what that user already has in the UI.
- Use `User::employees()` (existing scope) wherever a tool needs to enumerate staff — it
  already excludes Parent/Student mobile accounts.
- Never use `new DateTime()` against Eloquent date-cast attributes — use
  `Carbon::parse($value)->format('Y-m-d')`.
- Tests: PHPUnit Feature tests, `RefreshDatabase`, mirror the `userWithPermissions()` helper
  pattern from `tests/Feature/Atlas/WorkspaceSyncTest.php`.

---

## File structure

```
database/migrations/
  2026_08_03_090000_create_dyna_conversations_and_messages_tables.php
  2026_08_03_090100_seed_dyna_permission.php

config/
  services.php                                    (add 'bedrock' block)

app/Models/Atlas/
  DynaConversation.php
  DynaMessage.php

app/Services/Atlas/Dyna/
  Tools/DynaTool.php                               (interface)
  Tools/GetHeadcountTool.php
  Tools/GetLeaveTrendsTool.php
  DynaToolRegistry.php
  DynaBedrockClientFactory.php
  DynaOrchestratorService.php

app/Http/Controllers/Api/
  DynaAuthController.php
  DynaController.php

routes/api.php                                     (add /api/dyna/* group)

tests/Feature/Atlas/Dyna/
  DynaToolRegistryTest.php
  GetHeadcountToolTest.php
  GetLeaveTrendsToolTest.php
  DynaAuthTest.php
  DynaChatEndpointTest.php
  DynaConversationShowTest.php
```

**Not in this plan (explicit fast-follow, same pattern as `GetHeadcountTool`/`GetLeaveTrendsTool`):**
payroll summary, IPCR completion rate, and enrollment stats tools. Task 4 establishes the
`DynaTool` contract and registry so each of those is a same-shaped addition later — they are
deliberately excluded here to keep this plan's tasks independently shippable and testable.

**Streaming is deliberately out of scope for this plan.** `POST /api/dyna/chat` (Task 10) is a
plain synchronous JSON endpoint — it blocks until `DynaOrchestratorService::reply()` has the
full answer, then returns `{conversation_id, answer}` once. The design spec's `ConverseStream`
token-by-token UX is a documented fast-follow: the AWS SDK for PHP's event-stream support for
`ConverseStream` is not well-documented enough to spec accurately here, and a synchronous
`converse()` call is simpler to implement and test correctly first. Dyna.app (the macOS plan)
is built against this synchronous contract — its chat UI shows a "thinking" state while
awaiting the response, not incremental token rendering, in v1.

---

### Task 1: Dyna schema — conversations & messages

**Files:**
- Create: `database/migrations/2026_08_03_090000_create_dyna_conversations_and_messages_tables.php`
- Test: `tests/Feature/Atlas/Dyna/DynaSchemaTest.php`

**Interfaces:**
- Produces: `dyna_conversations` table (`id`, `user_id`, `title` nullable, timestamps) and
  `dyna_messages` table (`id`, `dyna_conversation_id`, `role` enum-like string `user`/`assistant`,
  `content` text, `tool_calls` nullable json, `created_at`). Later tasks (`DynaConversation`,
  `DynaMessage` models) rely on exactly these column names.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DynaSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_dyna_conversations_and_messages_tables_exist_with_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('dyna_conversations'));
        $this->assertTrue(Schema::hasColumns('dyna_conversations', ['id', 'user_id', 'title', 'created_at', 'updated_at']));

        $this->assertTrue(Schema::hasTable('dyna_messages'));
        $this->assertTrue(Schema::hasColumns('dyna_messages', [
            'id', 'dyna_conversation_id', 'role', 'content', 'tool_calls', 'created_at',
        ]));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaSchemaTest"`
Expected: FAIL — tables don't exist.

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dyna_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('dyna_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dyna_conversation_id')->constrained('dyna_conversations')->cascadeOnDelete();
            $table->string('role', 20); // 'user' | 'assistant'
            $table->longText('content');
            $table->json('tool_calls')->nullable(); // [{name, input, result}] for audit
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dyna_messages');
        Schema::dropIfExists('dyna_conversations');
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_03_090000_create_dyna_conversations_and_messages_tables.php"`

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaSchemaTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_03_090000_create_dyna_conversations_and_messages_tables.php tests/Feature/Atlas/Dyna/DynaSchemaTest.php
git commit -m "feat(dyna): add dyna_conversations and dyna_messages tables"
```

---

### Task 2: `atlas.dyna.access` permission

**Files:**
- Create: `database/migrations/2026_08_03_090100_seed_dyna_permission.php`
- Test: `tests/Feature/Atlas/Dyna/DynaPermissionSeedTest.php`

**Interfaces:**
- Consumes: `roles`/`permissions`/`permission_role` tables (existing).
- Produces: permission row `atlas.dyna.access`, granted to `Administrator`, `OCD`,
  `DivisionChief`. Later tasks' route middleware (`permission:atlas.dyna.access`) rely on this
  name exactly.

- [ ] **Step 1: Write the failing test**

**Note on test strategy (found during execution):** this repo's `TestCase` does not
auto-seed base roles (confirmed by reading `tests/TestCase.php` — it only calls
`withoutVite()`), and `RefreshDatabase` runs every migration — including this one — against a
completely empty `roles` table before the test body runs. So the migration's grants would
match nothing if we only relied on the automatic migrate step. The test below seeds the three
target roles itself, then re-runs this migration's `up()` directly (safe — it's
`updateOrInsert`/`insertOrIgnore`, fully idempotent) to exercise the grant logic against a
roles-already-exist state, which is how it actually behaves in production (roles pre-exist
before any new migration runs there).

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DynaPermissionSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_dyna_permission_is_granted_to_administrator_ocd_and_division_chief(): void
    {
        foreach (['Administrator', 'OCD', 'DivisionChief'] as $roleName) {
            Role::firstOrCreate(['name' => $roleName]);
        }

        $migration = require database_path('migrations/2026_08_03_090100_seed_dyna_permission.php');
        $migration->up();

        $this->assertDatabaseHas('permissions', ['name' => 'atlas.dyna.access']);

        foreach (['Administrator', 'OCD', 'DivisionChief'] as $roleName) {
            $roleId = DB::table('roles')->where('name', $roleName)->value('id');
            $permissionId = DB::table('permissions')->where('name', 'atlas.dyna.access')->value('id');

            $this->assertDatabaseHas('permission_role', [
                'role_id' => $roleId,
                'permission_id' => $permissionId,
            ]);
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaPermissionSeedTest"`
Expected: FAIL — the migration file doesn't exist yet, so `require` fatals (a valid red state).

- [ ] **Step 3: Write the migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['name' => 'atlas.dyna.access'],
            [
                'module' => 'Atlas',
                'description' => 'Use the Dyna AI assistant (analytics & insights chat)',
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        $roleIds = DB::table('roles')->whereIn('name', ['Administrator', 'OCD', 'DivisionChief'])->pluck('id');
        $permissionId = DB::table('permissions')->where('name', 'atlas.dyna.access')->value('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('name', 'atlas.dyna.access')->value('id');
        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_03_090100_seed_dyna_permission.php"`

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaPermissionSeedTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_03_090100_seed_dyna_permission.php tests/Feature/Atlas/Dyna/DynaPermissionSeedTest.php
git commit -m "feat(dyna): seed atlas.dyna.access permission"
```

---

### Task 3: `DynaConversation` and `DynaMessage` models

**Files:**
- Create: `app/Models/Atlas/DynaConversation.php`
- Create: `app/Models/Atlas/DynaMessage.php`
- Test: `tests/Feature/Atlas/Dyna/DynaConversationModelTest.php`

**Interfaces:**
- Consumes: `dyna_conversations`/`dyna_messages` tables (Task 1).
- Produces: `DynaConversation::messages()` (HasMany, ordered by `created_at`),
  `DynaConversation::user()` (BelongsTo), `DynaMessage::conversation()` (BelongsTo),
  `DynaMessage->tool_calls` cast to array. Task 5+ (orchestrator) creates records through
  these models.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Atlas\DynaConversation;
use App\Models\Atlas\DynaMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynaConversationModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_has_ordered_messages_and_belongs_to_a_user(): void
    {
        $user = User::factory()->create();
        $conversation = DynaConversation::create(['user_id' => $user->id, 'title' => 'Leave trends']);

        $second = DynaMessage::create([
            'dyna_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => 'second',
            'created_at' => now()->addMinute(),
        ]);
        $first = DynaMessage::create([
            'dyna_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => 'first',
            'tool_calls' => [['name' => 'get_headcount', 'input' => [], 'result' => ['total_headcount' => 42]]],
            'created_at' => now(),
        ]);

        $this->assertTrue($conversation->user->is($user));
        $this->assertEquals(['first', 'second'], $conversation->fresh()->messages->pluck('content')->all());
        $this->assertIsArray($first->fresh()->tool_calls);
        $this->assertEquals('get_headcount', $first->fresh()->tool_calls[0]['name']);
        $this->assertTrue($second->conversation->is($conversation));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaConversationModelTest"`
Expected: FAIL — class `App\Models\Atlas\DynaConversation` not found.

- [ ] **Step 3: Write the models**

```php
<?php

namespace App\Models\Atlas;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynaConversation extends Model
{
    protected $fillable = ['user_id', 'title'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DynaMessage::class)->orderBy('created_at');
    }
}
```

```php
<?php

namespace App\Models\Atlas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynaMessage extends Model
{
    public $timestamps = false;

    protected $fillable = ['dyna_conversation_id', 'role', 'content', 'tool_calls', 'created_at'];

    protected $casts = [
        'tool_calls' => 'array',
        'created_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(DynaConversation::class, 'dyna_conversation_id');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaConversationModelTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Atlas/DynaConversation.php app/Models/Atlas/DynaMessage.php tests/Feature/Atlas/Dyna/DynaConversationModelTest.php
git commit -m "feat(dyna): add DynaConversation and DynaMessage models"
```

---

### Task 4: `DynaTool` contract + `DynaToolRegistry`

**Files:**
- Create: `app/Services/Atlas/Dyna/Tools/DynaTool.php`
- Create: `app/Services/Atlas/Dyna/DynaToolRegistry.php`
- Test: `tests/Feature/Atlas/Dyna/DynaToolRegistryTest.php`

**Interfaces:**
- Produces: `DynaTool` interface (`name(): string`, `description(): string`,
  `inputSchema(): array`, `execute(User $user, array $input): array`);
  `DynaToolRegistry::toBedrockToolConfig(): array` (returns Bedrock `ToolConfiguration` shape:
  `['tools' => [['toolSpec' => ['name' => ..., 'description' => ..., 'inputSchema' => ['json' => ...]]]]]`);
  `DynaToolRegistry::execute(string $name, array $input, User $user): array` (throws
  `InvalidArgumentException` for an unknown tool name). Task 5 (orchestrator) and Task 6/7
  (concrete tools) depend on this contract.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\User;
use App\Services\Atlas\Dyna\DynaToolRegistry;
use App\Services\Atlas\Dyna\Tools\DynaTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynaToolRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_builds_bedrock_tool_config_from_registered_tools(): void
    {
        $tool = new class implements DynaTool {
            public function name(): string { return 'ping'; }
            public function description(): string { return 'Returns pong.'; }
            public function inputSchema(): array { return ['type' => 'object', 'properties' => []]; }
            public function execute(User $user, array $input): array { return ['pong' => true]; }
        };

        $registry = new DynaToolRegistry([$tool]);

        $this->assertEquals([
            'tools' => [[
                'toolSpec' => [
                    'name' => 'ping',
                    'description' => 'Returns pong.',
                    'inputSchema' => ['json' => ['type' => 'object', 'properties' => []]],
                ],
            ]],
        ], $registry->toBedrockToolConfig());

        $this->assertEquals(['pong' => true], $registry->execute('ping', [], User::factory()->make()));
    }

    public function test_registry_rejects_an_unknown_tool_name(): void
    {
        $registry = new DynaToolRegistry([]);

        $this->expectException(\InvalidArgumentException::class);
        $registry->execute('does_not_exist', [], User::factory()->make());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaToolRegistryTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the contract and registry**

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\User;

interface DynaTool
{
    /** Snake_case tool name, sent to Bedrock verbatim as the tool identifier. */
    public function name(): string;

    /** Plain-English description the model uses to decide when to call this tool. */
    public function description(): string;

    /** JSON Schema (draft-07-ish subset Bedrock accepts) describing the tool's input. */
    public function inputSchema(): array;

    /**
     * Runs the tool AS the requesting user — implementations MUST scope any query
     * to what $user is already permitted to see (mirror the equivalent web-UI query).
     *
     * @return array JSON-serializable result handed back to the model as a tool_result.
     */
    public function execute(User $user, array $input): array;
}
```

```php
<?php

namespace App\Services\Atlas\Dyna;

use App\Models\User;
use App\Services\Atlas\Dyna\Tools\DynaTool;

class DynaToolRegistry
{
    /** @var array<string, DynaTool> */
    private array $tools;

    /** @param DynaTool[] $tools */
    public function __construct(array $tools)
    {
        $this->tools = collect($tools)->keyBy(fn (DynaTool $t) => $t->name())->all();
    }

    public function toBedrockToolConfig(): array
    {
        return [
            'tools' => collect($this->tools)->values()->map(fn (DynaTool $t) => [
                'toolSpec' => [
                    'name' => $t->name(),
                    'description' => $t->description(),
                    'inputSchema' => ['json' => $t->inputSchema()],
                ],
            ])->all(),
        ];
    }

    public function execute(string $name, array $input, User $user): array
    {
        if (! isset($this->tools[$name])) {
            throw new \InvalidArgumentException("Unknown Dyna tool: {$name}");
        }

        return $this->tools[$name]->execute($user, $input);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaToolRegistryTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/Tools/DynaTool.php app/Services/Atlas/Dyna/DynaToolRegistry.php tests/Feature/Atlas/Dyna/DynaToolRegistryTest.php
git commit -m "feat(dyna): add DynaTool contract and DynaToolRegistry"
```

---

### Task 5: `GetHeadcountTool`

**Files:**
- Create: `app/Services/Atlas/Dyna/Tools/GetHeadcountTool.php`
- Test: `tests/Feature/Atlas/Dyna/GetHeadcountToolTest.php`

**Interfaces:**
- Consumes: `App\Models\User::employees()` scope (existing), `DynaTool` interface (Task 4).
- Produces: `GetHeadcountTool` implementing `DynaTool`, `name() === 'get_headcount'`.

**Schema note (found during execution):** `divisions.division_name` is the real column —
not `name` — per `database/migrations/2025_09_26_015744_create_divisions_table.php`; it's
`string`, required, no default. `divisions.status` is an enum of `active`/`not_active` (not
`inactive`). No `Division` factory existed yet — created
`database/factories/DivisionFactory.php` (`division_name` + `status: 'active'`) as part of
this task.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Division;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetHeadcountTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetHeadcountToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_sees_campus_wide_headcount(): void
    {
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();
        User::factory()->count(2)->create(['division_id' => $divisionA->id, 'status' => 'active']);
        User::factory()->count(3)->create(['division_id' => $divisionB->id, 'status' => 'active']);
        $administrator = $this->userWithRole('Administrator');

        $result = (new GetHeadcountTool())->execute($administrator, []);

        $this->assertEquals(6, $result['total_headcount']); // 2 + 3 + the administrator user itself
    }

    public function test_division_chief_only_sees_their_own_division(): void
    {
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();
        User::factory()->count(2)->create(['division_id' => $divisionA->id, 'status' => 'active']);
        User::factory()->count(3)->create(['division_id' => $divisionB->id, 'status' => 'active']);
        $chief = $this->userWithRole('DivisionChief', ['division_id' => $divisionA->id]);

        $result = (new GetHeadcountTool())->execute($chief, []);

        $this->assertEquals(3, $result['total_headcount']); // 2 in division A + the chief themself
    }

    private function userWithRole(string $roleName, array $attributes = []): User
    {
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user = User::factory()->create(array_merge(['status' => 'active'], $attributes));
        $user->roles()->attach($role);

        return $user;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=GetHeadcountToolTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the tool**

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\User;

class GetHeadcountTool implements DynaTool
{
    public function name(): string
    {
        return 'get_headcount';
    }

    public function description(): string
    {
        return 'Returns active employee headcount, optionally broken down by division. '
             . 'Use for questions about staff/faculty counts or division size.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'group_by_division' => [
                    'type' => 'boolean',
                    'description' => 'If true, break the headcount down by division name.',
                ],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        $query = User::employees()->where('status', '<>', 'inactive');

        if (! $user->hasAnyRole(['Administrator', 'OCD'])) {
            $query->where('division_id', $user->division_id);
        }

        if (! empty($input['group_by_division'])) {
            return $query->with('division')
                ->get()
                ->groupBy(fn (User $u) => $u->division?->division_name ?? 'Unassigned')
                ->map->count()
                ->toArray();
        }

        return ['total_headcount' => $query->count()];
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=GetHeadcountToolTest"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/Tools/GetHeadcountTool.php tests/Feature/Atlas/Dyna/GetHeadcountToolTest.php
git commit -m "feat(dyna): add GetHeadcountTool"
```

---

### Task 6: `GetLeaveTrendsTool`

**Files:**
- Create: `app/Services/Atlas/Dyna/Tools/GetLeaveTrendsTool.php`
- Modify: `app/Models/HR/LeaveApplication.php` (add `HasFactory` — see note below)
- Create: `database/factories/HR/LeaveApplicationFactory.php`
- Test: `tests/Feature/Atlas/Dyna/GetLeaveTrendsToolTest.php`

**Interfaces:**
- Consumes: `App\Models\HR\LeaveApplication` (existing, `user()` BelongsTo confirmed),
  `DynaTool` interface (Task 4).
- Produces: `GetLeaveTrendsTool` implementing `DynaTool`, `name() === 'get_leave_trends'`.

**Notes from execution:**
- `LeaveApplication` did not have the `HasFactory` trait, so `LeaveApplication::factory()`
  wasn't callable — added `use HasFactory;` (non-breaking addition) alongside the model's
  existing `SoftDeletes, HasApprovalSnapshots` traits, and created
  `database/factories/HR/LeaveApplicationFactory.php` (required columns per
  `database/migrations/2026_03_22_000007_create_leave_applications_table.php`: `user_id`,
  `leave_type_id` — via `LeaveType::factory()`, which already existed — `date_from`,
  `date_to`, `days_applied`; `status` defaults to `'pending'` in the schema but the factory
  sets it explicitly).
- `whereBetween('created_at', [$input['from_date'], $input['to_date']])` on raw date strings
  has a real boundary bug: the upper bound gets treated as midnight, silently excluding same-day
  records created later that day. Fixed with
  `Carbon::parse($input['to_date'])->endOfDay()` (and `startOfDay()` on the lower bound) —
  see the implementation below.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\Tools\GetLeaveTrendsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GetLeaveTrendsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_division_chief_only_sees_leave_applications_from_their_division(): void
    {
        $divisionA = Division::factory()->create();
        $divisionB = Division::factory()->create();

        $inDivisionA = User::factory()->create(['division_id' => $divisionA->id]);
        $inDivisionB = User::factory()->create(['division_id' => $divisionB->id]);

        LeaveApplication::factory()->create(['user_id' => $inDivisionA->id, 'status' => 'approved', 'created_at' => '2026-07-15']);
        LeaveApplication::factory()->create(['user_id' => $inDivisionA->id, 'status' => 'pending', 'created_at' => '2026-07-20']);
        LeaveApplication::factory()->create(['user_id' => $inDivisionB->id, 'status' => 'approved', 'created_at' => '2026-07-18']);

        $chief = User::factory()->create(['division_id' => $divisionA->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));

        $result = (new GetLeaveTrendsTool())->execute($chief, [
            'from_date' => '2026-07-01',
            'to_date' => '2026-07-31',
        ]);

        $this->assertEquals(['approved' => 1, 'pending' => 1], $result);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=GetLeaveTrendsToolTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the tool**

```php
<?php

namespace App\Services\Atlas\Dyna\Tools;

use App\Models\HR\LeaveApplication;
use App\Models\User;
use Illuminate\Support\Carbon;

class GetLeaveTrendsTool implements DynaTool
{
    public function name(): string
    {
        return 'get_leave_trends';
    }

    public function description(): string
    {
        return 'Returns leave application counts grouped by status (pending, forwarded, '
             . 'approved, rejected) for a given date range. Use for questions about leave '
             . 'volume, pending approvals, or leave trends over time.';
    }

    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['from_date', 'to_date'],
            'properties' => [
                'from_date' => ['type' => 'string', 'description' => 'Start date, format YYYY-MM-DD.'],
                'to_date' => ['type' => 'string', 'description' => 'End date, format YYYY-MM-DD.'],
            ],
        ];
    }

    public function execute(User $user, array $input): array
    {
        $query = LeaveApplication::query()
            ->whereBetween('created_at', [
                Carbon::parse($input['from_date'])->startOfDay(),
                Carbon::parse($input['to_date'])->endOfDay(),
            ]);

        if (! $user->hasAnyRole(['Administrator', 'OCD'])) {
            $query->whereHas('user', fn ($q) => $q->where('division_id', $user->division_id));
        }

        return $query->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=GetLeaveTrendsToolTest"`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/Tools/GetLeaveTrendsTool.php tests/Feature/Atlas/Dyna/GetLeaveTrendsToolTest.php
git commit -m "feat(dyna): add GetLeaveTrendsTool"
```

---

### Task 7: Bedrock client factory + config

**Files:**
- Modify: `config/services.php`
- Create: `app/Services/Atlas/Dyna/DynaBedrockClientFactory.php`
- Test: `tests/Feature/Atlas/Dyna/DynaBedrockClientFactoryTest.php`

**Interfaces:**
- Produces: `config('services.bedrock.region')`, `config('services.bedrock.inference_profile_id')`;
  `DynaBedrockClientFactory::make(): \Aws\BedrockRuntime\BedrockRuntimeClient`. Task 8
  (orchestrator) consumes this factory.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Services\Atlas\Dyna\DynaBedrockClientFactory;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Tests\TestCase;

class DynaBedrockClientFactoryTest extends TestCase
{
    public function test_make_returns_a_configured_bedrock_runtime_client(): void
    {
        config(['services.bedrock.region' => 'us-east-1']);

        $client = (new DynaBedrockClientFactory())->make();

        $this->assertInstanceOf(BedrockRuntimeClient::class, $client);
        $this->assertEquals('us-east-1', $client->getRegion());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaBedrockClientFactoryTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Add config and write the factory**

Add to `config/services.php`:

```php
'bedrock' => [
    'region' => env('BEDROCK_REGION', 'us-east-1'),
    // Cross-region inference profile ID for Claude Sonnet 5, e.g. 'us.anthropic.claude-sonnet-5-...-v1:0'
    // Confirm the current ID with: aws bedrock list-inference-profiles --region us-east-1
    'inference_profile_id' => env('BEDROCK_INFERENCE_PROFILE_ID'),
],
```

```php
<?php

namespace App\Services\Atlas\Dyna;

use Aws\BedrockRuntime\BedrockRuntimeClient;

class DynaBedrockClientFactory
{
    public function make(): BedrockRuntimeClient
    {
        $config = [
            'region' => config('services.bedrock.region', 'us-east-1'),
            'version' => 'latest',
        ];

        // Explicit credentials only when locally configured; otherwise fall back
        // to the ECS task role (mirrors PdfTextExtractorService::makeTextractClient()).
        $key = config('filesystems.disks.s3.key');
        if ($key) {
            $config['credentials'] = [
                'key' => $key,
                'secret' => config('filesystems.disks.s3.secret'),
            ];
        }

        return new BedrockRuntimeClient($config);
    }
}
```

Add `BEDROCK_REGION` and `BEDROCK_INFERENCE_PROFILE_ID` to `.env.example`.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaBedrockClientFactoryTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add config/services.php app/Services/Atlas/Dyna/DynaBedrockClientFactory.php tests/Feature/Atlas/Dyna/DynaBedrockClientFactoryTest.php .env.example
git commit -m "feat(dyna): add Bedrock client factory and config"
```

---

### Task 8: `DynaOrchestratorService` — the tool-use loop

**Files:**
- Create: `app/Services/Atlas/Dyna/DynaOrchestratorService.php`
- Test: `tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php`

**Interfaces:**
- Consumes: `DynaToolRegistry` (Task 4), `DynaBedrockClientFactory` (Task 7),
  `DynaConversation`/`DynaMessage` (Task 3), `BedrockRuntimeClient::converse()`.
- Produces: `DynaOrchestratorService::reply(User $user, DynaConversation $conversation, string $userMessage): string`
  — persists the user message and the final assistant message (with `tool_calls` populated),
  returns the assistant's final text. Task 9 (controller) consumes this method.

**Note on the Bedrock client in the test:** mock `BedrockRuntimeClient` with Mockery (the
codebase already uses `Mockery` in `WorkspaceSyncTest`) rather than hitting real Bedrock in
tests — `converse()` is a single method call, easy to fake a canned response for.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Atlas\DynaConversation;
use App\Models\User;
use App\Services\Atlas\Dyna\DynaBedrockClientFactory;
use App\Services\Atlas\Dyna\DynaOrchestratorService;
use App\Services\Atlas\Dyna\DynaToolRegistry;
use App\Services\Atlas\Dyna\Tools\DynaTool;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Aws\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DynaOrchestratorServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_executes_a_requested_tool_then_returns_the_models_final_answer(): void
    {
        $user = User::factory()->create();
        $conversation = DynaConversation::create(['user_id' => $user->id]);

        $tool = new class implements DynaTool {
            public function name(): string { return 'get_headcount'; }
            public function description(): string { return 'desc'; }
            public function inputSchema(): array { return ['type' => 'object', 'properties' => []]; }
            public function execute(User $user, array $input): array { return ['total_headcount' => 42]; }
        };
        $registry = new DynaToolRegistry([$tool]);

        $bedrock = Mockery::mock(BedrockRuntimeClient::class);
        $bedrock->shouldReceive('converse')
            ->once()
            ->andReturn(new Result([
                'output' => ['message' => ['role' => 'assistant', 'content' => [
                    ['toolUse' => ['toolUseId' => 'tu_1', 'name' => 'get_headcount', 'input' => []]],
                ]]],
                'stopReason' => 'tool_use',
            ]));
        $bedrock->shouldReceive('converse')
            ->once()
            ->andReturn(new Result([
                'output' => ['message' => ['role' => 'assistant', 'content' => [
                    ['text' => 'There are 42 active employees.'],
                ]]],
                'stopReason' => 'end_turn',
            ]));

        $clientFactory = Mockery::mock(DynaBedrockClientFactory::class);
        $clientFactory->shouldReceive('make')->andReturn($bedrock);

        $orchestrator = new DynaOrchestratorService($registry, $clientFactory);

        $answer = $orchestrator->reply($user, $conversation, 'How many active employees do we have?');

        $this->assertEquals('There are 42 active employees.', $answer);
        $this->assertCount(2, $conversation->fresh()->messages);
        $assistantMessage = $conversation->fresh()->messages->last();
        $this->assertEquals('get_headcount', $assistantMessage->tool_calls[0]['name']);
        $this->assertEquals(['total_headcount' => 42], $assistantMessage->tool_calls[0]['result']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaOrchestratorServiceTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the orchestrator**

```php
<?php

namespace App\Services\Atlas\Dyna;

use App\Models\Atlas\DynaConversation;
use App\Models\Atlas\DynaMessage;
use App\Models\User;

class DynaOrchestratorService
{
    private const SYSTEM_PROMPT = <<<'TEXT'
        You are Dyna, an analytics and insights assistant for Atlas, the campus management
        system for Philippine Science High School - Caraga Region Campus. You answer questions
        for the Campus Director and Division Chiefs (MANCOM) using the tools available to you.
        Always call a tool to get real data before stating any number — never estimate or
        invent statistics. If no tool can answer the question, say so plainly.
        TEXT;

    private const MAX_TOOL_TURNS = 5;

    public function __construct(
        private readonly DynaToolRegistry $tools,
        private readonly DynaBedrockClientFactory $clientFactory,
    ) {}

    public function reply(User $user, DynaConversation $conversation, string $userMessage): string
    {
        DynaMessage::create([
            'dyna_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
            'created_at' => now(),
        ]);

        $client = $this->clientFactory->make();
        $messages = $this->historyAsBedrockMessages($conversation);
        $messages[] = ['role' => 'user', 'content' => [['text' => $userMessage]]];

        $toolCallLog = [];

        for ($turn = 0; $turn < self::MAX_TOOL_TURNS; $turn++) {
            $result = $client->converse([
                'modelId' => config('services.bedrock.inference_profile_id'),
                'system' => [['text' => self::SYSTEM_PROMPT]],
                'messages' => $messages,
                'toolConfig' => $this->tools->toBedrockToolConfig(),
                'inferenceConfig' => ['maxTokens' => 1024],
            ]);

            $assistantContent = $result['output']['message']['content'];
            $messages[] = ['role' => 'assistant', 'content' => $assistantContent];

            $toolUseBlocks = array_values(array_filter($assistantContent, fn ($b) => isset($b['toolUse'])));

            if (empty($toolUseBlocks)) {
                $finalText = collect($assistantContent)->pluck('text')->filter()->implode('');

                DynaMessage::create([
                    'dyna_conversation_id' => $conversation->id,
                    'role' => 'assistant',
                    'content' => $finalText,
                    'tool_calls' => $toolCallLog ?: null,
                    'created_at' => now(),
                ]);

                return $finalText;
            }

            $toolResultBlocks = [];
            foreach ($toolUseBlocks as $block) {
                $toolUse = $block['toolUse'];
                $output = $this->tools->execute($toolUse['name'], $toolUse['input'] ?? [], $user);

                $toolCallLog[] = ['name' => $toolUse['name'], 'input' => $toolUse['input'] ?? [], 'result' => $output];

                $toolResultBlocks[] = ['toolResult' => [
                    'toolUseId' => $toolUse['toolUseId'],
                    'content' => [['json' => $output]],
                ]];
            }

            $messages[] = ['role' => 'user', 'content' => $toolResultBlocks];
        }

        $fallback = "I wasn't able to complete that within the allowed number of tool calls — try narrowing the question.";

        DynaMessage::create([
            'dyna_conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $fallback,
            'tool_calls' => $toolCallLog ?: null,
            'created_at' => now(),
        ]);

        return $fallback;
    }

    private function historyAsBedrockMessages(DynaConversation $conversation): array
    {
        return $conversation->messages->map(fn (DynaMessage $m) => [
            'role' => $m->role,
            'content' => [['text' => $m->content]],
        ])->all();
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaOrchestratorServiceTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Services/Atlas/Dyna/DynaOrchestratorService.php tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php
git commit -m "feat(dyna): add DynaOrchestratorService tool-use loop"
```

---

### Task 9: `/api/dyna/login` — token issuance

**Files:**
- Create: `app/Http/Controllers/Api/DynaAuthController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Atlas/Dyna/DynaAuthTest.php`

**Interfaces:**
- Consumes: `User::createToken()` (Sanctum, existing — mirrors
  `StudentAttendance\Api\AuthController::login`), `atlas.dyna.access` permission (Task 2).
- Produces: `POST /api/dyna/login` returning `{token, user: {name, email}}` on success.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DynaAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_dyna_access_can_log_in_and_receive_a_token(): void
    {
        $user = $this->userWithPermissions(['atlas.dyna.access']);
        $user->update(['password' => Hash::make('correct-password')]);

        $response = $this->postJson('/api/dyna/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'device_name' => 'Dyna.app on MacBook Pro',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'user' => ['name', 'email']]);
    }

    public function test_a_user_without_dyna_access_is_denied(): void
    {
        $user = User::factory()->create(['password' => Hash::make('correct-password')]);

        $response = $this->postJson('/api/dyna/login', [
            'email' => $user->email,
            'password' => 'correct-password',
            'device_name' => 'Dyna.app on MacBook Pro',
        ]);

        $response->assertStatus(403);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Dyna Test '.uniqid()]);
        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'Atlas', 'description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaAuthTest"`
Expected: FAIL — route not found.

- [ ] **Step 3: Write the controller and route**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DynaAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['The provided credentials are incorrect.']]);
        }

        if (! $user->hasPermission('atlas.dyna.access')) {
            return response()->json(['message' => 'This account does not have Dyna access.'], 403);
        }

        $user->tokens()->where('name', $validated['device_name'])->delete();
        $token = $user->createToken($validated['device_name'], ['dyna'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['name' => $user->name, 'email' => $user->email],
        ]);
    }
}
```

Add to `routes/api.php`:

```php
use App\Http\Controllers\Api\DynaAuthController;
use App\Http\Controllers\Api\DynaController;

Route::prefix('dyna')->name('dyna.')->group(function () {
    Route::post('/login', [DynaAuthController::class, 'login'])->name('login')->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'permission:atlas.dyna.access'])->group(function () {
        Route::post('/chat', [DynaController::class, 'chat'])->name('chat');
        Route::get('/conversations', [DynaController::class, 'conversations'])->name('conversations');
    });
});
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaAuthTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/DynaAuthController.php routes/api.php tests/Feature/Atlas/Dyna/DynaAuthTest.php
git commit -m "feat(dyna): add /api/dyna/login token issuance"
```

---

### Task 10: `POST /api/dyna/chat` — the chat endpoint

**Files:**
- Create: `app/Http/Controllers/Api/DynaController.php`
- Modify: `app/Providers/AppServiceProvider.php` (see note below)
- Modify: `routes/api.php` (already added in Task 9)
- Test: `tests/Feature/Atlas/Dyna/DynaChatEndpointTest.php`

**Interfaces:**
- Consumes: `DynaOrchestratorService::reply()` (Task 8), `DynaConversation` (Task 3).
- Produces: `POST /api/dyna/chat` accepting `{conversation_id?: int, message: string}`, creates
  a new `DynaConversation` when `conversation_id` is omitted, returns
  `{conversation_id, answer}`. `GET /api/dyna/conversations` lists the authenticated user's own
  conversations (id, title, updated_at) for the Mac app's history sidebar.

**Real bug found during execution — container binding was missing:** the third test below
(unmocked `DynaOrchestratorService`, real container resolution) failed with
`Unresolvable dependency ... array $tools in DynaToolRegistry` — Laravel's container cannot
auto-wire an array-typed constructor parameter by type-hint alone, even though `GetHeadcountTool`
and `GetLeaveTrendsTool` themselves resolve fine individually. Task 4's registry design assumed
this would "just work" via constructor injection; it does not. Fixed by adding an explicit
singleton binding in `app/Providers/AppServiceProvider.php::register()`:

```php
use App\Services\Atlas\Dyna\DynaToolRegistry;
use App\Services\Atlas\Dyna\Tools\GetHeadcountTool;
use App\Services\Atlas\Dyna\Tools\GetLeaveTrendsTool;

public function register(): void
{
    $this->app->singleton(DynaToolRegistry::class, function ($app) {
        return new DynaToolRegistry([
            $app->make(GetHeadcountTool::class),
            $app->make(GetLeaveTrendsTool::class),
        ]);
    });
}
```

Any future Dyna tool (payroll summary, IPCR completion, enrollment stats — the fast-follow
tools noted at the top of this plan) must be added to this array too, or the container will
silently keep resolving `DynaToolRegistry` without it.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Atlas\DynaConversation;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Atlas\Dyna\DynaOrchestratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DynaChatEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_creates_a_conversation_when_none_is_given_and_returns_the_answer(): void
    {
        $user = $this->userWithPermissions(['atlas.dyna.access']);

        $this->mock(DynaOrchestratorService::class, function ($mock) {
            $mock->shouldReceive('reply')
                ->once()
                ->andReturn('There are 42 active employees.');
        });

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/dyna/chat', ['message' => 'How many active employees do we have?']);

        $response->assertOk()->assertJsonStructure(['conversation_id', 'answer']);
        $this->assertDatabaseHas('dyna_conversations', ['user_id' => $user->id]);
    }

    public function test_chat_reuses_an_existing_conversation_owned_by_the_user(): void
    {
        $user = $this->userWithPermissions(['atlas.dyna.access']);
        $conversation = DynaConversation::create(['user_id' => $user->id]);

        $this->mock(DynaOrchestratorService::class, function ($mock) {
            $mock->shouldReceive('reply')->once()->andReturn('Follow-up answer.');
        });

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/dyna/chat', ['conversation_id' => $conversation->id, 'message' => 'And last quarter?']);

        $response->assertOk()->assertJson(['conversation_id' => $conversation->id]);
    }

    public function test_chat_rejects_a_conversation_id_owned_by_another_user(): void
    {
        $owner = $this->userWithPermissions(['atlas.dyna.access']);
        $conversation = DynaConversation::create(['user_id' => $owner->id]);
        $intruder = $this->userWithPermissions(['atlas.dyna.access']);

        $response = $this->actingAs($intruder, 'sanctum')
            ->postJson('/api/dyna/chat', ['conversation_id' => $conversation->id, 'message' => 'hi']);

        $response->assertStatus(404);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Dyna Test '.uniqid()]);
        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'Atlas', 'description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaChatEndpointTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Atlas\DynaConversation;
use App\Services\Atlas\Dyna\DynaOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynaController extends Controller
{
    public function __construct(private readonly DynaOrchestratorService $orchestrator) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $user = $request->user();

        if (! empty($validated['conversation_id'])) {
            $conversation = DynaConversation::where('user_id', $user->id)
                ->findOrFail($validated['conversation_id']);
        } else {
            $conversation = DynaConversation::create([
                'user_id' => $user->id,
                'title' => str($validated['message'])->limit(60),
            ]);
        }

        $answer = $this->orchestrator->reply($user, $conversation, $validated['message']);

        return response()->json(['conversation_id' => $conversation->id, 'answer' => $answer]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $conversations = DynaConversation::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at']);

        return response()->json($conversations);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaChatEndpointTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/DynaController.php tests/Feature/Atlas/Dyna/DynaChatEndpointTest.php
git commit -m "feat(dyna): add POST /api/dyna/chat endpoint"
```

---

### Task 11: `GET /api/dyna/conversations/{id}` — conversation detail with messages

**Files:**
- Modify: `app/Http/Controllers/Api/DynaController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Atlas/Dyna/DynaConversationShowTest.php`

**Interfaces:**
- Consumes: `DynaConversation` / `DynaMessage` (Task 3).
- Produces: `GET /api/dyna/conversations/{id}` returning
  `{id, title, messages: [{role, content, created_at}]}` for a conversation owned by the
  requesting user, 404 otherwise. Dyna.app's macOS plan (`2026-08-02-dyna-macos-app.md`,
  Task 5/7) depends on this exact shape to populate the chat view when a user selects a past
  conversation from the sidebar — without it, selecting a past conversation shows a blank
  thread even though the server-side history still exists.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Atlas\Dyna;

use App\Models\Atlas\DynaConversation;
use App\Models\Atlas\DynaMessage;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynaConversationShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_the_conversations_messages_in_order(): void
    {
        $user = $this->userWithPermissions(['atlas.dyna.access']);
        $conversation = DynaConversation::create(['user_id' => $user->id, 'title' => 'Leave trends']);
        DynaMessage::create(['dyna_conversation_id' => $conversation->id, 'role' => 'user', 'content' => 'first', 'created_at' => now()]);
        DynaMessage::create(['dyna_conversation_id' => $conversation->id, 'role' => 'assistant', 'content' => 'second', 'created_at' => now()->addMinute()]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/dyna/conversations/{$conversation->id}");

        $response->assertOk()
            ->assertJsonPath('title', 'Leave trends')
            ->assertJsonPath('messages.0.content', 'first')
            ->assertJsonPath('messages.1.content', 'second');
    }

    public function test_show_rejects_a_conversation_owned_by_another_user(): void
    {
        $owner = $this->userWithPermissions(['atlas.dyna.access']);
        $conversation = DynaConversation::create(['user_id' => $owner->id]);
        $intruder = $this->userWithPermissions(['atlas.dyna.access']);

        $response = $this->actingAs($intruder, 'sanctum')
            ->getJson("/api/dyna/conversations/{$conversation->id}");

        $response->assertStatus(404);
    }

    private function userWithPermissions(array $permissionNames): User
    {
        $role = Role::create(['name' => 'Dyna Test '.uniqid()]);
        foreach ($permissionNames as $name) {
            $permission = Permission::firstOrCreate(['name' => $name], ['module' => 'Atlas', 'description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user = User::factory()->create();
        $user->roles()->attach($role);

        return $user;
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaConversationShowTest"`
Expected: FAIL — route not found.

- [ ] **Step 3: Add the `show` method and route**

Add to `DynaController`:

```php
    public function show(Request $request, int $id): JsonResponse
    {
        $conversation = DynaConversation::where('user_id', $request->user()->id)
            ->with('messages')
            ->findOrFail($id);

        return response()->json([
            'id' => $conversation->id,
            'title' => $conversation->title,
            'messages' => $conversation->messages->map(fn ($m) => [
                'role' => $m->role,
                'content' => $m->content,
                'created_at' => $m->created_at,
            ]),
        ]);
    }
```

Add to `routes/api.php`, inside the existing `auth:sanctum` + `permission:atlas.dyna.access`
group from Task 9:

```php
Route::get('/conversations/{id}', [DynaController::class, 'show'])->name('conversations.show');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=DynaConversationShowTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/DynaController.php routes/api.php tests/Feature/Atlas/Dyna/DynaConversationShowTest.php
git commit -m "feat(dyna): add GET /api/dyna/conversations/{id} for history detail"
```

---

### Task 12: Full run — verify the whole Dyna backend test suite together

**Files:** none (verification-only task).

- [ ] **Step 1: Run the full Dyna test suite**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=Dyna"`
Expected: PASS — every test from Tasks 1-10, no interaction failures (e.g. permission
seeder migration ordering, `RefreshDatabase` conflicts).

- [ ] **Step 2: PHP syntax-check every modified/created file**

Run the project's `lint` skill (`php -l` sweep) over every file touched in this plan.

- [ ] **Step 3: Manual smoke test against real Bedrock (dev only)**

Set `BEDROCK_INFERENCE_PROFILE_ID` in `.env` to a real Sonnet 5 cross-region profile ID
(look it up with `aws bedrock list-inference-profiles --region us-east-1`), obtain a token via
`POST /api/dyna/login`, then `POST /api/dyna/chat` with a real question
("How many active employees do we have?") and confirm a sensible, correctly-scoped answer
comes back. This is the first point this plan touches real AWS — do it deliberately, not as
part of automated CI.

- [ ] **Step 4: Commit (if Step 2 required fixes)**

```bash
git add -u
git commit -m "fix(dyna): address lint findings"
```
