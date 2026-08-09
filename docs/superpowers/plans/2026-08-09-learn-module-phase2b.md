# Learn Module Phase 2b: Push Grades to Class Record — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an instructor link a graded Learn assignment to a pre-existing Class Record assessment and manually push graded scores into it, per `docs/superpowers/specs/2026-08-09-learn-module-phase2b-design.md`.

**Architecture:** Two nullable columns on the existing `learn_assignments` table (`class_record_assessment_id`, `pushed_at`) — no new tables. A `ClassRecordPushService` resolves link candidates and performs linking/pushing; it never creates, dates, or reschedules a `ClassRecordAssessment` — only an instructor does that themselves through Class Record's own existing, WAT-enforced flow. Linking requires an exact `max_score` match; pushing overwrites `ClassRecordScore` rows via `updateOrCreate` for every currently-graded submission, skipping (and reporting) any student missing from the target quarter's roster.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia 2, Tailwind — reusing Phase 1/2's Learn infrastructure and Class Record's existing models untouched.

## Global Constraints

- All Phase 1/2 constraints still apply (base64 uploads, `Storage::disk('s3')`, `Inertia::render(...)`, eager-load relations, migrations always write `down()`).
- **No new permission strings.** Every authorization check reuses `Course::canEdit()` (Learn) and `GradingCategory::canEditOn()` (Class Record, already correctly PEHM-subject-scoped) exactly as they exist today.
- **Learn must never create, date, or reschedule a `ClassRecordAssessment`.** This is the mechanism by which WAT's daily/weekly caps, Friday-noon plotting deadline, and schedule-day rule stay enforced — see the spec's "WAT compliance" section. No task in this plan creates a `ClassRecordAssessment` row or writes to its `plotted_at`/`activity_date` columns.
- Linking requires `assessment.max_score` to exactly equal `assignment.maxScore()` — reject (422/`ValidationException`) otherwise, never scale.
- Pushing always overwrites (`updateOrCreate`), never skips an already-scored student on the Class Record side — the only skip case is a student missing entirely from the target quarter's roster (`ClassRecordStudent`).
- `ClassRecordScore.class_record_student_id` references `class_record_students.id` (a per-quarter roster snapshot), **not** `students.id` directly — always resolve through `ClassRecordStudent` first.

---

### Task 1: Schema — link and push-tracking columns on `learn_assignments`

**Files:**
- Create: `database/migrations/2026_08_09_100006_add_class_record_link_to_learn_assignments_table.php`
- Test: `tests/Feature/Learn/LearnAssignmentClassRecordLinkSchemaTest.php`

**Interfaces:**
- Produces columns: `learn_assignments.class_record_assessment_id` (nullable FK → `class_record_assessments`, `nullOnDelete`), `learn_assignments.pushed_at` (nullable timestamp).

- [ ] **Step 1: Write the failing schema test**

```php
<?php

namespace Tests\Feature\Learn;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearnAssignmentClassRecordLinkSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_learn_assignments_has_class_record_link_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('learn_assignments', [
            'class_record_assessment_id', 'pushed_at',
        ]));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnAssignmentClassRecordLinkSchemaTest.php"`
Expected: FAIL — columns don't exist.

- [ ] **Step 3: Write the migration**

`database/migrations/2026_08_09_100006_add_class_record_link_to_learn_assignments_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learn_assignments', function (Blueprint $table) {
            $table->foreignId('class_record_assessment_id')->nullable()->after('due_at')
                  ->constrained('class_record_assessments')->nullOnDelete();
            $table->timestamp('pushed_at')->nullable()->after('class_record_assessment_id');
        });
    }

    public function down(): void
    {
        Schema::table('learn_assignments', function (Blueprint $table) {
            $table->dropForeign(['class_record_assessment_id']);
            $table->dropColumn(['class_record_assessment_id', 'pushed_at']);
        });
    }
};
```

- [ ] **Step 4: Run migration and the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_09_100006_add_class_record_link_to_learn_assignments_table.php --force"`
Then: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnAssignmentClassRecordLinkSchemaTest.php"`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_09_100006_add_class_record_link_to_learn_assignments_table.php \
        tests/Feature/Learn/LearnAssignmentClassRecordLinkSchemaTest.php
git commit -m "feat(learn): add class_record_assessment_id/pushed_at columns to learn_assignments"
```

---

### Task 2: Assignment model — classRecordAssessment relation

**Files:**
- Modify: `app/Models/Learn/Assignment.php`
- Test: `tests/Feature/Learn/AssignmentClassRecordRelationTest.php`

**Interfaces:**
- Produces: `Assignment::classRecordAssessment(): BelongsTo`. Adds `class_record_assessment_id`, `pushed_at` to `$fillable`; adds `pushed_at` to `$casts` as `datetime`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentClassRecordRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_assignment_resolves_its_linked_class_record_assessment(): void
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
        $assignment = Assignment::create(['title' => 'Essay', 'submission_type' => 'text', 'points_possible' => 40]);
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        $teacher = User::factory()->create();
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
        $assessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 1, 'title' => 'Essay 1', 'max_score' => 40, 'sort_order' => 1,
        ]);

        $assignment->update(['class_record_assessment_id' => $assessment->id, 'pushed_at' => now()]);

        $this->assertTrue($assignment->fresh()->classRecordAssessment->is($assessment));
        $this->assertNotNull($assignment->fresh()->pushed_at);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/AssignmentClassRecordRelationTest.php"`
Expected: FAIL — `class_record_assessment_id`/`pushed_at` not fillable, relation doesn't exist.

- [ ] **Step 3: Update `Assignment`**

In `app/Models/Learn/Assignment.php`, add the import:

```php
use App\Models\ClassRecord\ClassRecordAssessment;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
```

Update `$fillable` and `$casts`:

```php
    protected $fillable = [
        'title', 'instructions', 'submission_type', 'points_possible', 'due_at',
        'class_record_assessment_id', 'pushed_at',
    ];

    protected $casts = [
        'points_possible' => 'decimal:2',
        'due_at' => 'datetime',
        'pushed_at' => 'datetime',
    ];
```

Add the relation, after `submissions()`:

```php
    public function classRecordAssessment(): BelongsTo
    {
        return $this->belongsTo(ClassRecordAssessment::class, 'class_record_assessment_id');
    }
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/AssignmentClassRecordRelationTest.php"`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Learn/Assignment.php tests/Feature/Learn/AssignmentClassRecordRelationTest.php
git commit -m "feat(learn): add Assignment::classRecordAssessment relation"
```

---

### Task 3: ClassRecordPushService — candidates, link, push

**Files:**
- Create: `app/Services/Learn/ClassRecordPushService.php`
- Test: `tests/Feature/Learn/ClassRecordPushServiceTest.php`

**Interfaces:**
- Consumes: `Assignment::course()`/`maxScore()`/`canEdit()` (Phase 2), `GradingCategory::canEditOn()` (existing Class Record code).
- Produces: `ClassRecordPushService::candidateClassRecords(Assignment $assignment): Collection<ClassRecord>` (each with `quarters.assessments.gradingCategory` eager-loaded, assessments filtered to `is_graded = true`), `ClassRecordPushService::link(Assignment $assignment, int $assessmentId, User $user): void` (throws `ValidationException` on max-score mismatch, aborts 403 on either permission failure), `ClassRecordPushService::push(Assignment $assignment, User $user): array{pushed: int, skipped: array<int, string>}`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\Learn\Submission;
use App\Models\User;
use App\Services\Learn\ClassRecordPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClassRecordPushServiceTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private Section $section;
    private Subject $subject;
    private Course $course;
    private User $teacher;
    private GradingOption $option;
    private ClassRecord $classRecord;
    private ClassRecordQuarter $quarter;
    private GradingCategory $category;

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
        $this->subject = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SCI8', 'name' => 'Science 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $this->teacher = User::factory()->create();

        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $term->id,
            'assignment_type' => 'teaching', 'subject_id' => $this->subject->id,
            'section_id' => $this->section->id, 'load_units' => 3,
        ]);

        $this->course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $term->id,
        ]);

        $this->option = GradingOption::create(['name' => 'Standard', 'is_active' => true]);
        $this->classRecord = ClassRecord::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $this->subject->name,
            'year_level_section' => "G-{$this->section->levelid} {$this->section->sectionname}",
            'teacher_id' => $this->teacher->id, 'status' => 'draft',
        ]);
        $this->quarter = ClassRecordQuarter::create([
            'class_record_id' => $this->classRecord->id, 'grading_option_id' => $this->option->id,
            'quarter' => 1, 'is_locked' => false,
        ]);
        $this->category = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'name' => 'Written Work', 'code' => 'WW',
            'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);
    }

    private function makeAssessment(float $maxScore = 40): ClassRecordAssessment
    {
        return ClassRecordAssessment::create([
            'class_record_quarter_id' => $this->quarter->id, 'grading_category_id' => $this->category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 1, 'title' => 'Essay 1', 'max_score' => $maxScore, 'sort_order' => 1,
        ]);
    }

    private function makeAssignment(float $pointsPossible = 40): Assignment
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $assignment = Assignment::create(['title' => 'Essay', 'submission_type' => 'text', 'points_possible' => $pointsPossible]);
        $assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        return $assignment;
    }

    private function enrollAndRoster(): int
    {
        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'PS' . str_pad((string) mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT),
            'lastname' => 'Cruz', 'firstname' => 'Juan', 'sex' => 'M',
        ]);
        ClassRecordStudent::create([
            'class_record_quarter_id' => $this->quarter->id, 'student_id' => $studentId,
            'family_name' => 'Cruz', 'given_name' => 'Juan', 'sequence_number' => 1, 'is_active' => true,
        ]);

        return $studentId;
    }

    public function test_candidate_class_records_resolves_matching_records_with_graded_assessments(): void
    {
        $this->makeAssessment();
        $assignment = $this->makeAssignment();

        $candidates = app(ClassRecordPushService::class)->candidateClassRecords($assignment);

        $this->assertCount(1, $candidates);
        $this->assertTrue($candidates->first()->is($this->classRecord));
        $this->assertCount(1, $candidates->first()->quarters->first()->assessments);
    }

    public function test_candidate_class_records_includes_sibling_records_with_different_category_labels(): void
    {
        $this->makeAssessment();

        // Same subject+section+SY, different category_label — e.g. a STEM Research
        // "Ongoing"/"Completed" split. Both should surface as separate candidates.
        $siblingRecord = ClassRecord::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $this->subject->name,
            'category_label' => 'Completed', 'year_level_section' => "G-{$this->section->levelid} {$this->section->sectionname}",
            'teacher_id' => $this->teacher->id, 'status' => 'draft',
        ]);
        $siblingQuarter = ClassRecordQuarter::create([
            'class_record_id' => $siblingRecord->id, 'grading_option_id' => $this->option->id,
            'quarter' => 1, 'is_locked' => false,
        ]);
        ClassRecordAssessment::create([
            'class_record_quarter_id' => $siblingQuarter->id, 'grading_category_id' => $this->category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 1, 'title' => 'Sibling Essay', 'max_score' => 40, 'sort_order' => 1,
        ]);
        $assignment = $this->makeAssignment();

        $candidates = app(ClassRecordPushService::class)->candidateClassRecords($assignment);

        $this->assertCount(2, $candidates);
        $this->assertEqualsCanonicalizing(
            ['', 'Completed'],
            $candidates->pluck('category_label')->map(fn ($v) => $v ?? '')->all()
        );
    }

    public function test_link_succeeds_when_max_scores_match(): void
    {
        $assessment = $this->makeAssessment(40);
        $assignment = $this->makeAssignment(40);

        app(ClassRecordPushService::class)->link($assignment, $assessment->id, $this->teacher);

        $this->assertSame($assessment->id, $assignment->fresh()->class_record_assessment_id);
    }

    public function test_link_rejects_a_max_score_mismatch(): void
    {
        $assessment = $this->makeAssessment(20);
        $assignment = $this->makeAssignment(40);

        $this->expectException(ValidationException::class);

        app(ClassRecordPushService::class)->link($assignment, $assessment->id, $this->teacher);
    }

    public function test_link_403s_a_stranger_with_no_learn_edit_access(): void
    {
        $assessment = $this->makeAssessment();
        $assignment = $this->makeAssignment();
        $stranger = User::factory()->create();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(ClassRecordPushService::class)->link($assignment, $assessment->id, $stranger);
    }

    public function test_link_403s_when_learn_instructor_lacks_class_record_access_to_the_specific_category(): void
    {
        $assessment = $this->makeAssessment(40);

        // PEHM-style subject scoping: give the target category a subject_id and
        // restrict that subject to a DIFFERENT co-teacher on the class record.
        // $this->teacher can edit the Learn assignment but has no Class Record
        // write access to this specific leaf — link must still 403.
        $otherTeacher = User::factory()->create();
        $this->category->update(['subject_id' => $this->subject->id]);
        \App\Models\ClassRecord\ClassRecordTeacher::create([
            'class_record_id' => $this->classRecord->id, 'subject_id' => $this->subject->id,
            'user_id' => $otherTeacher->id, 'is_primary' => true,
        ]);
        $assignment = $this->makeAssignment(40);

        $this->assertTrue($assignment->canEdit($this->teacher), 'sanity check: Learn-side access is fine');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(ClassRecordPushService::class)->link($assignment, $assessment->id, $this->teacher);
    }

    public function test_push_copies_graded_scores_and_overwrites_on_repush(): void
    {
        $assessment = $this->makeAssessment(40);
        $assignment = $this->makeAssignment(40);
        $studentId = $this->enrollAndRoster();

        Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $studentId,
            'text_body' => 'x', 'submitted_at' => now(), 'score' => 35, 'graded_at' => now(),
        ]);

        $service = app(ClassRecordPushService::class);
        $service->link($assignment, $assessment->id, $this->teacher);
        $result = $service->push($assignment, $this->teacher);

        $this->assertSame(1, $result['pushed']);
        $this->assertEmpty($result['skipped']);

        $classRecordStudent = ClassRecordStudent::where('student_id', $studentId)->first();
        $this->assertSame('35.00', ClassRecordScore::where([
            'class_record_student_id' => $classRecordStudent->id, 'class_record_assessment_id' => $assessment->id,
        ])->value('score'));

        // Re-push after a regrade overwrites, not duplicates.
        Submission::where('learn_assignment_id', $assignment->id)->update(['score' => 38]);
        $service->push($assignment, $this->teacher);

        $this->assertSame(1, ClassRecordScore::where([
            'class_record_student_id' => $classRecordStudent->id, 'class_record_assessment_id' => $assessment->id,
        ])->count());
        $this->assertSame('38.00', ClassRecordScore::where([
            'class_record_student_id' => $classRecordStudent->id, 'class_record_assessment_id' => $assessment->id,
        ])->value('score'));
    }

    public function test_push_skips_and_reports_a_student_missing_from_the_quarter_roster(): void
    {
        $assessment = $this->makeAssessment(40);
        $assignment = $this->makeAssignment(40);

        $unrosteredStudentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'PS' . str_pad((string) mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT),
            'lastname' => 'Reyes', 'firstname' => 'Ana', 'sex' => 'F',
        ]);
        Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $unrosteredStudentId,
            'text_body' => 'x', 'submitted_at' => now(), 'score' => 30, 'graded_at' => now(),
        ]);

        $service = app(ClassRecordPushService::class);
        $service->link($assignment, $assessment->id, $this->teacher);
        $result = $service->push($assignment, $this->teacher);

        $this->assertSame(0, $result['pushed']);
        $this->assertCount(1, $result['skipped']);
        $this->assertStringContainsString('Reyes', $result['skipped'][0]);
    }

    public function test_push_never_pushes_ungraded_submissions(): void
    {
        $assessment = $this->makeAssessment(40);
        $assignment = $this->makeAssignment(40);
        $studentId = $this->enrollAndRoster();

        Submission::create([
            'learn_assignment_id' => $assignment->id, 'student_id' => $studentId,
            'text_body' => 'x', 'submitted_at' => now(),
        ]);

        $service = app(ClassRecordPushService::class);
        $service->link($assignment, $assessment->id, $this->teacher);
        $result = $service->push($assignment, $this->teacher);

        $this->assertSame(0, $result['pushed']);
        $this->assertDatabaseCount('class_record_scores', 0);
    }

    public function test_push_sets_pushed_at_and_never_touches_wat_governed_columns(): void
    {
        $assessment = $this->makeAssessment(40);
        $originalActivityDate = $assessment->activity_date;
        $originalPlottedAt = $assessment->plotted_at;
        $assignment = $this->makeAssignment(40);
        $this->enrollAndRoster();

        $service = app(ClassRecordPushService::class);
        $service->link($assignment, $assessment->id, $this->teacher);
        $service->push($assignment, $this->teacher);

        $this->assertNotNull($assignment->fresh()->pushed_at);
        $assessment->refresh();
        $this->assertEquals($originalActivityDate, $assessment->activity_date);
        $this->assertEquals($originalPlottedAt, $assessment->plotted_at);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ClassRecordPushServiceTest.php"`
Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Write `ClassRecordPushService`**

`app/Services/Learn/ClassRecordPushService.php`:

```php
<?php

namespace App\Services\Learn;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\Learn\Assignment;
use App\Models\Learn\Submission;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Links a Learn assignment to a pre-existing Class Record assessment and
 * pushes graded scores into it. Never creates, dates, or reschedules a
 * ClassRecordAssessment — the instructor does that themselves through Class
 * Record's own existing WAT-enforced flow. This service only ever selects
 * an already-plotted assessment and writes ClassRecordScore rows.
 */
class ClassRecordPushService
{
    /** @return Collection<int, ClassRecord> */
    public function candidateClassRecords(Assignment $assignment): Collection
    {
        $course = $assignment->course();
        if (! $course) {
            return collect();
        }

        return ClassRecord::active()
            ->where('subject_id', $course->subject_id)
            ->where('section_id', $course->section_id)
            ->where('school_year_id', $course->school_year_id)
            ->with([
                'quarters' => fn ($q) => $q->orderBy('quarter'),
                'quarters.assessments' => fn ($q) => $q->where('is_graded', true)->with('gradingCategory'),
            ])
            ->get();
    }

    public function link(Assignment $assignment, int $assessmentId, User $user): void
    {
        abort_unless($assignment->canEdit($user), 403);

        $assessment = ClassRecordAssessment::with(['gradingCategory', 'quarter.classRecord'])->findOrFail($assessmentId);

        abort_unless(
            $assessment->gradingCategory->canEditOn($assessment->quarter->classRecord, $user),
            403
        );

        $maxScore = $assignment->maxScore();
        if ($maxScore === null || (float) $assessment->max_score !== $maxScore) {
            throw ValidationException::withMessages([
                'class_record_assessment_id' => "The assessment's max score ({$assessment->max_score}) must exactly match this assignment's max score ({$maxScore}) before linking.",
            ]);
        }

        $assignment->update(['class_record_assessment_id' => $assessment->id]);
    }

    /** @return array{pushed: int, skipped: array<int, string>} */
    public function push(Assignment $assignment, User $user): array
    {
        abort_if(! $assignment->class_record_assessment_id, 422, 'Link a Class Record assessment first.');

        $assessment = $assignment->classRecordAssessment()->with(['gradingCategory', 'quarter.classRecord'])->firstOrFail();

        abort_unless($assignment->canEdit($user), 403);
        abort_unless($assessment->gradingCategory->canEditOn($assessment->quarter->classRecord, $user), 403);

        $submissions = Submission::where('learn_assignment_id', $assignment->id)->whereNotNull('graded_at')->get();

        $pushed = 0;
        $skipped = [];

        foreach ($submissions as $submission) {
            $classRecordStudent = ClassRecordStudent::where('class_record_quarter_id', $assessment->class_record_quarter_id)
                ->where('student_id', $submission->student_id)
                ->first();

            if (! $classRecordStudent) {
                $student = DB::table('students')->where('id', $submission->student_id)->first(['lastname', 'firstname']);
                $skipped[] = $student ? trim("{$student->lastname}, {$student->firstname}") : "Student #{$submission->student_id}";
                continue;
            }

            ClassRecordScore::updateOrCreate(
                ['class_record_student_id' => $classRecordStudent->id, 'class_record_assessment_id' => $assessment->id],
                ['score' => $submission->score]
            );
            $pushed++;
        }

        $assignment->update(['pushed_at' => now()]);

        return ['pushed' => $pushed, 'skipped' => $skipped];
    }
}
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ClassRecordPushServiceTest.php"`
Expected: PASS (10 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Learn/ClassRecordPushService.php tests/Feature/Learn/ClassRecordPushServiceTest.php
git commit -m "feat(learn): add ClassRecordPushService — link and push graded scores"
```

---

### Task 4: ClassRecordPushController + routes

**Files:**
- Create: `app/Http/Controllers/Learn/ClassRecordPushController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/ClassRecordPushControllerTest.php`

**Interfaces:**
- Consumes: `ClassRecordPushService::link()`/`push()` (Task 3).
- Produces routes: `learn.assignments.link` (PUT `/learn/assignments/{assignment}/link`), `learn.assignments.push` (POST `/learn/assignments/{assignment}/push`).

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
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\Learn\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClassRecordPushControllerTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private User $teacher;
    private User $stranger;
    private ClassRecordAssessment $assessment;
    private ClassRecordQuarter $quarter;
    private Assignment $assignment;

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
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $this->assignment = Assignment::create(['title' => 'Essay', 'submission_type' => 'text', 'points_possible' => 40]);
        $this->assignment->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        $option = GradingOption::create(['name' => 'Standard', 'is_active' => true]);
        $classRecord = ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $option->id, 'school_year_id' => $sy->id,
            'school_year' => $sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $this->teacher->id, 'status' => 'draft',
        ]);
        $this->quarter = ClassRecordQuarter::create([
            'class_record_id' => $classRecord->id, 'grading_option_id' => $option->id,
            'quarter' => 1, 'is_locked' => false,
        ]);
        $category = GradingCategory::create([
            'grading_option_id' => $option->id, 'name' => 'Written Work', 'code' => 'WW',
            'weight' => 0.5, 'max_assessments' => 5, 'sort_order' => 1,
        ]);
        $this->assessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $this->quarter->id, 'grading_category_id' => $category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 1, 'title' => 'Essay 1', 'max_score' => 40, 'sort_order' => 1,
        ]);
    }

    public function test_instructor_can_link_and_push_stranger_cannot(): void
    {
        $this->actingAs($this->teacher)
            ->put(route('learn.assignments.link', $this->assignment), ['class_record_assessment_id' => $this->assessment->id])
            ->assertRedirect();
        $this->assertSame($this->assessment->id, $this->assignment->fresh()->class_record_assessment_id);

        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'PS' . str_pad((string) mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT),
            'lastname' => 'Cruz', 'firstname' => 'Juan', 'sex' => 'M',
        ]);
        ClassRecordStudent::create([
            'class_record_quarter_id' => $this->quarter->id, 'student_id' => $studentId,
            'family_name' => 'Cruz', 'given_name' => 'Juan', 'sequence_number' => 1, 'is_active' => true,
        ]);
        Submission::create([
            'learn_assignment_id' => $this->assignment->id, 'student_id' => $studentId,
            'text_body' => 'x', 'submitted_at' => now(), 'score' => 35, 'graded_at' => now(),
        ]);

        $this->actingAs($this->teacher)
            ->post(route('learn.assignments.push', $this->assignment))
            ->assertRedirect();
        $this->assertNotNull($this->assignment->fresh()->pushed_at);

        $newAssessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $this->quarter->id, 'grading_category_id' => $this->assessment->grading_category_id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 2, 'title' => 'Essay 2', 'max_score' => 40, 'sort_order' => 2,
        ]);
        $this->actingAs($this->stranger)
            ->put(route('learn.assignments.link', $this->assignment), ['class_record_assessment_id' => $newAssessment->id])
            ->assertForbidden();
        $this->actingAs($this->stranger)
            ->post(route('learn.assignments.push', $this->assignment))
            ->assertForbidden();
    }

    public function test_link_returns_validation_error_on_max_score_mismatch(): void
    {
        $mismatched = ClassRecordAssessment::create([
            'class_record_quarter_id' => $this->quarter->id, 'grading_category_id' => $this->assessment->grading_category_id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 3, 'title' => 'Quiz', 'max_score' => 10, 'sort_order' => 3,
        ]);

        $this->actingAs($this->teacher)
            ->put(route('learn.assignments.link', $this->assignment), ['class_record_assessment_id' => $mismatched->id])
            ->assertSessionHasErrors('class_record_assessment_id');
    }

    public function test_push_without_a_link_returns_422(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('learn.assignments.push', $this->assignment))
            ->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ClassRecordPushControllerTest.php"`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Write `ClassRecordPushController`**

`app/Http/Controllers/Learn/ClassRecordPushController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Assignment;
use App\Services\Learn\ClassRecordPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClassRecordPushController extends Controller
{
    public function __construct(private ClassRecordPushService $pushService)
    {
    }

    /** PUT /learn/assignments/{assignment}/link */
    public function link(Request $request, Assignment $assignment)
    {
        $validated = $request->validate([
            'class_record_assessment_id' => 'required|integer|exists:class_record_assessments,id',
        ]);

        $this->pushService->link($assignment, $validated['class_record_assessment_id'], Auth::user());

        return back()->with('success', 'Linked to Class Record assessment.');
    }

    /** POST /learn/assignments/{assignment}/push */
    public function push(Assignment $assignment)
    {
        $result = $this->pushService->push($assignment, Auth::user());

        $message = "Pushed {$result['pushed']} score(s) to Class Record.";
        if (! empty($result['skipped'])) {
            $message .= ' Skipped (not on quarter roster): ' . implode(', ', $result['skipped']) . '.';
        }

        return back()->with('success', $message);
    }
}
```

- [ ] **Step 4: Add routes**

Add inside the `learn.` route group in `routes/web.php`, immediately after the `submissions.file` line from Phase 2 Task 6:

```php
    Route::put('/assignments/{assignment}/link', [\App\Http\Controllers\Learn\ClassRecordPushController::class, 'link'])->name('assignments.link');
    Route::post('/assignments/{assignment}/push', [\App\Http\Controllers\Learn\ClassRecordPushController::class, 'push'])->name('assignments.push');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ClassRecordPushControllerTest.php"`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/ClassRecordPushController.php routes/web.php \
        tests/Feature/Learn/ClassRecordPushControllerTest.php
git commit -m "feat(learn): add link/push HTTP endpoints for Class Record grade push"
```

---

### Task 5: Surface link status and candidates in the grading roster page

**Files:**
- Modify: `app/Http/Controllers/Learn/AssignmentGradingController.php`
- Test: `tests/Feature/Learn/AssignmentGradingClassRecordDataTest.php`

**Interfaces:**
- Consumes: `ClassRecordPushService::candidateClassRecords()` (Task 3).
- Extends the existing `Inertia::render('Learn/Grading', ...)` payload from Phase 2 Task 6 with `assignment.class_record_link` and `assignment.class_record_options`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
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

class AssignmentGradingClassRecordDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_grading_page_surfaces_link_status_and_candidate_assessments(): void
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
        $assessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 1, 'title' => 'Essay 1', 'max_score' => 40, 'sort_order' => 1,
        ]);

        $response = $this->actingAs($teacher)->get(route('learn.assignments.submissions', $assignment));

        $response->assertInertia(fn ($page) => $page
            ->where('assignment.class_record_link', null)
            ->where('assignment.class_record_options.0.id', $classRecord->id)
            ->where('assignment.class_record_options.0.quarters.0.assessments.0.id', $assessment->id)
        );

        $assignment->update(['class_record_assessment_id' => $assessment->id, 'pushed_at' => now()]);

        $response = $this->actingAs($teacher)->get(route('learn.assignments.submissions', $assignment));
        $response->assertInertia(fn ($page) => $page
            ->where('assignment.class_record_link.assessment_id', $assessment->id)
            ->where('assignment.class_record_link.assessment_title', 'Essay 1')
        );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/AssignmentGradingClassRecordDataTest.php"`
Expected: FAIL — no `class_record_link`/`class_record_options` keys in the payload.

- [ ] **Step 3: Update `AssignmentGradingController`**

Add the import and constructor parameter:

```php
use App\Services\Learn\ClassRecordPushService;
```

```php
    public function __construct(
        private SubmissionRosterService $roster,
        private CourseFileService $files,
        private ClassRecordPushService $pushService,
    ) {
    }
```

In `index()`, after `$assignment->load('rubric.criteria');`, add:

```php
        $assignment->load(['classRecordAssessment.gradingCategory', 'classRecordAssessment.quarter.classRecord']);
        $classRecordOptions = $this->pushService->candidateClassRecords($assignment);
```

In the returned `Inertia::render('Learn/Grading', [...])` array, inside the `'assignment' => [...]` block, add two new keys after `'rubric' => ...`:

```php
                'class_record_link' => $assignment->classRecordAssessment ? [
                    'assessment_id' => $assignment->classRecordAssessment->id,
                    'assessment_title' => $assignment->classRecordAssessment->title,
                    'class_record_name' => $assignment->classRecordAssessment->quarter->classRecord->display_name,
                    'quarter' => $assignment->classRecordAssessment->quarter->quarter,
                    'category_name' => $assignment->classRecordAssessment->gradingCategory->name,
                    'max_score' => (float) $assignment->classRecordAssessment->max_score,
                    'pushed_at' => $assignment->pushed_at?->toIso8601String(),
                ] : null,
                'class_record_options' => $classRecordOptions->map(fn ($cr) => [
                    'id' => $cr->id,
                    'display_name' => $cr->display_name,
                    'quarters' => $cr->quarters->map(fn ($q) => [
                        'id' => $q->id,
                        'quarter' => $q->quarter,
                        'assessments' => $q->assessments->map(fn ($a) => [
                            'id' => $a->id,
                            'title' => $a->title,
                            'max_score' => (float) $a->max_score,
                            'category_name' => $a->gradingCategory->name,
                        ])->values(),
                    ])->values(),
                ])->values(),
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/AssignmentGradingClassRecordDataTest.php"`
Expected: PASS (1 test).

- [ ] **Step 5: Run Task 6 (Phase 2)'s existing grading controller test to confirm no regression**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/AssignmentGradingControllerTest.php"`
Expected: PASS (still 6 tests — the new payload keys must not break the existing roster/grading assertions).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/AssignmentGradingController.php \
        tests/Feature/Learn/AssignmentGradingClassRecordDataTest.php
git commit -m "feat(learn): surface Class Record link status and candidates on the grading page"
```

---

### Task 6: Grading.vue — link picker and push button

**Files:**
- Modify: `resources/js/Pages/Learn/Grading.vue`

**Interfaces:**
- Consumes `assignment.class_record_link` and `assignment.class_record_options` from Task 5's payload.
- Uses named routes: `learn.assignments.link`, `learn.assignments.push`.

- [ ] **Step 1: Add the Class Record panel**

In `resources/js/Pages/Learn/Grading.vue`, add to `<script setup>` after the existing `reopen` function:

```js
const selectedClassRecordId = ref('')
const selectedQuarterId = ref('')
const selectedAssessmentId = ref('')

const availableQuarters = computed(() => {
  const cr = props.assignment.class_record_options.find(c => c.id === Number(selectedClassRecordId.value))
  return cr ? cr.quarters : []
})
const availableAssessments = computed(() => {
  const q = availableQuarters.value.find(q => q.id === Number(selectedQuarterId.value))
  return q ? q.assessments : []
})

function linkAssessment() {
  if (! selectedAssessmentId.value) return
  router.put(route('learn.assignments.link', props.assignment.id), {
    class_record_assessment_id: selectedAssessmentId.value,
  }, { preserveScroll: true })
}

function pushToClassRecord() {
  router.post(route('learn.assignments.push', props.assignment.id), {}, { preserveScroll: true })
}
```

Add `computed` to the Vue import at the top of the file:

```js
import { computed, ref } from 'vue'
```

- [ ] **Step 2: Add the panel to the template**

Add this section right after the `<div>` header block (before the roster `<div v-for="row in roster" ...>` loop):

```html
      <div class="border border-slate-200 rounded-lg p-4 space-y-2">
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Class Record</p>

        <div v-if="assignment.class_record_link">
          <p class="text-sm text-slate-700">
            Linked to <strong>{{ assignment.class_record_link.class_record_name }}</strong> —
            Q{{ assignment.class_record_link.quarter }} — {{ assignment.class_record_link.category_name }} —
            "{{ assignment.class_record_link.assessment_title }}"
          </p>
          <p class="text-xs text-slate-500 mt-1">
            {{ assignment.class_record_link.pushed_at ? `Last pushed ${new Date(assignment.class_record_link.pushed_at).toLocaleString('en-PH')}` : 'Not pushed yet' }}
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
              <option v-for="cr in assignment.class_record_options" :key="cr.id" :value="cr.id">{{ cr.display_name }}</option>
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
          <button @click="linkAssessment" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-4 py-2 rounded-lg text-sm font-medium">
            Link
          </button>
        </div>
      </div>
```

- [ ] **Step 3: Build frontend assets and verify no compile errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing `Learn/Grading.vue`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Learn/Grading.vue
git commit -m "feat(learn): add Class Record link picker and push button to the grading page"
```

---

### Task 7: Full test suite + manual verification

**Files:** none created — verification only.

- [ ] **Step 1: Run all Phase 2b tests together**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M vendor/bin/phpunit tests/Feature/Learn tests/Feature/StudentPortal/LearnControllerTest.php tests/Feature/StudentPortal/LearnAssignmentSerializationTest.php tests/Feature/StudentPortal/LearnSubmissionControllerTest.php"`
Expected: all Phase 1 + Phase 2 + Phase 2b Learn tests pass together (no regressions in either direction).

- [ ] **Step 2: Run the full project regression suite**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=1G vendor/bin/phpunit"` (run in the background and wait — this takes 15-20+ minutes; do not run any other command that touches the database while it's running, including `php artisan tinker`/`db:seed`, to avoid contaminating the shared dev/test database)
Expected: no new failures beyond the known pre-existing baseline (cross-check failing test names against prior Phase 1/2 runs — none should mention Learn, Class Record's `ClassRecordAssessment`/`ClassRecordScore`/`GradingCategory`/`WatRuleService`, or any file this plan touched).

- [ ] **Step 3: Manual browser verification — golden path**

As a faculty member with a current-SY teaching `LoadAssignment` and an existing Class Record for the same course:

1. In Class Record, create (or reuse) a graded assessment under some quarter/category with a specific max score — note the exact number.
2. In Learn, create an assignment with `points_possible` set to that exact same number, grade at least one student's submission.
3. Open the assignment's grading page ("View submissions") — confirm the Class Record panel shows the picker with the correct candidate Class Record/quarter/assessment options.
4. Attempt to link to an assessment with a *different* max score — confirm it's rejected with a clear error.
5. Link to the matching-max-score assessment — confirm the panel switches to "Linked to …" and shows "Not pushed yet."
6. Click "Push graded scores" — confirm the Class Record's assessment now shows the pushed score for the graded student, and the Learn page shows "Last pushed …".
7. Regrade the student in Learn, push again — confirm the Class Record score updates (not duplicates).
8. Confirm the assessment's `activity_date`/plotted status in Class Record is completely unchanged throughout — linking/pushing never touched WAT-governed plotting.
9. As a faculty member without Class Record write access to that category (or without the Learn teaching load), confirm both linking and pushing 403 appropriately.

- [ ] **Step 4: Report results**

Note any issues found during manual verification; fix and re-verify before considering Phase 2b complete. Do not commit for this task — it is verification only.

---

## Phase 2b Complete — Next Steps

Once all 7 tasks pass, Learn Phase 2b (Push Grades to Class Record) is done. Phase 2c (reusable rubric bank) and Phase 3 (Quizzes) each need their own `superpowers:brainstorming` → design → plan cycle before implementation, per the roadmap in `docs/superpowers/specs/2026-08-09-learn-module-phase2b-design.md`.
