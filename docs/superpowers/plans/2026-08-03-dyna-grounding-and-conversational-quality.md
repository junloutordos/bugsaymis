# Dyna Grounding, Reliability, and Conversational Quality Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the four concrete gaps behind Dyna's "wrong answers" — missing date grounding, uncaught tool-execution failures, unswept Carbon-leak risk, and a system prompt with no conversational guidance — without changing Dyna's tool-only architecture.

**Architecture:** All changes are localized to `App\Services\Atlas\Dyna\DynaOrchestratorService`, `App\Services\Atlas\Dyna\DynaToolRegistry`, and their test suites, plus a new shared test trait consumed by every Dyna tool test. No new tools, no new routes, no schema changes.

**Tech Stack:** Laravel 12 / PHP 8.4, PHPUnit (via `docker compose exec php`), AWS Bedrock Converse API (mocked in tests via Mockery).

## Global Constraints

- Dyna stays tool-only — no raw SQL / generic query tool (per approved spec, decision final).
- Model stays Amazon Nova Pro — no model switch in this plan (per approved spec).
- Run tests via `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit ..."` — Docker service name is `php`, never `app`.
- `php artisan test` doesn't forward `-d memory_limit=` to its PHPUnit subprocess — use `vendor/bin/phpunit` directly for any full-suite run.
- Stage git commits by explicit file path — never `git add -A` / `git add .`.
- No comments explaining *what* code does — only *why*, and only where non-obvious (matches existing Dyna code style, e.g. the comments already in `DynaOrchestratorService`/`ExecutiveDashboardAdapterTool`).

---

## Background: sweep already done, no live bugs found

Before writing this plan, all 14 tool files that had never been checked for the Carbon-leak bug class (`GetClassRecordComplianceTool`, `GetClassScheduleTool`, `GetCompetitionsStatsTool`, `GetDisciplineCaseStatsTool`, `GetEmployeeInfoTool`, `GetEnrollmentStatusBreakdownTool`, `GetFacultyLoadDistributionTool`, `GetGateAttendanceTrendTool`, `GetHeadcountTool`, `GetHomeroomAttendanceSummaryTool`, `GetLeaveTrendsTool`, `GetLibraryStatsTool`, `GetStudentInfoTool`, `GetTeacherAttendanceStatsTool`) were read and cross-referenced against their models' real `$casts`. **All 14 are clean** — every one either does `selectRaw(...)->pluck(...)->toArray()` scalar aggregation (never touches a Carbon object) or calls `->toArray()` on an Eloquent model/collection (which safely serializes date casts to strings via `attributesToArray()`). The 9 `ExecutiveDashboardAdapterTool` subclasses (`GetPerformanceStatsTool`, `GetRequestsStatsTool`, `GetSatisfactionStatsTool`, `GetAcademicsStatsTool`, `GetRecruitmentStatsTool`, `GetFinanceStatsTool`, `GetOperationsStatsTool`, `GetAttentionItemsTool`, `GetDivisionScorecardTool`) are also structurally safe — their shared `execute()` already round-trips the result through `json_decode(json_encode($section), true)`, which serializes any Carbon/Collection to a JSON-safe value.

The bug class was correctly isolated to the 2 tools already fixed on 2026-08-03 (`GetEmployeeFullProfileTool`, `GetStudentFullProfileTool`) — the only tools with large hand-built profile arrays mixing many raw `->map()` closures across many models. Tasks 3-5 below add the regression guard everywhere anyway (cheap insurance against a future regression reintroducing this pattern), but involve **no bug fixes**, only new tests.

---

### Task 1: Ground the system prompt with today's date

**Files:**
- Modify: `app/Services/Atlas/Dyna/DynaOrchestratorService.php:26-52`
- Test: `tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new — internal behavior change only, no signature changes.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php`, inside the `DynaOrchestratorServiceTest` class (all classes/functions used below are already imported at the top of this file):

```php
    public function test_reply_includes_todays_date_in_the_system_prompt(): void
    {
        $user = User::factory()->create();
        $conversation = DynaConversation::create(['user_id' => $user->id]);
        $registry = new DynaToolRegistry([]);

        $capturedArgs = null;

        $bedrock = Mockery::mock(BedrockRuntimeClient::class);
        $bedrock->shouldReceive('converse')
            ->once()
            ->andReturnUsing(function (array $args) use (&$capturedArgs) {
                $capturedArgs = $args;

                return new Result([
                    'output' => ['message' => ['role' => 'assistant', 'content' => [
                        ['text' => 'Sure, one moment.'],
                    ]]],
                    'stopReason' => 'end_turn',
                ]);
            });

        $clientFactory = Mockery::mock(DynaBedrockClientFactory::class);
        $clientFactory->shouldReceive('make')->andReturn($bedrock);

        $orchestrator = new DynaOrchestratorService($registry, $clientFactory);

        $orchestrator->reply($user, $conversation, 'How many gate scans today?');

        $this->assertStringContainsString(now()->toDateString(), $capturedArgs['system'][0]['text']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_reply_includes_todays_date_in_the_system_prompt tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php"`
Expected: FAIL — the captured `system` text is the static `SYSTEM_PROMPT` constant, which contains no date.

- [ ] **Step 3: Implement**

In `app/Services/Atlas/Dyna/DynaOrchestratorService.php`, inside `reply()`, replace:

```php
        DynaMessage::create([
            'dyna_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
            'created_at' => now(),
        ]);

        $toolCallLog = [];

        for ($turn = 0; $turn < self::MAX_TOOL_TURNS; $turn++) {
            $result = $client->converse([
                'modelId' => config('services.bedrock.inference_profile_id'),
                'system' => [['text' => self::SYSTEM_PROMPT]],
```

with:

```php
        DynaMessage::create([
            'dyna_conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $userMessage,
            'created_at' => now(),
        ]);

        // SYSTEM_PROMPT is a compile-time const, so today's date — needed to resolve
        // relative-date questions ("today", "this week") into a tool's from_date/to_date —
        // has to be appended here at request time instead.
        $systemPrompt = self::SYSTEM_PROMPT."\n\nToday's date is ".now()->toDateString().'.';
        $toolCallLog = [];

        for ($turn = 0; $turn < self::MAX_TOOL_TURNS; $turn++) {
            $result = $client->converse([
                'modelId' => config('services.bedrock.inference_profile_id'),
                'system' => [['text' => $systemPrompt]],
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_reply_includes_todays_date_in_the_system_prompt tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php"`
Expected: PASS

- [ ] **Step 5: Run the full orchestrator suite to confirm no regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php"`
Expected: PASS, all tests (the existing 5 plus the new one)

- [ ] **Step 6: Commit**

```bash
git add app/Services/Atlas/Dyna/DynaOrchestratorService.php tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php
git commit -m "fix(dyna): ground system prompt with today's date so relative-date questions resolve"
```

---

### Task 2: Make DynaToolRegistry tolerate tool execution failures

**Files:**
- Modify: `app/Services/Atlas/Dyna/DynaToolRegistry.php`
- Test: `tests/Feature/Atlas/Dyna/DynaToolRegistryTest.php`
- Test: `tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `DynaToolRegistry::execute()` now never throws for a tool-internal failure — it returns `['error' => 'This data could not be retrieved right now.']` instead. It still throws `\InvalidArgumentException` for an unknown tool name (unchanged).

- [ ] **Step 1: Write the failing registry-level test**

Add to `tests/Feature/Atlas/Dyna/DynaToolRegistryTest.php`. First add this import alongside the existing ones at the top of the file:

```php
use Illuminate\Support\Facades\Log;
```

Then add inside the `DynaToolRegistryTest` class:

```php
    public function test_registry_catches_a_tool_exception_logs_it_and_returns_a_structured_error(): void
    {
        Log::spy();

        $tool = new class implements DynaTool {
            public function name(): string { return 'broken_tool'; }
            public function description(): string { return 'desc'; }
            public function inputSchema(): array { return ['type' => 'object', 'properties' => []]; }
            public function execute(User $user, array $input): array { throw new \RuntimeException('DB timeout'); }
        };
        $registry = new DynaToolRegistry([$tool]);

        $result = $registry->execute('broken_tool', [], User::factory()->make());

        $this->assertEquals(['error' => 'This data could not be retrieved right now.'], $result);
        Log::shouldHaveReceived('error')->once()->withArgs(
            fn ($message, $context) => $message === 'Dyna tool execution failed' && $context['tool'] === 'broken_tool'
        );
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_registry_catches_a_tool_exception_logs_it_and_returns_a_structured_error tests/Feature/Atlas/Dyna/DynaToolRegistryTest.php"`
Expected: FAIL — the `\RuntimeException` propagates uncaught out of `execute()`, PHPUnit reports it as an error, not the expected return value.

- [ ] **Step 3: Implement**

Replace the full contents of `app/Services/Atlas/Dyna/DynaToolRegistry.php` with:

```php
<?php

namespace App\Services\Atlas\Dyna;

use App\Models\User;
use App\Services\Atlas\Dyna\Tools\DynaTool;
use Illuminate\Support\Facades\Log;

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

        try {
            return $this->tools[$name]->execute($user, $input);
        } catch (\Throwable $e) {
            // A tool exception must never crash the whole chat turn — it's caught here so
            // Bedrock gets a valid tool result and the model can tell the user plainly that
            // this specific piece of data couldn't be retrieved, instead of the request
            // 500ing with no answer at all. Logged so failures are visible in CloudWatch
            // proactively rather than only being found after a user complaint.
            Log::error('Dyna tool execution failed', [
                'tool' => $name,
                'input' => $input,
                'user_id' => $user->id,
                'exception' => $e,
            ]);

            return ['error' => 'This data could not be retrieved right now.'];
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_registry_catches_a_tool_exception_logs_it_and_returns_a_structured_error tests/Feature/Atlas/Dyna/DynaToolRegistryTest.php"`
Expected: PASS

- [ ] **Step 5: Write the failing end-to-end orchestrator test**

Add to `tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php`, inside the `DynaOrchestratorServiceTest` class:

```php
    public function test_reply_surfaces_a_caught_tool_error_to_the_model_as_a_tool_result(): void
    {
        $user = User::factory()->create();
        $conversation = DynaConversation::create(['user_id' => $user->id]);

        $tool = new class implements DynaTool {
            public function name(): string { return 'get_headcount'; }
            public function description(): string { return 'desc'; }
            public function inputSchema(): array { return ['type' => 'object', 'properties' => []]; }
            public function execute(User $user, array $input): array { throw new \RuntimeException('DB timeout'); }
        };
        $registry = new DynaToolRegistry([$tool]);

        $capturedSecondCallArgs = null;

        $bedrock = Mockery::mock(BedrockRuntimeClient::class);
        $bedrock->shouldReceive('converse')
            ->once()
            ->andReturn(new Result([
                'output' => ['message' => ['role' => 'assistant', 'content' => [
                    ['toolUse' => ['toolUseId' => 'tu_1', 'name' => 'get_headcount', 'input' => []]],
                ]]],
                'stopReason' => 'tool_use',
            ]))
            ->ordered();
        $bedrock->shouldReceive('converse')
            ->once()
            ->ordered()
            ->andReturnUsing(function (array $args) use (&$capturedSecondCallArgs) {
                $capturedSecondCallArgs = $args;

                return new Result([
                    'output' => ['message' => ['role' => 'assistant', 'content' => [
                        ['text' => "I couldn't retrieve the headcount right now."],
                    ]]],
                    'stopReason' => 'end_turn',
                ]);
            });

        $clientFactory = Mockery::mock(DynaBedrockClientFactory::class);
        $clientFactory->shouldReceive('make')->andReturn($bedrock);

        $orchestrator = new DynaOrchestratorService($registry, $clientFactory);

        $answer = $orchestrator->reply($user, $conversation, 'How many active employees do we have?');

        $toolResultContent = $capturedSecondCallArgs['messages'][2]['content'][0]['toolResult']['content'][0]['json'];
        $this->assertEquals(['error' => 'This data could not be retrieved right now.'], $toolResultContent);
        $this->assertEquals("I couldn't retrieve the headcount right now.", $answer);
    }
```

This test should already pass once Step 3 is in place (no orchestrator code change needed — `DynaToolRegistry::execute()` already returns a plain array to the existing loop). It documents and locks in the end-to-end behavior.

- [ ] **Step 6: Run it to confirm**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_reply_surfaces_a_caught_tool_error_to_the_model_as_a_tool_result tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php"`
Expected: PASS

- [ ] **Step 7: Run both full test files to confirm no regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit tests/Feature/Atlas/Dyna/DynaToolRegistryTest.php tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php"`
Expected: PASS, all tests

- [ ] **Step 8: Commit**

```bash
git add app/Services/Atlas/Dyna/DynaToolRegistry.php tests/Feature/Atlas/Dyna/DynaToolRegistryTest.php tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php
git commit -m "fix(dyna): catch tool execution failures instead of crashing the chat turn"
```

---

### Task 3: Extract a shared JSON-safety regression assertion trait

**Files:**
- Create: `tests/Feature/Atlas/Dyna/Concerns/AssertsJsonSafeToolResult.php`
- Modify: `tests/Feature/Atlas/Dyna/GetEmployeeFullProfileToolTest.php`
- Modify: `tests/Feature/Atlas/Dyna/GetStudentFullProfileToolTest.php`

**Interfaces:**
- Produces: `Tests\Feature\Atlas\Dyna\Concerns\AssertsJsonSafeToolResult` trait with one method, `assertNoNonScalarLeaves(mixed $value, string $path = 'result'): void`, usable by any `TestCase` subclass via `use AssertsJsonSafeToolResult;`. Tasks 4 and 5 both consume this exact method name/signature.

This is a pure refactor (DRY-ing up an assertion currently duplicated verbatim in 2 files) — no behavior change, so it's TDD'd by keeping the existing tests green throughout rather than a new failing test.

- [ ] **Step 1: Create the trait**

```php
<?php

namespace Tests\Feature\Atlas\Dyna\Concerns;

trait AssertsJsonSafeToolResult
{
    /**
     * Regression guard for a real prod bug: a ->map() closure extracting a Carbon-cast
     * date via raw property access leaks a live Carbon object into a tool's return array.
     * Mocked-Bedrock tests never catch this — only the real AWS SDK's Converse
     * document-type validator does, rejecting the whole request with `[...][json] is not
     * a valid document type`. Assert every leaf value is JSON-safe (scalar/null/array) so
     * this bug class can't silently reappear in any Dyna tool.
     */
    private function assertNoNonScalarLeaves(mixed $value, string $path = 'result'): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $this->assertNoNonScalarLeaves($item, "{$path}.{$key}");
            }

            return;
        }

        $this->assertTrue(
            is_scalar($value) || is_null($value),
            "Expected a JSON-safe scalar at {$path}, got ".(is_object($value) ? get_class($value) : gettype($value))
        );
    }
}
```

- [ ] **Step 2: Refactor `GetEmployeeFullProfileToolTest.php` to use it**

Add this import near the top of the file, alongside the other imports:

```php
use Tests\Feature\Atlas\Dyna\Concerns\AssertsJsonSafeToolResult;
```

Find the class declaration and its trait-use line, and change:

```php
class GetEmployeeFullProfileToolTest extends TestCase
{
    use RefreshDatabase;
```

to:

```php
class GetEmployeeFullProfileToolTest extends TestCase
{
    use AssertsJsonSafeToolResult;
    use RefreshDatabase;
```

Then delete the now-duplicate private method from this file (it's identical to the trait's):

```php
    private function assertNoNonScalarLeaves(mixed $value, string $path = 'result'): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                $this->assertNoNonScalarLeaves($item, "{$path}.{$key}");
            }

            return;
        }

        $this->assertTrue(
            is_scalar($value) || is_null($value),
            "Expected a JSON-safe scalar at {$path}, got ".(is_object($value) ? get_class($value) : gettype($value))
        );
    }
```

- [ ] **Step 3: Refactor `GetStudentFullProfileToolTest.php` the same way**

Same three edits: add the `use Tests\Feature\Atlas\Dyna\Concerns\AssertsJsonSafeToolResult;` import, add `use AssertsJsonSafeToolResult;` before `use RefreshDatabase;` inside the class, delete the duplicate private method from this file.

- [ ] **Step 4: Run both files to confirm the refactor didn't break anything**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit tests/Feature/Atlas/Dyna/GetEmployeeFullProfileToolTest.php tests/Feature/Atlas/Dyna/GetStudentFullProfileToolTest.php"`
Expected: PASS, all tests (same count as before the refactor)

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Atlas/Dyna/Concerns/AssertsJsonSafeToolResult.php tests/Feature/Atlas/Dyna/GetEmployeeFullProfileToolTest.php tests/Feature/Atlas/Dyna/GetStudentFullProfileToolTest.php
git commit -m "test(dyna): extract shared JSON-safety regression assertion into a trait"
```

---

### Task 4: Add JSON-safety regression tests to the 14 unswept tool test suites

**Files:**
- Modify: `tests/Feature/Atlas/Dyna/GetClassRecordComplianceToolTest.php`
- Modify: `tests/Feature/Atlas/Dyna/GetClassScheduleToolTest.php`
- Modify: `tests/Feature/Atlas/Dyna/GetCompetitionsStatsToolTest.php`
- Modify: `tests/Feature/Atlas/Dyna/GetDisciplineCaseStatsToolTest.php`
- Modify: `tests/Feature/Atlas/Dyna/GetEmployeeInfoToolTest.php`
- Modify: `tests/Feature/Atlas/Dyna/GetEnrollmentStatusBreakdownToolTest.php`
- Modify: `tests/Feature/Atlas/Dyna/GetFacultyLoadDistributionToolTest.php`
- Modify: `tests/Feature/Atlas/Dyna/GetGateAttendanceTrendToolTest.php`
- Modify: `tests/Feature/Atlas/Dyna/GetHeadcountToolTest.php`
- Modify: `tests/Feature/Atlas/Dyna/GetHomeroomAttendanceSummaryToolTest.php`
- Modify: `tests/Feature/Atlas/Dyna/GetLeaveTrendsToolTest.php`
- Modify: `tests/Feature/Atlas/Dyna/GetLibraryStatsToolTest.php`
- Modify: `tests/Feature/Atlas/Dyna/GetStudentInfoToolTest.php`
- Modify: `tests/Feature/Atlas/Dyna/GetTeacherAttendanceStatsToolTest.php`

**Interfaces:**
- Consumes: `Tests\Feature\Atlas\Dyna\Concerns\AssertsJsonSafeToolResult` from Task 3 (`use AssertsJsonSafeToolResult;` + call `$this->assertNoNonScalarLeaves($result)`).

Each step below is independent (add one test method to one file) and mechanically identical in shape: add the trait import + `use` line, add one new test method that re-runs the file's existing "happy path" arrangement and asserts the result is leak-free. None of these are expected to fail even before any production code change (confirmed clean in the Background section above) — they're regression coverage, not bug fixes, so "RED" here means the trait/method isn't wired up yet, not that a real leak exists.

- [ ] **Step 1: `GetClassRecordComplianceToolTest.php`**

Add import `use Tests\Feature\Atlas\Dyna\Concerns\AssertsJsonSafeToolResult;`, add `use AssertsJsonSafeToolResult;` before `use RefreshDatabase;` in the class, then add:

```php
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $teacher = User::factory()->create();
        $gradingOption = GradingOption::create(['name' => 'Standard']);
        ClassRecord::create(['teacher_id' => $teacher->id, 'grading_option_id' => $gradingOption->id, 'school_year' => '2026-2027', 'status' => 'checked', 'subject_name' => 'Math', 'year_level_section' => 'G7-A']);

        $administrator = User::factory()->create();
        $administrator->roles()->attach(Role::firstOrCreate(['name' => 'Administrator']));

        $result = (new GetClassRecordComplianceTool())->execute($administrator, []);

        $this->assertNoNonScalarLeaves($result);
    }
```

- [ ] **Step 2: `GetClassScheduleToolTest.php`**

Add the same import + trait `use` line, then add:

```php
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $term = AcademicTerm::create(['school_year_id' => $schoolYear->id, 'name' => 'Q1', 'is_current' => true]);
        $subject = Subject::create(['name' => 'Physics', 'code' => 'PHYS1', 'grade_level' => 8, 'school_year_id' => $schoolYear->id]);
        $section = Section::create(['sectionname' => 'Newton', 'levelid' => 8]);
        $faculty = User::factory()->create(['name' => 'Maria Santos']);

        ClassSchedule::create([
            'user_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'classroom_id' => null,
            'school_year_id' => $schoolYear->id,
            'academic_term_id' => $term->id,
            'day_of_week' => 'Monday',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'status' => 'active',
        ]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'faculty_loading.view']);

        $result = (new GetClassScheduleTool())->execute($user, ['faculty_identifier' => 'Maria Santos']);

        $this->assertNoNonScalarLeaves($result);
    }
```

- [ ] **Step 3: `GetCompetitionsStatsToolTest.php`**

Add the same import + trait `use` line, then add:

```php
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $creator = User::factory()->create();
        Competition::create(['title' => 'Math Olympiad', 'level' => 'regional', 'date_from' => '2026-07-01', 'created_by' => $creator->id]);

        $user = User::factory()->create();

        $result = (new GetCompetitionsStatsTool())->execute($user, []);

        $this->assertNoNonScalarLeaves($result);
    }
```

- [ ] **Step 4: `GetDisciplineCaseStatsToolTest.php`**

Add the same import + trait `use` line, then add (individual-mode path, since it returns more fields than the aggregate counts):

```php
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $lastname = 'DisciplineLeafCheck'.uniqid();
        $studentId = \DB::table('students')->insertGetId([
            'lastname' => $lastname, 'firstname' => 'Test',
        ]);

        $filer = User::factory()->create();
        DisciplineCase::create(['case_no' => 'DISC-2026-07-'.uniqid(), 'student_id' => $studentId, 'filer_id' => $filer->id, 'status' => 'resolved', 'threat_level' => 'low', 'nature_of_offense' => 'Tardiness', 'incident_date' => '2026-07-01', 'school_year_id' => 1]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'discipline.view']);

        $result = (new GetDisciplineCaseStatsTool())->execute($user, ['student_identifier' => $lastname]);

        $this->assertNoNonScalarLeaves($result);
    }
```

- [ ] **Step 5: `GetEmployeeInfoToolTest.php`**

Add the same import + trait `use` line, then add:

```php
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $division = Division::factory()->create();
        $employee = User::factory()->create([
            'name' => 'Leaf Check Employee', 'division_id' => $division->id, 'position' => 'Teacher III',
            'salary_grade' => 15, 'salary_step' => 3, 'status' => 'active',
        ]);

        $chief = User::factory()->create(['division_id' => $division->id]);
        $division->update(['division_chief_id' => $chief->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));
        $chief->roles()->first()->permissions()->attach(
            Permission::firstOrCreate(['name' => 'hr.employees.manage'], ['module' => 'HR', 'description' => 'x'])
        );
        $chief->roles()->first()->permissions()->attach(
            Permission::firstOrCreate(['name' => 'atlas.dyna.access'], ['module' => 'Atlas', 'description' => 'x'])
        );

        $result = (new GetEmployeeInfoTool())->execute($chief, ['identifier' => 'Leaf Check Employee']);

        $this->assertNoNonScalarLeaves($result);
    }
```

- [ ] **Step 6: `GetEnrollmentStatusBreakdownToolTest.php`**

Add the same import + trait `use` line, then add:

```php
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        StudentEnrollment::create(['student_id' => 1, 'school_year_id' => $schoolYear->id, 'section_id' => 1, 'grade_level' => 7, 'status' => 'enrolled', 'enrollment_date' => now()]);

        $user = User::factory()->create();

        $result = (new GetEnrollmentStatusBreakdownTool())->execute($user, []);

        $this->assertNoNonScalarLeaves($result);
    }
```

- [ ] **Step 7: `GetFacultyLoadDistributionToolTest.php`**

Add the same import + trait `use` line, then add:

```php
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $division = Division::factory()->create();
        $faculty = User::factory()->create(['division_id' => $division->id]);

        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $term = AcademicTerm::create(['school_year_id' => $schoolYear->id, 'name' => '1st Semester']);

        FacultyLoad::create(['user_id' => $faculty->id, 'school_year_id' => $schoolYear->id, 'academic_term_id' => $term->id, 'load_status' => 'overload', 'total_units' => 20]);

        $chief = User::factory()->create(['division_id' => $division->id]);
        $division->update(['division_chief_id' => $chief->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));

        $result = (new GetFacultyLoadDistributionTool())->execute($chief, []);

        $this->assertNoNonScalarLeaves($result);
    }
```

- [ ] **Step 8: `GetGateAttendanceTrendToolTest.php`**

Add the same import + trait `use` line, then add:

```php
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        StudentAttendanceLog::create(['student_id' => 1, 'raw_barcode' => 'BC1', 'type' => 'in', 'scan_time' => '2026-07-27 07:00:00']);

        $user = User::factory()->create();

        $result = (new GetGateAttendanceTrendTool())->execute($user, [
            'from_date' => '2026-07-27', 'to_date' => '2026-07-28',
        ]);

        $this->assertNoNonScalarLeaves($result);
    }
```

- [ ] **Step 9: `GetHeadcountToolTest.php`**

Add the same import + trait `use` line, then add:

```php
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $division = Division::factory()->create();
        User::factory()->count(2)->create(['division_id' => $division->id, 'status' => 'active']);
        $administrator = $this->userWithRole('Administrator');

        $result = (new GetHeadcountTool())->execute($administrator, []);

        $this->assertNoNonScalarLeaves($result);
    }
```

- [ ] **Step 10: `GetHomeroomAttendanceSummaryToolTest.php`**

Add the same import + trait `use` line, then add:

```php
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $report = MonthlyReport::create(['section_id' => 1, 'school_year_id' => $schoolYear->id, 'month' => 7, 'year' => 2026]);
        MonthlyReportLine::create(['homeroom_monthly_report_id' => $report->id, 'student_id' => 1, 'cutting_count' => 2, 'is_perfect_attendance' => false, 'excused_absences' => 1, 'unexcused_absences' => 1]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'homeroom-attendance.admin']);

        $result = (new GetHomeroomAttendanceSummaryTool())->execute($user, []);

        $this->assertNoNonScalarLeaves($result);
    }
```

- [ ] **Step 11: `GetLeaveTrendsToolTest.php`**

Add the same import + trait `use` line, then add:

```php
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $division = Division::factory()->create();
        $inDivision = User::factory()->create(['division_id' => $division->id]);
        LeaveApplication::factory()->create(['user_id' => $inDivision->id, 'status' => 'approved', 'created_at' => '2026-07-15']);

        $chief = User::factory()->create(['division_id' => $division->id]);
        $chief->roles()->attach(Role::firstOrCreate(['name' => 'DivisionChief']));

        $result = (new GetLeaveTrendsTool())->execute($chief, [
            'from_date' => '2026-07-01',
            'to_date' => '2026-07-31',
        ]);

        $this->assertNoNonScalarLeaves($result);
    }
```

- [ ] **Step 12: `GetLibraryStatsToolTest.php`**

Add the same import + trait `use` line, then add:

```php
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $book = LibraryCollection::create(['title' => 'Test Book']);
        Borrowing::create(['collection_id' => $book->id, 'borrower_type' => 'App\\Models\\User', 'borrower_id' => 1, 'borrow_date' => now()->subDays(5), 'due_date' => now()->addDays(3), 'status' => 'Borrowed']);

        $user = User::factory()->create();

        $result = (new GetLibraryStatsTool())->execute($user, []);

        $this->assertNoNonScalarLeaves($result);
    }
```

- [ ] **Step 13: `GetStudentInfoToolTest.php`**

Add the same import + trait `use` line, then add:

```php
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $lastname = 'StudentInfoLeafCheck'.uniqid();
        $studentId = \DB::table('students')->insertGetId(['lastname' => $lastname, 'firstname' => 'Test']);

        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        StudentEnrollment::create(['student_id' => $studentId, 'school_year_id' => $schoolYear->id, 'section_id' => 1, 'grade_level' => 9, 'status' => 'enrolled', 'enrollment_date' => now()]);

        $user = $this->userWithPermissions(['atlas.dyna.access', 'students.enrollment.view']);

        $result = (new GetStudentInfoTool())->execute($user, ['identifier' => $lastname]);

        $this->assertNoNonScalarLeaves($result);
    }
```

- [ ] **Step 14: `GetTeacherAttendanceStatsToolTest.php`**

Add the same import + trait `use` line, then add:

```php
    public function test_result_contains_no_non_scalar_leaked_date_objects(): void
    {
        $teacher = User::factory()->create();
        $schoolYear = SchoolYear::create(['name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-03-31', 'is_current' => true]);
        $classroom = Classroom::create(['name' => 'Science Hall 101', 'code' => 'SH-101-'.uniqid(), 'school_year_id' => $schoolYear->id]);
        TeacherTapLog::create(['user_id' => $teacher->id, 'classroom_id' => $classroom->id, 'status' => 'on_time', 'tapped_at' => now(), 'is_late' => false]);

        $administrator = User::factory()->create();
        $administrator->roles()->attach(Role::firstOrCreate(['name' => 'Administrator']));

        $result = (new GetTeacherAttendanceStatsTool())->execute($administrator, []);

        $this->assertNoNonScalarLeaves($result);
    }
```

- [ ] **Step 15: Run all 14 files together**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit tests/Feature/Atlas/Dyna/GetClassRecordComplianceToolTest.php tests/Feature/Atlas/Dyna/GetClassScheduleToolTest.php tests/Feature/Atlas/Dyna/GetCompetitionsStatsToolTest.php tests/Feature/Atlas/Dyna/GetDisciplineCaseStatsToolTest.php tests/Feature/Atlas/Dyna/GetEmployeeInfoToolTest.php tests/Feature/Atlas/Dyna/GetEnrollmentStatusBreakdownToolTest.php tests/Feature/Atlas/Dyna/GetFacultyLoadDistributionToolTest.php tests/Feature/Atlas/Dyna/GetGateAttendanceTrendToolTest.php tests/Feature/Atlas/Dyna/GetHeadcountToolTest.php tests/Feature/Atlas/Dyna/GetHomeroomAttendanceSummaryToolTest.php tests/Feature/Atlas/Dyna/GetLeaveTrendsToolTest.php tests/Feature/Atlas/Dyna/GetLibraryStatsToolTest.php tests/Feature/Atlas/Dyna/GetStudentInfoToolTest.php tests/Feature/Atlas/Dyna/GetTeacherAttendanceStatsToolTest.php"`
Expected: PASS, all tests (each file's original tests plus the one new regression test each)

If any of these unexpectedly FAIL with a Carbon-object or non-scalar-leaf message: that means the "clean" finding for that specific file was wrong — fix the flagged line the same way `GetEmployeeFullProfileTool`/`GetStudentFullProfileTool` were fixed (replace the raw property access with `?->format('Y-m-d')` / `?->toDateTimeString()` matching the field's real cast), then re-run.

- [ ] **Step 16: Commit**

```bash
git add tests/Feature/Atlas/Dyna/GetClassRecordComplianceToolTest.php tests/Feature/Atlas/Dyna/GetClassScheduleToolTest.php tests/Feature/Atlas/Dyna/GetCompetitionsStatsToolTest.php tests/Feature/Atlas/Dyna/GetDisciplineCaseStatsToolTest.php tests/Feature/Atlas/Dyna/GetEmployeeInfoToolTest.php tests/Feature/Atlas/Dyna/GetEnrollmentStatusBreakdownToolTest.php tests/Feature/Atlas/Dyna/GetFacultyLoadDistributionToolTest.php tests/Feature/Atlas/Dyna/GetGateAttendanceTrendToolTest.php tests/Feature/Atlas/Dyna/GetHeadcountToolTest.php tests/Feature/Atlas/Dyna/GetHomeroomAttendanceSummaryToolTest.php tests/Feature/Atlas/Dyna/GetLeaveTrendsToolTest.php tests/Feature/Atlas/Dyna/GetLibraryStatsToolTest.php tests/Feature/Atlas/Dyna/GetStudentInfoToolTest.php tests/Feature/Atlas/Dyna/GetTeacherAttendanceStatsToolTest.php
git commit -m "test(dyna): add JSON-safety regression coverage to the 14 previously-unswept tools"
```

---

### Task 5: Add JSON-safety regression coverage for the 9 dashboard-adapter tools

**Files:**
- Modify: `tests/Feature/Atlas/Dyna/ExecutiveDashboardAdapterToolsTest.php`

**Interfaces:**
- Consumes: `Tests\Feature\Atlas\Dyna\Concerns\AssertsJsonSafeToolResult` from Task 3.

The 9 `ExecutiveDashboardAdapterTool` subclasses share one `execute()` implementation (in the base class) that already round-trips through `json_decode(json_encode(...))`, so they're structurally safe today — but a single test here still closes the "every Dyna tool has this guard" gap cheaply, and would catch it if that round-trip is ever refactored away.

- [ ] **Step 1: Add the imports**

Add to the top of `tests/Feature/Atlas/Dyna/ExecutiveDashboardAdapterToolsTest.php`, alongside the existing `use` statements:

```php
use App\Services\Atlas\Dyna\Tools\GetAcademicsStatsTool;
use App\Services\Atlas\Dyna\Tools\GetFinanceStatsTool;
use App\Services\Atlas\Dyna\Tools\GetOperationsStatsTool;
use App\Services\Atlas\Dyna\Tools\GetRecruitmentStatsTool;
use App\Services\Atlas\Dyna\Tools\GetRequestsStatsTool;
use App\Services\ExecutiveDashboardService;
use Tests\Feature\Atlas\Dyna\Concerns\AssertsJsonSafeToolResult;
```

(`GetAttentionItemsTool`, `GetDivisionScorecardTool`, `GetPerformanceStatsTool`, `GetSatisfactionStatsTool` are already imported in this file.)

- [ ] **Step 2: Add the trait to the class**

Find:

```php
class ExecutiveDashboardAdapterToolsTest extends TestCase
{
    use RefreshDatabase;
```

Change to:

```php
class ExecutiveDashboardAdapterToolsTest extends TestCase
{
    use AssertsJsonSafeToolResult;
    use RefreshDatabase;
```

- [ ] **Step 3: Add the test**

Add inside the class (this file already has a `userWithRole()` helper used by its existing tests — reuse it):

```php
    public function test_all_dashboard_adapter_results_contain_no_non_scalar_leaked_date_objects(): void
    {
        $administrator = $this->userWithRole('Administrator');
        $dashboard = app(ExecutiveDashboardService::class);

        $tools = [
            new GetPerformanceStatsTool($dashboard),
            new GetRequestsStatsTool($dashboard),
            new GetSatisfactionStatsTool($dashboard),
            new GetAcademicsStatsTool($dashboard),
            new GetRecruitmentStatsTool($dashboard),
            new GetFinanceStatsTool($dashboard),
            new GetOperationsStatsTool($dashboard),
            new GetAttentionItemsTool($dashboard),
            new GetDivisionScorecardTool($dashboard),
        ];

        foreach ($tools as $tool) {
            $result = $tool->execute($administrator, []);
            $this->assertNoNonScalarLeaves($result, get_class($tool));
        }
    }
```

- [ ] **Step 4: Run it**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit tests/Feature/Atlas/Dyna/ExecutiveDashboardAdapterToolsTest.php"`
Expected: PASS, all tests (the existing ones plus the new one)

- [ ] **Step 5: Commit**

```bash
git add tests/Feature/Atlas/Dyna/ExecutiveDashboardAdapterToolsTest.php
git commit -m "test(dyna): add JSON-safety regression coverage for the 9 dashboard-adapter tools"
```

---

### Task 6: Rewrite the Dyna system prompt for conversational tone and tool-error handling

**Files:**
- Modify: `app/Services/Atlas/Dyna/DynaOrchestratorService.php:11-17`
- Test: `tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: nothing new — `SYSTEM_PROMPT` content change only.

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php`, inside the class:

```php
    public function test_system_prompt_instructs_conversational_tone_and_tool_error_handling(): void
    {
        $user = User::factory()->create();
        $conversation = DynaConversation::create(['user_id' => $user->id]);
        $registry = new DynaToolRegistry([]);

        $capturedArgs = null;

        $bedrock = Mockery::mock(BedrockRuntimeClient::class);
        $bedrock->shouldReceive('converse')
            ->once()
            ->andReturnUsing(function (array $args) use (&$capturedArgs) {
                $capturedArgs = $args;

                return new Result([
                    'output' => ['message' => ['role' => 'assistant', 'content' => [
                        ['text' => 'Sure, one moment.'],
                    ]]],
                    'stopReason' => 'end_turn',
                ]);
            });

        $clientFactory = Mockery::mock(DynaBedrockClientFactory::class);
        $clientFactory->shouldReceive('make')->andReturn($bedrock);

        $orchestrator = new DynaOrchestratorService($registry, $clientFactory);

        $orchestrator->reply($user, $conversation, 'Hello');

        $systemText = $capturedArgs['system'][0]['text'];
        $this->assertStringContainsString('error', $systemText);
        $this->assertStringContainsString('briefing a colleague', $systemText);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_system_prompt_instructs_conversational_tone_and_tool_error_handling tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php"`
Expected: FAIL — the current `SYSTEM_PROMPT` contains neither "error" (as tool-result guidance) nor "briefing a colleague".

- [ ] **Step 3: Implement**

In `app/Services/Atlas/Dyna/DynaOrchestratorService.php`, replace:

```php
    private const SYSTEM_PROMPT = <<<'TEXT'
        You are Dyna, an analytics and insights assistant for Atlas, the campus management
        system for Philippine Science High School - Caraga Region Campus. You answer questions
        for the Campus Director and Division Chiefs (MANCOM) using the tools available to you.
        Always call a tool to get real data before stating any number — never estimate or
        invent statistics. If no tool can answer the question, say so plainly.
        TEXT;
```

with:

```php
    private const SYSTEM_PROMPT = <<<'TEXT'
        You are Dyna, an analytics and insights assistant for Atlas, the campus management
        system for Philippine Science High School - Caraga Region Campus. You answer questions
        for the Campus Director and Division Chiefs (MANCOM) using the tools available to you.

        Always call a tool to get real data before stating any number — never estimate or
        invent statistics. If no tool can answer the question, say so plainly, and if it
        would help, say what information you'd need to answer it (a date range, a name, a
        school year) instead of just refusing.

        If a tool result contains an "error" key, tell the user plainly that this specific
        piece of data could not be retrieved right now — do not treat it as if no such data
        exists, and do not silently leave it out of your answer.

        Write like you're briefing a colleague, not printing a report: use natural prose, and
        when a question needs more than one tool call, weave the results into one coherent
        answer instead of listing raw numbers back to back.
        TEXT;
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit --filter test_system_prompt_instructs_conversational_tone_and_tool_error_handling tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php"`
Expected: PASS

- [ ] **Step 5: Run the full orchestrator suite to confirm no regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php"`
Expected: PASS, all tests

- [ ] **Step 6: Commit**

```bash
git add app/Services/Atlas/Dyna/DynaOrchestratorService.php tests/Feature/Atlas/Dyna/DynaOrchestratorServiceTest.php
git commit -m "feat(dyna): rewrite system prompt for conversational tone and tool-error handling"
```

---

### Task 7: Full Dyna suite verification

**Files:** none (verification only)

- [ ] **Step 1: Run the entire Dyna test suite**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && vendor/bin/phpunit -d memory_limit=512M --filter Dyna tests/Feature/Atlas/Dyna"`
Expected: PASS, all tests (this project's Dyna suite was 47 tests before this plan; expect 47 + 1 (Task 1) + 2 (Task 2) + 14 (Task 4) + 1 (Task 5) + 1 (Task 6) = 66)

- [ ] **Step 2: PHP-lint every file touched by this plan**

Use the repo's `lint` skill (PHP syntax-check on the modified working tree), or directly:

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && for f in app/Services/Atlas/Dyna/DynaOrchestratorService.php app/Services/Atlas/Dyna/DynaToolRegistry.php tests/Feature/Atlas/Dyna/*.php tests/Feature/Atlas/Dyna/Concerns/*.php; do php -l \"\$f\"; done"`
Expected: `No syntax errors detected` for every file

- [ ] **Step 3: Report status**

No commit for this task — it's verification only. If everything passes, the plan is complete and ready for the standard `junlou` → `main` deploy flow (per `CLAUDE.md`), which is a separate, explicit user decision, not part of this plan.
