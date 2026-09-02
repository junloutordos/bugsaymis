# Research Advisory Submission Requirements — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let a Research Coordinator create submission requirements (deadline + required files) that broadcast to research groups by scope, with an adviser-facing submission flow, coordinator review, and full notification lifecycle.

**Architecture:** New `research_groups` entity fixes the module's implicit (string-matched) grouping so co-advisers share one requirement instance. Requirements fan out to matching groups as `research_requirement_assignments`; advisers submit files (base64→S3, per project convention) creating `research_requirement_submissions` rows; coordinators accept/return. A daily scheduled command drives reminder/overdue notifications, reusing the existing `NotificationService` (bell/push) + a new paired Mailable (email).

**Tech Stack:** Laravel 12 / PHP 8.4, MySQL 8, Vue 3 `<script setup>` + Inertia 2, Tailwind. PHPUnit (class-based, `RefreshDatabase`).

**Spec:** `docs/superpowers/specs/2026-09-02-research-advisory-submission-requirements-design.md`

## Global Constraints

- Files uploaded as base64 JSON (never `multipart/form-data` — Cloudflare WAF blocks it). Decode server-side, `Storage::disk('s3')->put()` — never `disk('public')`.
- Files served only through an authenticated, ownership-checked proxy route — never a direct S3 URL.
- Controllers use `abort_unless($request->user()->hasAnyPermission([...]))`, not `$this->authorize()` (this module's existing convention — `authorize()` doesn't support the pipe/OR syntax route middleware does).
- Migrations are additive only (new tables, nullable FK) — no destructive changes, safe in one blue-green deploy.
- All new Eloquent writes go through `App\Models\FacultyLoading\*` namespace, matching existing module layout.
- Tests: PHPUnit, `RefreshDatabase`, ad-hoc `Role`/`Permission::firstOrCreate()` helpers (see `tests/Feature/FacultyLoading/FacultyLoadingHttpTest.php` `userWith()`) — not the seeded "Research Coordinator" role.
- Run tests via: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=<TestClass>"` (project is in `/Users/junlou/bugsaymis-docker`, dev service name is `php`, not `app`).
- Lint modified PHP files with the `lint` skill (syntax-check) before each commit.

---

## Phase 0 — Foundational: explicit `research_groups` entity

### Task 1: `research_groups` table + model

**Files:**
- Create: `database/migrations/2026_09_03_100000_create_research_groups_table.php`
- Create: `app/Models/FacultyLoading/ResearchGroup.php`
- Test: `tests/Unit/FacultyLoading/ResearchGroupModelTest.php`

**Interfaces:**
- Produces: `ResearchGroup` model with `id, academic_term_id, grade_level, title, research_type, timestamps`; relation `advisories(): HasMany` → `ResearchAdvisory`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\SchoolYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchGroupModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_and_read_research_group(): void
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);

        $group = ResearchGroup::create([
            'academic_term_id' => $term->id,
            'grade_level'      => 10,
            'title'            => 'The Effects of X on Y',
            'research_type'    => 'investigatory',
        ]);

        $this->assertDatabaseHas('research_groups', [
            'id'    => $group->id,
            'title' => 'The Effects of X on Y',
        ]);
        $this->assertSame(10, $group->fresh()->grade_level);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchGroupModelTest"`
Expected: FAIL — table `research_groups` doesn't exist / class not found.

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
        Schema::create('research_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->tinyInteger('grade_level');
            $table->string('title', 500);
            $table->enum('research_type', ['thesis', 'investigatory', 'science_research', 'feasibility'])->nullable();
            $table->timestamps();

            $table->index(['academic_term_id', 'grade_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_groups');
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_03_100000_create_research_groups_table.php"`

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchGroup extends Model
{
    protected $table = 'research_groups';

    protected $fillable = [
        'academic_term_id',
        'grade_level',
        'title',
        'research_type',
    ];

    protected $casts = [
        'grade_level' => 'integer',
    ];

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function advisories(): HasMany
    {
        return $this->hasMany(ResearchAdvisory::class);
    }

    /** True if at least one non-dropped advisory row belongs to this group. */
    public function scopeActive($query)
    {
        return $query->whereHas('advisories', fn ($q) => $q->where('status', '<>', 'dropped'));
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchGroupModelTest"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_03_100000_create_research_groups_table.php app/Models/FacultyLoading/ResearchGroup.php tests/Unit/FacultyLoading/ResearchGroupModelTest.php
git commit -m "feat(research-advisory): add research_groups table and model"
```

---

### Task 2: `research_group_id` on `research_advisories` + relation

**Files:**
- Create: `database/migrations/2026_09_03_100001_add_research_group_id_to_research_advisories_table.php`
- Modify: `app/Models/FacultyLoading/ResearchAdvisory.php`
- Test: `tests/Unit/FacultyLoading/ResearchGroupModelTest.php` (extend)

**Interfaces:**
- Consumes: `ResearchGroup` (Task 1).
- Produces: `ResearchAdvisory::researchGroup(): BelongsTo`, `research_advisories.research_group_id` (nullable FK, `nullOnDelete`).

- [ ] **Step 1: Write the failing test**

Add to `tests/Unit/FacultyLoading/ResearchGroupModelTest.php`:

```php
    public function test_research_advisory_belongs_to_a_research_group(): void
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X', 'research_type' => 'thesis']);

        $faculty = \App\Models\User::factory()->create();
        $advisory = \App\Models\FacultyLoading\ResearchAdvisory::create([
            'user_id' => $faculty->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'research_title' => 'X', 'grade_level' => 10, 'advisory_role' => 'lead',
            'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active',
            'research_group_id' => $group->id,
        ]);

        $this->assertTrue($advisory->researchGroup->is($group));
        $this->assertCount(1, $group->fresh()->advisories);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchGroupModelTest"`
Expected: FAIL — unknown column `research_group_id`.

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
        Schema::table('research_advisories', function (Blueprint $table) {
            $table->foreignId('research_group_id')->nullable()->after('load_assignment_id')
                ->constrained('research_groups')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('research_advisories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('research_group_id');
        });
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_03_100001_add_research_group_id_to_research_advisories_table.php"`

- [ ] **Step 4: Add fillable + relation**

In `app/Models/FacultyLoading/ResearchAdvisory.php`, add `'research_group_id'` to `$fillable` (after `'load_assignment_id'`) and add:

```php
    public function researchGroup(): BelongsTo
    {
        return $this->belongsTo(ResearchGroup::class);
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchGroupModelTest"`
Expected: PASS (both tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_03_100001_add_research_group_id_to_research_advisories_table.php app/Models/FacultyLoading/ResearchAdvisory.php tests/Unit/FacultyLoading/ResearchGroupModelTest.php
git commit -m "feat(research-advisory): link research_advisories to research_groups"
```

---

### Task 3: `ResearchGroupResolver` service

**Files:**
- Create: `app/Services/FacultyLoading/ResearchGroupResolver.php`
- Test: `tests/Unit/FacultyLoading/ResearchGroupResolverTest.php`

**Interfaces:**
- Consumes: `ResearchGroup` (Task 1).
- Produces: `ResearchGroupResolver::resolve(int $academicTermId, int $gradeLevel, string $title, ?string $researchType = null): ResearchGroup` — normalizes title (trim + case-insensitive match), `firstOrCreate`s the group.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Services\FacultyLoading\ResearchGroupResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchGroupResolverTest extends TestCase
{
    use RefreshDatabase;

    private function makeTerm(): AcademicTerm
    {
        $sy = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        return AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
    }

    public function test_creates_group_on_first_resolve(): void
    {
        $term = $this->makeTerm();
        $group = (new ResearchGroupResolver())->resolve($term->id, 10, '  The Effects of X  ', 'thesis');

        $this->assertSame('The Effects of X', $group->title);
        $this->assertSame(10, $group->grade_level);
        $this->assertSame('thesis', $group->research_type);
    }

    public function test_reuses_existing_group_on_case_insensitive_title_match(): void
    {
        $term  = $this->makeTerm();
        $resolver = new ResearchGroupResolver();

        $first  = $resolver->resolve($term->id, 10, 'The Effects of X', 'thesis');
        $second = $resolver->resolve($term->id, 10, '  the effects of x', 'thesis');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, \App\Models\FacultyLoading\ResearchGroup::count());
    }

    public function test_different_grade_level_creates_a_separate_group(): void
    {
        $term  = $this->makeTerm();
        $resolver = new ResearchGroupResolver();

        $g10 = $resolver->resolve($term->id, 10, 'Same Title', 'thesis');
        $g11 = $resolver->resolve($term->id, 11, 'Same Title', 'thesis');

        $this->assertNotSame($g10->id, $g11->id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchGroupResolverTest"`
Expected: FAIL — class `ResearchGroupResolver` not found.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\ResearchGroup;

class ResearchGroupResolver
{
    /**
     * Find or create the ResearchGroup for (term, grade, normalized title).
     * research_type is only set on create — it does not overwrite an
     * existing group's type on a later resolve (a group's canonical type
     * is set by whoever created it first).
     */
    public function resolve(int $academicTermId, int $gradeLevel, string $title, ?string $researchType = null): ResearchGroup
    {
        $normalized = trim($title);

        $existing = ResearchGroup::where('academic_term_id', $academicTermId)
            ->where('grade_level', $gradeLevel)
            ->whereRaw('LOWER(title) = ?', [mb_strtolower($normalized)])
            ->first();

        if ($existing) {
            return $existing;
        }

        return ResearchGroup::create([
            'academic_term_id' => $academicTermId,
            'grade_level'      => $gradeLevel,
            'title'            => $normalized,
            'research_type'    => $researchType,
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchGroupResolverTest"`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/FacultyLoading/ResearchGroupResolver.php tests/Unit/FacultyLoading/ResearchGroupResolverTest.php
git commit -m "feat(research-advisory): add ResearchGroupResolver service"
```

---

### Task 4: Wire resolver into `ResearchAdvisoryController`

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/ResearchAdvisoryController.php` (`store()` L117-185, `update()` L189-246)
- Test: `tests/Feature/FacultyLoading/ResearchAdvisoryGroupLinkingTest.php`

**Interfaces:**
- Consumes: `ResearchGroupResolver::resolve()` (Task 3).
- Produces: every `ResearchAdvisory` created/updated via this controller now has a non-null `research_group_id`; co-advisers sharing the same title+grade+term end up on the same group.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchAdvisoryGroupLinkingTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        $role = Role::create(['name' => 'TestCoordinator_'.uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'faculty_loading.research_advisories'], ['module' => 'FacultyLoading', 'description' => 'x']);
        $role->permissions()->attach($perm->id);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role->id);
        return $user;
    }

    private function makeTerm(): AcademicTerm
    {
        $sy = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        return AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
    }

    public function test_creating_two_co_advised_groups_with_same_title_shares_one_research_group(): void
    {
        $coordinator = $this->coordinator();
        $term = $this->makeTerm();
        $lead = User::factory()->create();
        $co   = User::factory()->create();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-advisories.store'), [
            'user_id' => $lead->id, 'academic_term_id' => $term->id, 'research_title' => 'Shared Title',
            'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0,
        ])->assertSessionHasNoErrors();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-advisories.store'), [
            'user_id' => $co->id, 'academic_term_id' => $term->id, 'research_title' => 'Shared Title',
            'grade_level' => 10, 'advisory_role' => 'co_adviser', 'research_type' => 'thesis', 'load_units' => 0.5,
        ])->assertSessionHasNoErrors();

        $leadRow = ResearchAdvisory::where('user_id', $lead->id)->first();
        $coRow   = ResearchAdvisory::where('user_id', $co->id)->first();

        $this->assertNotNull($leadRow->research_group_id);
        $this->assertSame($leadRow->research_group_id, $coRow->research_group_id);
    }

    public function test_renaming_title_on_update_re_resolves_group(): void
    {
        $coordinator = $this->coordinator();
        $term = $this->makeTerm();
        $lead = User::factory()->create();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-advisories.store'), [
            'user_id' => $lead->id, 'academic_term_id' => $term->id, 'research_title' => 'Original Title',
            'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0,
        ]);
        $advisory = ResearchAdvisory::where('user_id', $lead->id)->first();
        $originalGroupId = $advisory->research_group_id;

        $this->actingAs($coordinator)->put(route('faculty-loading.research-advisories.update', $advisory->id), [
            'research_title' => 'Renamed Title', 'grade_level' => 10, 'advisory_role' => 'lead',
            'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active',
        ])->assertSessionHasNoErrors();

        $advisory->refresh();
        $this->assertNotSame($originalGroupId, $advisory->research_group_id);
        $this->assertSame('Renamed Title', $advisory->researchGroup->title);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchAdvisoryGroupLinkingTest"`
Expected: FAIL — `research_group_id` is null on both assertions.

- [ ] **Step 3: Wire the resolver in**

In `app/Http/Controllers/FacultyLoading/ResearchAdvisoryController.php`:

Add to the constructor and imports:

```php
use App\Services\FacultyLoading\ResearchGroupResolver;
```

```php
    public function __construct(
        private readonly LoadComputationService $loads,
        private readonly ResearchGroupResolver $groups,
    ) {}
```

In `store()`, right before `ResearchAdvisory::create([...])` (L161), add:

```php
        $group = $this->groups->resolve($data['academic_term_id'], $data['grade_level'], $data['research_title'], $data['research_type'] ?? null);
```

and add `'research_group_id' => $group->id,` to the `create([...])` array (alongside `'load_assignment_id' => null,`).

In `update()`, replace:

```php
        $oldGradeLevel = $researchAdvisory->grade_level;
        $researchAdvisory->update($data);
```

with:

```php
        $oldGradeLevel = $researchAdvisory->grade_level;
        $group = $this->groups->resolve($researchAdvisory->academic_term_id, (int) $data['grade_level'], $data['research_title'], $data['research_type'] ?? null);
        $data['research_group_id'] = $group->id;
        $researchAdvisory->update($data);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchAdvisoryGroupLinkingTest"`
Expected: PASS (2 tests)

- [ ] **Step 5: Run full existing Research Advisory suite for regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=FacultyLoadingHttpTest"`
Expected: PASS, no regressions.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/ResearchAdvisoryController.php tests/Feature/FacultyLoading/ResearchAdvisoryGroupLinkingTest.php
git commit -m "feat(research-advisory): resolve research_group_id on advisory create/update"
```

---

### Task 5: `research-groups:backfill` command

**Files:**
- Create: `app/Console/Commands/BackfillResearchGroups.php`
- Test: `tests/Feature/Console/BackfillResearchGroupsTest.php`

**Interfaces:**
- Consumes: `ResearchGroupResolver::resolve()` (Task 3).
- Produces: artisan command `research-groups:backfill {--dry-run}` — populates `research_group_id` on every `ResearchAdvisory` row where it's null, idempotent, single transaction.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Console;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class BackfillResearchGroupsTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfills_group_id_and_dedupes_co_advisers(): void
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $lead = User::factory()->create();
        $co   = User::factory()->create();

        // Directly insert rows bypassing the controller (simulates pre-existing legacy data with no group_id).
        $leadRow = ResearchAdvisory::create(['user_id' => $lead->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'research_title' => 'Legacy Title', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active']);
        $coRow   = ResearchAdvisory::create(['user_id' => $co->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'research_title' => 'legacy title', 'grade_level' => 10, 'advisory_role' => 'co_adviser', 'research_type' => 'thesis', 'load_units' => 0.5, 'status' => 'active']);

        Artisan::call('research-groups:backfill');

        $leadRow->refresh();
        $coRow->refresh();
        $this->assertNotNull($leadRow->research_group_id);
        $this->assertSame($leadRow->research_group_id, $coRow->research_group_id);
        $this->assertSame(1, \App\Models\FacultyLoading\ResearchGroup::count());
    }

    public function test_dry_run_makes_no_changes(): void
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $lead = User::factory()->create();
        ResearchAdvisory::create(['user_id' => $lead->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'research_title' => 'X', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active']);

        Artisan::call('research-groups:backfill', ['--dry-run' => true]);

        $this->assertSame(0, \App\Models\FacultyLoading\ResearchGroup::count());
        $this->assertNull(ResearchAdvisory::first()->research_group_id);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=BackfillResearchGroupsTest"`
Expected: FAIL — command `research-groups:backfill` not found.

- [ ] **Step 3: Write the command**

```php
<?php

namespace App\Console\Commands;

use App\Models\FacultyLoading\ResearchAdvisory;
use App\Services\FacultyLoading\ResearchGroupResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off, idempotent backfill: populate research_advisories.research_group_id
 * for rows created before the research_groups entity existed, deduping
 * co-advisers on the same (term, grade, title) into a single group.
 */
class BackfillResearchGroups extends Command
{
    protected $signature = 'research-groups:backfill {--dry-run : Report matches without writing}';

    protected $description = 'Link legacy research_advisories rows to research_groups by term+grade+title';

    public function handle(ResearchGroupResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = ResearchAdvisory::whereNull('research_group_id')->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to backfill — all research advisories already have a research_group_id.');
            return self::SUCCESS;
        }

        $linked = 0;

        DB::transaction(function () use ($rows, $resolver, $dryRun, &$linked) {
            foreach ($rows as $row) {
                $group = $resolver->resolve($row->academic_term_id, $row->grade_level, $row->research_title, $row->research_type);
                $this->line(($dryRun ? '[dry-run] ' : '')."Advisory #{$row->id} (\"{$row->research_title}\", Grade {$row->grade_level}) → group #{$group->id}");

                if (! $dryRun) {
                    $row->update(['research_group_id' => $group->id]);
                }
                $linked++;
            }

            if ($dryRun) {
                DB::rollBack();
            }
        });

        $this->newLine();
        $this->info("Linked: {$linked} / {$rows->count()}".($dryRun ? ' (dry-run — nothing written)' : ''));

        return self::SUCCESS;
    }
}
```

Note: `DB::rollBack()` inside a `DB::transaction()` closure throws in real MySQL usage once the closure returns normally (Laravel expects the closure to either complete or throw) — replace the dry-run rollback approach with a cleaner one that never opens a transaction for dry-run:

```php
    public function handle(ResearchGroupResolver $resolver): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = ResearchAdvisory::whereNull('research_group_id')->get();

        if ($rows->isEmpty()) {
            $this->info('Nothing to backfill — all research advisories already have a research_group_id.');
            return self::SUCCESS;
        }

        $linked = 0;
        $apply = function () use ($rows, $resolver, $dryRun, &$linked) {
            foreach ($rows as $row) {
                $group = $resolver->resolve($row->academic_term_id, $row->grade_level, $row->research_title, $row->research_type);
                $this->line(($dryRun ? '[dry-run] ' : '')."Advisory #{$row->id} (\"{$row->research_title}\", Grade {$row->grade_level}) → group #{$group->id}");

                if (! $dryRun) {
                    $row->update(['research_group_id' => $group->id]);
                }
                $linked++;
            }
        };

        if ($dryRun) {
            $apply();
        } else {
            DB::transaction($apply);
        }

        $this->newLine();
        $this->info("Linked: {$linked} / {$rows->count()}".($dryRun ? ' (dry-run — nothing written)' : ''));

        return self::SUCCESS;
    }
```

Use this second version as the actual implementation.

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=BackfillResearchGroupsTest"`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/BackfillResearchGroups.php tests/Feature/Console/BackfillResearchGroupsTest.php
git commit -m "feat(research-advisory): add research-groups:backfill command"
```

---

## Phase 1 — Submission requirements (coordinator side)

### Task 6: `research_requirements` table + model

**Files:**
- Create: `database/migrations/2026_09_03_100002_create_research_requirements_table.php`
- Create: `app/Models/FacultyLoading/ResearchRequirement.php`
- Test: `tests/Unit/FacultyLoading/ResearchRequirementModelTest.php`

**Interfaces:**
- Produces: `ResearchRequirement` model — `id, created_by, academic_term_id, title, description, research_type, grade_levels (array cast), accepted_file_types, max_files, due_at, allow_late_submission, status, timestamps`; relations `createdBy(): BelongsTo` (User), `academicTerm(): BelongsTo`, `assignments(): HasMany`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchRequirementModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_requirement_with_grade_levels_array(): void
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $coordinator = User::factory()->create();

        $req = ResearchRequirement::create([
            'created_by'             => $coordinator->id,
            'academic_term_id'       => $term->id,
            'title'                  => 'Chapter 1 Draft',
            'description'            => 'Submit the Introduction chapter.',
            'research_type'          => null,
            'grade_levels'           => [10, 11],
            'accepted_file_types'    => 'pdf,docx',
            'max_files'              => 3,
            'due_at'                 => now()->addDays(14),
            'allow_late_submission'  => false,
            'status'                 => 'active',
        ]);

        $fresh = $req->fresh();
        $this->assertSame([10, 11], $fresh->grade_levels);
        $this->assertFalse($fresh->allow_late_submission);
        $this->assertTrue($fresh->createdBy->is($coordinator));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementModelTest"`
Expected: FAIL — table doesn't exist.

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
        Schema::create('research_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->enum('research_type', ['thesis', 'investigatory', 'science_research', 'feasibility'])->nullable();
            $table->json('grade_levels')->nullable();
            $table->string('accepted_file_types', 255)->nullable();
            $table->tinyInteger('max_files')->default(5);
            $table->dateTime('due_at');
            $table->boolean('allow_late_submission')->default(true);
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();

            $table->index(['academic_term_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_requirements');
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_03_100002_create_research_requirements_table.php"`

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchRequirement extends Model
{
    protected $table = 'research_requirements';

    protected $fillable = [
        'created_by',
        'academic_term_id',
        'title',
        'description',
        'research_type',
        'grade_levels',
        'accepted_file_types',
        'max_files',
        'due_at',
        'allow_late_submission',
        'status',
    ];

    protected $casts = [
        'grade_levels'          => 'array',
        'max_files'             => 'integer',
        'due_at'                => 'datetime',
        'allow_late_submission' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ResearchRequirementAssignment::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementModelTest"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_03_100002_create_research_requirements_table.php app/Models/FacultyLoading/ResearchRequirement.php tests/Unit/FacultyLoading/ResearchRequirementModelTest.php
git commit -m "feat(research-advisory): add research_requirements table and model"
```

---

### Task 7: `research_requirement_assignments` table + model

**Files:**
- Create: `database/migrations/2026_09_03_100003_create_research_requirement_assignments_table.php`
- Create: `app/Models/FacultyLoading/ResearchRequirementAssignment.php`
- Modify: `app/Models/FacultyLoading/ResearchRequirement.php` (already has `assignments()` from Task 6 — no change needed here)
- Test: `tests/Unit/FacultyLoading/ResearchRequirementAssignmentModelTest.php`

**Interfaces:**
- Consumes: `ResearchRequirement` (Task 6), `ResearchGroup` (Task 1).
- Produces: `ResearchRequirementAssignment` — `id, research_requirement_id, research_group_id, status (pending|submitted|accepted|returned), excluded, reminder_sent_at, overdue_notified_at, timestamps`; relations `requirement(): BelongsTo`, `researchGroup(): BelongsTo`, `submissions(): HasMany`. Unique on `(research_requirement_id, research_group_id)`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class ResearchRequirementAssignmentModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequirement(): array
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X', 'research_type' => 'thesis']);
        $coordinator = User::factory()->create();
        $req = ResearchRequirement::create([
            'created_by' => $coordinator->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1',
            'due_at' => now()->addDays(14), 'status' => 'active',
        ]);
        return [$req, $group];
    }

    public function test_can_create_assignment_with_default_pending_status(): void
    {
        [$req, $group] = $this->makeRequirement();

        $assignment = ResearchRequirementAssignment::create([
            'research_requirement_id' => $req->id,
            'research_group_id'       => $group->id,
        ]);

        $this->assertSame('pending', $assignment->fresh()->status);
        $this->assertFalse($assignment->fresh()->excluded);
        $this->assertTrue($assignment->researchGroup->is($group));
        $this->assertTrue($assignment->requirement->is($req));
    }

    public function test_unique_constraint_prevents_duplicate_assignment(): void
    {
        [$req, $group] = $this->makeRequirement();
        ResearchRequirementAssignment::create(['research_requirement_id' => $req->id, 'research_group_id' => $group->id]);

        $this->expectException(QueryException::class);
        ResearchRequirementAssignment::create(['research_requirement_id' => $req->id, 'research_group_id' => $group->id]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementAssignmentModelTest"`
Expected: FAIL — table doesn't exist.

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
        Schema::create('research_requirement_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_requirement_id')->constrained('research_requirements')->cascadeOnDelete();
            $table->foreignId('research_group_id')->constrained('research_groups')->cascadeOnDelete();
            $table->enum('status', ['pending', 'submitted', 'accepted', 'returned'])->default('pending');
            $table->boolean('excluded')->default(false);
            $table->dateTime('reminder_sent_at')->nullable();
            $table->dateTime('overdue_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['research_requirement_id', 'research_group_id'], 'uq_requirement_group');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_requirement_assignments');
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_03_100003_create_research_requirement_assignments_table.php"`

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchRequirementAssignment extends Model
{
    protected $table = 'research_requirement_assignments';

    protected $fillable = [
        'research_requirement_id',
        'research_group_id',
        'status',
        'excluded',
        'reminder_sent_at',
        'overdue_notified_at',
    ];

    protected $casts = [
        'excluded'             => 'boolean',
        'reminder_sent_at'     => 'datetime',
        'overdue_notified_at'  => 'datetime',
    ];

    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ResearchRequirement::class, 'research_requirement_id');
    }

    public function researchGroup(): BelongsTo
    {
        return $this->belongsTo(ResearchGroup::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ResearchRequirementSubmission::class)->latest('submitted_at');
    }

    public function scopeVisible($query)
    {
        return $query->where('excluded', false);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementAssignmentModelTest"`
Expected: PASS (2 tests) — note this test file references `ResearchRequirementSubmission` in the model's `submissions()` relation, which doesn't exist as a class yet; PHP won't error on an unresolved class inside a method body until it's called, so this test still passes. Task 15 creates that class before any code calls `submissions()`.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_03_100003_create_research_requirement_assignments_table.php app/Models/FacultyLoading/ResearchRequirementAssignment.php tests/Unit/FacultyLoading/ResearchRequirementAssignmentModelTest.php
git commit -m "feat(research-advisory): add research_requirement_assignments table and model"
```

---

### Task 8: `RequirementFanoutService`

**Files:**
- Create: `app/Services/FacultyLoading/RequirementFanoutService.php`
- Test: `tests/Unit/FacultyLoading/RequirementFanoutServiceTest.php`

**Interfaces:**
- Consumes: `ResearchRequirement` (Task 6), `ResearchGroup` (Task 1), `ResearchRequirementAssignment` (Task 7).
- Produces:
  - `RequirementFanoutService::fanOut(ResearchRequirement $requirement): Collection<ResearchRequirementAssignment>` — creates a `pending` assignment for every active `ResearchGroup` matching the requirement's `academic_term_id` + (`grade_levels` or all) + (`research_type` or all), skipping ones that already have an assignment. Returns the newly created assignments only.
  - `RequirementFanoutService::matchingGroups(ResearchRequirement $requirement): Collection<ResearchGroup>` — the scope-matching query alone (used by both fan-out and sync).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use App\Services\FacultyLoading\RequirementFanoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementFanoutServiceTest extends TestCase
{
    use RefreshDatabase;

    private AcademicTerm $term;

    protected function setUp(): void
    {
        parent::setUp();
        $sy = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $this->term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
    }

    private function makeActiveGroup(int $gradeLevel, string $researchType, string $title): ResearchGroup
    {
        $group = ResearchGroup::create(['academic_term_id' => $this->term->id, 'grade_level' => $gradeLevel, 'title' => $title, 'research_type' => $researchType]);
        ResearchAdvisory::create([
            'user_id' => User::factory()->create()->id, 'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'research_title' => $title, 'grade_level' => $gradeLevel, 'advisory_role' => 'lead', 'research_type' => $researchType,
            'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $group->id,
        ]);
        return $group;
    }

    public function test_fans_out_to_groups_matching_grade_and_type(): void
    {
        $g10Thesis = $this->makeActiveGroup(10, 'thesis', 'Group A');
        $g11Thesis = $this->makeActiveGroup(11, 'thesis', 'Group B');
        $g10Invest = $this->makeActiveGroup(10, 'investigatory', 'Group C');

        $requirement = ResearchRequirement::create([
            'created_by' => User::factory()->create()->id, 'academic_term_id' => $this->term->id, 'title' => 'Chapter 1',
            'grade_levels' => [10], 'research_type' => 'thesis', 'due_at' => now()->addDays(14), 'status' => 'active',
        ]);

        $created = (new RequirementFanoutService())->fanOut($requirement);

        $this->assertCount(1, $created);
        $this->assertSame($g10Thesis->id, $created->first()->research_group_id);
        $this->assertSame(1, ResearchRequirementAssignment::where('research_requirement_id', $requirement->id)->count());
    }

    public function test_null_scope_matches_all_grades_and_types(): void
    {
        $this->makeActiveGroup(10, 'thesis', 'Group A');
        $this->makeActiveGroup(11, 'investigatory', 'Group B');

        $requirement = ResearchRequirement::create([
            'created_by' => User::factory()->create()->id, 'academic_term_id' => $this->term->id, 'title' => 'Chapter 1',
            'grade_levels' => null, 'research_type' => null, 'due_at' => now()->addDays(14), 'status' => 'active',
        ]);

        $created = (new RequirementFanoutService())->fanOut($requirement);

        $this->assertCount(2, $created);
    }

    public function test_dropped_only_group_is_excluded(): void
    {
        $group = ResearchGroup::create(['academic_term_id' => $this->term->id, 'grade_level' => 10, 'title' => 'Dropped Group', 'research_type' => 'thesis']);
        ResearchAdvisory::create([
            'user_id' => User::factory()->create()->id, 'school_year_id' => $this->term->school_year_id, 'academic_term_id' => $this->term->id,
            'research_title' => 'Dropped Group', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis',
            'load_units' => 1.0, 'status' => 'dropped', 'research_group_id' => $group->id,
        ]);

        $requirement = ResearchRequirement::create([
            'created_by' => User::factory()->create()->id, 'academic_term_id' => $this->term->id, 'title' => 'Chapter 1',
            'grade_levels' => null, 'research_type' => null, 'due_at' => now()->addDays(14), 'status' => 'active',
        ]);

        $created = (new RequirementFanoutService())->fanOut($requirement);

        $this->assertCount(0, $created);
    }

    public function test_running_fan_out_twice_is_idempotent(): void
    {
        $this->makeActiveGroup(10, 'thesis', 'Group A');
        $requirement = ResearchRequirement::create([
            'created_by' => User::factory()->create()->id, 'academic_term_id' => $this->term->id, 'title' => 'Chapter 1',
            'grade_levels' => null, 'research_type' => null, 'due_at' => now()->addDays(14), 'status' => 'active',
        ]);

        $service = new RequirementFanoutService();
        $first  = $service->fanOut($requirement);
        $second = $service->fanOut($requirement);

        $this->assertCount(1, $first);
        $this->assertCount(0, $second); // nothing new to create
        $this->assertSame(1, ResearchRequirementAssignment::count());
    }

    public function test_sync_picks_up_a_group_created_after_the_requirement(): void
    {
        $this->makeActiveGroup(10, 'thesis', 'Group A');
        $requirement = ResearchRequirement::create([
            'created_by' => User::factory()->create()->id, 'academic_term_id' => $this->term->id, 'title' => 'Chapter 1',
            'grade_levels' => null, 'research_type' => null, 'due_at' => now()->addDays(14), 'status' => 'active',
        ]);
        $service = new RequirementFanoutService();
        $service->fanOut($requirement);

        $this->makeActiveGroup(10, 'thesis', 'Group B (new)');
        $second = $service->fanOut($requirement);

        $this->assertCount(1, $second);
        $this->assertSame(2, ResearchRequirementAssignment::count());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=RequirementFanoutServiceTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use Illuminate\Support\Collection;

class RequirementFanoutService
{
    /**
     * Groups that match a requirement's scope: same term, active (has a
     * non-dropped advisory), grade in grade_levels (or all if null),
     * research_type equal (or all if null).
     *
     * @return Collection<int, ResearchGroup>
     */
    public function matchingGroups(ResearchRequirement $requirement): Collection
    {
        return ResearchGroup::query()
            ->where('academic_term_id', $requirement->academic_term_id)
            ->active()
            ->when($requirement->grade_levels, fn ($q) => $q->whereIn('grade_level', $requirement->grade_levels))
            ->when($requirement->research_type, fn ($q) => $q->where('research_type', $requirement->research_type))
            ->get();
    }

    /**
     * Create a pending assignment for every matching group that doesn't
     * already have one for this requirement. Never removes or excludes
     * existing assignments — safe to call repeatedly (fan-out on create,
     * "Sync" action later).
     *
     * @return Collection<int, ResearchRequirementAssignment> newly created assignments only
     */
    public function fanOut(ResearchRequirement $requirement): Collection
    {
        $matching = $this->matchingGroups($requirement);

        $existingGroupIds = ResearchRequirementAssignment::where('research_requirement_id', $requirement->id)
            ->pluck('research_group_id')
            ->all();

        $toCreate = $matching->reject(fn ($group) => in_array($group->id, $existingGroupIds, true));

        return $toCreate->map(fn ($group) => ResearchRequirementAssignment::create([
            'research_requirement_id' => $requirement->id,
            'research_group_id'       => $group->id,
        ]));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=RequirementFanoutServiceTest"`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/FacultyLoading/RequirementFanoutService.php tests/Unit/FacultyLoading/RequirementFanoutServiceTest.php
git commit -m "feat(research-advisory): add RequirementFanoutService"
```

---

### Task 9: `ResearchRequirementController` — `index` + `store`

**Files:**
- Create: `app/Http/Controllers/FacultyLoading/ResearchRequirementController.php`
- Test: `tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php`

**Interfaces:**
- Consumes: `RequirementFanoutService` (Task 8), `ResearchRequirement`/`ResearchRequirementAssignment` (Tasks 6-7).
- Produces: `GET faculty-loading/research-requirements` (Inertia page `FacultyLoading/ResearchRequirements/Index` with `requirements[]` incl. `stats`, `terms[]`, `currentTerm`), `POST faculty-loading/research-requirements` (create + fan-out).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchRequirementHttpTest extends TestCase
{
    use RefreshDatabase;

    private function coordinator(): User
    {
        $role = Role::create(['name' => 'TestCoordinator_'.uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'faculty_loading.research_advisories'], ['module' => 'FacultyLoading', 'description' => 'x']);
        $role->permissions()->attach($perm->id);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role->id);
        return $user;
    }

    private function faculty(): User
    {
        $role = Role::create(['name' => 'TestFaculty_'.uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'faculty_loading.view_own'], ['module' => 'FacultyLoading', 'description' => 'x']);
        $role->permissions()->attach($perm->id);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role->id);
        return $user;
    }

    private function makeTerm(): AcademicTerm
    {
        $sy = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        return AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
    }

    private function makeActiveGroup(AcademicTerm $term, int $gradeLevel, string $researchType, string $title): ResearchGroup
    {
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => $gradeLevel, 'title' => $title, 'research_type' => $researchType]);
        ResearchAdvisory::create([
            'user_id' => User::factory()->create()->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id,
            'research_title' => $title, 'grade_level' => $gradeLevel, 'advisory_role' => 'lead', 'research_type' => $researchType,
            'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $group->id,
        ]);
        return $group;
    }

    public function test_coordinator_can_create_requirement_and_it_fans_out(): void
    {
        $term = $this->makeTerm();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $this->makeActiveGroup($term, 11, 'thesis', 'Group B');

        $response = $this->actingAs($this->coordinator())->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id,
            'title'            => 'Chapter 1 Draft',
            'description'      => 'Submit the Introduction chapter.',
            'research_type'    => 'thesis',
            'grade_levels'     => [10],
            'due_at'           => now()->addDays(14)->toDateTimeString(),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('research_requirements', ['title' => 'Chapter 1 Draft']);
        $requirement = ResearchRequirement::first();
        $this->assertSame(1, $requirement->assignments()->count());
    }

    public function test_faculty_without_permission_cannot_create_requirement(): void
    {
        $term = $this->makeTerm();

        $this->actingAs($this->faculty())->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'X', 'due_at' => now()->addDays(1)->toDateTimeString(),
        ])->assertForbidden();
    }

    public function test_index_reports_compliance_stats_per_requirement(): void
    {
        $term = $this->makeTerm();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $this->makeActiveGroup($term, 10, 'thesis', 'Group B');

        $coordinator = $this->coordinator();
        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);

        $response = $this->actingAs($coordinator)->get(route('faculty-loading.research-requirements.index'));
        $response->assertOk();
        $requirements = $response->viewData('page')['props']['requirements'];
        $this->assertSame(2, $requirements[0]['stats']['total']);
        $this->assertSame(0, $requirements[0]['stats']['compliance_pct']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementHttpTest"`
Expected: FAIL — route `faculty-loading.research-requirements.store` not found.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Services\FacultyLoading\RequirementFanoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ResearchRequirementController extends Controller
{
    private const PERMISSIONS = ['faculty_loading.manage', 'faculty_loading.research_advisories'];

    public function __construct(private readonly RequirementFanoutService $fanout) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);

        $requirements = ResearchRequirement::with(['createdBy:id,name', 'academicTerm.schoolYear'])
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->orderByDesc('due_at')
            ->get()
            ->map(fn ($r) => $this->mapRequirement($r));

        $terms = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        return Inertia::render('FacultyLoading/ResearchRequirements/Index', [
            'requirements' => $requirements,
            'terms'        => $terms,
            'currentTerm'  => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'      => $request->only(['term_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $data = $request->validate([
            'academic_term_id'       => 'required|exists:academic_terms,id',
            'title'                  => 'required|string|max:255',
            'description'            => 'nullable|string|max:5000',
            'research_type'          => ['nullable', Rule::in(['thesis', 'investigatory', 'science_research', 'feasibility'])],
            'grade_levels'           => 'nullable|array',
            'grade_levels.*'         => 'integer|min:7|max:12',
            'accepted_file_types'    => 'nullable|string|max:255',
            'max_files'              => 'nullable|integer|min:1|max:20',
            'due_at'                 => 'required|date',
            'allow_late_submission'  => 'boolean',
        ]);

        $requirement = ResearchRequirement::create(array_merge($data, [
            'created_by'            => $request->user()->id,
            'max_files'             => $data['max_files'] ?? 5,
            'allow_late_submission' => $data['allow_late_submission'] ?? true,
            'status'                => 'active',
        ]));

        $created = $this->fanout->fanOut($requirement);

        return back()->with('success', "Requirement created and assigned to {$created->count()} research group(s).");
    }

    private function mapRequirement(ResearchRequirement $r): array
    {
        $counts = $r->assignments()->visible()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $total  = (int) $counts->sum();
        $accepted = (int) ($counts['accepted'] ?? 0);

        return [
            'id'                    => $r->id,
            'title'                 => $r->title,
            'description'           => $r->description,
            'research_type'         => $r->research_type,
            'grade_levels'          => $r->grade_levels,
            'accepted_file_types'   => $r->accepted_file_types,
            'max_files'             => $r->max_files,
            'due_at'                => $r->due_at->toIso8601String(),
            'allow_late_submission' => $r->allow_late_submission,
            'status'                => $r->status,
            'created_by'            => $r->createdBy ? ['id' => $r->createdBy->id, 'name' => $r->createdBy->name] : null,
            'term'                  => $r->academicTerm ? ['id' => $r->academicTerm->id, 'label' => $r->academicTerm->full_label] : null,
            'stats' => [
                'total'           => $total,
                'pending'         => (int) ($counts['pending'] ?? 0),
                'submitted'       => (int) ($counts['submitted'] ?? 0),
                'accepted'        => $accepted,
                'returned'        => (int) ($counts['returned'] ?? 0),
                'compliance_pct'  => $total > 0 ? (int) round(($accepted / $total) * 100) : 0,
            ],
        ];
    }
}
```

- [ ] **Step 4: Register the route** (temporary, minimal — full route group comes in Task 12; add just enough to pass this test)

In `routes/faculty-loading.php`, add the import `use App\Http\Controllers\FacultyLoading\ResearchRequirementController;` and, directly after the existing `research-advisories` group (L324-333), add:

```php
        Route::middleware('permission:faculty_loading.manage|faculty_loading.research_advisories')
            ->prefix('research-requirements')->name('research-requirements.')->group(function () {
                Route::get('/',  [ResearchRequirementController::class, 'index'])->name('index');
                Route::post('/', [ResearchRequirementController::class, 'store'])->name('store');
            });
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementHttpTest"`
Expected: PASS (3 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/ResearchRequirementController.php routes/faculty-loading.php tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php
git commit -m "feat(research-advisory): add ResearchRequirementController index+store"
```

---

### Task 10: `ResearchRequirementController` — `show` + `update` + `archive`

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/ResearchRequirementController.php`
- Modify: `routes/faculty-loading.php`
- Modify: `tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php` (append)

**Interfaces:**
- Produces: `GET .../{researchRequirement}` (Inertia page `Show` with `requirement`, `assignments[]`), `PUT .../{researchRequirement}` (metadata-only edit — never changes `academic_term_id`/`grade_levels`/`research_type`, which would silently orphan the scope semantics of existing assignments), `DELETE .../{researchRequirement}` (soft archive, `status = archived`).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php`:

```php
    public function test_show_returns_assignment_grid_with_group_and_advisers(): void
    {
        $term = $this->makeTerm();
        $group = $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $requirement = ResearchRequirement::first();

        $response = $this->actingAs($coordinator)->get(route('faculty-loading.research-requirements.show', $requirement->id));
        $response->assertOk();
        $assignments = $response->viewData('page')['props']['assignments'];
        $this->assertCount(1, $assignments);
        $this->assertSame($group->id, $assignments[0]['research_group']['id']);
        $this->assertCount(1, $assignments[0]['research_group']['advisers']);
    }

    public function test_update_edits_metadata_without_re_fanning_out(): void
    {
        $term = $this->makeTerm();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $requirement = ResearchRequirement::first();

        $this->actingAs($coordinator)->put(route('faculty-loading.research-requirements.update', $requirement->id), [
            'title' => 'Chapter 1 (Revised)', 'due_at' => now()->addDays(21)->toDateTimeString(), 'allow_late_submission' => false,
        ])->assertSessionHasNoErrors();

        $requirement->refresh();
        $this->assertSame('Chapter 1 (Revised)', $requirement->title);
        $this->assertFalse($requirement->allow_late_submission);
        $this->assertSame(1, $requirement->assignments()->count());
    }

    public function test_archive_sets_status_archived(): void
    {
        $term = $this->makeTerm();
        $coordinator = $this->coordinator();
        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $requirement = ResearchRequirement::first();

        $this->actingAs($coordinator)->delete(route('faculty-loading.research-requirements.archive', $requirement->id))
            ->assertSessionHasNoErrors();

        $this->assertSame('archived', $requirement->fresh()->status);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementHttpTest"`
Expected: FAIL — `show`/`update`/`archive` methods and routes don't exist.

- [ ] **Step 3: Add the controller methods**

Add imports `use App\Models\FacultyLoading\ResearchRequirementAssignment;` and add to `ResearchRequirementController`:

```php
    public function show(Request $request, ResearchRequirement $researchRequirement): Response
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $researchRequirement->load(['createdBy:id,name', 'academicTerm.schoolYear']);

        $assignments = $researchRequirement->assignments()
            ->with(['researchGroup.advisories.faculty:id,name'])
            ->get()
            ->map(fn ($a) => $this->mapAssignment($a));

        return Inertia::render('FacultyLoading/ResearchRequirements/Show', [
            'requirement'  => $this->mapRequirement($researchRequirement),
            'assignments'  => $assignments,
        ]);
    }

    public function update(Request $request, ResearchRequirement $researchRequirement): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $data = $request->validate([
            'title'                  => 'required|string|max:255',
            'description'            => 'nullable|string|max:5000',
            'accepted_file_types'    => 'nullable|string|max:255',
            'max_files'              => 'nullable|integer|min:1|max:20',
            'due_at'                 => 'required|date',
            'allow_late_submission'  => 'boolean',
        ]);

        $researchRequirement->update($data);

        return back()->with('success', 'Requirement updated.');
    }

    public function archive(Request $request, ResearchRequirement $researchRequirement): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $researchRequirement->update(['status' => 'archived']);

        return back()->with('success', 'Requirement archived.');
    }

    private function mapAssignment(ResearchRequirementAssignment $a): array
    {
        // Note: this deliberately does not touch $a->submissions yet — the
        // ResearchRequirementSubmission model doesn't exist until Task 15.
        // Task 16b adds the latest-submission fields once it does.
        return [
            'id'             => $a->id,
            'status'         => $a->status,
            'excluded'       => $a->excluded,
            'research_group' => [
                'id'          => $a->researchGroup->id,
                'title'       => $a->researchGroup->title,
                'grade_level' => $a->researchGroup->grade_level,
                'advisers'    => $a->researchGroup->advisories->map(fn ($adv) => [
                    'id' => $adv->faculty->id, 'name' => $adv->faculty->name, 'role' => $adv->advisory_role,
                ])->values()->all(),
            ],
        ];
    }
```

- [ ] **Step 4: Register the routes**

In `routes/faculty-loading.php`, replace the two-route `research-requirements` group from Task 9 with:

```php
        Route::middleware('permission:faculty_loading.manage|faculty_loading.research_advisories')
            ->prefix('research-requirements')->name('research-requirements.')->group(function () {
                Route::get('/',                     [ResearchRequirementController::class, 'index'])->name('index');
                Route::post('/',                    [ResearchRequirementController::class, 'store'])->name('store');
                Route::get('/{researchRequirement}',    [ResearchRequirementController::class, 'show'])->name('show');
                Route::put('/{researchRequirement}',    [ResearchRequirementController::class, 'update'])->name('update');
                Route::delete('/{researchRequirement}', [ResearchRequirementController::class, 'archive'])->name('archive');
            });
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementHttpTest"`
Expected: PASS (6 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/ResearchRequirementController.php routes/faculty-loading.php tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php
git commit -m "feat(research-advisory): add show/update/archive to ResearchRequirementController"
```

---

### Task 11: Sync + exception assignments (add / exclude) + groups picker

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/ResearchRequirementController.php`
- Modify: `routes/faculty-loading.php`
- Modify: `tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php` (append)

**Interfaces:**
- Produces: `POST .../{researchRequirement}/sync`, `POST .../{researchRequirement}/assignments` (add exception group), `PATCH .../assignments/{assignment}/toggle-exclude`, `GET .../research-groups?term_id=` (JSON picker of active groups for the "add exception" UI).

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php`:

```php
    public function test_sync_picks_up_a_group_created_after_the_requirement(): void
    {
        $term = $this->makeTerm();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $requirement = ResearchRequirement::first();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group B (new)');

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.sync', $requirement->id))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, $requirement->assignments()->count());
    }

    public function test_can_add_an_out_of_scope_group_as_an_exception(): void
    {
        $term = $this->makeTerm();
        $coordinator = $this->coordinator();
        $outOfScope = $this->makeActiveGroup($term, 12, 'feasibility', 'Exception Group'); // grade 12 + feasibility matches neither the grade_levels:[10] nor research_type:thesis scope below

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $requirement = ResearchRequirement::first();
        $this->assertSame(0, $requirement->assignments()->count());

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.assignments.store', $requirement->id), [
            'research_group_id' => $outOfScope->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $requirement->assignments()->count());
    }

    public function test_can_toggle_exclude_on_an_assignment(): void
    {
        $term = $this->makeTerm();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $assignment = ResearchRequirement::first()->assignments()->first();

        $this->actingAs($coordinator)->patch(route('faculty-loading.research-requirements.assignments.toggle-exclude', $assignment->id))
            ->assertSessionHasNoErrors();
        $this->assertTrue($assignment->fresh()->excluded);

        $this->actingAs($coordinator)->patch(route('faculty-loading.research-requirements.assignments.toggle-exclude', $assignment->id))
            ->assertSessionHasNoErrors();
        $this->assertFalse($assignment->fresh()->excluded);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementHttpTest"`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Add the controller methods**

```php
    public function sync(Request $request, ResearchRequirement $researchRequirement): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $created = $this->fanout->fanOut($researchRequirement);

        return back()->with('success', "{$created->count()} new research group(s) added.");
    }

    public function addAssignment(Request $request, ResearchRequirement $researchRequirement): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $data = $request->validate(['research_group_id' => 'required|exists:research_groups,id']);

        $assignment = ResearchRequirementAssignment::firstOrNew([
            'research_requirement_id' => $researchRequirement->id,
            'research_group_id'       => $data['research_group_id'],
        ]);

        if ($assignment->exists) {
            $assignment->update(['excluded' => false]);
        } else {
            $assignment->status   = 'pending';
            $assignment->excluded = false;
            $assignment->save();
        }

        return back()->with('success', 'Research group added to requirement.');
    }

    public function toggleExcludeAssignment(Request $request, ResearchRequirementAssignment $assignment): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $assignment->update(['excluded' => ! $assignment->excluded]);

        return back()->with('success', $assignment->excluded ? 'Group excluded from requirement.' : 'Group re-included.');
    }

    public function groupsForTerm(Request $request): \Illuminate\Http\JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $request->validate(['term_id' => 'required|exists:academic_terms,id']);

        $groups = \App\Models\FacultyLoading\ResearchGroup::where('academic_term_id', $request->term_id)
            ->active()
            ->orderBy('title')
            ->get(['id', 'title', 'grade_level', 'research_type']);

        return response()->json($groups);
    }
```

- [ ] **Step 4: Register the routes**

In `routes/faculty-loading.php`, expand the `research-requirements` group:

```php
        Route::middleware('permission:faculty_loading.manage|faculty_loading.research_advisories')
            ->prefix('research-requirements')->name('research-requirements.')->group(function () {
                Route::get('/',                     [ResearchRequirementController::class, 'index'])->name('index');
                Route::get('/research-groups',      [ResearchRequirementController::class, 'groupsForTerm'])->name('groups');
                Route::post('/',                    [ResearchRequirementController::class, 'store'])->name('store');
                Route::get('/{researchRequirement}',    [ResearchRequirementController::class, 'show'])->name('show');
                Route::put('/{researchRequirement}',    [ResearchRequirementController::class, 'update'])->name('update');
                Route::delete('/{researchRequirement}', [ResearchRequirementController::class, 'archive'])->name('archive');
                Route::post('/{researchRequirement}/sync', [ResearchRequirementController::class, 'sync'])->name('sync');
                Route::post('/{researchRequirement}/assignments', [ResearchRequirementController::class, 'addAssignment'])->name('assignments.store');
                Route::patch('/assignments/{assignment}/toggle-exclude', [ResearchRequirementController::class, 'toggleExcludeAssignment'])->name('assignments.toggle-exclude');
            });
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementHttpTest"`
Expected: PASS (9 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/ResearchRequirementController.php routes/faculty-loading.php tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php
git commit -m "feat(research-advisory): add sync + exception assignment management"
```

---

### Task 12: Sidebar nav entry for Research Requirements

**Files:**
- Modify: `resources/js/Layouts/navigation.js`

**Interfaces:**
- Produces: a new nav item, same gating as the existing "Research Advisories" entry.

- [ ] **Step 1: Add the nav entry**

In `resources/js/Layouts/navigation.js`, immediately after the existing "Research Advisories" entry (L1622-1629), add:

```js
      {
        label: "Research Requirements",
        routeName: "faculty-loading.research-requirements.index",
        href: route("faculty-loading.research-requirements.index"),
        icon: ClipboardDocumentCheckIcon,
        roles: [],
        permissions: ["faculty_loading.manage", "faculty_loading.research_advisories"],
      },
```

If `ClipboardDocumentCheckIcon` is not already imported at the top of the file, add it to the existing `@heroicons/vue/24/outline` import list.

- [ ] **Step 2: Verify the entry was added**

Run: `grep -n "Research Requirements" resources/js/Layouts/navigation.js`
Expected: one match, the new entry.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Layouts/navigation.js
git commit -m "feat(research-advisory): add Research Requirements sidebar entry"
```

---

### Task 13: Frontend — `ResearchRequirements/Index.vue`

**Files:**
- Create: `resources/js/Pages/FacultyLoading/ResearchRequirements/Index.vue`

**Interfaces:**
- Consumes: Inertia props from `ResearchRequirementController::index` (Task 9) — `requirements[]` (each with `stats`), `terms[]`, `currentTerm`, `filters`.
- Consumes routes: `faculty-loading.research-requirements.{index,store,show,archive}` (Tasks 9-10).

- [ ] **Step 1: Write the component**

```vue
<script setup>
import { ref, computed, watch } from 'vue'
import { Head, usePage, useForm, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import AppInput from '@/Components/AppInput.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { confirmDelete } from '@/Composables/useConfirm.js'
import { PlusIcon, EyeIcon, ArchiveBoxIcon, ClipboardDocumentCheckIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    requirements: Array,
    terms:        Array,
    currentTerm:  Object,
    filters:      Object,
})

const page  = usePage()
const flash = computed(() => page.props.flash ?? {})

const termId = ref(props.filters.term_id ?? props.currentTerm?.id ?? '')
function applyFilters() {
    window.location.href = route('faculty-loading.research-requirements.index') + (termId.value ? `?term_id=${termId.value}` : '')
}
watch(termId, applyFilters)

const GRADE_LEVELS = [7, 8, 9, 10, 11, 12]
const RESEARCH_TYPES = [
    { value: '', label: 'All Types' },
    { value: 'thesis', label: 'Thesis' },
    { value: 'investigatory', label: 'Investigatory' },
    { value: 'science_research', label: 'Science Research' },
    { value: 'feasibility', label: 'Feasibility' },
]

const showModal = ref(false)
const form = useForm({
    academic_term_id: '', title: '', description: '', research_type: '',
    grade_levels: [], accepted_file_types: '', max_files: 5,
    due_at: '', allow_late_submission: true,
})

function openCreate() {
    form.reset()
    form.academic_term_id = termId.value || props.currentTerm?.id || ''
    form.grade_levels = []
    form.max_files = 5
    form.allow_late_submission = true
    showModal.value = true
}
function closeModal() { showModal.value = false }

function toggleGrade(g) {
    const idx = form.grade_levels.indexOf(g)
    if (idx === -1) form.grade_levels.push(g)
    else form.grade_levels.splice(idx, 1)
}

function submit() {
    form.post(route('faculty-loading.research-requirements.store'), {
        preserveScroll: true, onSuccess: closeModal,
    })
}

const archiveForm = useForm({})
async function archive(req) {
    if (! await confirmDelete(`Archive requirement "${req.title}"? Advisers will no longer see it.`)) return
    archiveForm.delete(route('faculty-loading.research-requirements.archive', req.id), { preserveScroll: true })
}

const statusBadge = { active: 'green', archived: 'slate' }
function dueLabel(iso) {
    return new Date(iso).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
}
</script>

<template>
    <Head title="Research Requirements" />
    <AdminLayout title="Research Requirements">
        <div class="space-y-5">

            <AppPageHeader title="Research Requirements" subtitle="Set submission deadlines and required files for research groups.">
                <template #actions>
                    <AppButton @click="openCreate">
                        <PlusIcon class="h-4 w-4" /> New Requirement
                    </AppButton>
                </template>
            </AppPageHeader>

            <div v-if="flash.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">{{ flash.success }}</div>
            <div v-if="flash.error"   class="bg-danger-50 border border-danger-100 text-danger-600 rounded-lg px-4 py-3 text-sm">{{ flash.error }}</div>

            <AppFilterBar>
                <select v-model="termId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Terms</option>
                    <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}</option>
                </select>
            </AppFilterBar>

            <AppTable :is-empty="requirements.length === 0" :skeleton-cols="6">
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Title</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Due</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Scope</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Compliance</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Status</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider whitespace-nowrap">Actions</th>
                    </tr>
                </template>
                <template v-for="r in requirements" :key="r.id">
                    <tr class="hover:bg-slate-50/50">
                        <td class="px-4 py-3">
                            <p class="font-medium text-slate-800">{{ r.title }}</p>
                            <p class="text-xs text-slate-500">{{ r.term?.label }}</p>
                        </td>
                        <td class="px-4 py-3 text-center text-slate-600">{{ dueLabel(r.due_at) }}</td>
                        <td class="px-4 py-3 text-center text-xs text-slate-600">
                            {{ r.grade_levels?.length ? 'Grade ' + r.grade_levels.join(', ') : 'All Grades' }}
                            <span v-if="r.research_type"> · {{ r.research_type }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-2">
                                <div class="w-20 h-1.5 rounded-full bg-slate-100 overflow-hidden">
                                    <div class="h-full bg-indigo-500" :style="{ width: r.stats.compliance_pct + '%' }" />
                                </div>
                                <span class="text-xs text-slate-600">{{ r.stats.compliance_pct }}%</span>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-1">{{ r.stats.accepted }}/{{ r.stats.total }} accepted</p>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <AppBadge :color="statusBadge[r.status] ?? 'slate'" class="capitalize">{{ r.status }}</AppBadge>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <div class="flex items-center justify-center gap-1">
                                <Link :href="route('faculty-loading.research-requirements.show', r.id)">
                                    <AppIconButton label="View"><EyeIcon class="h-4 w-4" /></AppIconButton>
                                </Link>
                                <AppIconButton v-if="r.status === 'active'" label="Archive" variant="danger" @click="archive(r)">
                                    <ArchiveBoxIcon class="h-4 w-4" />
                                </AppIconButton>
                            </div>
                        </td>
                    </tr>
                </template>
                <template #empty>
                    <EmptyState title="No submission requirements yet" :icon="ClipboardDocumentCheckIcon" />
                </template>
            </AppTable>
        </div>

        <AppModal :show="showModal" title="New Submission Requirement" size="xl" @close="closeModal">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Term <span class="text-red-500">*</span></label>
                        <select v-model="form.academic_term_id" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                            <option value="">Select term…</option>
                            <option v-for="t in terms" :key="t.id" :value="t.id">{{ t.label }}</option>
                        </select>
                        <p v-if="form.errors.academic_term_id" class="mt-1 text-xs text-danger-500">{{ form.errors.academic_term_id }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Due Date/Time <span class="text-red-500">*</span></label>
                        <input v-model="form.due_at" type="datetime-local" required class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                        <p v-if="form.errors.due_at" class="mt-1 text-xs text-danger-500">{{ form.errors.due_at }}</p>
                    </div>
                </div>

                <AppInput v-model="form.title" label="Title" required maxlength="255" placeholder="e.g. Chapter 1 Draft" :error="form.errors.title" />

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Description / Instructions</label>
                    <textarea v-model="form.description" rows="3" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Research Type</label>
                        <select v-model="form.research_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                            <option v-for="t in RESEARCH_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                        <p class="mt-1 text-[11px] text-slate-400">Leave as "All Types" to target every research type.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-1">Grade Levels</label>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="g in GRADE_LEVELS" :key="g" type="button" @click="toggleGrade(g)"
                                class="px-2.5 py-1 rounded-full text-xs font-medium border"
                                :class="form.grade_levels.includes(g) ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-white border-slate-200 text-slate-600'">
                                G{{ g }}
                            </button>
                        </div>
                        <p class="mt-1 text-[11px] text-slate-400">Leave empty to target all grade levels.</p>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <AppInput v-model="form.accepted_file_types" label="Accepted File Types" placeholder="pdf,docx" />
                    <AppInput v-model.number="form.max_files" type="number" min="1" max="20" label="Max Files" />
                    <div class="flex items-end pb-2">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" v-model="form.allow_late_submission" class="rounded border-slate-300 text-indigo-600" />
                            Allow late submission
                        </label>
                    </div>
                </div>
            </div>

            <template #footer>
                <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
                <AppButton :loading="form.processing" @click="submit">Create Requirement</AppButton>
            </template>
        </AppModal>
    </AdminLayout>
</template>
```

- [ ] **Step 2: Manually verify**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"` (or use the `build` skill), then visit `/faculty-loading/research-requirements` as a coordinator user and confirm the page loads, the create modal opens, and creating a requirement shows up in the list with a compliance bar.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/FacultyLoading/ResearchRequirements/Index.vue
git commit -m "feat(research-advisory): add ResearchRequirements Index.vue"
```

---

### Task 14: Frontend — `ResearchRequirements/Show.vue` (assignment grid + exceptions)

**Files:**
- Create: `resources/js/Pages/FacultyLoading/ResearchRequirements/Show.vue`

**Interfaces:**
- Consumes: Inertia props from `ResearchRequirementController::show` (Task 10) — `requirement`, `assignments[]` (each with `research_group.advisers[]`).
- Consumes routes: `faculty-loading.research-requirements.{sync,assignments.store,assignments.toggle-exclude}` (Task 11), `faculty-loading.research-requirements.groups` (Task 11).
- Note: the "Latest Submission" column and review actions are intentionally NOT in this version — `latest_submission` doesn't exist on the response yet (that needs `ResearchRequirementSubmission`, added in Task 15). Task 16b adds the column; Task 24 adds review actions to it.

- [ ] **Step 1: Write the component**

```vue
<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, useForm } from '@inertiajs/vue3'
import axios from 'axios'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppFilterBar from '@/Components/AppFilterBar.vue'
import AppTable from '@/Components/AppTable.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { ArrowPathIcon, PlusIcon, EyeSlashIcon, EyeIcon, UserGroupIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    requirement: Object,
    assignments: Array,
})

const page  = usePage()
const flash = computed(() => page.props.flash ?? {})

const statusFilter = ref('')
const filteredAssignments = computed(() => {
    if (!statusFilter.value) return props.assignments
    return props.assignments.filter(a => a.status === statusFilter.value)
})

const statusBadge = { pending: 'slate', submitted: 'blue', accepted: 'green', returned: 'red' }

const syncForm = useForm({})
function sync() {
    syncForm.post(route('faculty-loading.research-requirements.sync', props.requirement.id), { preserveScroll: true })
}

const toggleForm = useForm({})
function toggleExclude(assignment) {
    toggleForm.patch(route('faculty-loading.research-requirements.assignments.toggle-exclude', assignment.id), { preserveScroll: true })
}

// ── Add exception group ──────────────────────────────────────────────────────
const showAddModal = ref(false)
const availableGroups = ref([])
const addForm = useForm({ research_group_id: '' })

async function openAddModal() {
    const { data } = await axios.get(route('faculty-loading.research-requirements.groups'), {
        params: { term_id: props.requirement?.term?.id ?? undefined },
    })
    const assignedIds = props.assignments.map(a => a.research_group.id)
    availableGroups.value = data.filter(g => !assignedIds.includes(g.id))
    addForm.reset()
    showAddModal.value = true
}
function submitAdd() {
    addForm.post(route('faculty-loading.research-requirements.assignments.store', props.requirement.id), {
        preserveScroll: true, onSuccess: () => { showAddModal.value = false },
    })
}
</script>

<template>
    <Head :title="requirement.title" />
    <AdminLayout :title="requirement.title">
        <div class="space-y-5">
            <AppPageHeader :title="requirement.title" :subtitle="requirement.description || 'No instructions provided.'">
                <template #actions>
                    <AppButton variant="secondary" :loading="syncForm.processing" @click="sync">
                        <ArrowPathIcon class="h-4 w-4" /> Sync New Groups
                    </AppButton>
                    <AppButton @click="openAddModal">
                        <PlusIcon class="h-4 w-4" /> Add Group
                    </AppButton>
                </template>
            </AppPageHeader>

            <div v-if="flash.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">{{ flash.success }}</div>

            <div class="grid grid-cols-4 gap-3 text-sm">
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-[11px] uppercase text-slate-400">Due</p>
                    <p class="font-medium text-slate-800">{{ new Date(requirement.due_at).toLocaleString('en-PH') }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-[11px] uppercase text-slate-400">Compliance</p>
                    <p class="font-medium text-slate-800">{{ requirement.stats.compliance_pct }}% ({{ requirement.stats.accepted }}/{{ requirement.stats.total }})</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-[11px] uppercase text-slate-400">Accepted File Types</p>
                    <p class="font-medium text-slate-800">{{ requirement.accepted_file_types || 'Any' }}</p>
                </div>
                <div class="rounded-lg border border-slate-200 bg-white p-3">
                    <p class="text-[11px] uppercase text-slate-400">Late Submission</p>
                    <p class="font-medium text-slate-800">{{ requirement.allow_late_submission ? 'Allowed' : 'Blocked after deadline' }}</p>
                </div>
            </div>

            <AppFilterBar>
                <select v-model="statusFilter" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                    <option value="">All Statuses</option>
                    <option value="pending">Pending</option>
                    <option value="submitted">Submitted</option>
                    <option value="accepted">Accepted</option>
                    <option value="returned">Returned</option>
                </select>
            </AppFilterBar>

            <AppTable :is-empty="filteredAssignments.length === 0" :skeleton-cols="4">
                <template #head>
                    <tr>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Research Group</th>
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Adviser(s)</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-4 py-3 text-center text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </template>
                <tr v-for="a in filteredAssignments" :key="a.id" :class="a.excluded ? 'opacity-40' : ''" class="hover:bg-slate-50/50">
                    <td class="px-4 py-3">
                        <p class="font-medium text-slate-800">{{ a.research_group.title }}</p>
                        <p class="text-xs text-slate-500">Grade {{ a.research_group.grade_level }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        <div class="flex items-center gap-1"><UserGroupIcon class="h-3.5 w-3.5 text-slate-400" />
                            {{ a.research_group.advisers.map(x => x.name).join(', ') }}
                        </div>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <AppBadge :color="statusBadge[a.status] ?? 'slate'" class="capitalize">{{ a.status }}</AppBadge>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button @click="toggleExclude(a)" class="text-xs text-slate-500 hover:text-slate-800 inline-flex items-center gap-1">
                            <component :is="a.excluded ? EyeIcon : EyeSlashIcon" class="h-4 w-4" />
                            {{ a.excluded ? 'Re-include' : 'Exclude' }}
                        </button>
                    </td>
                </tr>
                <template #empty>
                    <EmptyState title="No research groups assigned yet" :icon="UserGroupIcon" />
                </template>
            </AppTable>
        </div>

        <AppModal :show="showAddModal" title="Add Research Group" @close="showAddModal = false">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Research Group</label>
                <select v-model="addForm.research_group_id" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full">
                    <option value="">Select group…</option>
                    <option v-for="g in availableGroups" :key="g.id" :value="g.id">{{ g.title }} (Grade {{ g.grade_level }})</option>
                </select>
                <p v-if="availableGroups.length === 0" class="mt-2 text-xs text-slate-400 italic">No additional groups available for this term.</p>
            </div>
            <template #footer>
                <AppButton variant="secondary" @click="showAddModal = false">Cancel</AppButton>
                <AppButton :disabled="!addForm.research_group_id" :loading="addForm.processing" @click="submitAdd">Add</AppButton>
            </template>
        </AppModal>
    </AdminLayout>
</template>
```

- [ ] **Step 2: Manually verify**

Build assets and visit a requirement's Show page as a coordinator; confirm the assignment grid renders, "Sync New Groups" and "Add Group" work, and "Exclude"/"Re-include" toggles the row's opacity and status.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/FacultyLoading/ResearchRequirements/Show.vue
git commit -m "feat(research-advisory): add ResearchRequirements Show.vue"
```

---

## Phase 2 — Adviser submission & review workflow

### Task 15: `research_requirement_submissions` table + model

**Files:**
- Create: `database/migrations/2026_09_03_100004_create_research_requirement_submissions_table.php`
- Create: `app/Models/FacultyLoading/ResearchRequirementSubmission.php`
- Test: `tests/Unit/FacultyLoading/ResearchRequirementSubmissionModelTest.php`

**Interfaces:**
- Consumes: `ResearchRequirementAssignment` (Task 7).
- Produces: `ResearchRequirementSubmission` — `id, research_requirement_assignment_id, submitted_by, notes, submitted_at, is_late, review_status (pending|accepted|returned), review_comment, reviewed_by, reviewed_at, timestamps`; relations `assignment(): BelongsTo`, `submittedBy(): BelongsTo` (User), `reviewedBy(): BelongsTo` (User), `files(): HasMany`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\ResearchRequirementSubmission;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResearchRequirementSubmissionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_submission_with_default_pending_review(): void
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X', 'research_type' => 'thesis']);
        $coordinator = User::factory()->create();
        $adviser     = User::factory()->create();
        $requirement = ResearchRequirement::create(['created_by' => $coordinator->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(14), 'status' => 'active']);
        $assignment  = ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);

        $submission = ResearchRequirementSubmission::create([
            'research_requirement_assignment_id' => $assignment->id,
            'submitted_by' => $adviser->id,
            'notes'        => 'Attached draft.',
            'submitted_at' => now(),
            'is_late'      => false,
        ]);

        $fresh = $submission->fresh();
        $this->assertSame('pending', $fresh->review_status);
        $this->assertTrue($fresh->submittedBy->is($adviser));
        $this->assertTrue($fresh->assignment->is($assignment));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementSubmissionModelTest"`
Expected: FAIL — table doesn't exist.

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
        Schema::create('research_requirement_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_requirement_assignment_id')->constrained('research_requirement_assignments')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->text('notes')->nullable();
            $table->dateTime('submitted_at');
            $table->boolean('is_late')->default(false);
            $table->enum('review_status', ['pending', 'accepted', 'returned'])->default('pending');
            $table->text('review_comment')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['research_requirement_assignment_id', 'submitted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_requirement_submissions');
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_03_100004_create_research_requirement_submissions_table.php"`

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models\FacultyLoading;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchRequirementSubmission extends Model
{
    protected $table = 'research_requirement_submissions';

    protected $fillable = [
        'research_requirement_assignment_id',
        'submitted_by',
        'notes',
        'submitted_at',
        'is_late',
        'review_status',
        'review_comment',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'is_late'      => 'boolean',
        'reviewed_at'  => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ResearchRequirementAssignment::class, 'research_requirement_assignment_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function files(): HasMany
    {
        return $this->hasMany(ResearchRequirementSubmissionFile::class);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementSubmissionModelTest"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_03_100004_create_research_requirement_submissions_table.php app/Models/FacultyLoading/ResearchRequirementSubmission.php tests/Unit/FacultyLoading/ResearchRequirementSubmissionModelTest.php
git commit -m "feat(research-advisory): add research_requirement_submissions table and model"
```

---

### Task 16: `research_requirement_submission_files` table + model

**Files:**
- Create: `database/migrations/2026_09_03_100005_create_research_requirement_submission_files_table.php`
- Create: `app/Models/FacultyLoading/ResearchRequirementSubmissionFile.php`
- Test: `tests/Unit/FacultyLoading/ResearchRequirementSubmissionModelTest.php` (append)

**Interfaces:**
- Consumes: `ResearchRequirementSubmission` (Task 15).
- Produces: `ResearchRequirementSubmissionFile` — `id, research_requirement_submission_id, original_filename, s3_key, mime_type, size_bytes, timestamps`; relation `submission(): BelongsTo`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Unit/FacultyLoading/ResearchRequirementSubmissionModelTest.php`:

```php
    public function test_submission_has_many_files(): void
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X', 'research_type' => 'thesis']);
        $requirement = ResearchRequirement::create(['created_by' => User::factory()->create()->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(14), 'status' => 'active']);
        $assignment  = ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);
        $submission  = ResearchRequirementSubmission::create(['research_requirement_assignment_id' => $assignment->id, 'submitted_by' => User::factory()->create()->id, 'submitted_at' => now(), 'is_late' => false]);

        $file = \App\Models\FacultyLoading\ResearchRequirementSubmissionFile::create([
            'research_requirement_submission_id' => $submission->id,
            'original_filename' => 'chapter1.pdf',
            's3_key'             => 'research-requirements/1/chapter1.pdf',
            'mime_type'          => 'application/pdf',
            'size_bytes'         => 12345,
        ]);

        $this->assertCount(1, $submission->fresh()->files);
        $this->assertTrue($file->submission->is($submission));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementSubmissionModelTest"`
Expected: FAIL — table doesn't exist.

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
        Schema::create('research_requirement_submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_requirement_submission_id')->constrained('research_requirement_submissions')->cascadeOnDelete();
            $table->string('original_filename', 255);
            $table->string('s3_key', 500);
            $table->string('mime_type', 100);
            $table->unsignedInteger('size_bytes');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_requirement_submission_files');
    }
};
```

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_09_03_100005_create_research_requirement_submission_files_table.php"`

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models\FacultyLoading;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchRequirementSubmissionFile extends Model
{
    protected $table = 'research_requirement_submission_files';

    protected $fillable = [
        'research_requirement_submission_id',
        'original_filename',
        's3_key',
        'mime_type',
        'size_bytes',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(ResearchRequirementSubmission::class, 'research_requirement_submission_id');
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementSubmissionModelTest"`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_09_03_100005_create_research_requirement_submission_files_table.php app/Models/FacultyLoading/ResearchRequirementSubmissionFile.php tests/Unit/FacultyLoading/ResearchRequirementSubmissionModelTest.php
git commit -m "feat(research-advisory): add research_requirement_submission_files table and model"
```

---

### Task 16b: Wire `latest_submission` into `ResearchRequirementController::show` + `Show.vue`

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/ResearchRequirementController.php` (`show()`, `mapAssignment()`)
- Modify: `resources/js/Pages/FacultyLoading/ResearchRequirements/Show.vue`
- Modify: `tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php` (append)

**Interfaces:**
- Consumes: `ResearchRequirementSubmission`/`ResearchRequirementSubmissionFile` (Tasks 15-16) — deferred from Task 10 specifically because those models didn't exist yet there.
- Produces: `mapAssignment()` now includes a `latest_submission` key (`null` if none); `Show.vue` gains a "Latest Submission" column.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php`:

```php
    public function test_show_includes_the_latest_submission_for_an_assignment(): void
    {
        $term = $this->makeTerm();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();
        $adviser = User::factory()->create();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $assignment = ResearchRequirement::first()->assignments()->first();
        \App\Models\FacultyLoading\ResearchRequirementSubmission::create([
            'research_requirement_assignment_id' => $assignment->id, 'submitted_by' => $adviser->id,
            'notes' => 'Draft attached.', 'submitted_at' => now(), 'is_late' => false,
        ]);

        $response = $this->actingAs($coordinator)->get(route('faculty-loading.research-requirements.show', ResearchRequirement::first()->id));
        $assignments = $response->viewData('page')['props']['assignments'];

        $this->assertNotNull($assignments[0]['latest_submission']);
        $this->assertSame('Draft attached.', $assignments[0]['latest_submission']['notes']);
        $this->assertSame($adviser->name, $assignments[0]['latest_submission']['submitted_by']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementHttpTest"`
Expected: FAIL — `latest_submission` key missing from the response.

- [ ] **Step 3: Update the controller**

In `show()`, add `'submissions.submittedBy:id,name', 'submissions.files'` to the eager load:

```php
        $assignments = $researchRequirement->assignments()
            ->with(['researchGroup.advisories.faculty:id,name', 'submissions.submittedBy:id,name', 'submissions.files'])
            ->get()
            ->map(fn ($a) => $this->mapAssignment($a));
```

In `mapAssignment()`, restore the `latest_submission` key:

```php
    private function mapAssignment(ResearchRequirementAssignment $a): array
    {
        $latest = $a->submissions->first();

        return [
            'id'             => $a->id,
            'status'         => $a->status,
            'excluded'       => $a->excluded,
            'research_group' => [
                'id'          => $a->researchGroup->id,
                'title'       => $a->researchGroup->title,
                'grade_level' => $a->researchGroup->grade_level,
                'advisers'    => $a->researchGroup->advisories->map(fn ($adv) => [
                    'id' => $adv->faculty->id, 'name' => $adv->faculty->name, 'role' => $adv->advisory_role,
                ])->values()->all(),
            ],
            'latest_submission' => $latest ? [
                'id'             => $latest->id,
                'notes'          => $latest->notes,
                'submitted_at'   => $latest->submitted_at->toIso8601String(),
                'is_late'        => $latest->is_late,
                'review_status'  => $latest->review_status,
                'review_comment' => $latest->review_comment,
                'submitted_by'   => $latest->submittedBy?->name,
                'files'          => $latest->files->map(fn ($f) => ['id' => $f->id, 'name' => $f->original_filename, 'size' => $f->size_bytes])->values()->all(),
            ] : null,
        ];
    }
```

- [ ] **Step 4: Add the column to `Show.vue`**

In `resources/js/Pages/FacultyLoading/ResearchRequirements/Show.vue`, change `:skeleton-cols="4"` to `:skeleton-cols="5"` (accounting for the new column), and add a new `<th>` right after the "Status" header:

```html
                        <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Latest Submission</th>
```

and a matching `<td>` in the row, right after the Status `<td>` and before the Actions `<td>`:

```html
                    <td class="px-4 py-3 text-xs text-slate-600">
                        <template v-if="a.latest_submission">
                            {{ a.latest_submission.submitted_by }} — {{ new Date(a.latest_submission.submitted_at).toLocaleDateString('en-PH') }}
                            <span v-if="a.latest_submission.is_late" class="text-danger-500"> (late)</span>
                            <p v-if="a.latest_submission.notes" class="text-slate-400 italic">"{{ a.latest_submission.notes }}"</p>
                        </template>
                        <span v-else class="text-slate-400 italic">No submission yet</span>
                    </td>
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementHttpTest"`
Expected: PASS (all tests, including the new one)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/ResearchRequirementController.php resources/js/Pages/FacultyLoading/ResearchRequirements/Show.vue tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php
git commit -m "feat(research-advisory): show latest submission on requirement detail page"
```

---

### Task 17: `ResearchSubmissionFileService`

**Files:**
- Create: `app/Services/FacultyLoading/ResearchSubmissionFileService.php`
- Test: `tests/Unit/FacultyLoading/ResearchSubmissionFileServiceTest.php`

**Interfaces:**
- Produces:
  - `ResearchSubmissionFileService::MAX_BYTES` (int, 10MB) and `ALLOWED_MIMES` (array, mime → extension).
  - `decodeAndStore(string $dataUri, string $originalName, ?string $acceptedTypesCsv = null): array{s3_key: string, mime_type: string, size_bytes: int, original_filename: string}` — validates format/MIME/size/(optional) allowed-extension restriction, decodes, stores to `Storage::disk('s3')` under `research-requirements/{uniqid}.{ext}`, throws `Illuminate\Validation\ValidationException` on any failure.
  - `encodeKey(string $s3Key): string` / `decodeKey(string $fileId): ?string` — same `s3.<base64url>` scheme as `WFHService`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\FacultyLoading;

use App\Services\FacultyLoading\ResearchSubmissionFileService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ResearchSubmissionFileServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    private function dataUri(string $mime, string $content): string
    {
        return "data:{$mime};base64," . base64_encode($content);
    }

    public function test_stores_a_valid_pdf_and_returns_metadata(): void
    {
        $service = new ResearchSubmissionFileService();
        $result  = $service->decodeAndStore($this->dataUri('application/pdf', '%PDF-1.4 fake content'), 'chapter1.pdf');

        $this->assertSame('application/pdf', $result['mime_type']);
        $this->assertSame('chapter1.pdf', $result['original_filename']);
        Storage::disk('s3')->assertExists($result['s3_key']);
    }

    public function test_rejects_disallowed_mime_type(): void
    {
        $service = new ResearchSubmissionFileService();
        $this->expectException(ValidationException::class);
        $service->decodeAndStore($this->dataUri('application/x-msdownload', 'MZ...'), 'virus.exe');
    }

    public function test_rejects_file_over_size_cap(): void
    {
        $service = new ResearchSubmissionFileService();
        $big = str_repeat('a', ResearchSubmissionFileService::MAX_BYTES + 1);
        $this->expectException(ValidationException::class);
        $service->decodeAndStore($this->dataUri('application/pdf', $big), 'big.pdf');
    }

    public function test_rejects_malformed_data_uri(): void
    {
        $service = new ResearchSubmissionFileService();
        $this->expectException(ValidationException::class);
        $service->decodeAndStore('not-a-data-uri', 'x.pdf');
    }

    public function test_enforces_requirement_specific_accepted_types(): void
    {
        $service = new ResearchSubmissionFileService();
        $this->expectException(ValidationException::class);
        // pdf is globally allowed, but this requirement only accepts docx.
        $service->decodeAndStore($this->dataUri('application/pdf', 'content'), 'x.pdf', 'docx');
    }

    public function test_encode_decode_key_roundtrip(): void
    {
        $service = new ResearchSubmissionFileService();
        $encoded = $service->encodeKey('research-requirements/abc123.pdf');
        $this->assertStringStartsWith('s3.', $encoded);
        $this->assertSame('research-requirements/abc123.pdf', $service->decodeKey($encoded));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchSubmissionFileServiceTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services\FacultyLoading;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResearchSubmissionFileService
{
    public const MAX_BYTES = 10 * 1024 * 1024; // 10MB, matches Chat module's cap

    // Same whitelist as ChatController — deliberately excludes svg/html/executables.
    public const ALLOWED_MIMES = [
        'application/pdf' => 'pdf',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.ms-excel' => 'xls',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        'application/vnd.ms-powerpoint' => 'ppt',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'application/zip' => 'zip',
    ];

    /**
     * @return array{s3_key: string, mime_type: string, size_bytes: int, original_filename: string}
     */
    public function decodeAndStore(string $dataUri, string $originalName, ?string $acceptedTypesCsv = null): array
    {
        if (! preg_match('/^data:([^;]+);base64,(.+)$/', $dataUri, $m)) {
            throw ValidationException::withMessages(['file' => 'Invalid file format.']);
        }

        $mime = strtolower(trim($m[1]));
        if (! isset(self::ALLOWED_MIMES[$mime])) {
            throw ValidationException::withMessages(['file' => 'That file type is not supported.']);
        }

        $ext = self::ALLOWED_MIMES[$mime];

        if ($acceptedTypesCsv) {
            $allowed = array_map('trim', explode(',', strtolower($acceptedTypesCsv)));
            if (! in_array($ext, $allowed, true)) {
                throw ValidationException::withMessages(['file' => "This requirement only accepts: {$acceptedTypesCsv}."]);
            }
        }

        $binary = base64_decode($m[2], true);
        if ($binary === false) {
            throw ValidationException::withMessages(['file' => 'Invalid file data.']);
        }

        if (strlen($binary) > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'Files must be 10MB or smaller.']);
        }

        $s3Key = 'research-requirements/' . Str::uuid() . '.' . $ext;
        Storage::disk('s3')->put($s3Key, $binary);

        return [
            's3_key'             => $s3Key,
            'mime_type'          => $mime,
            'size_bytes'         => strlen($binary),
            'original_filename'  => $originalName,
        ];
    }

    public function encodeKey(string $s3Key): string
    {
        return 's3.' . rtrim(strtr(base64_encode($s3Key), '+/', '-_'), '=');
    }

    public function decodeKey(string $fileId): ?string
    {
        if (! str_starts_with($fileId, 's3.')) {
            return null;
        }
        $padded = strtr(substr($fileId, 3), '-_', '+/');
        $pad    = strlen($padded) % 4;
        if ($pad) $padded .= str_repeat('=', 4 - $pad);
        $decoded = base64_decode($padded, true);
        return ($decoded !== false) ? $decoded : null;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchSubmissionFileServiceTest"`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/FacultyLoading/ResearchSubmissionFileService.php tests/Unit/FacultyLoading/ResearchSubmissionFileServiceTest.php
git commit -m "feat(research-advisory): add ResearchSubmissionFileService"
```

---

### Task 18: `MyResearchRequirementController::index` (adviser list)

**Files:**
- Create: `app/Http/Controllers/FacultyLoading/MyResearchRequirementController.php`
- Modify: `routes/faculty-loading.php`
- Test: `tests/Feature/FacultyLoading/MyResearchRequirementHttpTest.php`

**Interfaces:**
- Produces: `GET faculty-loading/my-research-requirements` → Inertia page `FacultyLoading/MyResearchRequirements` with `assignments[]` scoped to the current user's own (non-dropped) research groups.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyResearchRequirementHttpTest extends TestCase
{
    use RefreshDatabase;

    private function adviser(): User
    {
        $role = Role::create(['name' => 'TestAdviser_'.uniqid()]);
        $perm = Permission::firstOrCreate(['name' => 'faculty_loading.view_own'], ['module' => 'FacultyLoading', 'description' => 'x']);
        $role->permissions()->attach($perm->id);
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role->id);
        return $user;
    }

    private function makeTerm(): AcademicTerm
    {
        $sy = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        return AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
    }

    public function test_adviser_only_sees_requirements_for_their_own_groups(): void
    {
        $term = $this->makeTerm();
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'My Group', 'research_type' => 'thesis']);
        $otherGroup = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'Other Group', 'research_type' => 'thesis']);

        $mine = $this->adviser();
        ResearchAdvisory::create(['user_id' => $mine->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id, 'research_title' => 'My Group', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $group->id]);
        ResearchAdvisory::create(['user_id' => User::factory()->create()->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id, 'research_title' => 'Other Group', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $otherGroup->id]);

        $requirement = ResearchRequirement::create(['created_by' => User::factory()->create()->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(14), 'status' => 'active']);
        ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);
        ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $otherGroup->id]);

        $response = $this->actingAs($mine)->get(route('faculty-loading.my-research-requirements.index'));
        $response->assertOk();
        $assignments = $response->viewData('page')['props']['assignments'];
        $this->assertCount(1, $assignments);
        $this->assertSame('My Group', $assignments[0]['research_group']['title']);
    }

    public function test_co_adviser_also_sees_the_shared_requirement(): void
    {
        $term = $this->makeTerm();
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'Shared Group', 'research_type' => 'thesis']);
        $lead = $this->adviser();
        $co   = $this->adviser();
        ResearchAdvisory::create(['user_id' => $lead->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id, 'research_title' => 'Shared Group', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $group->id]);
        ResearchAdvisory::create(['user_id' => $co->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id, 'research_title' => 'Shared Group', 'grade_level' => 10, 'advisory_role' => 'co_adviser', 'research_type' => 'thesis', 'load_units' => 0.5, 'status' => 'active', 'research_group_id' => $group->id]);

        $requirement = ResearchRequirement::create(['created_by' => User::factory()->create()->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(14), 'status' => 'active']);
        ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);

        $this->actingAs($co)->get(route('faculty-loading.my-research-requirements.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('assignments', 1));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=MyResearchRequirementHttpTest"`
Expected: FAIL — route not found.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyResearchRequirementController extends Controller
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $myGroupIds = ResearchAdvisory::where('user_id', $userId)
            ->where('status', '<>', 'dropped')
            ->whereNotNull('research_group_id')
            ->pluck('research_group_id');

        $assignments = ResearchRequirementAssignment::visible()
            ->whereIn('research_group_id', $myGroupIds)
            ->whereHas('requirement', fn ($q) => $q->where('status', 'active'))
            ->with(['requirement', 'researchGroup', 'submissions.submittedBy:id,name', 'submissions.files'])
            ->get()
            ->map(fn ($a) => $this->mapMyAssignment($a));

        return Inertia::render('FacultyLoading/MyResearchRequirements', [
            'assignments' => $assignments,
        ]);
    }

    private function mapMyAssignment(ResearchRequirementAssignment $a): array
    {
        $latest = $a->submissions->first();

        return [
            'id'          => $a->id,
            'status'      => $a->status,
            'requirement' => [
                'id'                    => $a->requirement->id,
                'title'                 => $a->requirement->title,
                'description'           => $a->requirement->description,
                'due_at'                => $a->requirement->due_at->toIso8601String(),
                'allow_late_submission' => $a->requirement->allow_late_submission,
                'accepted_file_types'   => $a->requirement->accepted_file_types,
                'max_files'             => $a->requirement->max_files,
            ],
            'research_group' => [
                'id'          => $a->researchGroup->id,
                'title'       => $a->researchGroup->title,
                'grade_level' => $a->researchGroup->grade_level,
            ],
            'latest_submission' => $latest ? [
                'id'             => $latest->id,
                'notes'          => $latest->notes,
                'submitted_at'   => $latest->submitted_at->toIso8601String(),
                'is_late'        => $latest->is_late,
                'review_status'  => $latest->review_status,
                'review_comment' => $latest->review_comment,
                'submitted_by'   => $latest->submittedBy?->name,
                'files'          => $latest->files->map(fn ($f) => ['id' => $f->id, 'name' => $f->original_filename])->values()->all(),
            ] : null,
        ];
    }
}
```

- [ ] **Step 4: Register the route**

Add `use App\Http\Controllers\FacultyLoading\MyResearchRequirementController;` to `routes/faculty-loading.php`, and inside the file's "1. Faculty — view own load" `permission:faculty_loading.view_own` group (L59-63), add:

```php
        Route::prefix('my-research-requirements')->name('my-research-requirements.')->group(function () {
            Route::get('/', [MyResearchRequirementController::class, 'index'])->name('index');
        });
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=MyResearchRequirementHttpTest"`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/MyResearchRequirementController.php routes/faculty-loading.php tests/Feature/FacultyLoading/MyResearchRequirementHttpTest.php
git commit -m "feat(research-advisory): add MyResearchRequirementController index"
```

---

### Task 19: `MyResearchRequirementController::submit`

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/MyResearchRequirementController.php`
- Modify: `routes/faculty-loading.php`
- Modify: `tests/Feature/FacultyLoading/MyResearchRequirementHttpTest.php` (append)

**Interfaces:**
- Consumes: `ResearchSubmissionFileService::decodeAndStore()` (Task 17).
- Produces: `POST faculty-loading/my-research-requirements/{assignment}/submissions` — `{ notes, files: [{ data, name }] }` → creates a `ResearchRequirementSubmission` + `ResearchRequirementSubmissionFile` rows, sets assignment status to `submitted`.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/FacultyLoading/MyResearchRequirementHttpTest.php`:

```php
    private function pdfDataUri(string $content = 'fake pdf content'): string
    {
        return 'data:application/pdf;base64,' . base64_encode($content);
    }

    private function assignmentFor(User $adviser, AcademicTerm $term, array $requirementOverrides = []): ResearchRequirementAssignment
    {
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X'.uniqid(), 'research_type' => 'thesis']);
        ResearchAdvisory::create(['user_id' => $adviser->id, 'school_year_id' => $term->school_year_id, 'academic_term_id' => $term->id, 'research_title' => $group->title, 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $group->id]);
        $requirement = ResearchRequirement::create(array_merge([
            'created_by' => User::factory()->create()->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1',
            'due_at' => now()->addDays(14), 'status' => 'active', 'max_files' => 5,
        ], $requirementOverrides));
        return ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);
    }

    public function test_adviser_can_submit_files_and_notes(): void
    {
        $term = $this->makeTerm();
        $adviser = $this->adviser();
        $assignment = $this->assignmentFor($adviser, $term);

        $response = $this->actingAs($adviser)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'notes' => 'Here is our draft.',
            'files' => [['data' => $this->pdfDataUri(), 'name' => 'chapter1.pdf']],
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('submitted', $assignment->fresh()->status);
        $this->assertDatabaseHas('research_requirement_submissions', ['research_requirement_assignment_id' => $assignment->id, 'notes' => 'Here is our draft.']);
        $this->assertSame(1, \App\Models\FacultyLoading\ResearchRequirementSubmissionFile::count());
    }

    public function test_non_member_cannot_submit_for_a_group_they_do_not_belong_to(): void
    {
        $term = $this->makeTerm();
        $owner  = $this->adviser();
        $intruder = $this->adviser();
        $assignment = $this->assignmentFor($owner, $term);

        $this->actingAs($intruder)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'files' => [['data' => $this->pdfDataUri(), 'name' => 'x.pdf']],
        ])->assertForbidden();
    }

    public function test_late_submission_blocked_when_not_allowed(): void
    {
        $term = $this->makeTerm();
        $adviser = $this->adviser();
        $assignment = $this->assignmentFor($adviser, $term, ['due_at' => now()->subDay(), 'allow_late_submission' => false]);

        $this->actingAs($adviser)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'files' => [['data' => $this->pdfDataUri(), 'name' => 'x.pdf']],
        ])->assertSessionHasErrors('due_at');

        $this->assertSame('pending', $assignment->fresh()->status);
    }

    public function test_returned_assignment_can_be_resubmitted_past_deadline(): void
    {
        $term = $this->makeTerm();
        $adviser = $this->adviser();
        $assignment = $this->assignmentFor($adviser, $term, ['due_at' => now()->subDay(), 'allow_late_submission' => false]);
        $assignment->update(['status' => 'returned']);

        $this->actingAs($adviser)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'files' => [['data' => $this->pdfDataUri(), 'name' => 'x.pdf']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('submitted', $assignment->fresh()->status);
    }

    public function test_exceeding_max_files_is_rejected(): void
    {
        $term = $this->makeTerm();
        $adviser = $this->adviser();
        $assignment = $this->assignmentFor($adviser, $term, ['max_files' => 1]);

        $this->actingAs($adviser)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'files' => [
                ['data' => $this->pdfDataUri('a'), 'name' => 'a.pdf'],
                ['data' => $this->pdfDataUri('b'), 'name' => 'b.pdf'],
            ],
        ])->assertSessionHasErrors('files');
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=MyResearchRequirementHttpTest"`
Expected: FAIL — route/method don't exist.

- [ ] **Step 3: Add the controller method**

Add imports to `MyResearchRequirementController.php`:

```php
use App\Models\FacultyLoading\ResearchRequirementSubmission;
use App\Models\FacultyLoading\ResearchRequirementSubmissionFile;
use App\Services\FacultyLoading\ResearchSubmissionFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
```

Add method:

```php
    public function submit(Request $request, ResearchRequirementAssignment $assignment, ResearchSubmissionFileService $fileService): RedirectResponse
    {
        $userId = $request->user()->id;

        $isMember = ResearchAdvisory::where('user_id', $userId)
            ->where('research_group_id', $assignment->research_group_id)
            ->where('status', '<>', 'dropped')
            ->exists();
        abort_unless($isMember, 403);

        $requirement = $assignment->requirement;

        if ($assignment->status === 'pending' && ! $requirement->allow_late_submission && now()->gt($requirement->due_at)) {
            return back()->withErrors(['due_at' => 'The deadline for this requirement has passed and late submissions are not allowed.']);
        }

        $data = $request->validate([
            'notes'        => 'nullable|string|max:2000',
            'files'        => 'required|array|min:1|max:' . $requirement->max_files,
            'files.*.data' => 'required|string',
            'files.*.name' => 'required|string|max:255',
        ]);

        $stored = [];
        try {
            foreach ($data['files'] as $file) {
                $stored[] = $fileService->decodeAndStore($file['data'], $file['name'], $requirement->accepted_file_types);
            }
        } catch (ValidationException $e) {
            foreach ($stored as $s) {
                Storage::disk('s3')->delete($s['s3_key']);
            }
            throw $e;
        }

        DB::transaction(function () use ($assignment, $userId, $data, $requirement, $stored) {
            $submission = ResearchRequirementSubmission::create([
                'research_requirement_assignment_id' => $assignment->id,
                'submitted_by' => $userId,
                'notes'        => $data['notes'] ?? null,
                'submitted_at' => now(),
                'is_late'      => now()->gt($requirement->due_at),
            ]);

            foreach ($stored as $s) {
                ResearchRequirementSubmissionFile::create(array_merge($s, [
                    'research_requirement_submission_id' => $submission->id,
                ]));
            }

            $assignment->update(['status' => 'submitted']);
        });

        return back()->with('success', 'Submission uploaded.');
    }
```

- [ ] **Step 4: Register the route**

In `routes/faculty-loading.php`, expand the `my-research-requirements` group added in Task 18:

```php
        Route::prefix('my-research-requirements')->name('my-research-requirements.')->group(function () {
            Route::get('/',                        [MyResearchRequirementController::class, 'index'])->name('index');
            Route::post('/{assignment}/submissions', [MyResearchRequirementController::class, 'submit'])->name('submit');
        });
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=MyResearchRequirementHttpTest"`
Expected: PASS (7 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/MyResearchRequirementController.php routes/faculty-loading.php tests/Feature/FacultyLoading/MyResearchRequirementHttpTest.php
git commit -m "feat(research-advisory): add adviser submission endpoint"
```

---

### Task 20: `ResearchRequirementController::review` (accept / return)

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/ResearchRequirementController.php`
- Modify: `routes/faculty-loading.php`
- Modify: `tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php` (append)

**Interfaces:**
- Produces: `POST faculty-loading/research-requirements/submissions/{submission}/review` — `{ decision: accepted|returned, comment? }`. Sets `review_status`/`review_comment`/`reviewed_by`/`reviewed_at` on the submission and mirrors `status` onto the parent assignment. Blocks self-review.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php`:

```php
    public function test_coordinator_can_accept_a_submission(): void
    {
        $term = $this->makeTerm();
        $group = $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $adviser = User::factory()->create();
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $assignment = ResearchRequirement::first()->assignments()->first();
        $submission = \App\Models\FacultyLoading\ResearchRequirementSubmission::create([
            'research_requirement_assignment_id' => $assignment->id, 'submitted_by' => $adviser->id, 'submitted_at' => now(), 'is_late' => false,
        ]);
        $assignment->update(['status' => 'submitted']);

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.submissions.review', $submission->id), [
            'decision' => 'accepted',
        ])->assertSessionHasNoErrors();

        $this->assertSame('accepted', $submission->fresh()->review_status);
        $this->assertSame('accepted', $assignment->fresh()->status);
        $this->assertSame($coordinator->id, $submission->fresh()->reviewed_by);
    }

    public function test_return_for_revision_requires_a_comment(): void
    {
        $term = $this->makeTerm();
        $group = $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $assignment = ResearchRequirement::first()->assignments()->first();
        $submission = \App\Models\FacultyLoading\ResearchRequirementSubmission::create([
            'research_requirement_assignment_id' => $assignment->id, 'submitted_by' => User::factory()->create()->id, 'submitted_at' => now(), 'is_late' => false,
        ]);

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.submissions.review', $submission->id), [
            'decision' => 'returned',
        ])->assertSessionHasErrors('comment');

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.submissions.review', $submission->id), [
            'decision' => 'returned', 'comment' => 'Please expand the literature review.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('returned', $submission->fresh()->review_status);
        $this->assertSame('returned', $assignment->fresh()->status);
    }

    public function test_reviewer_cannot_review_their_own_submission(): void
    {
        $term = $this->makeTerm();
        $group = $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $assignment = ResearchRequirement::first()->assignments()->first();
        $submission = \App\Models\FacultyLoading\ResearchRequirementSubmission::create([
            'research_requirement_assignment_id' => $assignment->id, 'submitted_by' => $coordinator->id, 'submitted_at' => now(), 'is_late' => false,
        ]);

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.submissions.review', $submission->id), [
            'decision' => 'accepted',
        ])->assertForbidden();
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementHttpTest"`
Expected: FAIL — route/method don't exist.

- [ ] **Step 3: Add the controller method**

Add import `use App\Models\FacultyLoading\ResearchRequirementSubmission;` and add to `ResearchRequirementController`:

```php
    public function review(Request $request, ResearchRequirementSubmission $submission): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);
        abort_if($submission->submitted_by === $request->user()->id, 403, 'You cannot review your own submission.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'returned'])],
            'comment'  => 'nullable|string|max:2000|required_if:decision,returned',
        ]);

        $submission->update([
            'review_status'  => $data['decision'],
            'review_comment' => $data['comment'] ?? null,
            'reviewed_by'    => $request->user()->id,
            'reviewed_at'    => now(),
        ]);

        $submission->assignment->update(['status' => $data['decision']]);

        return back()->with('success', $data['decision'] === 'accepted' ? 'Submission accepted.' : 'Submission returned for revision.');
    }
```

- [ ] **Step 4: Register the route**

Add to the `research-requirements` group in `routes/faculty-loading.php`:

```php
                Route::post('/submissions/{submission}/review', [ResearchRequirementController::class, 'review'])->name('submissions.review');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementHttpTest"`
Expected: PASS (12 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/ResearchRequirementController.php routes/faculty-loading.php tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php
git commit -m "feat(research-advisory): add coordinator review (accept/return) endpoint"
```

---

### Task 21: File proxy route

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/MyResearchRequirementController.php`
- Modify: `routes/faculty-loading.php`
- Modify: `tests/Feature/FacultyLoading/MyResearchRequirementHttpTest.php` (append)

**Interfaces:**
- Consumes: `ResearchSubmissionFileService::decodeKey()` (Task 17).
- Produces: `GET faculty-loading/research-requirements/files/{fileId}` — streams the file if the requester is a coordinator or a member of the owning research group; 403/404 otherwise.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/FacultyLoading/MyResearchRequirementHttpTest.php`:

```php
    public function test_group_member_can_download_their_own_submitted_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        $term = $this->makeTerm();
        $adviser = $this->adviser();
        $assignment = $this->assignmentFor($adviser, $term);

        $this->actingAs($adviser)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'files' => [['data' => $this->pdfDataUri(), 'name' => 'chapter1.pdf']],
        ]);
        $file = \App\Models\FacultyLoading\ResearchRequirementSubmissionFile::first();
        $fileId = (new \App\Services\FacultyLoading\ResearchSubmissionFileService())->encodeKey($file->s3_key);

        $this->actingAs($adviser)->get(route('faculty-loading.research-requirements.files.show', $fileId))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_unrelated_faculty_cannot_download_someone_elses_submitted_file(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        $term = $this->makeTerm();
        $owner = $this->adviser();
        $stranger = $this->adviser();
        $assignment = $this->assignmentFor($owner, $term);

        $this->actingAs($owner)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'files' => [['data' => $this->pdfDataUri(), 'name' => 'chapter1.pdf']],
        ]);
        $file = \App\Models\FacultyLoading\ResearchRequirementSubmissionFile::first();
        $fileId = (new \App\Services\FacultyLoading\ResearchSubmissionFileService())->encodeKey($file->s3_key);

        $this->actingAs($stranger)->get(route('faculty-loading.research-requirements.files.show', $fileId))
            ->assertForbidden();
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=MyResearchRequirementHttpTest"`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Add the controller method**

```php
    public function file(Request $request, string $fileId, ResearchSubmissionFileService $fileService)
    {
        $s3Key = $fileService->decodeKey($fileId);
        if (! $s3Key) {
            abort(404);
        }

        $file = ResearchRequirementSubmissionFile::where('s3_key', $s3Key)->first();
        if (! $file) {
            abort(404);
        }

        $assignment = $file->submission->assignment;
        $userId     = $request->user()->id;

        $isCoordinator = $request->user()->hasAnyPermission(['faculty_loading.manage', 'faculty_loading.research_advisories']);
        $isGroupMember = ResearchAdvisory::where('user_id', $userId)
            ->where('research_group_id', $assignment->research_group_id)
            ->exists();

        abort_unless($isCoordinator || $isGroupMember, 403);

        if (! Storage::disk('s3')->exists($s3Key)) {
            abort(404);
        }

        $contents = Storage::disk('s3')->get($s3Key);

        return response($contents, 200)
            ->header('Content-Type', $file->mime_type)
            ->header('Content-Disposition', 'attachment; filename="' . addslashes($file->original_filename) . '"')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, max-age=3600');
    }
```

- [ ] **Step 4: Register the route**

In `routes/faculty-loading.php`, add a standalone route (outside both permission-specific groups, since it needs to allow all three permissions) right after the `research-advisories` group:

```php
        Route::middleware('permission:faculty_loading.manage|faculty_loading.research_advisories|faculty_loading.view_own')
            ->get('/research-requirements/files/{fileId}', [MyResearchRequirementController::class, 'file'])
            ->name('research-requirements.files.show');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=MyResearchRequirementHttpTest"`
Expected: PASS (9 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/MyResearchRequirementController.php routes/faculty-loading.php tests/Feature/FacultyLoading/MyResearchRequirementHttpTest.php
git commit -m "feat(research-advisory): add authenticated+authorized file proxy route"
```

---

### Task 22: "My Research Submissions" sidebar entry

**Files:**
- Modify: `resources/js/Layouts/navigation.js`

**Interfaces:**
- Produces: a nav item gated by `faculty_loading.view_own`, pinned to top like "My Faculty Schedule"/"My Load Assignments".

- [ ] **Step 1: Add the nav entry**

In `resources/js/Layouts/navigation.js`, immediately after the "My Load Assignments" entry (the last entry before the closing `],` of the Faculty Loading section, L1761-1769), add:

```js
      {
        label: "My Research Submissions",
        routeName: "faculty-loading.my-research-requirements.index",
        href: route("faculty-loading.my-research-requirements.index"),
        icon: ClipboardDocumentCheckIcon,
        pinToTop: true,
        roles: [],
        permissions: ["faculty_loading.view_own"],
      },
```

(Reuses the `ClipboardDocumentCheckIcon` import added in Task 12.)

- [ ] **Step 2: Verify**

Run: `grep -n "My Research Submissions" resources/js/Layouts/navigation.js`
Expected: one match.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Layouts/navigation.js
git commit -m "feat(research-advisory): add My Research Submissions sidebar entry"
```

---

### Task 23: Frontend — `MyResearchRequirements.vue`

**Files:**
- Create: `resources/js/Pages/FacultyLoading/MyResearchRequirements.vue`

**Interfaces:**
- Consumes: Inertia props from `MyResearchRequirementController::index` (Task 18) — `assignments[]`.
- Consumes routes: `faculty-loading.my-research-requirements.submit` (Task 19), `faculty-loading.research-requirements.files.show` (Task 21).

- [ ] **Step 1: Write the component**

```vue
<script setup>
import { ref, computed } from 'vue'
import { Head, usePage, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppButton from '@/Components/AppButton.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppModal from '@/Components/AppModal.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { ClipboardDocumentCheckIcon, PaperClipIcon, XMarkIcon, ArrowUpTrayIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    assignments: Array,
})

const page  = usePage()
const flash = computed(() => page.props.flash ?? {})

const statusBadge = { pending: 'slate', submitted: 'blue', accepted: 'green', returned: 'red' }
const statusLabel = { pending: 'Not Submitted', submitted: 'Submitted — Awaiting Review', accepted: 'Accepted', returned: 'Returned for Revision' }

function isOverdue(a) {
    return a.status !== 'accepted' && new Date(a.requirement.due_at) < new Date()
}

// ── Submit modal ──────────────────────────────────────────────────────────────
const showModal   = ref(false)
const activeAssignment = ref(null)
const pendingFiles = ref([]) // [{ name, data }]
const fileError = ref('')

const form = useForm({ notes: '', files: [] })

function openSubmit(assignment) {
    activeAssignment.value = assignment
    pendingFiles.value = []
    fileError.value = ''
    form.reset()
    showModal.value = true
}
function closeModal() { showModal.value = false }

function handleFiles(e) {
    const list = e.target.files || e.dataTransfer.files
    const max = activeAssignment.value.requirement.max_files
    for (const file of list) {
        if (pendingFiles.value.length >= max) {
            fileError.value = `You can attach at most ${max} file(s).`
            break
        }
        if (file.size > 10 * 1024 * 1024) {
            fileError.value = `"${file.name}" is over the 10MB limit.`
            continue
        }
        const reader = new FileReader()
        reader.onload = (ev) => {
            pendingFiles.value.push({ name: file.name, data: ev.target.result })
        }
        reader.readAsDataURL(file)
    }
}
function removeFile(i) { pendingFiles.value.splice(i, 1) }

function submit() {
    form.files = pendingFiles.value
    form.post(route('faculty-loading.my-research-requirements.submit', activeAssignment.value.id), {
        preserveScroll: true, onSuccess: closeModal,
    })
}
</script>

<template>
    <Head title="My Research Submissions" />
    <AdminLayout title="My Research Submissions">
        <div class="space-y-5">
            <AppPageHeader title="My Research Submissions" subtitle="Deadlines and required files set by the Research Coordinator." />

            <div v-if="flash.success" class="bg-success-50 border border-success-100 text-success-700 rounded-lg px-4 py-3 text-sm">{{ flash.success }}</div>

            <div v-if="assignments.length === 0">
                <EmptyState title="No submission requirements assigned to your research groups yet" :icon="ClipboardDocumentCheckIcon" />
            </div>

            <div v-else class="grid gap-3 sm:grid-cols-2">
                <div v-for="a in assignments" :key="a.id" class="rounded-lg border border-slate-200 bg-white p-4 space-y-2"
                    :class="isOverdue(a) && a.status !== 'submitted' ? 'border-danger-200 bg-danger-50/30' : ''">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="font-medium text-slate-800">{{ a.requirement.title }}</p>
                            <p class="text-xs text-slate-500">{{ a.research_group.title }} · Grade {{ a.research_group.grade_level }}</p>
                        </div>
                        <AppBadge :color="statusBadge[a.status] ?? 'slate'">{{ statusLabel[a.status] ?? a.status }}</AppBadge>
                    </div>

                    <p v-if="a.requirement.description" class="text-sm text-slate-600">{{ a.requirement.description }}</p>

                    <p class="text-xs" :class="isOverdue(a) ? 'text-danger-600 font-medium' : 'text-slate-500'">
                        Due {{ new Date(a.requirement.due_at).toLocaleString('en-PH') }}
                        <span v-if="isOverdue(a)">— overdue{{ !a.requirement.allow_late_submission ? ' (late submission blocked)' : '' }}</span>
                    </p>

                    <div v-if="a.latest_submission" class="rounded-md bg-slate-50 p-2 text-xs text-slate-600 space-y-1">
                        <p>Last submitted by {{ a.latest_submission.submitted_by }} on {{ new Date(a.latest_submission.submitted_at).toLocaleDateString('en-PH') }}
                            <span v-if="a.latest_submission.is_late" class="text-danger-500">(late)</span>
                        </p>
                        <p v-if="a.latest_submission.review_comment" class="text-amber-700">Coordinator: "{{ a.latest_submission.review_comment }}"</p>
                        <div class="flex flex-wrap gap-1">
                            <a v-for="f in a.latest_submission.files" :key="f.id"
                                :href="route('faculty-loading.research-requirements.files.show', f.id)" target="_blank"
                                class="inline-flex items-center gap-1 rounded-full bg-white border border-slate-200 px-2 py-0.5 text-[11px] text-indigo-600 hover:underline">
                                <PaperClipIcon class="h-3 w-3" /> {{ f.name }}
                            </a>
                        </div>
                    </div>

                    <AppButton v-if="a.status === 'pending' || a.status === 'returned'" size="sm" @click="openSubmit(a)">
                        <ArrowUpTrayIcon class="h-4 w-4" /> {{ a.status === 'returned' ? 'Resubmit' : 'Submit' }}
                    </AppButton>
                </div>
            </div>
        </div>

        <AppModal :show="showModal" :title="activeAssignment ? 'Submit — ' + activeAssignment.requirement.title : ''" @close="closeModal">
            <div class="space-y-3">
                <p v-if="activeAssignment?.requirement.accepted_file_types" class="text-xs text-slate-500">
                    Accepted file types: {{ activeAssignment.requirement.accepted_file_types }}. Max {{ activeAssignment.requirement.max_files }} file(s).
                </p>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Files</label>
                    <input type="file" multiple @change="handleFiles" class="block w-full text-sm" />
                    <p v-if="fileError" class="mt-1 text-xs text-danger-500">{{ fileError }}</p>
                    <p v-if="form.errors.files" class="mt-1 text-xs text-danger-500">{{ form.errors.files }}</p>
                    <div v-if="pendingFiles.length" class="mt-2 flex flex-wrap gap-2">
                        <span v-for="(f, i) in pendingFiles" :key="i"
                            class="inline-flex items-center gap-1 rounded-full bg-indigo-50 border border-indigo-200 px-3 py-0.5 text-xs text-indigo-700">
                            {{ f.name }}
                            <button type="button" @click="removeFile(i)" class="hover:text-danger-500"><XMarkIcon class="h-3 w-3" /></button>
                        </span>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">Notes (optional)</label>
                    <textarea v-model="form.notes" rows="3" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                </div>
                <p v-if="form.errors.due_at" class="text-xs text-danger-500">{{ form.errors.due_at }}</p>
            </div>

            <template #footer>
                <AppButton variant="secondary" @click="closeModal">Cancel</AppButton>
                <AppButton :disabled="pendingFiles.length === 0" :loading="form.processing" @click="submit">Submit</AppButton>
            </template>
        </AppModal>
    </AdminLayout>
</template>
```

- [ ] **Step 2: Manually verify**

Build assets and visit `/faculty-loading/my-research-requirements` as a faculty user with an active research advisory that has a requirement assigned; confirm the card renders, file picker enforces `max_files`/10MB client-side, and submitting shows the success flash and updates the status badge.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/FacultyLoading/MyResearchRequirements.vue
git commit -m "feat(research-advisory): add MyResearchRequirements.vue"
```

---

### Task 24: Frontend — review actions on `ResearchRequirements/Show.vue`

**Files:**
- Modify: `resources/js/Pages/FacultyLoading/ResearchRequirements/Show.vue`

**Interfaces:**
- Consumes route: `faculty-loading.research-requirements.submissions.review` (Task 20).
- Consumes: `assignments[].latest_submission.id` (already present since Task 16b).

- [ ] **Step 1: Add review UI**

In `resources/js/Pages/FacultyLoading/ResearchRequirements/Show.vue`, add to the `<script setup>` block:

```js
import { PaperClipIcon, CheckIcon, ArrowUturnLeftIcon } from '@heroicons/vue/24/outline'

const reviewForm = useForm({ decision: '', comment: '' })
const returnModal = ref({ show: false, submissionId: null })

function accept(submissionId) {
    reviewForm.decision = 'accepted'
    reviewForm.comment  = ''
    reviewForm.post(route('faculty-loading.research-requirements.submissions.review', submissionId), { preserveScroll: true })
}
function openReturn(submissionId) {
    returnModal.value = { show: true, submissionId }
    reviewForm.decision = 'returned'
    reviewForm.comment  = ''
}
function submitReturn() {
    reviewForm.post(route('faculty-loading.research-requirements.submissions.review', returnModal.value.submissionId), {
        preserveScroll: true, onSuccess: () => { returnModal.value.show = false },
    })
}
```

Add the `PaperClipIcon, CheckIcon, ArrowUturnLeftIcon` to the existing `@heroicons/vue/24/outline` import line instead of a second import line.

Replace the "Latest Submission" `<td>` cell content with:

```html
                    <td class="px-4 py-3 text-xs text-slate-600">
                        <template v-if="a.latest_submission">
                            {{ a.latest_submission.submitted_by }} — {{ new Date(a.latest_submission.submitted_at).toLocaleDateString('en-PH') }}
                            <span v-if="a.latest_submission.is_late" class="text-danger-500"> (late)</span>
                            <div class="flex flex-wrap gap-1 mt-1">
                                <span v-for="f in a.latest_submission.files" :key="f.id"
                                    class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-600">
                                    <PaperClipIcon class="h-3 w-3" /> {{ f.name }}
                                </span>
                            </div>
                            <p v-if="a.latest_submission.notes" class="mt-1 italic text-slate-500">"{{ a.latest_submission.notes }}"</p>
                            <div v-if="a.status === 'submitted'" class="mt-2 flex gap-2">
                                <button @click="accept(a.latest_submission.id)" class="inline-flex items-center gap-1 text-xs text-success-600 hover:underline">
                                    <CheckIcon class="h-3.5 w-3.5" /> Accept
                                </button>
                                <button @click="openReturn(a.latest_submission.id)" class="inline-flex items-center gap-1 text-xs text-danger-600 hover:underline">
                                    <ArrowUturnLeftIcon class="h-3.5 w-3.5" /> Return
                                </button>
                            </div>
                            <p v-else-if="a.latest_submission.review_comment" class="mt-1 text-amber-700">Feedback: "{{ a.latest_submission.review_comment }}"</p>
                        </template>
                        <span v-else class="text-slate-400 italic">No submission yet</span>
                    </td>
```

Add the return-with-comment modal at the end of the `<template>`, right before `</AdminLayout>`:

```html
        <AppModal :show="returnModal.show" title="Return for Revision" @close="returnModal.show = false">
            <div>
                <label class="block text-xs font-medium text-slate-600 mb-1">Comment <span class="text-red-500">*</span></label>
                <textarea v-model="reviewForm.comment" rows="3" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-full" />
                <p v-if="reviewForm.errors.comment" class="mt-1 text-xs text-danger-500">{{ reviewForm.errors.comment }}</p>
            </div>
            <template #footer>
                <AppButton variant="secondary" @click="returnModal.show = false">Cancel</AppButton>
                <AppButton :disabled="!reviewForm.comment" :loading="reviewForm.processing" @click="submitReturn">Return for Revision</AppButton>
            </template>
        </AppModal>
```

- [ ] **Step 2: Manually verify**

As an adviser, submit a file for a requirement; as a coordinator, open the requirement's Show page, confirm Accept/Return buttons appear on `submitted` rows, Accept immediately flips the badge to "accepted", and Return opens the comment modal and flips the badge to "returned" with the comment visible to the adviser on their own page.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/FacultyLoading/ResearchRequirements/Show.vue
git commit -m "feat(research-advisory): add coordinator review UI to Show.vue"
```

---

## Phase 3 — Notifications

### Task 25: Generic `ResearchRequirementMail` Mailable + view

**Files:**
- Create: `app/Mail/ResearchRequirementMail.php`
- Create: `resources/views/emails/research_requirement.blade.php`
- Test: `tests/Unit/Mail/ResearchRequirementMailTest.php`

**Interfaces:**
- Produces: `new ResearchRequirementMail(string $recipientName, string $headerTitle, string $lead, array $details, ?string $actionUrl = null, ?string $actionLabel = null)` — one reusable Mailable for all 5 notification events (created/received/reviewed/reminder/overdue), following the project's `emails.layouts.base` convention.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Mail;

use App\Mail\ResearchRequirementMail;
use Tests\TestCase;

class ResearchRequirementMailTest extends TestCase
{
    public function test_renders_with_subject_and_details_table(): void
    {
        $mail = new ResearchRequirementMail(
            recipientName: 'Juan Dela Cruz',
            headerTitle: 'New Research Requirement Posted',
            lead: 'A new submission requirement has been posted for your research group.',
            details: [['Requirement', 'Chapter 1 Draft'], ['Due', 'September 20, 2026']],
            actionUrl: 'https://example.test/my-research-requirements',
            actionLabel: 'View Requirement',
        );

        $rendered = $mail->render();

        $this->assertStringContainsString('Juan Dela Cruz', $rendered);
        $this->assertStringContainsString('Chapter 1 Draft', $rendered);
        $this->assertStringContainsString('View Requirement', $rendered);
        $this->assertSame('New Research Requirement Posted', $mail->envelope()->subject);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementMailTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the Mailable**

```php
<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResearchRequirementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $headerTitle,
        public string $lead,
        public array $details = [],
        public ?string $actionUrl = null,
        public ?string $actionLabel = null,
    ) {}

    public function build()
    {
        return $this->subject($this->headerTitle)
            ->view('emails.research_requirement')
            ->with([
                'recipientName' => $this->recipientName,
                'headerTitle'   => $this->headerTitle,
                'lead'          => $this->lead,
                'details'       => $this->details,
                'actionUrl'     => $this->actionUrl,
                'actionLabel'   => $this->actionLabel,
            ]);
    }
}
```

- [ ] **Step 4: Write the view**

```blade
@extends('emails.layouts.base')

@section('header-title', $headerTitle)
@section('header-subtitle', 'Atlas — Research Advisory')

@section('content')
<p class="greeting">Hello <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">{{ $lead }}</p>

@if(!empty($details))
<table class="details" role="presentation">
    @foreach($details as [$label, $value])
    <tr><td class="lbl">{{ $label }}</td><td class="val">{{ $value }}</td></tr>
    @endforeach
</table>
@endif

@if($actionUrl)
<p><a href="{{ $actionUrl }}" class="btn btn-primary">{{ $actionLabel ?? 'View' }}</a></p>
@endif
@endsection
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementMailTest"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Mail/ResearchRequirementMail.php resources/views/emails/research_requirement.blade.php tests/Unit/Mail/ResearchRequirementMailTest.php
git commit -m "feat(research-advisory): add generic ResearchRequirementMail"
```

---

### Task 26: `NotifyResearchRequirementCreated` job

**Files:**
- Create: `app/Jobs/NotifyResearchRequirementCreated.php`
- Test: `tests/Unit/Jobs/NotifyResearchRequirementCreatedTest.php`

**Interfaces:**
- Consumes: `NotificationService::notifyUser()`, `ResearchRequirementMail` (Task 25).
- Produces: `NotifyResearchRequirementCreated::dispatch(int $requirementId, array $assignmentIds)` — for each assignment, notifies every adviser on that group (bell via `NotificationService`, email via `ResearchRequirementMail`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Jobs;

use App\Jobs\NotifyResearchRequirementCreated;
use App\Mail\ResearchRequirementMail;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotifyResearchRequirementCreatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifies_every_adviser_on_each_assigned_group(): void
    {
        Mail::fake();
        Notification::fake();

        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X', 'research_type' => 'thesis']);
        $lead = User::factory()->create();
        $co   = User::factory()->create();
        ResearchAdvisory::create(['user_id' => $lead->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'research_title' => 'X', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $group->id]);
        ResearchAdvisory::create(['user_id' => $co->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'research_title' => 'X', 'grade_level' => 10, 'advisory_role' => 'co_adviser', 'research_type' => 'thesis', 'load_units' => 0.5, 'status' => 'active', 'research_group_id' => $group->id]);

        $requirement = ResearchRequirement::create(['created_by' => User::factory()->create()->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(14), 'status' => 'active']);
        $assignment  = ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);

        (new NotifyResearchRequirementCreated($requirement->id, [$assignment->id]))->handle();

        Mail::assertSent(ResearchRequirementMail::class, 2);
        Notification::assertSentTo([$lead, $co], \App\Notifications\RequestStatusNotification::class);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=NotifyResearchRequirementCreatedTest"`
Expected: FAIL — class not found.

- [ ] **Step 3: Write the job**

```php
<?php

namespace App\Jobs;

use App\Mail\ResearchRequirementMail;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class NotifyResearchRequirementCreated implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $tries = 1;

    public function __construct(public int $requirementId, public array $assignmentIds)
    {
        $this->onQueue('bulk');
    }

    public function handle(): void
    {
        $requirement = ResearchRequirement::find($this->requirementId);
        if (! $requirement) {
            logger()->error('NotifyResearchRequirementCreated: requirement not found', ['requirement_id' => $this->requirementId]);
            return;
        }

        $assignments = ResearchRequirementAssignment::with('researchGroup')->whereIn('id', $this->assignmentIds)->get();

        $sent = 0;
        foreach ($assignments as $assignment) {
            $advisers = ResearchAdvisory::where('research_group_id', $assignment->research_group_id)
                ->where('status', '<>', 'dropped')
                ->with('faculty')
                ->get()
                ->pluck('faculty')
                ->filter();

            foreach ($advisers as $user) {
                try {
                    NotificationService::notifyUser(
                        $user,
                        'Research Requirement',
                        $requirement->title,
                        'A new submission requirement has been posted for your research group.',
                        route('faculty-loading.my-research-requirements.index'),
                    );

                    if ($user->email) {
                        Mail::to($user->email)->send(new ResearchRequirementMail(
                            recipientName: $user->name,
                            headerTitle: 'New Research Requirement Posted',
                            lead: "A new submission requirement has been posted for \"{$assignment->researchGroup->title}\".",
                            details: [
                                ['Requirement', $requirement->title],
                                ['Due', $requirement->due_at->format('F j, Y g:i A')],
                            ],
                            actionUrl: route('faculty-loading.my-research-requirements.index'),
                            actionLabel: 'View Requirement',
                        ));
                    }
                    $sent++;
                } catch (\Throwable $e) {
                    logger()->warning('NotifyResearchRequirementCreated: notify failed', [
                        'requirement_id' => $requirement->id, 'user_id' => $user->id, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        logger()->info('NotifyResearchRequirementCreated: complete', ['requirement_id' => $requirement->id, 'sent' => $sent]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('NotifyResearchRequirementCreated: job FAILED', ['requirement_id' => $this->requirementId, 'error' => $e->getMessage()]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=NotifyResearchRequirementCreatedTest"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/NotifyResearchRequirementCreated.php tests/Unit/Jobs/NotifyResearchRequirementCreatedTest.php
git commit -m "feat(research-advisory): add NotifyResearchRequirementCreated job"
```

---

### Task 27: `NotifyResearchSubmissionReceived` + `NotifyResearchSubmissionReviewed` jobs

**Files:**
- Create: `app/Jobs/NotifyResearchSubmissionReceived.php`
- Create: `app/Jobs/NotifyResearchSubmissionReviewed.php`
- Test: `tests/Unit/Jobs/NotifyResearchSubmissionJobsTest.php`

**Interfaces:**
- Produces:
  - `NotifyResearchSubmissionReceived::dispatch(int $submissionId)` — notifies the requirement's `created_by` (the coordinator).
  - `NotifyResearchSubmissionReviewed::dispatch(int $submissionId)` — notifies the submission's `submitted_by` of the accept/return decision.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Unit\Jobs;

use App\Jobs\NotifyResearchSubmissionReceived;
use App\Jobs\NotifyResearchSubmissionReviewed;
use App\Mail\ResearchRequirementMail;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\ResearchRequirementSubmission;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifyResearchSubmissionJobsTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubmission(): array
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X', 'research_type' => 'thesis']);
        $coordinator = User::factory()->create();
        $adviser     = User::factory()->create();
        $requirement = ResearchRequirement::create(['created_by' => $coordinator->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(14), 'status' => 'active']);
        $assignment  = ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);
        $submission  = ResearchRequirementSubmission::create(['research_requirement_assignment_id' => $assignment->id, 'submitted_by' => $adviser->id, 'submitted_at' => now(), 'is_late' => false]);
        return [$submission, $coordinator, $adviser];
    }

    public function test_received_job_notifies_the_coordinator(): void
    {
        Mail::fake();
        [$submission, $coordinator] = $this->makeSubmission();

        (new NotifyResearchSubmissionReceived($submission->id))->handle();

        Mail::assertSent(ResearchRequirementMail::class, fn ($mail) => $mail->hasTo($coordinator->email));
    }

    public function test_reviewed_job_notifies_the_submitter(): void
    {
        Mail::fake();
        [$submission, , $adviser] = $this->makeSubmission();
        $submission->update(['review_status' => 'accepted']);

        (new NotifyResearchSubmissionReviewed($submission->id))->handle();

        Mail::assertSent(ResearchRequirementMail::class, fn ($mail) => $mail->hasTo($adviser->email));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=NotifyResearchSubmissionJobsTest"`
Expected: FAIL — classes not found.

- [ ] **Step 3: Write the jobs**

```php
<?php

namespace App\Jobs;

use App\Mail\ResearchRequirementMail;
use App\Models\FacultyLoading\ResearchRequirementSubmission;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class NotifyResearchSubmissionReceived implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(public int $submissionId) {}

    public function handle(): void
    {
        $submission = ResearchRequirementSubmission::with(['assignment.requirement.createdBy', 'assignment.researchGroup', 'submittedBy'])->find($this->submissionId);
        if (! $submission) {
            logger()->error('NotifyResearchSubmissionReceived: submission not found', ['submission_id' => $this->submissionId]);
            return;
        }

        $coordinator = $submission->assignment->requirement->createdBy;
        if (! $coordinator) {
            return;
        }

        $url = route('faculty-loading.research-requirements.show', $submission->assignment->requirement->id);

        try {
            NotificationService::notifyUser(
                $coordinator,
                'Research Requirement',
                $submission->assignment->requirement->title,
                "{$submission->submittedBy?->name} submitted for \"{$submission->assignment->researchGroup->title}\".",
                $url,
            );

            if ($coordinator->email) {
                Mail::to($coordinator->email)->send(new ResearchRequirementMail(
                    recipientName: $coordinator->name,
                    headerTitle: 'New Research Submission Received',
                    lead: "{$submission->submittedBy?->name} submitted \"{$submission->assignment->requirement->title}\" for \"{$submission->assignment->researchGroup->title}\".",
                    details: [['Submitted', $submission->submitted_at->format('F j, Y g:i A')]],
                    actionUrl: $url,
                    actionLabel: 'Review Submission',
                ));
            }
        } catch (\Throwable $e) {
            logger()->warning('NotifyResearchSubmissionReceived: notify failed', ['submission_id' => $submission->id, 'error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('NotifyResearchSubmissionReceived: job FAILED', ['submission_id' => $this->submissionId, 'error' => $e->getMessage()]);
    }
}
```

```php
<?php

namespace App\Jobs;

use App\Mail\ResearchRequirementMail;
use App\Models\FacultyLoading\ResearchRequirementSubmission;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class NotifyResearchSubmissionReviewed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(public int $submissionId) {}

    public function handle(): void
    {
        $submission = ResearchRequirementSubmission::with(['assignment.requirement', 'assignment.researchGroup', 'submittedBy'])->find($this->submissionId);
        if (! $submission || ! $submission->submittedBy) {
            logger()->error('NotifyResearchSubmissionReviewed: submission or submitter not found', ['submission_id' => $this->submissionId]);
            return;
        }

        $user = $submission->submittedBy;
        $accepted = $submission->review_status === 'accepted';
        $url = route('faculty-loading.my-research-requirements.index');

        try {
            NotificationService::notifyUser(
                $user,
                'Research Requirement',
                $submission->assignment->requirement->title,
                $accepted ? 'Your submission was accepted.' : 'Your submission was returned for revision.',
                $url,
            );

            if ($user->email) {
                Mail::to($user->email)->send(new ResearchRequirementMail(
                    recipientName: $user->name,
                    headerTitle: $accepted ? 'Submission Accepted' : 'Submission Returned for Revision',
                    lead: $accepted
                        ? "Your submission for \"{$submission->assignment->requirement->title}\" has been accepted."
                        : "Your submission for \"{$submission->assignment->requirement->title}\" needs revision.",
                    details: $accepted ? [] : [['Feedback', $submission->review_comment ?? '—']],
                    actionUrl: $url,
                    actionLabel: 'View My Submissions',
                ));
            }
        } catch (\Throwable $e) {
            logger()->warning('NotifyResearchSubmissionReviewed: notify failed', ['submission_id' => $submission->id, 'error' => $e->getMessage()]);
        }
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('NotifyResearchSubmissionReviewed: job FAILED', ['submission_id' => $this->submissionId, 'error' => $e->getMessage()]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=NotifyResearchSubmissionJobsTest"`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/NotifyResearchSubmissionReceived.php app/Jobs/NotifyResearchSubmissionReviewed.php tests/Unit/Jobs/NotifyResearchSubmissionJobsTest.php
git commit -m "feat(research-advisory): add submission received/reviewed notification jobs"
```

---

### Task 28: Wire job dispatches into controllers

**Files:**
- Modify: `app/Http/Controllers/FacultyLoading/ResearchRequirementController.php` (`store()`, `sync()`, `addAssignment()`, `review()`)
- Modify: `app/Http/Controllers/FacultyLoading/MyResearchRequirementController.php` (`submit()`)
- Modify: `tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php` and `tests/Feature/FacultyLoading/MyResearchRequirementHttpTest.php` (append)

**Interfaces:**
- Consumes: `NotifyResearchRequirementCreated`, `NotifyResearchSubmissionReceived`, `NotifyResearchSubmissionReviewed` (Tasks 26-27).

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php`:

```php
    public function test_store_dispatches_created_notification_when_groups_match(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $term = $this->makeTerm();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group A');

        $this->actingAs($this->coordinator())->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);

        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\NotifyResearchRequirementCreated::class);
    }

    public function test_review_dispatches_reviewed_notification(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $term = $this->makeTerm();
        $this->makeActiveGroup($term, 10, 'thesis', 'Group A');
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.store'), [
            'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'grade_levels' => [10], 'research_type' => 'thesis',
            'due_at' => now()->addDays(14)->toDateTimeString(),
        ]);
        $assignment = ResearchRequirement::first()->assignments()->first();
        $submission = \App\Models\FacultyLoading\ResearchRequirementSubmission::create([
            'research_requirement_assignment_id' => $assignment->id, 'submitted_by' => User::factory()->create()->id, 'submitted_at' => now(), 'is_late' => false,
        ]);

        $this->actingAs($coordinator)->post(route('faculty-loading.research-requirements.submissions.review', $submission->id), ['decision' => 'accepted']);

        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\NotifyResearchSubmissionReviewed::class);
    }
```

Append to `tests/Feature/FacultyLoading/MyResearchRequirementHttpTest.php`:

```php
    public function test_submit_dispatches_received_notification(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        $term = $this->makeTerm();
        $adviser = $this->adviser();
        $assignment = $this->assignmentFor($adviser, $term);

        $this->actingAs($adviser)->post(route('faculty-loading.my-research-requirements.submit', $assignment->id), [
            'files' => [['data' => $this->pdfDataUri(), 'name' => 'chapter1.pdf']],
        ]);

        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\NotifyResearchSubmissionReceived::class);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementHttpTest,MyResearchRequirementHttpTest"`
Expected: FAIL — jobs never dispatched.

- [ ] **Step 3: Wire the dispatches**

In `ResearchRequirementController.php`, add imports:

```php
use App\Jobs\NotifyResearchRequirementCreated;
use App\Jobs\NotifyResearchSubmissionReviewed;
```

In `store()`, replace:

```php
        $created = $this->fanout->fanOut($requirement);

        return back()->with('success', "Requirement created and assigned to {$created->count()} research group(s).");
```

with:

```php
        $created = $this->fanout->fanOut($requirement);

        if ($created->isNotEmpty()) {
            NotifyResearchRequirementCreated::dispatch($requirement->id, $created->pluck('id')->all());
        }

        return back()->with('success', "Requirement created and assigned to {$created->count()} research group(s).");
```

Apply the identical `if ($created->isNotEmpty()) { NotifyResearchRequirementCreated::dispatch(...); }` pattern in `sync()` (after `$created = $this->fanout->fanOut($researchRequirement);`) and in `addAssignment()` (dispatch with the single new/re-included assignment's id when one was actually created or re-included — for `addAssignment()`, dispatch `NotifyResearchRequirementCreated::dispatch($researchRequirement->id, [$assignment->id]);` unconditionally after the `if/else` block, since either branch means the group now has visibility into this requirement).

In `review()`, add right after `$submission->assignment->update([...]);`:

```php
        NotifyResearchSubmissionReviewed::dispatch($submission->id);
```

In `MyResearchRequirementController.php`, add import `use App\Jobs\NotifyResearchSubmissionReceived;` and inside the `DB::transaction(...)` closure in `submit()`, after `$assignment->update(['status' => 'submitted']);`, add:

```php
            NotifyResearchSubmissionReceived::dispatch($submission->id);
```

(Dispatching inside the transaction closure is safe here — the default queue connection is not `sync` in this app, so the job is queued for after-commit delivery rather than executed inline; if the transaction rolls back for any other reason before this line is reached, the whole `submit()` method has already returned via exception before dispatch anyway.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ResearchRequirementHttpTest,MyResearchRequirementHttpTest"`
Expected: PASS (all tests in both files)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/FacultyLoading/ResearchRequirementController.php app/Http/Controllers/FacultyLoading/MyResearchRequirementController.php tests/Feature/FacultyLoading/ResearchRequirementHttpTest.php tests/Feature/FacultyLoading/MyResearchRequirementHttpTest.php
git commit -m "feat(research-advisory): wire notification job dispatches into controllers"
```

---

### Task 29: `research:send-requirement-reminders` command

**Files:**
- Create: `app/Console/Commands/SendResearchRequirementReminders.php`
- Test: `tests/Feature/Console/SendResearchRequirementRemindersTest.php`

**Interfaces:**
- Consumes: `NotificationService::notifyUser()`, `ResearchRequirementMail` (Task 25).
- Produces: artisan command `research:send-requirement-reminders` — sends a reminder for assignments (`pending`/`returned`) due within 3 days (once, guarded by `reminder_sent_at`), and an overdue notice for ones past due (once, guarded by `overdue_notified_at`), notifying every adviser on the group plus the requirement's coordinator for overdue.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Console;

use App\Mail\ResearchRequirementMail;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendResearchRequirementRemindersTest extends TestCase
{
    use RefreshDatabase;

    private function makeSetup(array $requirementOverrides = []): array
    {
        $sy   = SchoolYear::create(['name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30', 'is_current' => true, 'status' => 'active']);
        $term = AcademicTerm::create(['school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester', 'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true]);
        $group = ResearchGroup::create(['academic_term_id' => $term->id, 'grade_level' => 10, 'title' => 'X', 'research_type' => 'thesis']);
        $adviser = User::factory()->create();
        ResearchAdvisory::create(['user_id' => $adviser->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'research_title' => 'X', 'grade_level' => 10, 'advisory_role' => 'lead', 'research_type' => 'thesis', 'load_units' => 1.0, 'status' => 'active', 'research_group_id' => $group->id]);
        $coordinator = User::factory()->create();
        $requirement = ResearchRequirement::create(array_merge([
            'created_by' => $coordinator->id, 'academic_term_id' => $term->id, 'title' => 'Chapter 1', 'due_at' => now()->addDays(2), 'status' => 'active',
        ], $requirementOverrides));
        $assignment = ResearchRequirementAssignment::create(['research_requirement_id' => $requirement->id, 'research_group_id' => $group->id]);
        return [$assignment, $adviser, $coordinator];
    }

    public function test_sends_reminder_when_due_within_3_days_and_stamps_guard(): void
    {
        Mail::fake();
        [$assignment] = $this->makeSetup(['due_at' => now()->addDays(2)]);

        Artisan::call('research:send-requirement-reminders');

        Mail::assertSent(ResearchRequirementMail::class);
        $this->assertNotNull($assignment->fresh()->reminder_sent_at);
    }

    public function test_does_not_send_reminder_twice(): void
    {
        Mail::fake();
        [$assignment] = $this->makeSetup(['due_at' => now()->addDays(2)]);

        Artisan::call('research:send-requirement-reminders');
        Mail::fake(); // reset the sent-mail counter for a clean second assertion
        Artisan::call('research:send-requirement-reminders');

        Mail::assertNotSent(ResearchRequirementMail::class);
    }

    public function test_sends_overdue_notice_to_adviser_and_coordinator(): void
    {
        Mail::fake();
        [$assignment, $adviser, $coordinator] = $this->makeSetup(['due_at' => now()->subDay()]);

        Artisan::call('research:send-requirement-reminders');

        Mail::assertSent(ResearchRequirementMail::class, fn ($mail) => $mail->hasTo($adviser->email));
        Mail::assertSent(ResearchRequirementMail::class, fn ($mail) => $mail->hasTo($coordinator->email));
        $this->assertNotNull($assignment->fresh()->overdue_notified_at);
    }

    public function test_accepted_assignment_is_never_reminded(): void
    {
        Mail::fake();
        [$assignment] = $this->makeSetup(['due_at' => now()->addDays(2)]);
        $assignment->update(['status' => 'accepted']);

        Artisan::call('research:send-requirement-reminders');

        Mail::assertNotSent(ResearchRequirementMail::class);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=SendResearchRequirementRemindersTest"`
Expected: FAIL — command not found.

- [ ] **Step 3: Write the command**

```php
<?php

namespace App\Console\Commands;

use App\Mail\ResearchRequirementMail;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendResearchRequirementReminders extends Command
{
    protected $signature = 'research:send-requirement-reminders';

    protected $description = 'Send deadline reminders and overdue notices for pending/returned research requirement assignments';

    public function handle(): int
    {
        $reminded = $this->sendReminders();
        $overdue  = $this->sendOverdue();

        $this->info("Reminders sent: {$reminded}. Overdue notices sent: {$overdue}.");

        return self::SUCCESS;
    }

    private function sendReminders(): int
    {
        $assignments = ResearchRequirementAssignment::visible()
            ->whereIn('status', ['pending', 'returned'])
            ->whereNull('reminder_sent_at')
            ->whereHas('requirement', fn ($q) => $q->where('status', 'active')->whereBetween('due_at', [now(), now()->addDays(3)]))
            ->with(['requirement', 'researchGroup'])
            ->get();

        foreach ($assignments as $assignment) {
            $this->notifyGroup(
                $assignment,
                'Reminder: Research Requirement Due Soon',
                "\"{$assignment->requirement->title}\" is due on {$assignment->requirement->due_at->format('F j, Y g:i A')}.",
            );
            $assignment->update(['reminder_sent_at' => now()]);
        }

        return $assignments->count();
    }

    private function sendOverdue(): int
    {
        $assignments = ResearchRequirementAssignment::visible()
            ->whereIn('status', ['pending', 'returned'])
            ->whereNull('overdue_notified_at')
            ->whereHas('requirement', fn ($q) => $q->where('status', 'active')->where('due_at', '<', now()))
            ->with(['requirement.createdBy', 'researchGroup'])
            ->get();

        foreach ($assignments as $assignment) {
            $this->notifyGroup(
                $assignment,
                'Overdue: Research Requirement',
                "\"{$assignment->requirement->title}\" was due on {$assignment->requirement->due_at->format('F j, Y g:i A')} and has not been submitted.",
            );

            if ($coordinator = $assignment->requirement->createdBy) {
                $this->notifyUser(
                    $coordinator,
                    'Overdue: Research Requirement',
                    "\"{$assignment->researchGroup->title}\" has not submitted \"{$assignment->requirement->title}\" (was due {$assignment->requirement->due_at->format('F j, Y')}).",
                );
            }

            $assignment->update(['overdue_notified_at' => now()]);
        }

        return $assignments->count();
    }

    private function notifyGroup(ResearchRequirementAssignment $assignment, string $subject, string $lead): void
    {
        $advisers = ResearchAdvisory::where('research_group_id', $assignment->research_group_id)
            ->where('status', '<>', 'dropped')
            ->with('faculty')
            ->get()
            ->pluck('faculty')
            ->filter();

        foreach ($advisers as $user) {
            $this->notifyUser($user, $subject, $lead);
        }
    }

    private function notifyUser(User $user, string $subject, string $lead): void
    {
        try {
            NotificationService::notifyUser($user, 'Research Requirement', $subject, $lead, route('faculty-loading.my-research-requirements.index'));

            if ($user->email) {
                Mail::to($user->email)->send(new ResearchRequirementMail(
                    recipientName: $user->name,
                    headerTitle: $subject,
                    lead: $lead,
                    actionUrl: route('faculty-loading.my-research-requirements.index'),
                    actionLabel: 'View',
                ));
            }
        } catch (\Throwable $e) {
            logger()->warning('SendResearchRequirementReminders: notify failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=SendResearchRequirementRemindersTest"`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Console/Commands/SendResearchRequirementReminders.php tests/Feature/Console/SendResearchRequirementRemindersTest.php
git commit -m "feat(research-advisory): add scheduled reminder/overdue command"
```

---

### Task 30: Register the scheduled command

**Files:**
- Modify: `routes/console.php`

**Interfaces:**
- Consumes: `research:send-requirement-reminders` (Task 29).

- [ ] **Step 1: Add the schedule entry**

In `routes/console.php`, near the other daily-scheduled commands (alongside the `dailyAt` entries such as `pds:notify-annual-update`), add:

```php
Schedule::command('research:send-requirement-reminders')->dailyAt('07:30')->withoutOverlapping();
```

- [ ] **Step 2: Verify registration**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan schedule:list"`
Expected: a row for `research:send-requirement-reminders` at `07:30`.

- [ ] **Step 3: Commit**

```bash
git add routes/console.php
git commit -m "feat(research-advisory): schedule daily requirement reminder command"
```

---

## Final Verification

After all 30 tasks are complete:

- [ ] Run the full Faculty Loading + new-module test suite: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=FacultyLoading"` and `php artisan test tests/Unit/FacultyLoading tests/Feature/FacultyLoading tests/Unit/Jobs tests/Unit/Mail tests/Feature/Console` — all green, zero regressions in the pre-existing Research Advisory suite.
- [ ] Lint all modified/created PHP files via the `lint` skill.
- [ ] Build frontend assets (`build` skill) and manually click through, as both a synthetic coordinator and a synthetic faculty user (see `userWith()` pattern in tests — or grant the seeded permissions to a real dev account): create a requirement, confirm it appears on "My Research Submissions" for the right adviser(s), submit a file, accept one and return another with a comment, confirm the resubmit flow, and run `php artisan research:send-requirement-reminders` manually against a requirement with a near/past due date to see the reminder/overdue notification land.
- [ ] Run `php artisan research-groups:backfill --dry-run` against a copy of real data (or dev DB) before ever running it for real in production, per the existing dry-run convention.

