# Learn Module Phase 2: Assignments + Submissions + Rubric Grading — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Assignments as a new Learn Module Item type, with student submissions (text/file/link) and instructor grading (flat points or a per-assignment rubric), per `docs/superpowers/specs/2026-08-09-learn-module-phase2-design.md`.

**Architecture:** `Assignment` is a third `itemable` type on Phase 1's existing polymorphic `learn_module_items` — no changes to Phase 1 tables. Grading is either a flat `points_possible` typed in directly, or a `Rubric` → `RubricCriterion` breakdown whose per-criterion `RubricScore`s sum to the total, computed live (`Assignment::maxScore()`, never a stored duplicate). `Submission` is one row per `(assignment, student)` — resubmitting overwrites it until `graded_at` locks it; grading roster is built the same shape as `RosterService`/`CourseResolver` from Phase 1: enrollment-driven, left-joined against submissions.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia 2, Tailwind, Tiptap (`RichTextEditor.vue`) + DOMPurify (already added in Phase 1), S3 via the existing `CourseFileService`.

## Global Constraints

- All Phase 1 constraints still apply: base64 JSON uploads only, `Storage::disk('s3')` only, `Inertia::render(...)` only, `module.submodule.action` permission strings, eager-load relations, migrations always write `down()`.
- `students.id` is an unsigned **int**, not bigint, and the `students` table is MyISAM (cannot be an FK target) — `learn_submissions.student_id` is a plain `unsignedInteger` with no `->constrained()`, exactly matching `student_enrollments.student_id`'s existing pattern.
- Rich text (assignment instructions, text submissions) must be sanitized with DOMPurify **at render time** in Vue, not just validated on save — this is the Phase 1 stored-XSS lesson, and it applies again here.
- Link-type submissions (`link_url`) must be restricted to `http(s)` schemes before ever being rendered as a clickable `href` — same pattern as Phase 1's `video_url` fix.
- No new permission strings. All authoring/grading authorization reuses `Course::canEdit()`/`canView()` from Phase 1 via a new `Assignment::canEdit()`/`course()` convenience method — do not invent a parallel authorization path.
- Reopening a graded submission clears `score`, `feedback_comment`, `graded_at`, `graded_by`, and any `RubricScore` rows — never leave stale grading data behind after a reopen.
- Phase 1's `CourseController::serializeItem()` and `StudentPortal\LearnController::serializeItem()` currently have a **pre-existing bug**: `$itemable instanceof LearnPage ? 'page' : 'file'` silently mis-classifies anything that isn't a `Page` as `'file'`. Task 4 fixes this to a proper three-way `match(true)` — required before Assignment items can render correctly at all.

---

### Task 1: Assignment schema (5 migrations)

**Files:**
- Create: `database/migrations/2026_08_09_100001_create_learn_assignments_table.php`
- Create: `database/migrations/2026_08_09_100002_create_learn_rubrics_table.php`
- Create: `database/migrations/2026_08_09_100003_create_learn_rubric_criteria_table.php`
- Create: `database/migrations/2026_08_09_100004_create_learn_submissions_table.php`
- Create: `database/migrations/2026_08_09_100005_create_learn_rubric_scores_table.php`
- Test: `tests/Feature/Learn/LearnAssignmentSchemaTest.php`

**Interfaces:**
- Produces tables: `learn_assignments(id, title, instructions, submission_type, points_possible, due_at, timestamps)`, `learn_rubrics(id, learn_assignment_id unique, timestamps)`, `learn_rubric_criteria(id, learn_rubric_id, description, max_points, position, timestamps)`, `learn_submissions(id, learn_assignment_id, student_id, text_body, learn_file_id, link_url, submitted_at, score, feedback_comment, graded_at, graded_by, timestamps, unique(learn_assignment_id, student_id))`, `learn_rubric_scores(id, learn_submission_id, learn_rubric_criterion_id, points_earned, timestamps, unique(learn_submission_id, learn_rubric_criterion_id))`.

- [ ] **Step 1: Write the failing schema test**

```php
<?php

namespace Tests\Feature\Learn;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearnAssignmentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_learn_assignments_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_assignments'));
        $this->assertTrue(Schema::hasColumns('learn_assignments', [
            'id', 'title', 'instructions', 'submission_type', 'points_possible', 'due_at',
        ]));
    }

    public function test_learn_rubrics_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_rubrics'));
        $this->assertTrue(Schema::hasColumns('learn_rubrics', ['id', 'learn_assignment_id']));
    }

    public function test_learn_rubric_criteria_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_rubric_criteria'));
        $this->assertTrue(Schema::hasColumns('learn_rubric_criteria', [
            'id', 'learn_rubric_id', 'description', 'max_points', 'position',
        ]));
    }

    public function test_learn_submissions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_submissions'));
        $this->assertTrue(Schema::hasColumns('learn_submissions', [
            'id', 'learn_assignment_id', 'student_id', 'text_body', 'learn_file_id', 'link_url',
            'submitted_at', 'score', 'feedback_comment', 'graded_at', 'graded_by',
        ]));
    }

    public function test_learn_rubric_scores_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_rubric_scores'));
        $this->assertTrue(Schema::hasColumns('learn_rubric_scores', [
            'id', 'learn_submission_id', 'learn_rubric_criterion_id', 'points_earned',
        ]));
    }

    public function test_learn_submissions_one_per_assignment_per_student(): void
    {
        \Illuminate\Support\Facades\DB::table('learn_assignments')->insert([
            'title' => 'A', 'submission_type' => 'text', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \Illuminate\Support\Facades\DB::table('learn_submissions')->insert([
            ['learn_assignment_id' => 1, 'student_id' => 1, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ['learn_assignment_id' => 1, 'student_id' => 1, 'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnAssignmentSchemaTest.php"`
Expected: FAIL — tables don't exist.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_08_09_100001_create_learn_assignments_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('instructions')->nullable();
            $table->enum('submission_type', ['text', 'file', 'link']);
            $table->decimal('points_possible', 6, 2)->nullable()
                  ->comment('Ignored when a learn_rubrics row exists for this assignment');
            $table->timestamp('due_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_assignments');
    }
};
```

`database/migrations/2026_08_09_100002_create_learn_rubrics_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_rubrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_assignment_id')->unique()->constrained('learn_assignments')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_rubrics');
    }
};
```

`database/migrations/2026_08_09_100003_create_learn_rubric_criteria_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_rubric_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_rubric_id')->constrained('learn_rubrics')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('max_points', 6, 2);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['learn_rubric_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_rubric_criteria');
    }
};
```

`database/migrations/2026_08_09_100004_create_learn_submissions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_assignment_id')->constrained('learn_assignments')->cascadeOnDelete();
            $table->unsignedInteger('student_id')
                  ->comment('references students.id — app-level constraint (MyISAM cannot be FK target)');
            $table->longText('text_body')->nullable();
            $table->foreignId('learn_file_id')->nullable()->constrained('learn_files')->nullOnDelete();
            $table->string('link_url')->nullable();
            $table->timestamp('submitted_at');
            $table->decimal('score', 6, 2)->nullable();
            $table->text('feedback_comment')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['learn_assignment_id', 'student_id'], 'learn_submissions_assignment_student_unique');
            $table->index(['student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_submissions');
    }
};
```

`database/migrations/2026_08_09_100005_create_learn_rubric_scores_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_rubric_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_submission_id')->constrained('learn_submissions')->cascadeOnDelete();
            $table->foreignId('learn_rubric_criterion_id')->constrained('learn_rubric_criteria')->cascadeOnDelete();
            $table->decimal('points_earned', 6, 2);
            $table->timestamps();

            $table->unique(['learn_submission_id', 'learn_rubric_criterion_id'], 'learn_rubric_scores_submission_criterion_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_rubric_scores');
    }
};
```

- [ ] **Step 4: Run migrations and the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_09_100001_create_learn_assignments_table.php --path=database/migrations/2026_08_09_100002_create_learn_rubrics_table.php --path=database/migrations/2026_08_09_100003_create_learn_rubric_criteria_table.php --path=database/migrations/2026_08_09_100004_create_learn_submissions_table.php --path=database/migrations/2026_08_09_100005_create_learn_rubric_scores_table.php --force"`
Then: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnAssignmentSchemaTest.php"`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_09_100001_create_learn_assignments_table.php \
        database/migrations/2026_08_09_100002_create_learn_rubrics_table.php \
        database/migrations/2026_08_09_100003_create_learn_rubric_criteria_table.php \
        database/migrations/2026_08_09_100004_create_learn_submissions_table.php \
        database/migrations/2026_08_09_100005_create_learn_rubric_scores_table.php \
        tests/Feature/Learn/LearnAssignmentSchemaTest.php
git commit -m "feat(learn): add Phase 2 schema — assignments, rubrics, submissions, rubric scores"
```

---

### Task 2: Models — Assignment, Rubric, RubricCriterion, Submission, RubricScore

**Files:**
- Create: `app/Models/Learn/Assignment.php`
- Create: `app/Models/Learn/Rubric.php`
- Create: `app/Models/Learn/RubricCriterion.php`
- Create: `app/Models/Learn/Submission.php`
- Create: `app/Models/Learn/RubricScore.php`
- Test: `tests/Feature/Learn/AssignmentModelTest.php`

**Interfaces:**
- Consumes: `App\Models\Learn\ModuleItem` (Phase 1, `morphOne`/`morphTo`), `App\Models\Learn\Course::canEdit()` (Phase 1).
- Produces: `Assignment::moduleItem()`, `Assignment::rubric(): HasOne`, `Assignment::submissions(): HasMany`, `Assignment::maxScore(): ?float`, `Assignment::course(): ?Course`, `Assignment::canEdit(User $user): bool`. `Rubric::criteria(): HasMany` (ordered by `position`). `Submission::assignment()`, `Submission::file()`, `Submission::gradedBy()`, `Submission::rubricScores(): HasMany`, `Submission::isLate(): bool`, `Submission::isGraded(): bool`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\Learn\Module;
use App\Models\Learn\RubricScore;
use App\Models\Learn\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentModelTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourseWithModule(): Module
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);

        return $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
    }

    private function attachAssignment(Module $module, array $attributes = []): Assignment
    {
        $assignment = Assignment::create(array_merge([
            'title' => 'Essay', 'submission_type' => 'text',
        ], $attributes));
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        return $assignment;
    }

    public function test_assignment_max_score_uses_points_possible_when_no_rubric(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module, ['points_possible' => 50]);

        $this->assertSame(50.0, $assignment->maxScore());
    }

    public function test_assignment_max_score_sums_rubric_criteria_when_rubric_exists(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module, ['points_possible' => 50]);
        $rubric = $assignment->rubric()->create([]);
        $rubric->criteria()->create(['description' => 'Grammar', 'max_points' => 10, 'position' => 0]);
        $rubric->criteria()->create(['description' => 'Content', 'max_points' => 20, 'position' => 1]);

        $this->assertSame(30.0, $assignment->fresh()->maxScore());
    }

    public function test_assignment_max_score_is_null_when_neither_set(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module);

        $this->assertNull($assignment->maxScore());
    }

    public function test_assignment_course_resolves_through_module_item(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module);

        $this->assertTrue($assignment->course()->is($module->course));
    }

    public function test_assignment_can_edit_delegates_to_course_can_edit(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module);
        $stranger = User::factory()->create();

        $this->assertFalse($assignment->canEdit($stranger));
    }

    public function test_submission_is_late_compares_submitted_at_to_assignment_due_at(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module, ['due_at' => '2026-01-10 23:59:00']);

        $onTime = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => 1,
            'submitted_at' => '2026-01-10 12:00:00',
        ]);
        $late = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => 2,
            'submitted_at' => '2026-01-11 08:00:00',
        ]);

        $this->assertFalse($onTime->isLate());
        $this->assertTrue($late->isLate());
    }

    public function test_submission_is_late_false_when_assignment_has_no_due_date(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module);

        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => 1,
            'submitted_at' => now(),
        ]);

        $this->assertFalse($submission->isLate());
    }

    public function test_submission_is_graded_reflects_graded_at(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module);

        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => 1, 'submitted_at' => now(),
        ]);

        $this->assertFalse($submission->isGraded());
        $submission->update(['graded_at' => now(), 'score' => 90]);
        $this->assertTrue($submission->fresh()->isGraded());
    }

    public function test_rubric_score_belongs_to_submission_and_criterion(): void
    {
        $module = $this->makeCourseWithModule();
        $assignment = $this->attachAssignment($module);
        $rubric = $assignment->rubric()->create([]);
        $criterion = $rubric->criteria()->create(['description' => 'Grammar', 'max_points' => 10, 'position' => 0]);
        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => 1, 'submitted_at' => now(),
        ]);

        $score = RubricScore::create([
            'learn_submission_id' => $submission->id, 'learn_rubric_criterion_id' => $criterion->id, 'points_earned' => 8,
        ]);

        $this->assertTrue($score->submission->is($submission));
        $this->assertTrue($score->criterion->is($criterion));
        $this->assertCount(1, $submission->rubricScores);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/AssignmentModelTest.php"`
Expected: FAIL — model classes don't exist.

- [ ] **Step 3: Write the models**

`app/Models/Learn/Assignment.php`:

```php
<?php

namespace App\Models\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Assignment extends Model
{
    protected $table = 'learn_assignments';

    protected $fillable = ['title', 'instructions', 'submission_type', 'points_possible', 'due_at'];

    protected $casts = [
        'points_possible' => 'decimal:2',
        'due_at' => 'datetime',
    ];

    public function moduleItem(): MorphOne
    {
        return $this->morphOne(ModuleItem::class, 'itemable');
    }

    public function rubric(): HasOne
    {
        return $this->hasOne(Rubric::class, 'learn_assignment_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'learn_assignment_id');
    }

    /** Rubric total (when a rubric exists) takes precedence over points_possible; never both. */
    public function maxScore(): ?float
    {
        $rubric = $this->rubric;
        if ($rubric) {
            return (float) $rubric->criteria()->sum('max_points');
        }

        return $this->points_possible !== null ? (float) $this->points_possible : null;
    }

    /** The Learn course this assignment belongs to, resolved through its module item. */
    public function course(): ?Course
    {
        return $this->moduleItem?->module?->course;
    }

    public function canEdit(User $user): bool
    {
        return $this->course()?->canEdit($user) ?? false;
    }
}
```

`app/Models/Learn/Rubric.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rubric extends Model
{
    protected $table = 'learn_rubrics';

    protected $fillable = ['learn_assignment_id'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'learn_assignment_id');
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class, 'learn_rubric_id')->orderBy('position');
    }
}
```

`app/Models/Learn/RubricCriterion.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricCriterion extends Model
{
    protected $table = 'learn_rubric_criteria';

    protected $fillable = ['learn_rubric_id', 'description', 'max_points', 'position'];

    protected $casts = [
        'max_points' => 'decimal:2',
        'position' => 'integer',
    ];

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(Rubric::class, 'learn_rubric_id');
    }
}
```

`app/Models/Learn/Submission.php`:

```php
<?php

namespace App\Models\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Submission extends Model
{
    protected $table = 'learn_submissions';

    protected $fillable = [
        'learn_assignment_id', 'student_id', 'text_body', 'learn_file_id', 'link_url',
        'submitted_at', 'score', 'feedback_comment', 'graded_at', 'graded_by',
    ];

    protected $casts = [
        'student_id' => 'integer',
        'submitted_at' => 'datetime',
        'score' => 'decimal:2',
        'graded_at' => 'datetime',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class, 'learn_assignment_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'learn_file_id');
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    public function rubricScores(): HasMany
    {
        return $this->hasMany(RubricScore::class, 'learn_submission_id');
    }

    public function isLate(): bool
    {
        if (! $this->assignment->due_at) {
            return false;
        }

        return $this->submitted_at->gt($this->assignment->due_at);
    }

    public function isGraded(): bool
    {
        return $this->graded_at !== null;
    }
}
```

`app/Models/Learn/RubricScore.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricScore extends Model
{
    protected $table = 'learn_rubric_scores';

    protected $fillable = ['learn_submission_id', 'learn_rubric_criterion_id', 'points_earned'];

    protected $casts = [
        'points_earned' => 'decimal:2',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class, 'learn_submission_id');
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(RubricCriterion::class, 'learn_rubric_criterion_id');
    }
}
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/AssignmentModelTest.php"`
Expected: PASS (9 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Learn/Assignment.php app/Models/Learn/Rubric.php app/Models/Learn/RubricCriterion.php \
        app/Models/Learn/Submission.php app/Models/Learn/RubricScore.php tests/Feature/Learn/AssignmentModelTest.php
git commit -m "feat(learn): add Assignment/Rubric/RubricCriterion/Submission/RubricScore models"
```

---

### Task 3: Author an assignment (ModuleItemController::storeAssignment) + routes

**Files:**
- Modify: `app/Http/Controllers/Learn/ModuleItemController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/ModuleItemAssignmentControllerTest.php`

**Interfaces:**
- Consumes: `Course::canEdit()`, `Assignment::create()`, `Assignment::rubric()`, `Rubric::criteria()`, the existing private `attachItem(Module $module, $itemable): ModuleItem` helper already in `ModuleItemController`.
- Produces route: `learn.items.store-assignment` (POST `/learn/modules/{module}/items/assignment`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleItemAssignmentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private User $teacher;
    private User $stranger;
    private $module;

    protected function setUp(): void
    {
        parent::setUp();

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);

        $this->teacher = User::factory()->create();
        $this->stranger = User::factory()->create();

        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);

        $this->course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $this->module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
    }

    public function test_instructor_can_add_a_flat_points_assignment(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-assignment', $this->module), [
            'title' => 'Essay 1', 'instructions' => '<p>Write 500 words.</p>',
            'submission_type' => 'text', 'points_possible' => 50, 'due_at' => '2026-02-01 23:59:00',
        ])->assertRedirect();

        $this->assertDatabaseHas('learn_assignments', ['title' => 'Essay 1', 'points_possible' => 50]);
        $this->assertDatabaseHas('learn_module_items', [
            'learn_module_id' => $this->module->id, 'itemable_type' => \App\Models\Learn\Assignment::class,
        ]);
        $this->assertDatabaseMissing('learn_rubrics', ['learn_assignment_id' => \App\Models\Learn\Assignment::first()->id]);
    }

    public function test_instructor_can_add_a_rubric_assignment_and_points_possible_is_ignored(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-assignment', $this->module), [
            'title' => 'Lab Report', 'submission_type' => 'file', 'points_possible' => 999,
            'rubric_criteria' => [
                ['description' => 'Grammar', 'max_points' => 10],
                ['description' => 'Content', 'max_points' => 20],
            ],
        ])->assertRedirect();

        $assignment = \App\Models\Learn\Assignment::where('title', 'Lab Report')->firstOrFail();
        $this->assertNull($assignment->points_possible);
        $this->assertSame(30.0, $assignment->maxScore());
        $this->assertCount(2, $assignment->rubric->criteria);
    }

    public function test_stranger_cannot_add_an_assignment(): void
    {
        $this->actingAs($this->stranger)->post(route('learn.items.store-assignment', $this->module), [
            'title' => 'Hack', 'submission_type' => 'text',
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ModuleItemAssignmentControllerTest.php"`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Add `storeAssignment` to `ModuleItemController`**

Add this import near the top of `app/Http/Controllers/Learn/ModuleItemController.php`, alongside the existing `use App\Models\Learn\Page;`:

```php
use App\Models\Learn\Assignment;
```

Add this method to the class, after `storeFile`:

```php
    /** POST /learn/modules/{module}/items/assignment */
    public function storeAssignment(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'submission_type' => 'required|in:text,file,link',
            'points_possible' => 'nullable|numeric|min:0',
            'due_at' => 'nullable|date',
            'rubric_criteria' => 'nullable|array',
            'rubric_criteria.*.description' => 'required_with:rubric_criteria|string|max:255',
            'rubric_criteria.*.max_points' => 'required_with:rubric_criteria|numeric|min:0',
        ]);

        $hasRubric = ! empty($validated['rubric_criteria']);

        $assignment = Assignment::create([
            'title' => $validated['title'],
            'instructions' => $validated['instructions'] ?? null,
            'submission_type' => $validated['submission_type'],
            'points_possible' => $hasRubric ? null : ($validated['points_possible'] ?? null),
            'due_at' => $validated['due_at'] ?? null,
        ]);

        if ($hasRubric) {
            $rubric = $assignment->rubric()->create([]);
            foreach ($validated['rubric_criteria'] as $position => $criterion) {
                $rubric->criteria()->create([
                    'description' => $criterion['description'],
                    'max_points' => $criterion['max_points'],
                    'position' => $position,
                ]);
            }
        }

        $this->attachItem($module, $assignment);

        return back()->with('success', 'Assignment added.');
    }
```

- [ ] **Step 4: Add the route**

Add inside the `learn.` route group in `routes/web.php`, immediately after the `items.store-file` line:

```php
    Route::post('/modules/{module}/items/assignment', [\App\Http\Controllers\Learn\ModuleItemController::class, 'storeAssignment'])->name('items.store-assignment');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ModuleItemAssignmentControllerTest.php"`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/ModuleItemController.php routes/web.php \
        tests/Feature/Learn/ModuleItemAssignmentControllerTest.php
git commit -m "feat(learn): add assignment authoring with optional rubric"
```

---

### Task 4: Fix item-type serialization + surface assignments in course views

**Files:**
- Modify: `app/Http/Controllers/Learn/CourseController.php`
- Modify: `app/Http/Controllers/StudentPortal/LearnController.php`
- Test: `tests/Feature/Learn/CourseAssignmentSerializationTest.php`
- Test: `tests/Feature/StudentPortal/LearnAssignmentSerializationTest.php`

**Interfaces:**
- Consumes: `Assignment::maxScore()`, `Assignment::rubric` (Task 2), `Submission` (Task 2, student-side only).
- Fixes: the pre-existing `$itemable instanceof LearnPage ? 'page' : 'file'` bug (documented in Global Constraints) — replaced with an explicit three-way `match(true)` covering `Page`/`File`/`Assignment`, with a `'unknown'` fallback instead of silently mislabeling.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Learn/CourseAssignmentSerializationTest.php`:

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseAssignmentSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_serializes_assignment_item_type_and_rubric(): void
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $teacher = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);

        $assignment = Assignment::create(['title' => 'Essay', 'submission_type' => 'text', 'points_possible' => 40]);
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        $rubricAssignment = Assignment::create(['title' => 'Lab', 'submission_type' => 'file']);
        $rubricAssignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 1]);
        $rubric = $rubricAssignment->rubric()->create([]);
        $rubric->criteria()->create(['description' => 'Accuracy', 'max_points' => 15, 'position' => 0]);

        $response = $this->actingAs($teacher)->get(route('learn.show', $course));

        $response->assertInertia(fn ($page) => $page
            ->where('course.modules.0.items.0.type', 'assignment')
            ->where('course.modules.0.items.0.assignment.max_score', 40.0)
            ->where('course.modules.0.items.0.assignment.has_rubric', false)
            ->where('course.modules.0.items.1.type', 'assignment')
            ->where('course.modules.0.items.1.assignment.max_score', 15.0)
            ->where('course.modules.0.items.1.assignment.has_rubric', true)
        );
    }
}
```

`tests/Feature/StudentPortal/LearnAssignmentSerializationTest.php`:

```php
<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\Learn\Submission;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearnAssignmentSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_serializes_assignment_with_the_students_own_submission(): void
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'status' => 'published',
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $assignment = Assignment::create(['title' => 'Essay', 'submission_type' => 'text', 'points_possible' => 40]);
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);

        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'PS000000101', 'lastname' => 'Cruz', 'firstname' => 'Juan', 'sex' => 'M',
        ]);
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
        Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $studentId,
            'text_body' => 'My essay text', 'submitted_at' => now(),
        ]);

        session(['student_pisaysystemID' => 'PS000000101']);

        $response = $this->get(route('student-portal.learn.show', $course));

        $response->assertInertia(fn ($page) => $page
            ->where('course.modules.0.items.0.type', 'assignment')
            ->where('course.modules.0.items.0.assignment.submission.text_body', 'My essay text')
            ->where('course.modules.0.items.0.assignment.submission.is_graded', false)
        );
    }
}
```

- [ ] **Step 2: Run to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseAssignmentSerializationTest.php tests/Feature/StudentPortal/LearnAssignmentSerializationTest.php"`
Expected: FAIL — `type` resolves to `'file'` for assignment items (the pre-existing bug), and there is no `assignment` key in the payload.

- [ ] **Step 3: Fix `CourseController`**

In `app/Http/Controllers/Learn/CourseController.php`, add the import:

```php
use App\Models\Learn\Assignment;
```

Replace the `show()` method's eager load and the whole `serializeItem` method:

```php
    /** GET /learn/{course} */
    public function show(Course $course): Response
    {
        $user = Auth::user();
        abort_unless($course->canView($user), 403);

        $course->load(['subject', 'section', 'schoolYear', 'modules.items.itemable', 'announcements.postedBy']);
        $this->loadAssignmentRubrics($course);

        return Inertia::render('Learn/Show', [
            'course' => $this->serializeCourse($course, $user),
        ]);
    }
```

```php
    private function loadAssignmentRubrics(Course $course): void
    {
        foreach ($course->modules as $module) {
            foreach ($module->items as $item) {
                if ($item->itemable instanceof Assignment) {
                    $item->itemable->load('rubric.criteria');
                }
            }
        }
    }

    private function serializeItem($item): array
    {
        $itemable = $item->itemable;

        $type = match (true) {
            $itemable instanceof LearnPage => 'page',
            $itemable instanceof LearnFile => 'file',
            $itemable instanceof Assignment => 'assignment',
            default => 'unknown',
        };

        return [
            'id' => $item->id,
            'type' => $type,
            'position' => $item->position,
            'is_published' => $item->isPublished(),
            'title' => $itemable?->title,
            'body' => $itemable instanceof LearnPage ? $itemable->body : null,
            'video_url' => $itemable instanceof LearnPage ? $itemable->video_url : null,
            'file_url' => $itemable instanceof LearnFile
                ? route('learn.files.show', ['fileId' => $this->files->encodeFileId($itemable->s3_key)])
                : null,
            'assignment' => $itemable instanceof Assignment ? [
                'id' => $itemable->id,
                'instructions' => $itemable->instructions,
                'submission_type' => $itemable->submission_type,
                'due_at' => $itemable->due_at?->toIso8601String(),
                'max_score' => $itemable->maxScore(),
                'has_rubric' => $itemable->rubric !== null,
            ] : null,
        ];
    }
```

- [ ] **Step 4: Fix `StudentPortal\LearnController`**

In `app/Http/Controllers/StudentPortal/LearnController.php`, add imports:

```php
use App\Models\Learn\Assignment;
use App\Models\Learn\Submission;
```

Replace the `show()` method and `serializeItem`:

```php
    /** GET /student-portal/learn/{course} */
    public function show(Course $course): Response
    {
        $student = $this->currentStudent();
        abort_unless($course->isVisibleToStudent($student->id), 403);

        $course->load([
            'subject', 'section',
            'modules' => fn ($q) => $q->whereNotNull('published_at'),
            'modules.items' => fn ($q) => $q->whereNotNull('published_at'),
            'modules.items.itemable',
            'announcements.postedBy',
        ]);
        $this->loadAssignmentRubrics($course);

        return Inertia::render('StudentPortal/Learn/Show', [
            'course' => [
                'id' => $course->id,
                'subject_name' => $course->subject->name,
                'section_name' => $course->section->sectionname,
                'syllabus_body' => $course->syllabus_body,
                'modules' => $course->modules->map(fn ($m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'items' => $m->items->map(fn ($i) => $this->serializeItem($i, $student->id))->values(),
                ])->values(),
                'announcements' => $course->announcements->map(fn ($a) => [
                    'title' => $a->title,
                    'body' => $a->body,
                    'posted_by' => $a->postedBy?->name,
                    'posted_at' => $a->posted_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }
```

```php
    private function loadAssignmentRubrics(Course $course): void
    {
        foreach ($course->modules as $module) {
            foreach ($module->items as $item) {
                if ($item->itemable instanceof Assignment) {
                    $item->itemable->load('rubric.criteria');
                }
            }
        }
    }

    private function serializeItem($item, int $studentId): array
    {
        $itemable = $item->itemable;

        $type = match (true) {
            $itemable instanceof LearnPage => 'page',
            $itemable instanceof LearnFile => 'file',
            $itemable instanceof Assignment => 'assignment',
            default => 'unknown',
        };

        $assignmentData = null;
        if ($itemable instanceof Assignment) {
            $submission = Submission::where('learn_assignment_id', $itemable->id)
                ->where('student_id', $studentId)
                ->first();

            $assignmentData = [
                'id' => $itemable->id,
                'instructions' => $itemable->instructions,
                'submission_type' => $itemable->submission_type,
                'due_at' => $itemable->due_at?->toIso8601String(),
                'max_score' => $itemable->maxScore(),
                'submission' => $submission ? [
                    'id' => $submission->id,
                    'text_body' => $submission->text_body,
                    'link_url' => $submission->link_url,
                    'file_url' => $submission->learn_file_id
                        ? route('student-portal.learn.submissions.file', $submission->id)
                        : null,
                    'submitted_at' => $submission->submitted_at->toIso8601String(),
                    'score' => $submission->score !== null ? (float) $submission->score : null,
                    'feedback_comment' => $submission->feedback_comment,
                    'is_graded' => $submission->isGraded(),
                    'is_late' => $submission->isLate(),
                ] : null,
            ];
        }

        return [
            'id' => $item->id,
            'type' => $type,
            'title' => $itemable?->title,
            'body' => $itemable instanceof LearnPage ? $itemable->body : null,
            'video_url' => $itemable instanceof LearnPage ? $itemable->video_url : null,
            'file_url' => $itemable instanceof LearnFile
                ? route('student-portal.learn.file', ['fileId' => $this->files->encodeFileId($itemable->s3_key)])
                : null,
            'assignment' => $assignmentData,
        ];
    }
```

Note: `route('student-portal.learn.submissions.file', ...)` is defined in Task 7 — this task's tests only assert on `text_body`/`is_graded`, not `file_url`, so the route not existing yet does not break this task's tests (the ternary only calls `route()` when `learn_file_id` is set, and this task's fixtures use `submission_type: 'text'`).

- [ ] **Step 5: Run the tests**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseAssignmentSerializationTest.php tests/Feature/StudentPortal/LearnAssignmentSerializationTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 6: Run Phase 1's existing controller tests to confirm no regression**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseControllerTest.php tests/Feature/StudentPortal/LearnControllerTest.php"`
Expected: PASS (all previously-passing tests still pass — the type-detection fix must not change behavior for Page/File items).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Learn/CourseController.php app/Http/Controllers/StudentPortal/LearnController.php \
        tests/Feature/Learn/CourseAssignmentSerializationTest.php tests/Feature/StudentPortal/LearnAssignmentSerializationTest.php
git commit -m "fix(learn): correct 3-way item-type detection and surface assignments in course views"
```

---

### Task 5: SubmissionRosterService

**Files:**
- Create: `app/Services/Learn/SubmissionRosterService.php`
- Test: `tests/Feature/Learn/SubmissionRosterServiceTest.php`

**Interfaces:**
- Consumes: `Assignment::course()` (Task 2), `App\Models\Registrar\StudentEnrollment` (Phase 1), `Submission` (Task 2).
- Produces: `SubmissionRosterService::rosterFor(Assignment $assignment): Collection<int, array{student_id:int, name:string, submission_id:?int, status:string}>`. `status` is one of `not_submitted`/`submitted`/`late`/`graded`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\Learn\Submission;
use App\Models\Registrar\StudentEnrollment;
use App\Services\Learn\SubmissionRosterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubmissionRosterServiceTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private Assignment $assignment;
    private SchoolYear $sy;
    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $this->section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $this->course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $this->assignment = Assignment::create([
            'title' => 'Essay', 'submission_type' => 'text', 'points_possible' => 40,
            'due_at' => '2026-01-10 23:59:00',
        ]);
        $this->assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
    }

    private function enrollStudent(string $lastname): int
    {
        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'PS' . str_pad((string) $studentId ?? rand(1, 999999999), 9, '0', STR_PAD_LEFT),
            'lastname' => $lastname, 'firstname' => 'Test', 'sex' => 'M',
        ]);
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $this->sy->id, 'section_id' => $this->section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);

        return $studentId;
    }

    public function test_roster_shows_not_submitted_submitted_late_and_graded_statuses(): void
    {
        $notSubmitted = $this->enrollStudent('Alpha');
        $submittedOnTime = $this->enrollStudent('Bravo');
        $submittedLate = $this->enrollStudent('Charlie');
        $graded = $this->enrollStudent('Delta');

        Submission::create([
            'learn_assignment_id' => $this->assignment->id, 'student_id' => $submittedOnTime,
            'text_body' => 'x', 'submitted_at' => '2026-01-10 08:00:00',
        ]);
        Submission::create([
            'learn_assignment_id' => $this->assignment->id, 'student_id' => $submittedLate,
            'text_body' => 'x', 'submitted_at' => '2026-01-11 08:00:00',
        ]);
        Submission::create([
            'learn_assignment_id' => $this->assignment->id, 'student_id' => $graded,
            'text_body' => 'x', 'submitted_at' => '2026-01-09 08:00:00',
            'score' => 35, 'graded_at' => now(),
        ]);

        $roster = app(SubmissionRosterService::class)->rosterFor($this->assignment);

        $this->assertCount(4, $roster);
        $byStudent = $roster->keyBy('student_id');
        $this->assertSame('not_submitted', $byStudent[$notSubmitted]['status']);
        $this->assertSame('submitted', $byStudent[$submittedOnTime]['status']);
        $this->assertSame('late', $byStudent[$submittedLate]['status']);
        $this->assertSame('graded', $byStudent[$graded]['status']);
    }

    public function test_roster_excludes_students_not_enrolled_in_the_course_section(): void
    {
        $enrolled = $this->enrollStudent('Enrolled');

        $otherSection = Section::create([
            'levelid' => 8, 'sectionname' => 'Ruby', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
        $notEnrolledId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'PS000000999', 'lastname' => 'Outside', 'firstname' => 'Test', 'sex' => 'F',
        ]);
        StudentEnrollment::create([
            'student_id' => $notEnrolledId, 'school_year_id' => $this->sy->id, 'section_id' => $otherSection->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);

        $roster = app(SubmissionRosterService::class)->rosterFor($this->assignment);

        $this->assertCount(1, $roster);
        $this->assertSame($enrolled, $roster->first()['student_id']);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/SubmissionRosterServiceTest.php"`
Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Write `SubmissionRosterService`**

`app/Services/Learn/SubmissionRosterService.php`:

```php
<?php

namespace App\Services\Learn;

use App\Models\Learn\Assignment;
use App\Models\Learn\Submission;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the per-assignment grading roster: every enrolled student in the
 * assignment's course, left-joined against their submission (if any) — same
 * shape as Phase 1's CourseResolver/RosterService compute-on-read pattern.
 */
class SubmissionRosterService
{
    /** @return Collection<int, array{student_id:int, name:string, submission_id:?int, status:string}> */
    public function rosterFor(Assignment $assignment): Collection
    {
        $course = $assignment->course();
        if (! $course) {
            return collect();
        }

        $studentIds = StudentEnrollment::where('school_year_id', $course->school_year_id)
            ->where('section_id', $course->section_id)
            ->where('status', 'enrolled')
            ->pluck('student_id')
            ->unique()
            ->values();

        $students = DB::table('students')->whereIn('id', $studentIds)->get(['id', 'lastname', 'firstname'])->keyBy('id');

        $submissions = Submission::where('learn_assignment_id', $assignment->id)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');

        return $studentIds->map(function ($studentId) use ($students, $submissions) {
            $student = $students->get($studentId);
            $submission = $submissions->get($studentId);

            return [
                'student_id' => (int) $studentId,
                'name' => $student ? trim("{$student->lastname}, {$student->firstname}") : "Student #{$studentId}",
                'submission_id' => $submission?->id,
                'status' => $this->statusFor($submission),
            ];
        })->sortBy('name')->values();
    }

    private function statusFor(?Submission $submission): string
    {
        if (! $submission) {
            return 'not_submitted';
        }
        if ($submission->isGraded()) {
            return 'graded';
        }

        return $submission->isLate() ? 'late' : 'submitted';
    }
}
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/SubmissionRosterServiceTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Learn/SubmissionRosterService.php tests/Feature/Learn/SubmissionRosterServiceTest.php
git commit -m "feat(learn): add SubmissionRosterService for the per-assignment grading roster"
```

---

### Task 6: Instructor grading — AssignmentGradingController + routes

**Files:**
- Create: `app/Http/Controllers/Learn/AssignmentGradingController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/AssignmentGradingControllerTest.php`

**Interfaces:**
- Consumes: `SubmissionRosterService::rosterFor()` (Task 5), `Assignment::canEdit()`/`maxScore()`/`rubric` (Task 2), `CourseFileService::streamResponse()` (Phase 1).
- Produces routes: `learn.assignments.submissions` (GET), `learn.submissions.grade` (PUT), `learn.submissions.reopen` (POST), `learn.submissions.file` (GET).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\Learn\Submission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AssignmentGradingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private User $teacher;
    private User $stranger;
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);

        $this->teacher = User::factory()->create();
        $this->stranger = User::factory()->create();
        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);

        $this->course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);

        $this->studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'PS000000201', 'lastname' => 'Cruz', 'firstname' => 'Juan', 'sex' => 'M',
        ]);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
    }

    private function makeFlatAssignment(): Assignment
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $assignment = Assignment::create(['title' => 'Essay', 'submission_type' => 'text', 'points_possible' => 40]);
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        return $assignment;
    }

    private function makeRubricAssignment(): Assignment
    {
        $module = $this->course->modules()->create(['title' => 'Week 2', 'position' => 1]);
        $assignment = Assignment::create(['title' => 'Lab', 'submission_type' => 'text']);
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
        $rubric = $assignment->rubric()->create([]);
        $rubric->criteria()->create(['description' => 'Grammar', 'max_points' => 10, 'position' => 0]);
        $rubric->criteria()->create(['description' => 'Content', 'max_points' => 20, 'position' => 1]);

        return $assignment->fresh();
    }

    public function test_index_shows_roster_and_403s_for_non_instructor(): void
    {
        $assignment = $this->makeFlatAssignment();

        $response = $this->actingAs($this->teacher)->get(route('learn.assignments.submissions', $assignment));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Learn/Grading')
            ->has('roster', 1)
            ->where('roster.0.status', 'not_submitted')
        );

        $this->actingAs($this->stranger)->get(route('learn.assignments.submissions', $assignment))->assertForbidden();
    }

    public function test_instructor_can_grade_a_flat_points_submission(): void
    {
        $assignment = $this->makeFlatAssignment();
        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $this->studentId,
            'text_body' => 'My essay', 'submitted_at' => now(),
        ]);

        $this->actingAs($this->teacher)
            ->put(route('learn.submissions.grade', $submission), ['score' => 35, 'feedback_comment' => 'Good work'])
            ->assertRedirect();

        $submission->refresh();
        $this->assertSame('35.00', $submission->score);
        $this->assertSame('Good work', $submission->feedback_comment);
        $this->assertNotNull($submission->graded_at);
        $this->assertSame($this->teacher->id, $submission->graded_by);
    }

    public function test_flat_points_score_cannot_exceed_points_possible(): void
    {
        $assignment = $this->makeFlatAssignment();
        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $this->studentId,
            'text_body' => 'My essay', 'submitted_at' => now(),
        ]);

        $this->actingAs($this->teacher)
            ->put(route('learn.submissions.grade', $submission), ['score' => 999])
            ->assertSessionHasErrors('score');
    }

    public function test_instructor_can_grade_a_rubric_submission_and_score_is_the_sum(): void
    {
        $assignment = $this->makeRubricAssignment();
        $criteria = $assignment->rubric->criteria;
        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $this->studentId,
            'text_body' => 'My lab', 'submitted_at' => now(),
        ]);

        $this->actingAs($this->teacher)->put(route('learn.submissions.grade', $submission), [
            'rubric_scores' => [
                $criteria[0]->id => 8,
                $criteria[1]->id => 18,
            ],
            'feedback_comment' => 'Nice',
        ])->assertRedirect();

        $submission->refresh();
        $this->assertSame('26.00', $submission->score);
        $this->assertCount(2, $submission->rubricScores);
    }

    public function test_reopen_clears_grade_and_unlocks_the_submission(): void
    {
        $assignment = $this->makeFlatAssignment();
        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $this->studentId,
            'text_body' => 'My essay', 'submitted_at' => now(),
            'score' => 30, 'graded_at' => now(), 'graded_by' => $this->teacher->id,
        ]);

        $this->actingAs($this->teacher)->post(route('learn.submissions.reopen', $submission))->assertRedirect();

        $submission->refresh();
        $this->assertNull($submission->score);
        $this->assertNull($submission->graded_at);
        $this->assertNull($submission->graded_by);
    }

    public function test_stranger_cannot_grade_or_reopen(): void
    {
        $assignment = $this->makeFlatAssignment();
        $submission = Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $this->studentId,
            'text_body' => 'My essay', 'submitted_at' => now(),
        ]);

        $this->actingAs($this->stranger)
            ->put(route('learn.submissions.grade', $submission), ['score' => 10])
            ->assertForbidden();
        $this->actingAs($this->stranger)
            ->post(route('learn.submissions.reopen', $submission))
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/AssignmentGradingControllerTest.php"`
Expected: FAIL — controller/routes don't exist.

- [ ] **Step 3: Write `AssignmentGradingController`**

`app/Http/Controllers/Learn/AssignmentGradingController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Assignment;
use App\Models\Learn\RubricScore;
use App\Models\Learn\Submission;
use App\Services\Learn\CourseFileService;
use App\Services\Learn\SubmissionRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentGradingController extends Controller
{
    public function __construct(
        private SubmissionRosterService $roster,
        private CourseFileService $files,
    ) {
    }

    /** GET /learn/assignments/{assignment}/submissions */
    public function index(Assignment $assignment): Response
    {
        $user = Auth::user();
        abort_unless($assignment->canEdit($user), 403);

        $assignment->load('rubric.criteria');

        $submissions = Submission::where('learn_assignment_id', $assignment->id)
            ->with('rubricScores')
            ->get()
            ->keyBy('student_id');

        $roster = $this->roster->rosterFor($assignment)->map(function ($row) use ($submissions) {
            $submission = $submissions->get($row['student_id']);

            return array_merge($row, [
                'text_body' => $submission?->text_body,
                'link_url' => $submission?->link_url,
                'file_url' => $submission?->learn_file_id
                    ? route('learn.submissions.file', $submission->id)
                    : null,
                'submitted_at' => $submission?->submitted_at?->toIso8601String(),
                'score' => $submission?->score !== null ? (float) $submission->score : null,
                'feedback_comment' => $submission?->feedback_comment,
                'is_graded' => $submission?->isGraded() ?? false,
                'rubric_scores' => $submission
                    ? $submission->rubricScores->mapWithKeys(
                        fn ($rs) => [(string) $rs->learn_rubric_criterion_id => (float) $rs->points_earned]
                    )
                    : (object) [],
            ]);
        });

        return Inertia::render('Learn/Grading', [
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'submission_type' => $assignment->submission_type,
                'max_score' => $assignment->maxScore(),
                'rubric' => $assignment->rubric ? [
                    'criteria' => $assignment->rubric->criteria->map(fn ($c) => [
                        'id' => $c->id, 'description' => $c->description, 'max_points' => (float) $c->max_points,
                    ])->values(),
                ] : null,
            ],
            'roster' => $roster->values(),
        ]);
    }

    /** PUT /learn/submissions/{submission}/grade */
    public function grade(Request $request, Submission $submission)
    {
        $user = Auth::user();
        $assignment = $submission->assignment;
        abort_unless($assignment->canEdit($user), 403);

        $rubric = $assignment->rubric;

        if ($rubric) {
            $criteria = $rubric->criteria;
            $criterionIds = $criteria->pluck('id')->all();

            $validated = $request->validate([
                'rubric_scores' => 'required|array',
                'rubric_scores.*' => 'required|numeric|min:0',
                'feedback_comment' => 'nullable|string',
            ]);

            $total = 0;
            foreach ($validated['rubric_scores'] as $criterionId => $points) {
                abort_unless(in_array((int) $criterionId, $criterionIds, true), 422);
                $criterion = $criteria->firstWhere('id', (int) $criterionId);
                abort_if((float) $points > (float) $criterion->max_points, 422);

                RubricScore::updateOrCreate(
                    ['learn_submission_id' => $submission->id, 'learn_rubric_criterion_id' => (int) $criterionId],
                    ['points_earned' => $points]
                );
                $total += $points;
            }

            $submission->update([
                'score' => $total,
                'feedback_comment' => $validated['feedback_comment'] ?? null,
                'graded_at' => now(),
                'graded_by' => $user->id,
            ]);
        } else {
            $rules = ['score' => ['required', 'numeric', 'min:0']];
            if ($assignment->points_possible !== null) {
                $rules['score'][] = 'max:' . $assignment->points_possible;
            }
            $rules['feedback_comment'] = 'nullable|string';

            $validated = $request->validate($rules);

            $submission->update([
                'score' => $validated['score'],
                'feedback_comment' => $validated['feedback_comment'] ?? null,
                'graded_at' => now(),
                'graded_by' => $user->id,
            ]);
        }

        return back()->with('success', 'Submission graded.');
    }

    /** POST /learn/submissions/{submission}/reopen */
    public function reopen(Submission $submission)
    {
        $user = Auth::user();
        abort_unless($submission->assignment->canEdit($user), 403);

        $submission->rubricScores()->delete();
        $submission->update(['score' => null, 'feedback_comment' => null, 'graded_at' => null, 'graded_by' => null]);

        return back()->with('success', 'Submission reopened for resubmission.');
    }

    /** GET /learn/submissions/{submission}/file */
    public function file(Submission $submission)
    {
        $user = Auth::user();
        abort_unless($submission->assignment->canEdit($user), 403);
        abort_if(! $submission->file, 404);

        return $this->files->streamResponse($submission->file);
    }
}
```

- [ ] **Step 4: Add routes**

Add inside the `learn.` route group in `routes/web.php`, immediately after the `items.store-assignment` line from Task 3:

```php
    Route::get('/assignments/{assignment}/submissions', [\App\Http\Controllers\Learn\AssignmentGradingController::class, 'index'])->name('assignments.submissions');
    Route::put('/submissions/{submission}/grade', [\App\Http\Controllers\Learn\AssignmentGradingController::class, 'grade'])->name('submissions.grade');
    Route::post('/submissions/{submission}/reopen', [\App\Http\Controllers\Learn\AssignmentGradingController::class, 'reopen'])->name('submissions.reopen');
    Route::get('/submissions/{submission}/file', [\App\Http\Controllers\Learn\AssignmentGradingController::class, 'file'])->name('submissions.file');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/AssignmentGradingControllerTest.php"`
Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/AssignmentGradingController.php routes/web.php \
        tests/Feature/Learn/AssignmentGradingControllerTest.php
git commit -m "feat(learn): add instructor grading — roster, points/rubric grading, reopen, submission file proxy"
```

---

### Task 7: Student submission endpoints (Student Portal)

**Files:**
- Modify: `app/Http/Controllers/StudentPortal/LearnController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/StudentPortal/LearnSubmissionControllerTest.php`

**Interfaces:**
- Consumes: `Assignment::course()` (Task 2), `Course::isVisibleToStudent()` (Phase 1), `CourseFileService::upload()`/`streamResponse()` (Phase 1).
- Produces routes: `student-portal.learn.assignments.submit` (POST), `student-portal.learn.submissions.file` (GET).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\Learn\Submission;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LearnSubmissionControllerTest extends TestCase
{
    use RefreshDatabase;

    private static int $studentCounter = 0;

    private Course $course;
    private int $studentId;
    private string $studentPisaysystemID;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $term = AcademicTerm::create([
            'school_year_id' => $sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $section = Section::create([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'is_active' => true,
        ]);
        $subject = Subject::create([
            'school_year_id' => $sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $this->course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id, 'status' => 'published',
        ]);

        // students is ENGINE=MyISAM and ignores transactions — RefreshDatabase's
        // per-test rollback never applies to it, so a fixed ID collides across
        // this file's test methods within one process run.
        self::$studentCounter++;
        $this->studentPisaysystemID = 'PS' . str_pad((string) self::$studentCounter, 9, '0', STR_PAD_LEFT);
        $this->studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => $this->studentPisaysystemID, 'lastname' => 'Cruz', 'firstname' => 'Juan', 'sex' => 'M',
        ]);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);

        session(['student_pisaysystemID' => $this->studentPisaysystemID]);
    }

    private function makeAssignment(string $submissionType): Assignment
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $assignment = Assignment::create(['title' => 'Work', 'submission_type' => $submissionType, 'points_possible' => 40]);
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);

        return $assignment;
    }

    public function test_student_can_submit_a_text_assignment(): void
    {
        $assignment = $this->makeAssignment('text');

        $this->post(route('student-portal.learn.assignments.submit', $assignment), [
            'text_body' => 'My essay text',
        ])->assertRedirect();

        $this->assertDatabaseHas('learn_submissions', [
            'learn_assignment_id' => $assignment->id, 'student_id' => $this->studentId, 'text_body' => 'My essay text',
        ]);
    }

    public function test_student_can_submit_a_link_assignment(): void
    {
        $assignment = $this->makeAssignment('link');

        $this->post(route('student-portal.learn.assignments.submit', $assignment), [
            'link_url' => 'https://docs.google.com/document/d/abc123',
        ])->assertRedirect();

        $this->assertDatabaseHas('learn_submissions', [
            'learn_assignment_id' => $assignment->id, 'link_url' => 'https://docs.google.com/document/d/abc123',
        ]);
    }

    public function test_student_can_submit_a_file_assignment(): void
    {
        $assignment = $this->makeAssignment('file');
        $dataUri = 'data:application/pdf;base64,' . base64_encode('fake pdf');

        $this->post(route('student-portal.learn.assignments.submit', $assignment), [
            'title' => 'homework.pdf', 'file_base64' => $dataUri,
        ])->assertRedirect();

        $submission = Submission::where('learn_assignment_id', $assignment->id)->firstOrFail();
        $this->assertNotNull($submission->learn_file_id);
    }

    public function test_resubmission_overwrites_the_same_row(): void
    {
        $assignment = $this->makeAssignment('text');

        $this->post(route('student-portal.learn.assignments.submit', $assignment), ['text_body' => 'First draft']);
        $this->post(route('student-portal.learn.assignments.submit', $assignment), ['text_body' => 'Final draft']);

        $this->assertSame(1, Submission::where('learn_assignment_id', $assignment->id)->count());
        $this->assertSame('Final draft', Submission::where('learn_assignment_id', $assignment->id)->first()->text_body);
    }

    public function test_graded_submission_cannot_be_resubmitted(): void
    {
        $assignment = $this->makeAssignment('text');
        Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $this->studentId,
            'text_body' => 'Graded already', 'submitted_at' => now(), 'score' => 30, 'graded_at' => now(),
        ]);

        $this->post(route('student-portal.learn.assignments.submit', $assignment), [
            'text_body' => 'Trying to change it',
        ])->assertForbidden();
    }

    public function test_student_cannot_submit_to_an_assignment_outside_their_enrolled_section(): void
    {
        $otherSection = Section::create([
            'levelid' => 8, 'sectionname' => 'Ruby', 'syid' => $this->course->school_year_id,
            'school_year_id' => $this->course->school_year_id, 'is_active' => true,
        ]);
        $otherSubject = Subject::create([
            'school_year_id' => $this->course->school_year_id, 'code' => 'MATH8', 'name' => 'Math 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $otherCourse = Course::create([
            'subject_id' => $otherSubject->id, 'section_id' => $otherSection->id,
            'school_year_id' => $this->course->school_year_id, 'academic_term_id' => $this->course->academic_term_id,
            'status' => 'published',
        ]);
        $module = $otherCourse->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $assignment = Assignment::create(['title' => 'Not mine', 'submission_type' => 'text']);
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);

        $this->post(route('student-portal.learn.assignments.submit', $assignment), [
            'text_body' => 'x',
        ])->assertForbidden();
    }

    public function test_student_cannot_view_another_students_submission_file_by_guessing_the_id(): void
    {
        $assignment = $this->makeAssignment('file');
        $dataUri = 'data:application/pdf;base64,' . base64_encode('fake pdf');
        $this->post(route('student-portal.learn.assignments.submit', $assignment), [
            'title' => 'homework.pdf', 'file_base64' => $dataUri,
        ]);
        $submission = Submission::where('learn_assignment_id', $assignment->id)->firstOrFail();

        self::$studentCounter++;
        $otherPisaysystemID = 'PS' . str_pad((string) self::$studentCounter, 9, '0', STR_PAD_LEFT);
        DB::table('students')->insert([
            'pisaysystemID' => $otherPisaysystemID, 'lastname' => 'Reyes', 'firstname' => 'Ana', 'sex' => 'F',
        ]);
        session(['student_pisaysystemID' => $otherPisaysystemID]);

        $this->get(route('student-portal.learn.submissions.file', $submission))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/LearnSubmissionControllerTest.php"`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Add submission endpoints to `StudentPortal\LearnController`**

Add these imports to `app/Http/Controllers/StudentPortal/LearnController.php`, alongside the existing `use App\Models\Learn\Assignment;` and `use App\Models\Learn\Submission;` added in Task 4:

```php
use Illuminate\Http\Request;
```

Add these two methods, after `show()`:

```php
    /** POST /student-portal/learn/assignments/{assignment}/submit */
    public function submit(Request $request, Assignment $assignment)
    {
        $student = $this->currentStudent();
        $course = $assignment->course();
        abort_if(! $course, 404);
        abort_unless($course->isVisibleToStudent($student->id), 403);

        $existing = Submission::where('learn_assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();
        abort_if($existing?->isGraded(), 403, 'This submission has already been graded and is locked.');

        $rules = match ($assignment->submission_type) {
            'text' => ['text_body' => 'required|string'],
            'link' => ['link_url' => 'required|url'],
            'file' => ['title' => 'required|string|max:255', 'file_base64' => 'required|string'],
        };
        $validated = $request->validate($rules);

        $data = ['submitted_at' => now()];
        match ($assignment->submission_type) {
            'text' => $data['text_body'] = $validated['text_body'],
            'link' => $data['link_url'] = $validated['link_url'],
            'file' => $data['learn_file_id'] = $this->files->upload(
                $course->id, $validated['title'], $validated['file_base64']
            )->id,
        };

        Submission::updateOrCreate(
            ['learn_assignment_id' => $assignment->id, 'student_id' => $student->id],
            $data
        );

        return back()->with('success', 'Submission saved.');
    }

    /** GET /student-portal/learn/submissions/{submission}/file */
    public function submissionFile(Submission $submission)
    {
        $student = $this->currentStudent();
        abort_unless($submission->student_id === $student->id, 403);
        abort_if(! $submission->file, 404);

        return $this->files->streamResponse($submission->file);
    }
```

- [ ] **Step 4: Add routes**

Add inside the `student.portal`-middleware block in `routes/web.php`, immediately after the `learn.show` line from Phase 1:

```php
        Route::post('/learn/assignments/{assignment}/submit', [\App\Http\Controllers\StudentPortal\LearnController::class, 'submit'])->name('learn.assignments.submit');
        Route::get('/learn/submissions/{submission}/file', [\App\Http\Controllers\StudentPortal\LearnController::class, 'submissionFile'])->name('learn.submissions.file');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/LearnSubmissionControllerTest.php"`
Expected: PASS (7 tests).

- [ ] **Step 6: Run Task 4's serialization test again to confirm the `route('student-portal.learn.submissions.file', ...)` reference now resolves**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/LearnAssignmentSerializationTest.php"`
Expected: PASS (still 1 test — this just confirms no route-not-found regression now that the route exists).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/StudentPortal/LearnController.php routes/web.php \
        tests/Feature/StudentPortal/LearnSubmissionControllerTest.php
git commit -m "feat(learn): add student submission endpoints (text/file/link, resubmit-until-graded)"
```

---

### Task 8: Faculty Vue — author assignments, grading roster page

**Files:**
- Modify: `resources/js/Pages/Learn/Show.vue`
- Create: `resources/js/Pages/Learn/Grading.vue`

**Interfaces:**
- Consumes props from `CourseController::show()` (`course.modules[].items[].assignment: {id, instructions, submission_type, due_at, max_score, has_rubric}|null`) and `AssignmentGradingController::index()` (`assignment: {id, title, submission_type, max_score, rubric:{criteria:[{id,description,max_points}]}|null}`, `roster: [{student_id, name, submission_id, status, text_body, link_url, file_url, submitted_at, score, feedback_comment, is_graded, rubric_scores}]`).
- Uses named routes: `learn.items.store-assignment`, `learn.assignments.submissions`, `learn.submissions.grade`, `learn.submissions.reopen`.

- [ ] **Step 1: Add assignment authoring + rendering to `Learn/Show.vue`**

In `resources/js/Pages/Learn/Show.vue`, add a new form ref and functions in the `<script setup>` block, after the existing `addFile` function:

```js
const assignmentForms = ref({})
function assignmentForm(moduleId) {
  if (! assignmentForms.value[moduleId]) {
    assignmentForms.value[moduleId] = useForm({
      title: '', instructions: '', submission_type: 'text',
      points_possible: '', due_at: '', rubric_criteria: [],
    })
  }
  return assignmentForms.value[moduleId]
}
function addRubricCriterion(moduleId) {
  assignmentForm(moduleId).rubric_criteria.push({ description: '', max_points: 10 })
}
function removeRubricCriterion(moduleId, index) {
  assignmentForm(moduleId).rubric_criteria.splice(index, 1)
}
function addAssignment(moduleId) {
  assignmentForm(moduleId).post(route('learn.items.store-assignment', moduleId), {
    preserveScroll: true,
    onSuccess: () => { assignmentForms.value[moduleId] = null },
  })
}
```

Add this block to the item-list `<div v-for="(item, itemIndex) in module.items" ...>` loop in the template, immediately after the existing file-link `<a>` line (`item.type === 'file'`):

```html
              <div v-if="item.type === 'assignment'" class="mt-1 space-y-1">
                <div v-if="item.assignment.instructions" class="prose prose-sm max-w-none" v-html="sanitizeHtml(item.assignment.instructions)" />
                <p class="text-xs text-slate-500">
                  {{ item.assignment.submission_type }} submission
                  <span v-if="item.assignment.due_at"> — due {{ new Date(item.assignment.due_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                  <span v-if="item.assignment.max_score !== null"> — {{ item.assignment.max_score }} pts{{ item.assignment.has_rubric ? ' (rubric)' : '' }}</span>
                </p>
                <Link :href="route('learn.assignments.submissions', item.assignment.id)" class="text-xs text-indigo-600 underline">View submissions</Link>
              </div>
```

Add this authoring form to the module's `v-if="course.can_edit"` add-content block, after the file-upload block:

```html
              <div v-if="course.can_edit" class="border-t border-slate-100 pt-3 space-y-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">New assignment</p>
                <input v-model="assignmentForm(module.id).title" placeholder="Assignment title" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
                <textarea v-model="assignmentForm(module.id).instructions" placeholder="Instructions (optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />
                <div class="flex gap-2">
                  <select v-model="assignmentForm(module.id).submission_type" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                    <option value="text">Text entry</option>
                    <option value="file">File upload</option>
                    <option value="link">Link</option>
                  </select>
                  <input v-model="assignmentForm(module.id).due_at" type="datetime-local" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm" />
                </div>

                <div v-if="assignmentForm(module.id).rubric_criteria.length === 0">
                  <input v-model="assignmentForm(module.id).points_possible" type="number" min="0" placeholder="Points possible" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
                </div>
                <div v-else class="space-y-1">
                  <div v-for="(criterion, i) in assignmentForm(module.id).rubric_criteria" :key="i" class="flex gap-2 items-center">
                    <input v-model="criterion.description" placeholder="Criterion" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
                    <input v-model="criterion.max_points" type="number" min="0" placeholder="Points" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-24" />
                    <button @click="removeRubricCriterion(module.id, i)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
                  </div>
                </div>
                <button @click="addRubricCriterion(module.id)" class="text-xs text-indigo-600 underline">+ Add rubric criterion</button>

                <button @click="addAssignment(module.id)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Add assignment</button>
              </div>
```

- [ ] **Step 2: Write `Learn/Grading.vue`**

`resources/js/Pages/Learn/Grading.vue`:

```vue
<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ assignment: Object, roster: Array })

const expandedStudentId = ref(null)
const gradeForms = ref({})

function statusLabel(status) {
  return { not_submitted: 'Not submitted', submitted: 'Submitted', late: 'Late', graded: 'Graded' }[status]
}
function statusClass(status) {
  return {
    not_submitted: 'bg-slate-100 text-slate-600',
    submitted: 'bg-blue-50 text-blue-700',
    late: 'bg-amber-50 text-amber-700',
    graded: 'bg-emerald-50 text-emerald-700',
  }[status]
}

function toggleExpand(row) {
  if (! row.submission_id) return
  expandedStudentId.value = expandedStudentId.value === row.student_id ? null : row.student_id

  if (! gradeForms.value[row.student_id]) {
    const rubricDefaults = {}
    if (props.assignment.rubric) {
      for (const c of props.assignment.rubric.criteria) {
        rubricDefaults[c.id] = row.rubric_scores[c.id] ?? ''
      }
    }
    gradeForms.value[row.student_id] = useForm({
      score: row.score ?? '',
      rubric_scores: rubricDefaults,
      feedback_comment: row.feedback_comment ?? '',
    })
  }
}

function submitGrade(row) {
  const form = gradeForms.value[row.student_id]
  const payload = props.assignment.rubric
    ? { rubric_scores: form.rubric_scores, feedback_comment: form.feedback_comment }
    : { score: form.score, feedback_comment: form.feedback_comment }

  router.put(route('learn.submissions.grade', row.submission_id), payload, { preserveScroll: true })
}

function reopen(row) {
  router.post(route('learn.submissions.reopen', row.submission_id), {}, { preserveScroll: true })
}
</script>

<template>
  <Head :title="`Grading — ${assignment.title}`" />
  <AdminLayout :title="assignment.title">
    <div class="max-w-4xl mx-auto py-6 px-4 space-y-4">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">{{ assignment.title }}</h1>
        <p class="text-sm text-slate-500">{{ assignment.submission_type }} submission — {{ assignment.max_score }} pts</p>
      </div>

      <div v-for="row in roster" :key="row.student_id" class="border border-slate-200 rounded-lg">
        <button
          class="w-full flex items-center justify-between px-4 py-3 text-left"
          :class="row.submission_id ? 'cursor-pointer hover:bg-slate-50' : 'cursor-default'"
          @click="toggleExpand(row)"
        >
          <span class="text-sm font-medium text-slate-800">{{ row.name }}</span>
          <div class="flex items-center gap-2">
            <span v-if="row.score !== null" class="text-xs text-slate-500">{{ row.score }} / {{ assignment.max_score }}</span>
            <span :class="['inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium', statusClass(row.status)]">
              {{ statusLabel(row.status) }}
            </span>
          </div>
        </button>

        <div v-if="expandedStudentId === row.student_id" class="border-t border-slate-100 p-4 space-y-3">
          <div v-if="assignment.submission_type === 'text'" class="prose prose-sm max-w-none whitespace-pre-line">{{ row.text_body }}</div>
          <a v-else-if="assignment.submission_type === 'link'" :href="row.link_url" target="_blank" rel="noopener noreferrer" class="text-sm text-indigo-600 underline">{{ row.link_url }}</a>
          <a v-else-if="assignment.submission_type === 'file'" :href="row.file_url" target="_blank" rel="noopener noreferrer" class="text-sm text-indigo-600 underline">Download submission</a>

          <div v-if="!row.is_graded">
            <div v-if="!assignment.rubric">
              <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Score</label>
              <input v-model="gradeForms[row.student_id].score" type="number" min="0" :max="assignment.max_score" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full mt-1" />
            </div>
            <div v-else class="space-y-2">
              <div v-for="criterion in assignment.rubric.criteria" :key="criterion.id" class="flex items-center gap-2">
                <span class="text-sm text-slate-700 flex-1">{{ criterion.description }}</span>
                <input v-model="gradeForms[row.student_id].rubric_scores[criterion.id]" type="number" min="0" :max="criterion.max_points" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-24" />
                <span class="text-xs text-slate-400">/ {{ criterion.max_points }}</span>
              </div>
            </div>
            <textarea v-model="gradeForms[row.student_id].feedback_comment" placeholder="Feedback (optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full mt-2" rows="2" />
            <button @click="submitGrade(row)" class="mt-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Save grade</button>
          </div>
          <div v-else>
            <p class="text-sm text-slate-600">{{ row.feedback_comment }}</p>
            <button @click="reopen(row)" class="mt-2 bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium">Reopen for resubmission</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 3: Build frontend assets and verify no compile errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing `Learn/Show.vue` or `Learn/Grading.vue`.

- [ ] **Step 4: Re-run Task 6's backend test to confirm the `Learn/Grading` Inertia page now resolves**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/AssignmentGradingControllerTest.php"`
Expected: PASS (still 6 tests — confirms the previously-page-existence-deferred assertion in `test_index_shows_roster_and_403s_for_non_instructor` is fully green now that `Grading.vue` exists).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Learn/Show.vue resources/js/Pages/Learn/Grading.vue
git commit -m "feat(learn): add faculty assignment authoring UI and grading roster page"
```

---

### Task 9: Student Vue — assignment rendering + submission form

**Files:**
- Modify: `resources/js/Pages/StudentPortal/Learn/Show.vue`

**Interfaces:**
- Consumes props from `StudentPortal\LearnController::show()` (`course.modules[].items[].assignment: {id, instructions, submission_type, due_at, max_score, submission:{id,text_body,link_url,file_url,submitted_at,score,feedback_comment,is_graded,is_late}|null}|null`).
- Uses named route: `student-portal.learn.assignments.submit`.

- [ ] **Step 1: Add assignment rendering + submission form**

In `resources/js/Pages/StudentPortal/Learn/Show.vue`, add to `<script setup>`:

```js
import { useForm } from '@inertiajs/vue3'

const submissionForms = ref({})
function submissionForm(item) {
  if (! submissionForms.value[item.id]) {
    submissionForms.value[item.id] = useForm({ text_body: '', link_url: '', title: '', file_base64: '' })
  }
  return submissionForms.value[item.id]
}

function readFileAsBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(reader.result)
    reader.onerror = reject
    reader.readAsDataURL(file)
  })
}

async function pickFile(item, event) {
  const file = event.target.files[0]
  if (! file) return
  const form = submissionForm(item)
  form.file_base64 = await readFileAsBase64(file)
  form.title = file.name
}

function submitAssignment(item) {
  const form = submissionForm(item)
  form.post(route('student-portal.learn.assignments.submit', item.assignment.id), { preserveScroll: true })
}
```

Also add `import { ref } from 'vue'` alongside the existing `Head` import at the top of the file (this component previously had no `<script setup>` reactive state).

Add this block to the item-rendering loop, immediately after the existing file-download `<a>` line (`item.type === 'file'`):

```html
              <div v-if="item.type === 'assignment'" class="mt-1 space-y-2">
                <div v-if="item.assignment.instructions" class="prose prose-sm max-w-none" v-html="sanitizeHtml(item.assignment.instructions)" />
                <p class="text-xs text-slate-500">
                  {{ item.assignment.submission_type }} submission
                  <span v-if="item.assignment.due_at"> — due {{ new Date(item.assignment.due_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                  <span v-if="item.assignment.max_score !== null"> — {{ item.assignment.max_score }} pts</span>
                </p>

                <div v-if="item.assignment.submission && item.assignment.submission.is_graded" class="border border-emerald-200 bg-emerald-50 rounded-lg p-3">
                  <p class="text-sm font-medium text-emerald-800">Score: {{ item.assignment.submission.score }} / {{ item.assignment.max_score }}</p>
                  <p v-if="item.assignment.submission.feedback_comment" class="text-sm text-emerald-700 mt-1">{{ item.assignment.submission.feedback_comment }}</p>
                </div>

                <div v-else-if="item.assignment.submission" class="border border-slate-200 rounded-lg p-3">
                  <p class="text-xs text-slate-500">
                    Submitted {{ new Date(item.assignment.submission.submitted_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}
                    <span v-if="item.assignment.submission.is_late" class="text-amber-600">— Late</span>
                  </p>
                  <p v-if="item.assignment.submission_type === 'text'" class="text-sm text-slate-700 mt-1 whitespace-pre-line">{{ item.assignment.submission.text_body }}</p>
                  <a v-else-if="item.assignment.submission_type === 'link'" :href="item.assignment.submission.link_url" target="_blank" rel="noopener noreferrer" class="text-sm text-indigo-600 underline">{{ item.assignment.submission.link_url }}</a>
                  <a v-else-if="item.assignment.submission_type === 'file'" :href="item.assignment.submission.file_url" target="_blank" rel="noopener noreferrer" class="text-sm text-indigo-600 underline">View my submission</a>
                </div>

                <div v-if="!item.assignment.submission || !item.assignment.submission.is_graded" class="border-t border-slate-100 pt-2 space-y-2">
                  <textarea v-if="item.assignment.submission_type === 'text'" v-model="submissionForm(item).text_body" placeholder="Type your submission" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="3" />
                  <input v-else-if="item.assignment.submission_type === 'link'" v-model="submissionForm(item).link_url" placeholder="https://..." class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
                  <input v-else-if="item.assignment.submission_type === 'file'" type="file" @change="e => pickFile(item, e)" class="text-sm" />
                  <button @click="submitAssignment(item)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
                    {{ item.assignment.submission ? 'Resubmit' : 'Submit' }}
                  </button>
                </div>
              </div>
```

- [ ] **Step 2: Build frontend assets and verify no compile errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing `StudentPortal/Learn/Show.vue`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/StudentPortal/Learn/Show.vue
git commit -m "feat(learn): add student assignment rendering and submission form"
```

---

### Task 10: Full test suite + manual verification

**Files:** none created — verification only.

- [ ] **Step 1: Run the full Phase 1 + Phase 2 Learn suite**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M vendor/bin/phpunit tests/Feature/Learn tests/Feature/StudentPortal/LearnControllerTest.php tests/Feature/StudentPortal/LearnAssignmentSerializationTest.php tests/Feature/StudentPortal/LearnSubmissionControllerTest.php"`
Expected: all tests pass (Phase 1's 45 plus Phase 2's new tests — no regressions in either direction).

- [ ] **Step 2: Run the full project regression suite**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=1G vendor/bin/phpunit"`
Expected: no new failures beyond whatever pre-existing baseline failures already exist in this codebase (confirm by checking that every failing test name is unrelated to `Learn`, `routes/web.php`, or any file this plan touched — cross-check against the known Phase 1 baseline if available).

- [ ] **Step 3: Manual browser verification — golden path**

With the dev server running, as a faculty member with a current-SY teaching `LoadAssignment`:

1. Open a course, add a module, add an assignment with `points_possible` (no rubric) and a due date in the past (to test late-flagging), publish the module/item.
2. Add a second assignment with 2 rubric criteria instead of `points_possible`.
3. Log in to the Student Portal as an enrolled student. Submit the first assignment (text/file/link matching its `submission_type`) — confirm it shows as "Submitted" or "Late" depending on the due date.
4. As faculty, open "View submissions" on that assignment — confirm the roster shows the student's correct status, expand their row, grade it with a score, save.
5. As the student, refresh the course page — confirm the score and feedback comment now show, and the submission form no longer allows resubmission (it's locked).
6. As faculty, click "Reopen for resubmission" — confirm the student can resubmit again.
7. Submit and grade the rubric assignment — confirm the per-criterion inputs sum correctly to the total score shown.
8. As a non-instructor faculty member (no teaching load for this course), confirm `/learn/assignments/{id}/submissions` 403s.

- [ ] **Step 4: Report results**

Note any issues found during manual verification; fix and re-verify before considering Phase 2 complete. Do not commit for this task — it is verification only.

---

## Phase 2 Complete — Next Steps

Once all 10 tasks pass, Learn Phase 2 (Assignments + Submissions + Rubric Grading) is done. Phase 2b (Class Record push) and Phase 2c (reusable rubric bank) each need their own `superpowers:brainstorming` → design → plan cycle before implementation, as do Phases 3 (Quizzes) and 4 (Discussions), per the roadmap in `docs/superpowers/specs/2026-08-09-learn-module-phase2-design.md`.
