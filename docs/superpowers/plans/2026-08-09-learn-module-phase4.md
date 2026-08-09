# Learn Module Phase 4: Discussions / Forums Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a graded-or-ungraded discussion board to Learn — nested (unlimited-depth) replies, soft-delete moderation, optional participation grading with Class Record push — per `docs/superpowers/specs/2026-08-09-learn-module-phase4-design.md`.

**Architecture:** `Discussion` becomes a sixth polymorphic `ModuleItem` itemable type alongside Page/File/Assignment/Quiz, reusing existing module/item CRUD. Replies use a simple adjacency list (`parent_post_id` self-reference on one `learn_discussion_posts` table); the full post tree for a discussion is fetched in one query and assembled into a nested array server-side (PHP), never recursive SQL. `Discussion` implements the existing `HasClassRecordLink` contract (Phase 3) with zero changes to `ClassRecordPushService` or the already-shipped `Assignment`/`Quiz` implementations.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia 2, Tailwind — reusing Phase 1-3's Learn infrastructure, no new npm dependencies.

## Global Constraints

- All prior Learn-phase constraints apply (base64 uploads n/a here — text-only posts, `Inertia::render(...)`, eager-load relations, migrations always write `down()`, `student_id` columns are always `unsignedInteger` with no FK — `students` is legacy MyISAM).
- MySQL identifier limit is 64 chars — `learn_discussion_grades`' natural unique-constraint name is right at the edge; use an explicit short name (see Task 1).
- **Soft-delete only, never hard-delete a post.** Deleting sets `is_deleted = true` plus `deleted_by_type`/`deleted_by_id`; the row and its children are never removed. No task should add a hard-delete path for posts.
- **No per-post grading.** Grading is one row per student per discussion (`learn_discussion_grades`), never tied to an individual post.
- **`author_type`/`author_id` and `deleted_by_type`/`deleted_by_id` are dual-identity pairs** (`'student'` → `student_id` in the legacy `students` table, `'faculty'` → `user_id` in `users`) — mirrors the existing pattern already used elsewhere in this codebase (`CourseAnnouncement.posted_by` for faculty vs `Submission.student_id` for students). Never assume a single FK covers both.
- Never modify `Assignment`'s or `Quiz`'s existing behavior, or `ClassRecordPushService`'s existing call sites — only add to them.

---

### Task 1: Discussion schema (3 migrations)

**Files:**
- Create: `database/migrations/2026_08_09_100018_create_learn_discussions_table.php`
- Create: `database/migrations/2026_08_09_100019_create_learn_discussion_posts_table.php`
- Create: `database/migrations/2026_08_09_100020_create_learn_discussion_grades_table.php`
- Test: `tests/Feature/Learn/LearnDiscussionSchemaTest.php`

**Interfaces:**
- Produces tables consumed by every later task in this plan.

- [ ] **Step 1: Write the failing schema test**

```php
<?php

namespace Tests\Feature\Learn;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearnDiscussionSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_learn_discussions_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_discussions'));
        $this->assertTrue(Schema::hasColumns('learn_discussions', [
            'id', 'title', 'prompt', 'points_possible', 'class_record_assessment_id', 'pushed_at',
        ]));
    }

    public function test_learn_discussion_posts_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_discussion_posts'));
        $this->assertTrue(Schema::hasColumns('learn_discussion_posts', [
            'id', 'learn_discussion_id', 'parent_post_id', 'author_type', 'author_id',
            'body', 'is_deleted', 'deleted_by_type', 'deleted_by_id',
        ]));
    }

    public function test_learn_discussion_grades_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_discussion_grades'));
        $this->assertTrue(Schema::hasColumns('learn_discussion_grades', [
            'id', 'learn_discussion_id', 'student_id', 'points_earned',
            'feedback_comment', 'graded_at', 'graded_by',
        ]));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnDiscussionSchemaTest.php"`
Expected: FAIL — none of the tables exist.

- [ ] **Step 3: Write the 3 migrations**

`database/migrations/2026_08_09_100018_create_learn_discussions_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_discussions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('prompt');
            $table->decimal('points_possible', 6, 2)->nullable();
            $table->foreignId('class_record_assessment_id')->nullable()
                  ->constrained('class_record_assessments')->nullOnDelete();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_discussions');
    }
};
```

`database/migrations/2026_08_09_100019_create_learn_discussion_posts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_discussion_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_discussion_id')->constrained('learn_discussions')->cascadeOnDelete();
            $table->foreignId('parent_post_id')->nullable()
                  ->constrained('learn_discussion_posts')->nullOnDelete();
            $table->enum('author_type', ['student', 'faculty']);
            $table->unsignedInteger('author_id')
                  ->comment('student_id (students table, no FK) or user_id (users table) depending on author_type');
            $table->longText('body');
            $table->boolean('is_deleted')->default(false);
            $table->enum('deleted_by_type', ['student', 'faculty'])->nullable();
            $table->unsignedInteger('deleted_by_id')->nullable();
            $table->timestamps();

            $table->index(['learn_discussion_id', 'parent_post_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_discussion_posts');
    }
};
```

`database/migrations/2026_08_09_100020_create_learn_discussion_grades_table.php`
(explicit short unique-constraint name — the auto-generated one sits right at the 64-char limit):

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_discussion_grades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_discussion_id')->constrained('learn_discussions')->cascadeOnDelete();
            $table->unsignedInteger('student_id')
                  ->comment('references students.id — app-level constraint (MyISAM cannot be FK target)');
            $table->decimal('points_earned', 6, 2)->nullable();
            $table->text('feedback_comment')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['learn_discussion_id', 'student_id'], 'ldg_discussion_student_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_discussion_grades');
    }
};
```

- [ ] **Step 4: Run migrations and the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_09_100018_create_learn_discussions_table.php --path=database/migrations/2026_08_09_100019_create_learn_discussion_posts_table.php --path=database/migrations/2026_08_09_100020_create_learn_discussion_grades_table.php --force"`
Then: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnDiscussionSchemaTest.php"`
Expected: PASS (3 tests). If a migration fails partway, drop the partially-created table via
tinker before retrying (same caveat as every prior phase's Task 1).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_09_100018_create_learn_discussions_table.php \
        database/migrations/2026_08_09_100019_create_learn_discussion_posts_table.php \
        database/migrations/2026_08_09_100020_create_learn_discussion_grades_table.php \
        tests/Feature/Learn/LearnDiscussionSchemaTest.php
git commit -m "feat(learn): add discussion schema (discussions, posts, grades)"
```

---

### Task 2: Discussion models

**Files:**
- Create: `app/Models/Learn/Discussion.php`
- Create: `app/Models/Learn/DiscussionPost.php`
- Create: `app/Models/Learn/DiscussionGrade.php`
- Test: `tests/Feature/Learn/DiscussionModelTest.php`

**Interfaces:**
- Consumes: `App\Contracts\Learn\HasClassRecordLink` (Phase 3) — `Discussion` implements it,
  identical shape to `Assignment`/`Quiz`.
- Produces: `Discussion::posts()`, `Discussion::moduleItem()`, `Discussion::course()`,
  `Discussion::canEdit(User)`, `Discussion::maxScore()`, `Discussion::gradedStudentScores()`;
  `DiscussionPost::replies()`, `DiscussionPost::parentPost()`, `DiscussionPost::isDeleted()` —
  all consumed by later tasks.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Contracts\Learn\HasClassRecordLink;
use App\Models\Learn\Discussion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscussionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_discussion_implements_has_class_record_link(): void
    {
        $this->assertInstanceOf(HasClassRecordLink::class, new Discussion());
    }

    public function test_discussion_has_nested_posts(): void
    {
        $discussion = Discussion::create(['title' => 'Week 1 Discussion', 'prompt' => 'Discuss chapter 1.']);
        $top = $discussion->posts()->create([
            'author_type' => 'student', 'author_id' => 111, 'body' => 'My thoughts.',
        ]);
        $reply = $discussion->posts()->create([
            'parent_post_id' => $top->id, 'author_type' => 'faculty', 'author_id' => 222, 'body' => 'Good point.',
        ]);

        $this->assertCount(2, $discussion->fresh()->posts);
        $this->assertSame($top->id, $reply->fresh()->parentPost->id);
        $this->assertCount(1, $top->fresh()->replies);
        $this->assertFalse($top->isDeleted());
    }

    public function test_max_score_and_graded_student_scores(): void
    {
        $discussion = Discussion::create(['title' => 'D', 'prompt' => 'P', 'points_possible' => 10]);
        $this->assertSame(10.0, $discussion->maxScore());

        $discussion->grades()->create(['student_id' => 111, 'points_earned' => 8]);
        $discussion->grades()->create(['student_id' => 222]); // ungraded — must be excluded

        $this->assertSame([111 => 8.0], $discussion->gradedStudentScores());
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/DiscussionModelTest.php"`
Expected: FAIL — model classes don't exist.

- [ ] **Step 3: Write the models**

`app/Models/Learn/Discussion.php`:

```php
<?php

namespace App\Models\Learn;

use App\Contracts\Learn\HasClassRecordLink;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Discussion extends Model implements HasClassRecordLink
{
    protected $table = 'learn_discussions';

    protected $fillable = [
        'title', 'prompt', 'points_possible', 'class_record_assessment_id', 'pushed_at',
    ];

    protected $casts = [
        'points_possible' => 'decimal:2',
        'pushed_at' => 'datetime',
    ];

    public function moduleItem(): MorphOne
    {
        return $this->morphOne(ModuleItem::class, 'itemable');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(DiscussionPost::class, 'learn_discussion_id')->orderBy('created_at');
    }

    public function grades(): HasMany
    {
        return $this->hasMany(DiscussionGrade::class, 'learn_discussion_id');
    }

    public function classRecordAssessment(): BelongsTo
    {
        return $this->belongsTo(ClassRecordAssessment::class, 'class_record_assessment_id');
    }

    public function maxScore(): ?float
    {
        return $this->points_possible !== null ? (float) $this->points_possible : null;
    }

    /** The Learn course this discussion belongs to, resolved through its module item. */
    public function course(): ?Course
    {
        return $this->moduleItem?->module?->course;
    }

    public function canEdit(User $user): bool
    {
        return $this->course()?->canEdit($user) ?? false;
    }

    /** @return array<int, float> student_id => points_earned, for every graded student. */
    public function gradedStudentScores(): array
    {
        return $this->grades()
            ->whereNotNull('points_earned')
            ->pluck('points_earned', 'student_id')
            ->map(fn ($score) => (float) $score)
            ->all();
    }
}
```

`app/Models/Learn/DiscussionPost.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscussionPost extends Model
{
    protected $table = 'learn_discussion_posts';

    protected $fillable = [
        'learn_discussion_id', 'parent_post_id', 'author_type', 'author_id', 'body',
        'is_deleted', 'deleted_by_type', 'deleted_by_id',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class, 'learn_discussion_id');
    }

    public function parentPost(): BelongsTo
    {
        return $this->belongsTo(DiscussionPost::class, 'parent_post_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DiscussionPost::class, 'parent_post_id')->orderBy('created_at');
    }

    public function isDeleted(): bool
    {
        return $this->is_deleted;
    }
}
```

`app/Models/Learn/DiscussionGrade.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscussionGrade extends Model
{
    protected $table = 'learn_discussion_grades';

    protected $fillable = [
        'learn_discussion_id', 'student_id', 'points_earned', 'feedback_comment', 'graded_at', 'graded_by',
    ];

    protected $casts = [
        'points_earned' => 'decimal:2',
        'graded_at' => 'datetime',
    ];

    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class, 'learn_discussion_id');
    }
}
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/DiscussionModelTest.php"`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Learn/Discussion.php app/Models/Learn/DiscussionPost.php app/Models/Learn/DiscussionGrade.php \
        tests/Feature/Learn/DiscussionModelTest.php
git commit -m "feat(learn): add discussion models"
```

---

### Task 3: Discussion creation (`storeDiscussion`)

**Files:**
- Modify: `app/Http/Controllers/Learn/ModuleItemController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/ModuleItemDiscussionControllerTest.php`

**Interfaces:**
- Produces route: `learn.items.store-discussion` (POST `/learn/modules/{module}/items/discussion`).

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
use App\Models\Learn\Discussion;
use App\Models\Learn\ModuleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleItemDiscussionControllerTest extends TestCase
{
    use RefreshDatabase;

    private $module;
    private User $teacher;

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
        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);

        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $this->module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
    }

    public function test_instructor_can_add_a_graded_discussion(): void
    {
        $response = $this->actingAs($this->teacher)->post(route('learn.items.store-discussion', $this->module), [
            'title' => 'Week 1 Discussion', 'prompt' => 'Discuss chapter 1.', 'points_possible' => 10,
        ]);

        $response->assertRedirect();

        $discussion = Discussion::where('title', 'Week 1 Discussion')->firstOrFail();
        $this->assertSame('Discuss chapter 1.', $discussion->prompt);
        $this->assertSame(10.0, $discussion->maxScore());

        $item = ModuleItem::where('itemable_type', Discussion::class)->where('itemable_id', $discussion->id)->first();
        $this->assertNotNull($item);
    }

    public function test_instructor_can_add_an_ungraded_discussion(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-discussion', $this->module), [
            'title' => 'Open Forum', 'prompt' => 'Anything goes.',
        ])->assertRedirect();

        $discussion = Discussion::where('title', 'Open Forum')->firstOrFail();
        $this->assertNull($discussion->maxScore());
    }

    public function test_stranger_cannot_add_a_discussion(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post(route('learn.items.store-discussion', $this->module), [
            'title' => 'X', 'prompt' => 'Y',
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ModuleItemDiscussionControllerTest.php"`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Add `storeDiscussion` to `ModuleItemController`**

Add the import:

```php
use App\Models\Learn\Discussion;
```

Add this method (anywhere among the other `store*` methods):

```php
    /** POST /learn/modules/{module}/items/discussion */
    public function storeDiscussion(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'prompt' => 'required|string',
            'points_possible' => 'nullable|numeric|min:0',
        ]);

        $discussion = Discussion::create($validated);
        $this->attachItem($module, $discussion);

        return back()->with('success', 'Discussion added.');
    }
```

- [ ] **Step 4: Add the route**

Add to `routes/web.php`, immediately after the `items.store-quiz` line:

```php
    Route::post('/modules/{module}/items/discussion', [\App\Http\Controllers\Learn\ModuleItemController::class, 'storeDiscussion'])->name('items.store-discussion');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ModuleItemDiscussionControllerTest.php"`
Expected: PASS (3 tests).

- [ ] **Step 6: Run Phase 3's existing quiz-authoring test to confirm no regression**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ModuleItemQuizControllerTest.php tests/Feature/Learn/ModuleItemAssignmentControllerTest.php"`
Expected: PASS, same counts as before.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Learn/ModuleItemController.php routes/web.php \
        tests/Feature/Learn/ModuleItemDiscussionControllerTest.php
git commit -m "feat(learn): add discussion creation endpoint (storeDiscussion)"
```

---

### Task 4: Item serialization for the `discussion` type (faculty + student)

**Files:**
- Modify: `app/Http/Controllers/Learn/CourseController.php`
- Modify: `app/Http/Controllers/StudentPortal/LearnController.php`
- Test: `tests/Feature/Learn/CourseDiscussionSerializationTest.php`
- Test: `tests/Feature/StudentPortal/LearnDiscussionSerializationTest.php`

**Interfaces:**
- Extends both controllers' `serializeItem()` `match(true)` with a `discussion` branch — summary
  only (title/prompt/points/post count), never the full post tree (that's Task 5/7's dedicated
  thread page).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Learn/CourseDiscussionSerializationTest.php`:

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
use App\Models\Learn\Discussion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseDiscussionSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_serializes_discussion_item_type(): void
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
        $discussion = Discussion::create(['title' => 'Discuss', 'prompt' => 'P', 'points_possible' => 10]);
        $discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
        $discussion->posts()->create(['author_type' => 'student', 'author_id' => 111, 'body' => 'Hi']);

        $response = $this->actingAs($teacher)->get(route('learn.show', $course));

        $response->assertInertia(fn ($page) => $page
            ->where('course.modules.0.items.0.type', 'discussion')
            ->where('course.modules.0.items.0.discussion.prompt', 'P')
            ->where('course.modules.0.items.0.discussion.max_score', 10)
            ->where('course.modules.0.items.0.discussion.post_count', 1)
        );
    }
}
```

`tests/Feature/StudentPortal/LearnDiscussionSerializationTest.php`:

```php
<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Discussion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnDiscussionSerializationTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_serializes_discussion_item_without_the_full_post_tree(): void
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        AcademicTerm::create([
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
            'school_year_id' => $sy->id, 'academic_term_id' => AcademicTerm::first()->id, 'status' => 'published',
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $discussion = Discussion::create(['title' => 'Discuss', 'prompt' => 'P']);
        $discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);
        $discussion->posts()->create(['author_type' => 'faculty', 'author_id' => 1, 'body' => 'Hi']);

        $studentId = mt_rand(1, 999999999);
        \Illuminate\Support\Facades\DB::table('students')->insert([
            'id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Test', 'lastname' => 'Student',
        ]);
        \App\Models\Registrar\StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
        session(['student_pisaysystemID' => "PS{$studentId}"]);

        $response = $this->get(route('student-portal.learn.show', $course));

        $response->assertInertia(fn ($page) => $page
            ->where('course.modules.0.items.0.type', 'discussion')
            ->where('course.modules.0.items.0.discussion.post_count', 1)
            ->missing('course.modules.0.items.0.discussion.posts')
        );
    }
}
```

- [ ] **Step 2: Run to verify both fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseDiscussionSerializationTest.php tests/Feature/StudentPortal/LearnDiscussionSerializationTest.php"`
Expected: FAIL — `type` resolves to `'unknown'`, no `discussion` key present.

- [ ] **Step 3: Update `CourseController`**

Add the import:

```php
use App\Models\Learn\Discussion;
```

In `show()`, add a call alongside the existing `loadQuizQuestions`:

```php
        $this->loadAssignmentRubrics($course);
        $this->loadQuizQuestions($course);
        $this->loadDiscussionPostCounts($course);
```

Add this private method (near `loadQuizQuestions`):

```php
    private function loadDiscussionPostCounts(Course $course): void
    {
        foreach ($course->modules as $module) {
            foreach ($module->items as $item) {
                if ($item->itemable instanceof Discussion) {
                    $item->itemable->load('posts');
                }
            }
        }
    }
```

Update `serializeItem()`'s `match(true)`:

```php
        $type = match (true) {
            $itemable instanceof LearnPage => 'page',
            $itemable instanceof LearnFile => 'file',
            $itemable instanceof Assignment => 'assignment',
            $itemable instanceof Quiz => 'quiz',
            $itemable instanceof Discussion => 'discussion',
            default => 'unknown',
        };
```

Add a `'discussion'` key to the returned array (alongside the existing `'quiz'` key):

```php
            'discussion' => $itemable instanceof Discussion ? [
                'id' => $itemable->id,
                'prompt' => $itemable->prompt,
                'max_score' => $itemable->maxScore(),
                'post_count' => $itemable->posts->count(),
            ] : null,
```

- [ ] **Step 4: Update `StudentPortal\LearnController`**

Add the import:

```php
use App\Models\Learn\Discussion;
```

In `show()`, add a call alongside the existing `loadQuizQuestionCounts`:

```php
        $this->loadAssignmentRubrics($course);
        $this->loadQuizQuestionCounts($course);
        $this->loadDiscussionPostCounts($course);
```

Add this private method (near `loadQuizQuestionCounts`):

```php
    private function loadDiscussionPostCounts(Course $course): void
    {
        foreach ($course->modules as $module) {
            foreach ($module->items as $item) {
                if ($item->itemable instanceof Discussion) {
                    $item->itemable->load('posts');
                }
            }
        }
    }
```

Update `serializeItem()`'s `match(true)`:

```php
        $type = match (true) {
            $itemable instanceof LearnPage => 'page',
            $itemable instanceof LearnFile => 'file',
            $itemable instanceof Assignment => 'assignment',
            $itemable instanceof Quiz => 'quiz',
            $itemable instanceof Discussion => 'discussion',
            default => 'unknown',
        };
```

Add a `'discussion'` key to the returned array (alongside the existing `'quiz'` key):

```php
            'discussion' => $itemable instanceof Discussion ? [
                'id' => $itemable->id,
                'prompt' => $itemable->prompt,
                'max_score' => $itemable->maxScore(),
                'post_count' => $itemable->posts->count(),
            ] : null,
```

- [ ] **Step 5: Run the tests**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseDiscussionSerializationTest.php tests/Feature/StudentPortal/LearnDiscussionSerializationTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 6: Run existing Quiz/Assignment serialization tests to confirm no regression**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseQuizSerializationTest.php tests/Feature/Learn/CourseAssignmentSerializationTest.php tests/Feature/StudentPortal/LearnQuizSerializationTest.php"`
Expected: PASS, same counts as before.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Learn/CourseController.php app/Http/Controllers/StudentPortal/LearnController.php \
        tests/Feature/Learn/CourseDiscussionSerializationTest.php tests/Feature/StudentPortal/LearnDiscussionSerializationTest.php
git commit -m "feat(learn): serialize discussion module items for faculty and student views"
```

---

### Task 5: Post tree assembly + faculty thread page

**Files:**
- Create: `app/Services/Learn/DiscussionPostService.php`
- Create: `app/Http/Controllers/Learn/DiscussionController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/DiscussionPostServiceTest.php`
- Test: `tests/Feature/Learn/DiscussionControllerTest.php`

**Interfaces:**
- Produces: `DiscussionPostService::tree(Discussion): array` — nested array, each node shaped
  `{id, author_type, author_id, author_name, body, is_deleted, created_at, updated_at, replies:
  [...]}`; consumed by both the faculty and student thread pages (Tasks 5 and 7).
- Produces route: `learn.discussions.show` (GET `/learn/discussions/{discussion}`).

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Learn/DiscussionPostServiceTest.php`:

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\Learn\Discussion;
use App\Models\User;
use App\Services\Learn\DiscussionPostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiscussionPostServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_tree_nests_replies_at_least_three_levels_deep_regardless_of_fetch_order(): void
    {
        $service = app(DiscussionPostService::class);
        $discussion = Discussion::create(['title' => 'D', 'prompt' => 'P']);

        $studentId = mt_rand(1, 999999999);
        DB::table('students')->insert(['id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Ana', 'lastname' => 'Cruz']);
        $teacher = User::factory()->create(['name' => 'Mr. Santos']);

        $top = $discussion->posts()->create(['author_type' => 'student', 'author_id' => $studentId, 'body' => 'Top level']);
        $reply1 = $discussion->posts()->create(['parent_post_id' => $top->id, 'author_type' => 'faculty', 'author_id' => $teacher->id, 'body' => 'Reply 1']);
        $reply2 = $discussion->posts()->create(['parent_post_id' => $reply1->id, 'author_type' => 'student', 'author_id' => $studentId, 'body' => 'Reply 2']);

        $tree = $service->tree($discussion);

        $this->assertCount(1, $tree);
        $this->assertSame('Top level', $tree[0]['body']);
        $this->assertSame('Ana Cruz', $tree[0]['author_name']);
        $this->assertCount(1, $tree[0]['replies']);
        $this->assertSame('Reply 1', $tree[0]['replies'][0]['body']);
        $this->assertSame('Mr. Santos', $tree[0]['replies'][0]['author_name']);
        $this->assertCount(1, $tree[0]['replies'][0]['replies']);
        $this->assertSame('Reply 2', $tree[0]['replies'][0]['replies'][0]['body']);
    }

    public function test_deleted_post_hides_body_but_keeps_children_in_the_tree(): void
    {
        $service = app(DiscussionPostService::class);
        $discussion = Discussion::create(['title' => 'D', 'prompt' => 'P']);

        $top = $discussion->posts()->create(['author_type' => 'student', 'author_id' => 111, 'body' => 'Original']);
        $discussion->posts()->create(['parent_post_id' => $top->id, 'author_type' => 'student', 'author_id' => 222, 'body' => 'A reply']);
        $top->update(['is_deleted' => true, 'deleted_by_type' => 'student', 'deleted_by_id' => 111]);

        $tree = $service->tree($discussion);

        $this->assertTrue($tree[0]['is_deleted']);
        $this->assertNull($tree[0]['body']);
        $this->assertCount(1, $tree[0]['replies']);
        $this->assertSame('A reply', $tree[0]['replies'][0]['body']);
    }
}
```

`tests/Feature/Learn/DiscussionControllerTest.php`:

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
use App\Models\Learn\Discussion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscussionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_instructor_can_view_the_thread(): void
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
        $discussion = Discussion::create(['title' => 'D', 'prompt' => 'P']);
        $discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        $response = $this->actingAs($teacher)->get(route('learn.discussions.show', $discussion));

        $response->assertInertia(fn ($page) => $page
            ->where('discussion.title', 'D')
            ->where('discussion.can_edit', true)
            ->where('current_user_id', $teacher->id)
        );
    }

    public function test_stranger_cannot_view_the_thread(): void
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
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $discussion = Discussion::create(['title' => 'D', 'prompt' => 'P']);
        $discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('learn.discussions.show', $discussion))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify both fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/DiscussionPostServiceTest.php tests/Feature/Learn/DiscussionControllerTest.php"`
Expected: FAIL — `DiscussionPostService`/route don't exist.

- [ ] **Step 3: Write `DiscussionPostService`**

`app/Services/Learn/DiscussionPostService.php`:

```php
<?php

namespace App\Services\Learn;

use App\Models\Learn\Discussion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DiscussionPostService
{
    /** @return array Nested tree — each node: {id, author_type, author_id, author_name, body, is_deleted, created_at, updated_at, replies}. */
    public function tree(Discussion $discussion): array
    {
        $posts = $discussion->posts()->get();
        $authorNames = $this->resolveAuthorNames($posts);

        $childrenByParent = [];
        foreach ($posts as $post) {
            $childrenByParent[$post->parent_post_id ?? 0][] = $post;
        }

        return $this->buildBranch(0, $childrenByParent, $authorNames);
    }

    private function buildBranch(int $parentId, array $childrenByParent, array $authorNames): array
    {
        $branch = [];

        foreach ($childrenByParent[$parentId] ?? [] as $post) {
            $branch[] = [
                'id' => $post->id,
                'author_type' => $post->author_type,
                'author_id' => $post->author_id,
                'author_name' => $authorNames["{$post->author_type}:{$post->author_id}"] ?? 'Unknown',
                'body' => $post->is_deleted ? null : $post->body,
                'is_deleted' => $post->is_deleted,
                'created_at' => $post->created_at->toIso8601String(),
                'updated_at' => $post->updated_at->toIso8601String(),
                'replies' => $this->buildBranch($post->id, $childrenByParent, $authorNames),
            ];
        }

        return $branch;
    }

    /** @return array<string, string> "{author_type}:{author_id}" => display name */
    private function resolveAuthorNames(Collection $posts): array
    {
        $studentIds = $posts->where('author_type', 'student')->pluck('author_id')->unique();
        $userIds = $posts->where('author_type', 'faculty')->pluck('author_id')->unique();

        $names = [];

        if ($studentIds->isNotEmpty()) {
            foreach (DB::table('students')->whereIn('id', $studentIds)->get(['id', 'firstname', 'lastname']) as $s) {
                $names["student:{$s->id}"] = trim("{$s->firstname} {$s->lastname}");
            }
        }

        if ($userIds->isNotEmpty()) {
            foreach (User::whereIn('id', $userIds)->get(['id', 'name']) as $u) {
                $names["faculty:{$u->id}"] = $u->name;
            }
        }

        return $names;
    }
}
```

- [ ] **Step 4: Write `DiscussionController`**

`app/Http/Controllers/Learn/DiscussionController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Discussion;
use App\Services\Learn\DiscussionPostService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DiscussionController extends Controller
{
    public function __construct(private DiscussionPostService $postService)
    {
    }

    /** GET /learn/discussions/{discussion} */
    public function show(Discussion $discussion): Response
    {
        $user = Auth::user();
        $course = $discussion->course();
        abort_if(! $course, 404);
        abort_unless($course->canView($user), 403);

        return Inertia::render('Learn/Discussion', [
            'discussion' => [
                'id' => $discussion->id,
                'title' => $discussion->title,
                'prompt' => $discussion->prompt,
                'max_score' => $discussion->maxScore(),
                'can_edit' => $course->canEdit($user),
            ],
            'posts' => $this->postService->tree($discussion),
            'current_user_id' => $user->id,
        ]);
    }
}
```

Note: `current_user_id` mirrors the same purpose as `current_student_id` in Task 7's student
payload — lets the Vue page determine "is this my post" without any client-side identity
comparison against a shared Inertia auth prop this codebase doesn't rely on elsewhere; every
other Learn page computes permission-relevant flags server-side and ships them as plain data.

- [ ] **Step 5: Add the route**

Add to `routes/web.php`, immediately after the `items.store-discussion` line:

```php
    Route::get('/discussions/{discussion}', [\App\Http\Controllers\Learn\DiscussionController::class, 'show'])->name('discussions.show');
```

- [ ] **Step 6: Run the tests**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/DiscussionPostServiceTest.php tests/Feature/Learn/DiscussionControllerTest.php"`
Expected: PASS (4 tests).

- [ ] **Step 7: Commit**

```bash
git add app/Services/Learn/DiscussionPostService.php app/Http/Controllers/Learn/DiscussionController.php \
        routes/web.php tests/Feature/Learn/DiscussionPostServiceTest.php tests/Feature/Learn/DiscussionControllerTest.php
git commit -m "feat(learn): add discussion post tree assembly and faculty thread page"
```

---

### Task 6: Faculty-side post CRUD

**Files:**
- Create: `app/Http/Controllers/Learn/DiscussionPostController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/DiscussionPostControllerTest.php`

**Interfaces:**
- Produces routes: `learn.discussion-posts.store` (POST `/learn/discussions/{discussion}/posts`),
  `learn.discussion-posts.update` (PUT `/learn/discussion-posts/{post}`),
  `learn.discussion-posts.destroy` (DELETE `/learn/discussion-posts/{post}`).

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
use App\Models\Learn\Discussion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscussionPostControllerTest extends TestCase
{
    use RefreshDatabase;

    private Discussion $discussion;
    private User $teacher;

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
        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $this->discussion = Discussion::create(['title' => 'D', 'prompt' => 'P']);
        $this->discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
    }

    public function test_instructor_can_post_a_top_level_reply_and_a_nested_reply(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.discussion-posts.store', $this->discussion), [
            'body' => 'Top level reply',
        ])->assertRedirect();

        $top = $this->discussion->fresh()->posts()->where('body', 'Top level reply')->firstOrFail();
        $this->assertSame('faculty', $top->author_type);
        $this->assertSame($this->teacher->id, $top->author_id);

        $this->actingAs($this->teacher)->post(route('learn.discussion-posts.store', $this->discussion), [
            'parent_post_id' => $top->id, 'body' => 'Nested reply',
        ])->assertRedirect();

        $nested = $this->discussion->fresh()->posts()->where('body', 'Nested reply')->firstOrFail();
        $this->assertSame($top->id, $nested->parent_post_id);
    }

    public function test_parent_post_id_from_a_different_discussion_is_rejected(): void
    {
        $otherDiscussion = Discussion::create(['title' => 'Other', 'prompt' => 'P']);
        $otherPost = $otherDiscussion->posts()->create(['author_type' => 'faculty', 'author_id' => $this->teacher->id, 'body' => 'x']);

        $this->actingAs($this->teacher)->post(route('learn.discussion-posts.store', $this->discussion), [
            'parent_post_id' => $otherPost->id, 'body' => 'Should fail',
        ])->assertStatus(422);
    }

    public function test_author_can_edit_their_own_post_but_not_someone_elses(): void
    {
        $post = $this->discussion->posts()->create(['author_type' => 'faculty', 'author_id' => $this->teacher->id, 'body' => 'Original']);

        $this->actingAs($this->teacher)->put(route('learn.discussion-posts.update', $post), ['body' => 'Edited'])->assertRedirect();
        $this->assertSame('Edited', $post->fresh()->body);

        $otherTeacher = User::factory()->create();
        $this->actingAs($otherTeacher)->put(route('learn.discussion-posts.update', $post), ['body' => 'Hijacked'])->assertForbidden();
        $this->assertSame('Edited', $post->fresh()->body);
    }

    public function test_editing_a_deleted_post_is_rejected(): void
    {
        $post = $this->discussion->posts()->create([
            'author_type' => 'faculty', 'author_id' => $this->teacher->id, 'body' => 'x',
            'is_deleted' => true, 'deleted_by_type' => 'faculty', 'deleted_by_id' => $this->teacher->id,
        ]);

        $this->actingAs($this->teacher)->put(route('learn.discussion-posts.update', $post), ['body' => 'Resurrected'])->assertForbidden();
    }

    public function test_author_can_delete_their_own_post_and_instructor_can_delete_anyones(): void
    {
        $studentId = mt_rand(1, 999999999);
        $studentPost = $this->discussion->posts()->create(['author_type' => 'student', 'author_id' => $studentId, 'body' => 'Student post']);

        $this->actingAs($this->teacher)->delete(route('learn.discussion-posts.destroy', $studentPost))->assertRedirect();
        $this->assertTrue($studentPost->fresh()->is_deleted);
        $this->assertSame('faculty', $studentPost->fresh()->deleted_by_type);
        $this->assertSame($this->teacher->id, $studentPost->fresh()->deleted_by_id);
    }

    public function test_stranger_cannot_post_edit_or_delete(): void
    {
        $stranger = User::factory()->create();
        $post = $this->discussion->posts()->create(['author_type' => 'faculty', 'author_id' => $this->teacher->id, 'body' => 'x']);

        $this->actingAs($stranger)->post(route('learn.discussion-posts.store', $this->discussion), ['body' => 'x'])->assertForbidden();
        $this->actingAs($stranger)->put(route('learn.discussion-posts.update', $post), ['body' => 'x'])->assertForbidden();
        $this->actingAs($stranger)->delete(route('learn.discussion-posts.destroy', $post))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/DiscussionPostControllerTest.php"`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Write `DiscussionPostController`**

`app/Http/Controllers/Learn/DiscussionPostController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Discussion;
use App\Models\Learn\DiscussionPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiscussionPostController extends Controller
{
    /** POST /learn/discussions/{discussion}/posts */
    public function store(Request $request, Discussion $discussion)
    {
        $user = Auth::user();
        $course = $discussion->course();
        abort_if(! $course, 404);
        abort_unless($course->canView($user), 403);

        $validated = $request->validate([
            'parent_post_id' => 'nullable|integer|exists:learn_discussion_posts,id',
            'body' => 'required|string',
        ]);

        if (! empty($validated['parent_post_id'])) {
            $parentBelongsHere = DiscussionPost::where('id', $validated['parent_post_id'])
                ->where('learn_discussion_id', $discussion->id)
                ->exists();
            abort_unless($parentBelongsHere, 422, 'Invalid parent post.');
        }

        $discussion->posts()->create([
            'parent_post_id' => $validated['parent_post_id'] ?? null,
            'author_type' => 'faculty',
            'author_id' => $user->id,
            'body' => $validated['body'],
        ]);

        return back()->with('success', 'Reply posted.');
    }

    /** PUT /learn/discussion-posts/{post} */
    public function update(Request $request, DiscussionPost $post)
    {
        $user = Auth::user();
        abort_unless($post->author_type === 'faculty' && $post->author_id === $user->id, 403);
        abort_if($post->is_deleted, 403, 'This post has been deleted.');

        $validated = $request->validate(['body' => 'required|string']);
        $post->update(['body' => $validated['body']]);

        return back()->with('success', 'Post updated.');
    }

    /** DELETE /learn/discussion-posts/{post} */
    public function destroy(DiscussionPost $post)
    {
        $user = Auth::user();
        $isOwnPost = $post->author_type === 'faculty' && $post->author_id === $user->id;
        $canModerate = $post->discussion->canEdit($user);
        abort_unless($isOwnPost || $canModerate, 403);

        $post->update([
            'is_deleted' => true,
            'deleted_by_type' => 'faculty',
            'deleted_by_id' => $user->id,
        ]);

        return back()->with('success', 'Post deleted.');
    }
}
```

- [ ] **Step 4: Add the routes**

Add to `routes/web.php`, immediately after the `discussions.show` line:

```php
    Route::post('/discussions/{discussion}/posts', [\App\Http\Controllers\Learn\DiscussionPostController::class, 'store'])->name('discussion-posts.store');
    Route::put('/discussion-posts/{post}', [\App\Http\Controllers\Learn\DiscussionPostController::class, 'update'])->name('discussion-posts.update');
    Route::delete('/discussion-posts/{post}', [\App\Http\Controllers\Learn\DiscussionPostController::class, 'destroy'])->name('discussion-posts.destroy');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/DiscussionPostControllerTest.php"`
Expected: PASS (6 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/DiscussionPostController.php routes/web.php \
        tests/Feature/Learn/DiscussionPostControllerTest.php
git commit -m "feat(learn): add faculty-side discussion post CRUD"
```

---

### Task 7: Student-facing thread page

**Files:**
- Create: `app/Http/Controllers/StudentPortal/DiscussionController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/StudentPortal/DiscussionControllerTest.php`

**Interfaces:**
- Consumes: `DiscussionPostService::tree()` (Task 5) — same tree shape as the faculty page.
- Produces route: `student-portal.learn.discussions.show` (GET
  `/student-portal/learn/discussions/{discussion}`). Payload includes `current_student_id` so the
  Vue page can determine which posts are the signed-in student's own (for showing edit/delete).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Discussion;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiscussionControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_can_view_the_thread(): void
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        AcademicTerm::create([
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
            'school_year_id' => $sy->id, 'academic_term_id' => AcademicTerm::first()->id, 'status' => 'published',
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $discussion = Discussion::create(['title' => 'D', 'prompt' => 'P']);
        $discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);

        $studentId = mt_rand(1, 999999999);
        DB::table('students')->insert(['id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Test', 'lastname' => 'Student']);
        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
        session(['student_pisaysystemID' => "PS{$studentId}"]);

        $response = $this->get(route('student-portal.learn.discussions.show', $discussion));

        $response->assertInertia(fn ($page) => $page
            ->where('discussion.title', 'D')
            ->where('current_student_id', $studentId)
        );
    }

    public function test_a_student_not_enrolled_in_the_course_is_forbidden(): void
    {
        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        AcademicTerm::create([
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
            'school_year_id' => $sy->id, 'academic_term_id' => AcademicTerm::first()->id, 'status' => 'published',
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $discussion = Discussion::create(['title' => 'D', 'prompt' => 'P']);
        $discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);

        $studentId = mt_rand(1, 999999999);
        DB::table('students')->insert(['id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Test', 'lastname' => 'Student']);
        // No StudentEnrollment row — not enrolled.
        session(['student_pisaysystemID' => "PS{$studentId}"]);

        $this->get(route('student-portal.learn.discussions.show', $discussion))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/DiscussionControllerTest.php"`
Expected: FAIL — route doesn't exist.

- [ ] **Step 3: Write `DiscussionController`**

`app/Http/Controllers/StudentPortal/DiscussionController.php`:

```php
<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Learn\Discussion;
use App\Models\Student;
use App\Services\Learn\DiscussionPostService;
use Inertia\Inertia;
use Inertia\Response;

class DiscussionController extends Controller
{
    public function __construct(private DiscussionPostService $postService)
    {
    }

    /** GET /student-portal/learn/discussions/{discussion} */
    public function show(Discussion $discussion): Response
    {
        $student = $this->currentStudent();
        $course = $discussion->course();
        abort_if(! $course, 404);
        abort_unless($course->isVisibleToStudent($student->id), 403);

        return Inertia::render('StudentPortal/Learn/Discussion', [
            'discussion' => [
                'id' => $discussion->id,
                'title' => $discussion->title,
                'prompt' => $discussion->prompt,
                'max_score' => $discussion->maxScore(),
            ],
            'posts' => $this->postService->tree($discussion),
            'current_student_id' => $student->id,
        ]);
    }

    private function currentStudent(): Student
    {
        return Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();
    }
}
```

- [ ] **Step 4: Add the route**

Add inside the `student.portal` middleware group in `routes/web.php`, immediately after the
`learn.quiz-attempts.show` line:

```php
        Route::get('/learn/discussions/{discussion}', [\App\Http\Controllers\StudentPortal\DiscussionController::class, 'show'])->name('learn.discussions.show');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/DiscussionControllerTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/StudentPortal/DiscussionController.php routes/web.php \
        tests/Feature/StudentPortal/DiscussionControllerTest.php
git commit -m "feat(learn): add student-facing discussion thread page"
```

---

### Task 8: Student-side post CRUD

**Files:**
- Create: `app/Http/Controllers/StudentPortal/DiscussionPostController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/StudentPortal/DiscussionPostControllerTest.php`

**Interfaces:**
- Produces routes: `student-portal.learn.discussion-posts.store` (POST
  `/student-portal/learn/discussions/{discussion}/posts`), `.update` (PUT
  `/student-portal/learn/discussion-posts/{post}`), `.destroy` (DELETE
  `/student-portal/learn/discussion-posts/{post}`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Discussion;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiscussionPostControllerTest extends TestCase
{
    use RefreshDatabase;

    private Discussion $discussion;
    private int $studentId;

    protected function setUp(): void
    {
        parent::setUp();

        $sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        AcademicTerm::create([
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
            'school_year_id' => $sy->id, 'academic_term_id' => AcademicTerm::first()->id, 'status' => 'published',
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0, 'published_at' => now()]);
        $this->discussion = Discussion::create(['title' => 'D', 'prompt' => 'P']);
        $this->discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0, 'published_at' => now()]);

        $this->studentId = mt_rand(1, 999999999);
        DB::table('students')->insert(['id' => $this->studentId, 'pisaysystemID' => "PS{$this->studentId}", 'firstname' => 'Test', 'lastname' => 'Student']);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
        session(['student_pisaysystemID' => "PS{$this->studentId}"]);
    }

    public function test_student_can_post_a_top_level_reply_and_a_nested_reply(): void
    {
        $this->post(route('student-portal.learn.discussion-posts.store', $this->discussion), [
            'body' => 'Top level',
        ])->assertRedirect();

        $top = $this->discussion->fresh()->posts()->where('body', 'Top level')->firstOrFail();
        $this->assertSame('student', $top->author_type);
        $this->assertSame($this->studentId, $top->author_id);

        $this->post(route('student-portal.learn.discussion-posts.store', $this->discussion), [
            'parent_post_id' => $top->id, 'body' => 'Nested',
        ])->assertRedirect();

        $this->assertSame($top->id, $this->discussion->fresh()->posts()->where('body', 'Nested')->firstOrFail()->parent_post_id);
    }

    public function test_student_can_edit_their_own_post_but_not_someone_elses(): void
    {
        $post = $this->discussion->posts()->create(['author_type' => 'student', 'author_id' => $this->studentId, 'body' => 'Original']);

        $this->put(route('student-portal.learn.discussion-posts.update', $post), ['body' => 'Edited'])->assertRedirect();
        $this->assertSame('Edited', $post->fresh()->body);

        $facultyPost = $this->discussion->posts()->create(['author_type' => 'faculty', 'author_id' => 1, 'body' => 'Teacher post']);
        $this->put(route('student-portal.learn.discussion-posts.update', $facultyPost), ['body' => 'Hijacked'])->assertForbidden();
    }

    public function test_editing_a_deleted_post_is_rejected(): void
    {
        $post = $this->discussion->posts()->create([
            'author_type' => 'student', 'author_id' => $this->studentId, 'body' => 'x',
            'is_deleted' => true, 'deleted_by_type' => 'student', 'deleted_by_id' => $this->studentId,
        ]);

        $this->put(route('student-portal.learn.discussion-posts.update', $post), ['body' => 'Resurrected'])->assertForbidden();
    }

    public function test_student_can_delete_their_own_post_but_not_another_students(): void
    {
        $post = $this->discussion->posts()->create(['author_type' => 'student', 'author_id' => $this->studentId, 'body' => 'x']);
        $this->delete(route('student-portal.learn.discussion-posts.destroy', $post))->assertRedirect();
        $this->assertTrue($post->fresh()->is_deleted);
        $this->assertSame('student', $post->fresh()->deleted_by_type);
        $this->assertSame($this->studentId, $post->fresh()->deleted_by_id);

        $otherStudentPost = $this->discussion->posts()->create(['author_type' => 'student', 'author_id' => 999999, 'body' => 'y']);
        $this->delete(route('student-portal.learn.discussion-posts.destroy', $otherStudentPost))->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/DiscussionPostControllerTest.php"`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Write `DiscussionPostController`**

`app/Http/Controllers/StudentPortal/DiscussionPostController.php`:

```php
<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Learn\Discussion;
use App\Models\Learn\DiscussionPost;
use App\Models\Student;
use Illuminate\Http\Request;

class DiscussionPostController extends Controller
{
    /** POST /student-portal/learn/discussions/{discussion}/posts */
    public function store(Request $request, Discussion $discussion)
    {
        $student = $this->currentStudent();
        $course = $discussion->course();
        abort_if(! $course, 404);
        abort_unless($course->isVisibleToStudent($student->id), 403);

        $validated = $request->validate([
            'parent_post_id' => 'nullable|integer|exists:learn_discussion_posts,id',
            'body' => 'required|string',
        ]);

        if (! empty($validated['parent_post_id'])) {
            $parentBelongsHere = DiscussionPost::where('id', $validated['parent_post_id'])
                ->where('learn_discussion_id', $discussion->id)
                ->exists();
            abort_unless($parentBelongsHere, 422, 'Invalid parent post.');
        }

        $discussion->posts()->create([
            'parent_post_id' => $validated['parent_post_id'] ?? null,
            'author_type' => 'student',
            'author_id' => $student->id,
            'body' => $validated['body'],
        ]);

        return back()->with('success', 'Reply posted.');
    }

    /** PUT /student-portal/learn/discussion-posts/{post} */
    public function update(Request $request, DiscussionPost $post)
    {
        $student = $this->currentStudent();
        abort_unless($post->author_type === 'student' && $post->author_id === $student->id, 403);
        abort_if($post->is_deleted, 403, 'This post has been deleted.');

        $validated = $request->validate(['body' => 'required|string']);
        $post->update(['body' => $validated['body']]);

        return back()->with('success', 'Post updated.');
    }

    /** DELETE /student-portal/learn/discussion-posts/{post} */
    public function destroy(DiscussionPost $post)
    {
        $student = $this->currentStudent();
        abort_unless($post->author_type === 'student' && $post->author_id === $student->id, 403);

        $post->update([
            'is_deleted' => true,
            'deleted_by_type' => 'student',
            'deleted_by_id' => $student->id,
        ]);

        return back()->with('success', 'Post deleted.');
    }

    private function currentStudent(): Student
    {
        return Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();
    }
}
```

- [ ] **Step 4: Add the routes**

Add inside the `student.portal` middleware group in `routes/web.php`, immediately after the
`learn.discussions.show` line:

```php
        Route::post('/learn/discussions/{discussion}/posts', [\App\Http\Controllers\StudentPortal\DiscussionPostController::class, 'store'])->name('learn.discussion-posts.store');
        Route::put('/learn/discussion-posts/{post}', [\App\Http\Controllers\StudentPortal\DiscussionPostController::class, 'update'])->name('learn.discussion-posts.update');
        Route::delete('/learn/discussion-posts/{post}', [\App\Http\Controllers\StudentPortal\DiscussionPostController::class, 'destroy'])->name('learn.discussion-posts.destroy');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/DiscussionPostControllerTest.php"`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/StudentPortal/DiscussionPostController.php routes/web.php \
        tests/Feature/StudentPortal/DiscussionPostControllerTest.php
git commit -m "feat(learn): add student-side discussion post CRUD"
```

---

### Task 9: Discussion grading roster

**Files:**
- Create: `app/Http/Controllers/Learn/DiscussionGradingController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/DiscussionGradingControllerTest.php`

**Interfaces:**
- Produces routes: `learn.discussions.grades` (GET `/learn/discussions/{discussion}/grades`),
  `learn.discussions.grade` (PUT `/learn/discussions/{discussion}/grades/{student}`).
- Reuses the same enrolled-roster resolution pattern as `SubmissionRosterService::rosterFor()`
  (Phase 2) — every enrolled student in the discussion's course, not just those who posted.

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
use App\Models\Learn\Discussion;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DiscussionGradingControllerTest extends TestCase
{
    use RefreshDatabase;

    private Discussion $discussion;
    private User $teacher;
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
        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id,
            'section_id' => $section->id, 'load_units' => 3,
        ]);
        $course = Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $this->discussion = Discussion::create(['title' => 'D', 'prompt' => 'P', 'points_possible' => 10]);
        $this->discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        $this->studentId = mt_rand(1, 999999999);
        DB::table('students')->insert(['id' => $this->studentId, 'pisaysystemID' => "PS{$this->studentId}", 'firstname' => 'Ana', 'lastname' => 'Cruz']);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => 8, 'status' => 'enrolled', 'enrollment_date' => now()->toDateString(),
        ]);
    }

    public function test_index_lists_every_enrolled_student_even_those_who_never_posted(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('learn.discussions.grades', $this->discussion));

        $response->assertInertia(fn ($page) => $page
            ->where('roster.0.student_id', $this->studentId)
            ->where('roster.0.name', 'Cruz, Ana')
            ->where('roster.0.points_earned', null)
        );
    }

    public function test_grading_a_student_persists_and_is_reflected_in_the_roster(): void
    {
        $this->actingAs($this->teacher)->put(route('learn.discussions.grade', [$this->discussion, $this->studentId]), [
            'points_earned' => 8, 'feedback_comment' => 'Great participation.',
        ])->assertRedirect();

        $grade = $this->discussion->grades()->where('student_id', $this->studentId)->firstOrFail();
        $this->assertSame('8.00', $grade->points_earned);
        $this->assertSame('Great participation.', $grade->feedback_comment);
        $this->assertNotNull($grade->graded_at);
    }

    public function test_grading_rejects_a_score_above_points_possible(): void
    {
        $this->actingAs($this->teacher)->put(route('learn.discussions.grade', [$this->discussion, $this->studentId]), [
            'points_earned' => 999,
        ])->assertSessionHasErrors('points_earned');
    }

    public function test_stranger_cannot_view_roster_or_grade(): void
    {
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->get(route('learn.discussions.grades', $this->discussion))->assertForbidden();
        $this->actingAs($stranger)->put(route('learn.discussions.grade', [$this->discussion, $this->studentId]), ['points_earned' => 5])->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/DiscussionGradingControllerTest.php"`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Write `DiscussionGradingController`**

`app/Http/Controllers/Learn/DiscussionGradingController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Discussion;
use App\Models\Learn\DiscussionGrade;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DiscussionGradingController extends Controller
{
    /** GET /learn/discussions/{discussion}/grades */
    public function index(Discussion $discussion): Response
    {
        $user = Auth::user();
        abort_unless($discussion->canEdit($user), 403);

        $course = $discussion->course();
        abort_if(! $course, 404);

        $studentIds = StudentEnrollment::where('school_year_id', $course->school_year_id)
            ->where('section_id', $course->section_id)
            ->where('status', 'enrolled')
            ->pluck('student_id')
            ->unique()
            ->values();

        $students = DB::table('students')->whereIn('id', $studentIds)->get(['id', 'lastname', 'firstname'])->keyBy('id');
        $grades = $discussion->grades()->whereIn('student_id', $studentIds)->get()->keyBy('student_id');

        $roster = $studentIds->map(function ($studentId) use ($students, $grades) {
            $student = $students->get($studentId);
            $grade = $grades->get($studentId);

            return [
                'student_id' => (int) $studentId,
                'name' => $student ? trim("{$student->lastname}, {$student->firstname}") : "Student #{$studentId}",
                'points_earned' => $grade?->points_earned !== null ? (float) $grade->points_earned : null,
                'feedback_comment' => $grade?->feedback_comment,
            ];
        })->sortBy('name')->values();

        return Inertia::render('Learn/DiscussionGrading', [
            'discussion' => [
                'id' => $discussion->id,
                'title' => $discussion->title,
                'max_score' => $discussion->maxScore(),
            ],
            'roster' => $roster,
        ]);
    }

    /** PUT /learn/discussions/{discussion}/grades/{student} */
    public function grade(Request $request, Discussion $discussion, Student $student)
    {
        $user = Auth::user();
        abort_unless($discussion->canEdit($user), 403);
        abort_if($discussion->points_possible === null, 422, 'This discussion is not graded.');

        $validated = $request->validate([
            'points_earned' => 'required|numeric|min:0|max:' . $discussion->points_possible,
            'feedback_comment' => 'nullable|string',
        ]);

        DiscussionGrade::updateOrCreate(
            ['learn_discussion_id' => $discussion->id, 'student_id' => $student->id],
            [
                'points_earned' => $validated['points_earned'],
                'feedback_comment' => $validated['feedback_comment'] ?? null,
                'graded_at' => now(),
                'graded_by' => $user->id,
            ]
        );

        return back()->with('success', 'Grade saved.');
    }
}
```

- [ ] **Step 4: Add the routes**

Add to `routes/web.php`, immediately after the `discussion-posts.destroy` line:

```php
    Route::get('/discussions/{discussion}/grades', [\App\Http\Controllers\Learn\DiscussionGradingController::class, 'index'])->name('discussions.grades');
    Route::put('/discussions/{discussion}/grades/{student}', [\App\Http\Controllers\Learn\DiscussionGradingController::class, 'grade'])->name('discussions.grade');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/DiscussionGradingControllerTest.php"`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/DiscussionGradingController.php routes/web.php \
        tests/Feature/Learn/DiscussionGradingControllerTest.php
git commit -m "feat(learn): add discussion grading roster"
```

---

### Task 10: Class Record push for Discussion + WAT invariant

**Files:**
- Create: `app/Http/Controllers/Learn/DiscussionClassRecordPushController.php`
- Modify: `app/Http/Controllers/Learn/DiscussionGradingController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/DiscussionClassRecordPushTest.php`

**Interfaces:**
- Consumes: `ClassRecordPushService` (Phase 3, already generically typed against
  `HasClassRecordLink` — zero changes needed to the service itself, same additive-widening
  pattern as adding Quiz support did).
- Produces routes: `learn.discussions.link` (PUT `/learn/discussions/{discussion}/link`),
  `learn.discussions.push` (POST `/learn/discussions/{discussion}/push`).
- Extends `DiscussionGradingController::index()`'s payload with `class_record_link`/
  `class_record_options`, same shape as `QuizGradingController::index()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\Discussion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DiscussionClassRecordPushTest extends TestCase
{
    use RefreshDatabase;

    public function test_link_and_push_work_for_a_discussion_and_never_touch_wat_scheduling_fields(): void
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
        $discussion = Discussion::create(['title' => 'D', 'prompt' => 'P', 'points_possible' => 20]);
        $discussion->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        $option = GradingOption::create(['name' => 'Standard', 'is_active' => true]);
        $classRecord = ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $option->id, 'school_year_id' => $sy->id,
            'school_year' => $sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);
        $quarter = ClassRecordQuarter::create([
            'class_record_id' => $classRecord->id, 'grading_option_id' => $option->id,
            'quarter' => 1, 'is_locked' => false,
        ]);
        $category = GradingCategory::create([
            'grading_option_id' => $option->id, 'name' => 'Written Work', 'code' => 'WW',
            'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);
        $plottedAt = now()->subDays(3);
        $activityDate = now()->subDays(2)->toDateString();
        $assessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 1, 'title' => 'Discussion 1', 'max_score' => 20, 'sort_order' => 1,
            'plotted_at' => $plottedAt, 'activity_date' => $activityDate,
        ]);

        $studentId = mt_rand(1, 999999999);
        \Illuminate\Support\Facades\DB::table('students')->insert([
            'id' => $studentId, 'pisaysystemID' => "PS{$studentId}", 'firstname' => 'Test', 'lastname' => 'Student',
        ]);
        ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'student_id' => $studentId,
            'family_name' => 'Student', 'given_name' => 'Test', 'sex' => 'M', 'sequence_number' => 1,
        ]);
        $discussion->grades()->create(['student_id' => $studentId, 'points_earned' => 18, 'graded_at' => now()]);

        $this->actingAs($teacher)->put(route('learn.discussions.link', $discussion), [
            'class_record_assessment_id' => $assessment->id,
        ])->assertRedirect();
        $this->assertSame($assessment->id, $discussion->fresh()->class_record_assessment_id);

        $this->actingAs($teacher)->post(route('learn.discussions.push', $discussion))->assertRedirect();

        $this->assertDatabaseHas('class_record_scores', [
            'class_record_student_id' => ClassRecordStudent::where('student_id', $studentId)->first()->id,
            'class_record_assessment_id' => $assessment->id,
            'score' => 18,
        ]);
        $this->assertNotNull($discussion->fresh()->pushed_at);

        // WAT invariant: linking/pushing never touches the assessment's own scheduling fields.
        $assessment->refresh();
        $this->assertSame($plottedAt->toDateTimeString(), $assessment->plotted_at->toDateTimeString());
        $this->assertSame($activityDate, $assessment->activity_date->toDateString());
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/DiscussionClassRecordPushTest.php"`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Write `DiscussionClassRecordPushController`**

`app/Http/Controllers/Learn/DiscussionClassRecordPushController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Discussion;
use App\Services\Learn\ClassRecordPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiscussionClassRecordPushController extends Controller
{
    public function __construct(private ClassRecordPushService $pushService)
    {
    }

    /** PUT /learn/discussions/{discussion}/link */
    public function link(Request $request, Discussion $discussion)
    {
        $validated = $request->validate([
            'class_record_assessment_id' => 'required|integer|exists:class_record_assessments,id',
        ]);

        $this->pushService->link($discussion, $validated['class_record_assessment_id'], Auth::user());

        return back()->with('success', 'Linked to Class Record assessment.');
    }

    /** POST /learn/discussions/{discussion}/push */
    public function push(Discussion $discussion)
    {
        $result = $this->pushService->push($discussion, Auth::user());

        $message = "Pushed {$result['pushed']} score(s) to Class Record.";
        if (! empty($result['skipped'])) {
            $message .= ' Skipped (not on quarter roster): ' . implode(', ', $result['skipped']) . '.';
        }

        return back()->with('success', $message);
    }
}
```

- [ ] **Step 4: Extend `DiscussionGradingController::index` with Class Record data**

Add the constructor:

```php
    public function __construct(private ClassRecordPushService $pushService)
    {
    }
```

Add the import:

```php
use App\Services\Learn\ClassRecordPushService;
```

In `index()`, eager load the link and compute candidates before building the response:

```php
        $discussion->load(['classRecordAssessment.gradingCategory', 'classRecordAssessment.quarter.classRecord']);
        $classRecordOptions = $this->pushService->candidateClassRecords($discussion);
```

Extend the `'discussion'` key in the returned Inertia payload:

```php
            'discussion' => [
                'id' => $discussion->id,
                'title' => $discussion->title,
                'max_score' => $discussion->maxScore(),
                'class_record_link' => $discussion->classRecordAssessment ? [
                    'assessment_id' => $discussion->classRecordAssessment->id,
                    'assessment_title' => $discussion->classRecordAssessment->title,
                    'class_record_name' => $discussion->classRecordAssessment->quarter->classRecord->display_name,
                    'quarter' => $discussion->classRecordAssessment->quarter->quarter,
                    'category_name' => $discussion->classRecordAssessment->gradingCategory->name,
                    'max_score' => (float) $discussion->classRecordAssessment->max_score,
                    'pushed_at' => $discussion->pushed_at?->toIso8601String(),
                ] : null,
                'class_record_options' => $classRecordOptions->map(fn ($cr) => [
                    'id' => $cr->id,
                    'display_name' => $cr->display_name,
                    'quarters' => $cr->quarters->map(fn ($q) => [
                        'id' => $q->id,
                        'quarter' => $q->quarter,
                        'assessments' => $q->assessments->map(fn ($a) => [
                            'id' => $a->id, 'title' => $a->title, 'max_score' => (float) $a->max_score,
                            'category_name' => $a->gradingCategory->name,
                        ])->values(),
                    ])->values(),
                ])->values(),
            ],
```

- [ ] **Step 5: Add the routes**

Add to `routes/web.php`, immediately after the `discussions.grade` line:

```php
    Route::put('/discussions/{discussion}/link', [\App\Http\Controllers\Learn\DiscussionClassRecordPushController::class, 'link'])->name('discussions.link');
    Route::post('/discussions/{discussion}/push', [\App\Http\Controllers\Learn\DiscussionClassRecordPushController::class, 'push'])->name('discussions.push');
```

- [ ] **Step 6: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/DiscussionClassRecordPushTest.php"`
Expected: PASS (1 test).

- [ ] **Step 7: Run Phase 3's Class Record push tests + Task 9's grading test to confirm no regression**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/QuizClassRecordPushTest.php tests/Feature/Learn/ClassRecordPushServiceTest.php tests/Feature/Learn/DiscussionGradingControllerTest.php"`
Expected: PASS, same counts as before — widening usage to a third `HasClassRecordLink`
implementer and adding a constructor dependency to `DiscussionGradingController` must not change
existing behavior.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Learn/DiscussionClassRecordPushController.php \
        app/Http/Controllers/Learn/DiscussionGradingController.php routes/web.php \
        tests/Feature/Learn/DiscussionClassRecordPushTest.php
git commit -m "feat(learn): add Class Record push for discussions"
```

---

### Task 11: Show.vue — discussion authoring UI

**Files:**
- Modify: `resources/js/Pages/Learn/Show.vue`

**Interfaces:**
- Consumes `learn.items.store-discussion` (Task 3), `learn.discussions.show` /
  `learn.discussions.grades` (Tasks 5/9) for navigation links.
- No backend test — frontend-only, verified by build (same pattern as every prior frontend task).

- [ ] **Step 1: Add a `ChatBubbleLeftRightIcon` import and discussion form state**

Add `ChatBubbleLeftRightIcon` to the existing `@heroicons/vue/24/outline` import line (alongside
`AcademicCapIcon`).

Add this state and functions after the existing `deleteAnnouncement` function (or anywhere among
the other module-item functions):

```js
const discussionForms = ref({})
function discussionForm(moduleId) {
  if (! discussionForms.value[moduleId]) {
    discussionForms.value[moduleId] = useForm({ title: '', prompt: '', points_possible: '' })
  }
  return discussionForms.value[moduleId]
}
function addDiscussion(moduleId) {
  discussionForm(moduleId).post(route('learn.items.store-discussion', moduleId), {
    preserveScroll: true,
    onSuccess: () => { discussionForms.value[moduleId] = null },
  })
}
```

- [ ] **Step 2: Add discussion item display to the item list**

In the item-list loop, immediately after the existing `v-if="item.type === 'quiz'"` block closes
(right before the item's outer `</div>` that follows it), add:

```html
                <div v-if="item.type === 'discussion'" class="mt-1 space-y-1">
                  <div class="prose prose-sm max-w-none" v-html="sanitizeHtml(item.discussion.prompt)" />
                  <p class="text-xs text-slate-500">
                    {{ item.discussion.post_count }} post{{ item.discussion.post_count === 1 ? '' : 's' }}
                    <span v-if="item.discussion.max_score !== null"> — {{ item.discussion.max_score }} pts</span>
                  </p>
                  <div class="flex gap-2">
                    <Link :href="route('learn.discussions.show', item.discussion.id)" class="text-xs text-indigo-600 underline">View discussion</Link>
                    <Link v-if="item.discussion.max_score !== null" :href="route('learn.discussions.grades', item.discussion.id)" class="text-xs text-indigo-600 underline">Grades</Link>
                  </div>
                </div>
```

Also update the icon line — change:

```html
              <DocumentIcon v-if="item.type === 'page'" class="h-5 w-5 text-slate-400 shrink-0" />
              <AcademicCapIcon v-else-if="item.type === 'quiz'" class="h-5 w-5 text-slate-400 shrink-0" />
              <PaperClipIcon v-else class="h-5 w-5 text-slate-400 shrink-0" />
```

to:

```html
              <DocumentIcon v-if="item.type === 'page'" class="h-5 w-5 text-slate-400 shrink-0" />
              <AcademicCapIcon v-else-if="item.type === 'quiz'" class="h-5 w-5 text-slate-400 shrink-0" />
              <ChatBubbleLeftRightIcon v-else-if="item.type === 'discussion'" class="h-5 w-5 text-slate-400 shrink-0" />
              <PaperClipIcon v-else class="h-5 w-5 text-slate-400 shrink-0" />
```

- [ ] **Step 3: Add the discussion authoring form**

Immediately after the "New quiz" section's closing `</div>` (right before the module's item-list
outer `</div>` that follows it), add:

```html
              <div v-if="course.can_edit" class="border-t border-slate-100 pt-3 space-y-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">New discussion</p>
                <input v-model="discussionForm(module.id).title" placeholder="Discussion title" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
                <textarea v-model="discussionForm(module.id).prompt" placeholder="Discussion prompt" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />
                <input v-model="discussionForm(module.id).points_possible" type="number" min="0" placeholder="Points possible (optional — leave blank for ungraded)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
                <button @click="addDiscussion(module.id)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Add discussion</button>
              </div>
```

- [ ] **Step 4: Build and verify no errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing `Learn/Show.vue`.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Learn/Show.vue
git commit -m "feat(learn): add discussion authoring UI to Show.vue"
```

---

### Task 12: Shared post-tree component + faculty thread page

**Files:**
- Create: `resources/js/Components/DiscussionPostNode.vue`
- Create: `resources/js/Pages/Learn/Discussion.vue`

**Interfaces:**
- Produces: `<DiscussionPostNode :post :current-author-type :current-author-id :can-moderate-any
  @reply @edit @delete />` — a self-recursive component (Vue 3 SFCs can reference themselves by
  filename with no explicit registration) rendering one post plus its nested `replies`, reused by
  both the faculty (this task) and student (Task 14) thread pages.
- Consumes `learn.discussions.show` (Task 5, now including `current_user_id`),
  `learn.discussion-posts.store/update/destroy` (Task 6). No backend test — frontend-only,
  verified by build.

- [ ] **Step 1: Write `DiscussionPostNode.vue`**

`resources/js/Components/DiscussionPostNode.vue`:

```vue
<script setup>
import { ref, computed } from 'vue'
import MathContent from '@/Components/MathContent.vue'
import DOMPurify from 'dompurify'

const props = defineProps({
  post: Object,
  currentAuthorType: String,
  currentAuthorId: [Number, String],
  canModerateAny: Boolean,
})

const emit = defineEmits(['reply', 'edit', 'delete'])

function sanitizeHtml(html) {
  return DOMPurify.sanitize(html || '')
}

const isOwnPost = computed(() =>
  props.post.author_type === props.currentAuthorType && Number(props.post.author_id) === Number(props.currentAuthorId)
)
const canEditPost = computed(() => isOwnPost.value && ! props.post.is_deleted)
const canDeletePost = computed(() => (isOwnPost.value || props.canModerateAny) && ! props.post.is_deleted)

const replying = ref(false)
const editing = ref(false)
const replyBody = ref('')
const editBody = ref(props.post.body || '')

function submitReply() {
  if (! replyBody.value.trim()) return
  emit('reply', props.post.id, replyBody.value)
  replyBody.value = ''
  replying.value = false
}
function submitEdit() {
  emit('edit', props.post.id, editBody.value)
  editing.value = false
}
</script>

<template>
  <div class="border-l-2 border-slate-100 pl-3">
    <div class="border border-slate-200 rounded-lg p-3 mb-2">
      <div class="flex items-center justify-between">
        <p class="text-xs font-medium text-slate-700">{{ post.author_name }}</p>
        <p class="text-xs text-slate-400">{{ new Date(post.created_at).toLocaleString('en-PH') }}</p>
      </div>

      <p v-if="post.is_deleted" class="text-sm text-slate-400 italic mt-1">[deleted]</p>
      <MathContent v-else-if="!editing" :html="sanitizeHtml(post.body)" class="prose prose-sm max-w-none mt-1" />
      <div v-else class="mt-1 space-y-2">
        <textarea v-model="editBody" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />
        <div class="flex gap-2">
          <button @click="submitEdit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Save</button>
          <button @click="editing = false" class="text-xs text-slate-500 underline">Cancel</button>
        </div>
      </div>

      <div v-if="!post.is_deleted" class="flex gap-3 mt-2">
        <button @click="replying = !replying" class="text-xs text-indigo-600 underline">Reply</button>
        <button v-if="canEditPost && !editing" @click="editing = true" class="text-xs text-slate-500 underline">Edit</button>
        <button v-if="canDeletePost" @click="emit('delete', post.id)" class="text-xs text-red-500 underline">Delete</button>
      </div>

      <div v-if="replying" class="mt-2 space-y-2">
        <textarea v-model="replyBody" placeholder="Write a reply" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />
        <div class="flex gap-2">
          <button @click="submitReply" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Post reply</button>
          <button @click="replying = false" class="text-xs text-slate-500 underline">Cancel</button>
        </div>
      </div>
    </div>

    <DiscussionPostNode
      v-for="reply in post.replies"
      :key="reply.id"
      :post="reply"
      :current-author-type="currentAuthorType"
      :current-author-id="currentAuthorId"
      :can-moderate-any="canModerateAny"
      @reply="(...args) => emit('reply', ...args)"
      @edit="(...args) => emit('edit', ...args)"
      @delete="(...args) => emit('delete', ...args)"
    />
  </div>
</template>
```

- [ ] **Step 2: Write `Learn/Discussion.vue`**

`resources/js/Pages/Learn/Discussion.vue`:

```vue
<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import DiscussionPostNode from '@/Components/DiscussionPostNode.vue'
import DOMPurify from 'dompurify'

const props = defineProps({ discussion: Object, posts: Array, current_user_id: Number })

function sanitizeHtml(html) {
  return DOMPurify.sanitize(html || '')
}

const newTopLevelBody = ref('')
function postTopLevel() {
  if (! newTopLevelBody.value.trim()) return
  router.post(route('learn.discussion-posts.store', props.discussion.id), { body: newTopLevelBody.value }, {
    preserveScroll: true,
    onSuccess: () => { newTopLevelBody.value = '' },
  })
}

function handleReply(parentPostId, body) {
  router.post(route('learn.discussion-posts.store', props.discussion.id), { parent_post_id: parentPostId, body }, { preserveScroll: true })
}
function handleEdit(postId, body) {
  router.put(route('learn.discussion-posts.update', postId), { body }, { preserveScroll: true })
}
function handleDelete(postId) {
  router.delete(route('learn.discussion-posts.destroy', postId), { preserveScroll: true })
}
</script>

<template>
  <Head :title="discussion.title" />
  <AdminLayout :title="discussion.title">
    <div class="max-w-3xl mx-auto py-6 px-4 space-y-4">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">{{ discussion.title }}</h1>
        <div class="prose prose-sm max-w-none mt-2" v-html="sanitizeHtml(discussion.prompt)" />
      </div>

      <div class="border border-slate-200 rounded-lg p-4 space-y-2">
        <textarea v-model="newTopLevelBody" placeholder="Post a reply to the discussion" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="3" />
        <button @click="postTopLevel" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Post</button>
      </div>

      <DiscussionPostNode
        v-for="post in posts"
        :key="post.id"
        :post="post"
        current-author-type="faculty"
        :current-author-id="current_user_id"
        :can-moderate-any="discussion.can_edit"
        @reply="handleReply"
        @edit="handleEdit"
        @delete="handleDelete"
      />
      <p v-if="posts.length === 0" class="text-sm text-slate-400">No posts yet.</p>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 3: Build and verify no errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing either file.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/DiscussionPostNode.vue resources/js/Pages/Learn/Discussion.vue
git commit -m "feat(learn): add discussion post tree component and faculty thread page"
```

---

### Task 13: Discussion grading UI

**Files:**
- Create: `resources/js/Pages/Learn/DiscussionGrading.vue`

**Interfaces:**
- Consumes `learn.discussions.grades`/`learn.discussions.grade` (Task 9),
  `learn.discussions.link`/`learn.discussions.push` (Task 10). No backend test — frontend-only,
  verified by build.

- [ ] **Step 1: Write `DiscussionGrading.vue`**

`resources/js/Pages/Learn/DiscussionGrading.vue`:

```vue
<script setup>
import { computed, ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ discussion: Object, roster: Array })

const gradeForms = ref({})
function gradeForm(studentId) {
  if (! gradeForms.value[studentId]) {
    const row = props.roster.find(r => r.student_id === studentId)
    gradeForms.value[studentId] = useForm({
      points_earned: row?.points_earned ?? '',
      feedback_comment: row?.feedback_comment ?? '',
    })
  }
  return gradeForms.value[studentId]
}
function saveGrade(studentId) {
  gradeForm(studentId).put(route('learn.discussions.grade', [props.discussion.id, studentId]), { preserveScroll: true })
}

const selectedClassRecordId = ref('')
const selectedQuarterId = ref('')
const selectedAssessmentId = ref('')

const availableQuarters = computed(() => {
  const cr = (props.discussion.class_record_options || []).find(c => c.id === Number(selectedClassRecordId.value))
  return cr ? cr.quarters : []
})
const availableAssessments = computed(() => {
  const q = availableQuarters.value.find(q => q.id === Number(selectedQuarterId.value))
  return q ? q.assessments : []
})

function linkAssessment() {
  if (! selectedAssessmentId.value) return
  router.put(route('learn.discussions.link', props.discussion.id), {
    class_record_assessment_id: selectedAssessmentId.value,
  }, { preserveScroll: true })
}
function pushToClassRecord() {
  router.post(route('learn.discussions.push', props.discussion.id), {}, { preserveScroll: true })
}
</script>

<template>
  <Head :title="`Discussion Grading — ${discussion.title}`" />
  <AdminLayout :title="discussion.title">
    <div class="max-w-3xl mx-auto py-6 px-4 space-y-4">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">{{ discussion.title }}</h1>
        <p class="text-sm text-slate-500">{{ discussion.max_score }} pts total</p>
      </div>

      <div class="border border-slate-200 rounded-lg p-4 space-y-2">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Class Record</p>

        <div v-if="discussion.class_record_link">
          <p class="text-sm text-slate-700">
            Linked to <strong>{{ discussion.class_record_link.class_record_name }}</strong> —
            Q{{ discussion.class_record_link.quarter }} — {{ discussion.class_record_link.category_name }} —
            "{{ discussion.class_record_link.assessment_title }}"
          </p>
          <p class="text-xs text-slate-500 mt-1">
            {{ discussion.class_record_link.pushed_at ? `Last pushed ${new Date(discussion.class_record_link.pushed_at).toLocaleString('en-PH')}` : 'Not pushed yet' }}
          </p>
          <button @click="pushToClassRecord" class="mt-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
            Push graded scores
          </button>
        </div>

        <div v-else class="space-y-2">
          <p class="text-xs text-slate-500">Not linked yet. Pick the Class Record assessment to push scores into.</p>
          <div class="flex gap-2">
            <select v-model="selectedClassRecordId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1">
              <option value="" disabled>Class Record</option>
              <option v-for="cr in discussion.class_record_options" :key="cr.id" :value="cr.id">{{ cr.display_name }}</option>
            </select>
            <select v-model="selectedQuarterId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
              <option value="" disabled>Quarter</option>
              <option v-for="q in availableQuarters" :key="q.id" :value="q.id">Q{{ q.quarter }}</option>
            </select>
            <select v-model="selectedAssessmentId" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1">
              <option value="" disabled>Assessment</option>
              <option v-for="a in availableAssessments" :key="a.id" :value="a.id">{{ a.category_name }} — {{ a.title }} ({{ a.max_score }} pts)</option>
            </select>
          </div>
          <button @click="linkAssessment" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium">Link</button>
        </div>
      </div>

      <div class="border border-slate-200 rounded-lg divide-y divide-slate-100">
        <div v-for="row in roster" :key="row.student_id" class="p-3 flex items-center gap-3">
          <p class="text-sm text-slate-700 flex-1">{{ row.name }}</p>
          <input v-model="gradeForm(row.student_id).points_earned" type="number" min="0" :max="discussion.max_score" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-20" />
          <input v-model="gradeForm(row.student_id).feedback_comment" placeholder="Feedback (optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
          <button @click="saveGrade(row.student_id)" class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-lg text-xs font-medium">Save</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 2: Build and verify no errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing `Learn/DiscussionGrading.vue`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Learn/DiscussionGrading.vue
git commit -m "feat(learn): add discussion grading UI"
```

---

### Task 14: Student-facing discussion thread page

**Files:**
- Modify: `resources/js/Pages/StudentPortal/Learn/Show.vue`
- Create: `resources/js/Pages/StudentPortal/Learn/Discussion.vue`

**Interfaces:**
- Consumes `item.discussion` (Task 4), `student-portal.learn.discussions.show` (Task 7),
  `student-portal.learn.discussion-posts.store/update/destroy` (Task 8), reuses
  `DiscussionPostNode.vue` (Task 12). No backend test — frontend-only, verified by build.

- [ ] **Step 1: Add a `ChatBubbleLeftRightIcon` import and a `Link` import to `Show.vue`**

Change the Inertia import line:

```js
import { Head, Link, router, useForm } from '@inertiajs/vue3'
```

Add `ChatBubbleLeftRightIcon` to the existing `@heroicons/vue/24/outline` import line.

- [ ] **Step 2: Add discussion item display**

Update the item-icon line — change:

```html
              <DocumentIcon v-if="item.type === 'page'" class="h-5 w-5 text-slate-400 shrink-0" />
              <AcademicCapIcon v-else-if="item.type === 'quiz'" class="h-5 w-5 text-slate-400 shrink-0" />
              <PaperClipIcon v-else class="h-5 w-5 text-slate-400 shrink-0" />
```

to:

```html
              <DocumentIcon v-if="item.type === 'page'" class="h-5 w-5 text-slate-400 shrink-0" />
              <AcademicCapIcon v-else-if="item.type === 'quiz'" class="h-5 w-5 text-slate-400 shrink-0" />
              <ChatBubbleLeftRightIcon v-else-if="item.type === 'discussion'" class="h-5 w-5 text-slate-400 shrink-0" />
              <PaperClipIcon v-else class="h-5 w-5 text-slate-400 shrink-0" />
```

Immediately after the closing `</div>` of the existing `v-if="item.type === 'quiz'"` block, add:

```html
                <div v-if="item.type === 'discussion'" class="mt-1 space-y-1">
                  <div class="prose prose-sm max-w-none" v-html="sanitizeHtml(item.discussion.prompt)" />
                  <p class="text-xs text-slate-500">
                    {{ item.discussion.post_count }} post{{ item.discussion.post_count === 1 ? '' : 's' }}
                    <span v-if="item.discussion.max_score !== null"> — {{ item.discussion.max_score }} pts</span>
                  </p>
                  <Link :href="route('student-portal.learn.discussions.show', item.discussion.id)" class="text-xs text-indigo-600 underline">View discussion</Link>
                </div>
```

- [ ] **Step 3: Build and verify `Show.vue` still compiles**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing `StudentPortal/Learn/Show.vue`.

- [ ] **Step 4: Write `StudentPortal/Learn/Discussion.vue`**

`resources/js/Pages/StudentPortal/Learn/Discussion.vue`:

```vue
<script setup>
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import DiscussionPostNode from '@/Components/DiscussionPostNode.vue'
import DOMPurify from 'dompurify'

const props = defineProps({ discussion: Object, posts: Array, current_student_id: Number })

function sanitizeHtml(html) {
  return DOMPurify.sanitize(html || '')
}

const newTopLevelBody = ref('')
function postTopLevel() {
  if (! newTopLevelBody.value.trim()) return
  router.post(route('student-portal.learn.discussion-posts.store', props.discussion.id), { body: newTopLevelBody.value }, {
    preserveScroll: true,
    onSuccess: () => { newTopLevelBody.value = '' },
  })
}

function handleReply(parentPostId, body) {
  router.post(route('student-portal.learn.discussion-posts.store', props.discussion.id), { parent_post_id: parentPostId, body }, { preserveScroll: true })
}
function handleEdit(postId, body) {
  router.put(route('student-portal.learn.discussion-posts.update', postId), { body }, { preserveScroll: true })
}
function handleDelete(postId) {
  router.delete(route('student-portal.learn.discussion-posts.destroy', postId), { preserveScroll: true })
}
</script>

<template>
  <Head :title="discussion.title" />
  <StudentPortalLayout>
    <div class="max-w-3xl mx-auto py-6 px-4 space-y-4">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">{{ discussion.title }}</h1>
        <div class="prose prose-sm max-w-none mt-2" v-html="sanitizeHtml(discussion.prompt)" />
      </div>

      <div class="border border-slate-200 rounded-lg p-4 space-y-2">
        <textarea v-model="newTopLevelBody" placeholder="Post a reply to the discussion" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="3" />
        <button @click="postTopLevel" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Post</button>
      </div>

      <DiscussionPostNode
        v-for="post in posts"
        :key="post.id"
        :post="post"
        current-author-type="student"
        :current-author-id="current_student_id"
        :can-moderate-any="false"
        @reply="handleReply"
        @edit="handleEdit"
        @delete="handleDelete"
      />
      <p v-if="posts.length === 0" class="text-sm text-slate-400">No posts yet.</p>
    </div>
  </StudentPortalLayout>
</template>
```

- [ ] **Step 5: Build and verify no errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing either file.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/StudentPortal/Learn/Show.vue resources/js/Pages/StudentPortal/Learn/Discussion.vue
git commit -m "feat(learn): add student-facing discussion thread UI"
```

---

### Task 15: Full regression + manual verification

**Files:** none created — verification only.

- [ ] **Step 1: Run the full Learn + StudentPortal suite together**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M vendor/bin/phpunit tests/Feature/Learn tests/Feature/StudentPortal --no-coverage"`
Expected: every Learn/StudentPortal test from Phases 1 through 4 passes together — no
regressions in either direction.

- [ ] **Step 2: Run the full project regression suite**

Run in the background (15–20+ minutes; do not run anything else that touches the database while
it's running): `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=1G vendor/bin/phpunit --no-coverage 2>/dev/null"`
Expected: no new failures beyond whatever pre-existing baseline is documented as of this plan's
execution (the same unrelated-module baseline confirmed during Phase 3's Task 22) — cross-check
failing test names; none should be in `tests/Feature/Learn` or `tests/Feature/StudentPortal`.

- [ ] **Step 3: Manual browser verification — golden path**

As a faculty member with a current-SY teaching `LoadAssignment`:

1. Create a discussion with `points_possible` set, post a top-level reply and a nested reply to
   your own reply (3 levels deep).
2. As a student enrolled in that section, view the discussion, post a top-level reply and reply
   to the instructor's post.
3. As the instructor, edit your own post's body, confirm it updates; try (and fail) to edit the
   student's post via a direct request if testing that boundary matters to you.
4. Delete the student's post as the instructor; confirm it shows "[deleted]" but its children (if
   any) remain visible, and confirm the student's own other posts are unaffected.
5. As the student, delete your own remaining post; confirm the same soft-delete behavior.
6. As the instructor, open the grading roster; confirm every enrolled student appears even if
   some never posted; grade one student; confirm the score persists on reload.
7. Link the discussion to a pre-existing Class Record assessment with a matching max score, push
   scores, confirm they land in Class Record and the linked assessment's `plotted_at`/
   `activity_date` are untouched.
8. Create an ungraded discussion (no `points_possible`); confirm no "Grades" link appears for it
   on the course page.

- [ ] **Step 4: Report results**

Note any issues found during manual verification; fix and re-verify before considering Phase 4
complete. Do not commit for this task — it is verification only.

---

## Phase 4 Complete — Program Status

Once all 15 tasks pass, the Learn Module roadmap's Phases 1 through 4 are all complete: course
shell, assignments, Class Record push, a reusable rubric bank, a full quiz engine with a question
bank and analytics, and now nested-thread discussions with optional participation grading. This
was the last phase named in the original roadmap table (Phase 1's design spec listed only
Phases 1–4) — any further Learn work would need its own scoping conversation from scratch, not a
continuation of an existing roadmap entry.














