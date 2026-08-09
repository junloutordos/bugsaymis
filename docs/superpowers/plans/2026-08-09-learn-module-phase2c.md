# Learn Module Phase 2c: Reusable Rubric Bank — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an instructor save a rubric's criteria as a named personal template while creating an assignment, and reuse it (client-side pre-fill) when building future assignments' rubrics, per `docs/superpowers/specs/2026-08-09-learn-module-phase2c-design.md`.

**Architecture:** Two new tables (`learn_rubric_templates`, `learn_rubric_template_criteria`) with **no foreign key to any assignment's actual rubric in either direction**. Saving copies criteria from a just-submitted assignment's rubric into a new template row; applying copies a template's criteria into the assignment-creation form client-side, submitted through the unchanged `storeAssignment` endpoint. This absence of any link is the entire safety mechanism — nothing about a template can ever affect a graded assignment, and nothing about an assignment can ever affect a template.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia 2, Tailwind — reusing Phase 1/2/2b's Learn infrastructure untouched.

## Global Constraints

- All prior Learn-phase constraints still apply (base64 uploads, `Storage::disk('s3')`, `Inertia::render(...)`, eager-load relations, migrations always write `down()`).
- **No new permission strings.** Template ownership is enforced by direct `user_id` equality, the same lightweight pattern Phase 2 already uses for submission ownership (`$submission->student_id === $student->id`) — not `Course::canEdit()`, since a template isn't tied to any course.
- **Never create a foreign key or any other link between `learn_rubric_templates`/`learn_rubric_template_criteria` and `learn_rubrics`/`learn_rubric_criteria`.** Every copy operation (save, apply) must produce fully independent rows. No task in this plan should add such a link.
- Templates are rename/delete only — no endpoint or UI in this plan edits a template's criteria after creation.
- This phase makes zero changes to grading, scoring, submissions, or the Class Record push (Phase 2b).

---

### Task 1: Schema — rubric template tables

**Files:**
- Create: `database/migrations/2026_08_09_100007_create_learn_rubric_templates_table.php`
- Create: `database/migrations/2026_08_09_100008_create_learn_rubric_template_criteria_table.php`
- Test: `tests/Feature/Learn/LearnRubricTemplateSchemaTest.php`

**Interfaces:**
- Produces tables: `learn_rubric_templates(id, user_id, name, timestamps)`, `learn_rubric_template_criteria(id, learn_rubric_template_id, description, max_points, position, timestamps)`.

- [ ] **Step 1: Write the failing schema test**

```php
<?php

namespace Tests\Feature\Learn;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearnRubricTemplateSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_learn_rubric_templates_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_rubric_templates'));
        $this->assertTrue(Schema::hasColumns('learn_rubric_templates', ['id', 'user_id', 'name']));
    }

    public function test_learn_rubric_template_criteria_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_rubric_template_criteria'));
        $this->assertTrue(Schema::hasColumns('learn_rubric_template_criteria', [
            'id', 'learn_rubric_template_id', 'description', 'max_points', 'position',
        ]));
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnRubricTemplateSchemaTest.php"`
Expected: FAIL — tables don't exist.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_08_09_100007_create_learn_rubric_templates_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_rubric_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_rubric_templates');
    }
};
```

`database/migrations/2026_08_09_100008_create_learn_rubric_template_criteria_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_rubric_template_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_rubric_template_id')->constrained('learn_rubric_templates')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('max_points', 6, 2);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['learn_rubric_template_id', 'position'], 'learn_rubric_template_criteria_template_position_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_rubric_template_criteria');
    }
};
```

- [ ] **Step 4: Run migrations and the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_09_100007_create_learn_rubric_templates_table.php --path=database/migrations/2026_08_09_100008_create_learn_rubric_template_criteria_table.php --force"`
Then: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnRubricTemplateSchemaTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_09_100007_create_learn_rubric_templates_table.php \
        database/migrations/2026_08_09_100008_create_learn_rubric_template_criteria_table.php \
        tests/Feature/Learn/LearnRubricTemplateSchemaTest.php
git commit -m "feat(learn): add rubric template schema (learn_rubric_templates, criteria)"
```

---

### Task 2: RubricTemplate + RubricTemplateCriterion models

**Files:**
- Create: `app/Models/Learn/RubricTemplate.php`
- Create: `app/Models/Learn/RubricTemplateCriterion.php`
- Test: `tests/Feature/Learn/RubricTemplateModelTest.php`

**Interfaces:**
- Produces: `RubricTemplate::user(): BelongsTo`, `RubricTemplate::criteria(): HasMany` (ordered by `position`). `RubricTemplateCriterion::template(): BelongsTo`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\Learn\RubricTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubricTemplateModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_belongs_to_user_and_has_ordered_criteria(): void
    {
        $user = User::factory()->create();
        $template = RubricTemplate::create(['user_id' => $user->id, 'name' => 'Essay Rubric']);
        $template->criteria()->create(['description' => 'Content', 'max_points' => 20, 'position' => 1]);
        $template->criteria()->create(['description' => 'Grammar', 'max_points' => 10, 'position' => 0]);

        $this->assertTrue($template->user->is($user));
        $this->assertSame(['Grammar', 'Content'], $template->fresh()->criteria->pluck('description')->all());
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/RubricTemplateModelTest.php"`
Expected: FAIL — model classes don't exist.

- [ ] **Step 3: Write the models**

`app/Models/Learn/RubricTemplate.php`:

```php
<?php

namespace App\Models\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubricTemplate extends Model
{
    protected $table = 'learn_rubric_templates';

    protected $fillable = ['user_id', 'name'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(RubricTemplateCriterion::class, 'learn_rubric_template_id')->orderBy('position');
    }
}
```

`app/Models/Learn/RubricTemplateCriterion.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricTemplateCriterion extends Model
{
    protected $table = 'learn_rubric_template_criteria';

    protected $fillable = ['learn_rubric_template_id', 'description', 'max_points', 'position'];

    protected $casts = [
        'max_points' => 'decimal:2',
        'position' => 'integer',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(RubricTemplate::class, 'learn_rubric_template_id');
    }
}
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/RubricTemplateModelTest.php"`
Expected: PASS (1 test).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Learn/RubricTemplate.php app/Models/Learn/RubricTemplateCriterion.php \
        tests/Feature/Learn/RubricTemplateModelTest.php
git commit -m "feat(learn): add RubricTemplate/RubricTemplateCriterion models"
```

---

### Task 3: RubricTemplateController (rename/delete) + routes

**Files:**
- Create: `app/Http/Controllers/Learn/RubricTemplateController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Learn/RubricTemplateControllerTest.php`

**Interfaces:**
- Produces routes: `learn.rubric-templates.update` (PUT `/learn/rubric-templates/{template}`), `learn.rubric-templates.destroy` (DELETE `/learn/rubric-templates/{template}`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\Learn\RubricTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubricTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_rename_and_delete_a_template(): void
    {
        $user = User::factory()->create();
        $template = RubricTemplate::create(['user_id' => $user->id, 'name' => 'Original']);

        $this->actingAs($user)
            ->put(route('learn.rubric-templates.update', $template), ['name' => 'Renamed'])
            ->assertRedirect();
        $this->assertSame('Renamed', $template->fresh()->name);

        $this->actingAs($user)
            ->delete(route('learn.rubric-templates.destroy', $template))
            ->assertRedirect();
        $this->assertDatabaseMissing('learn_rubric_templates', ['id' => $template->id]);
    }

    public function test_non_owner_cannot_rename_or_delete(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $template = RubricTemplate::create(['user_id' => $owner->id, 'name' => 'Original']);

        $this->actingAs($stranger)
            ->put(route('learn.rubric-templates.update', $template), ['name' => 'Hacked'])
            ->assertForbidden();
        $this->actingAs($stranger)
            ->delete(route('learn.rubric-templates.destroy', $template))
            ->assertForbidden();

        $this->assertDatabaseHas('learn_rubric_templates', ['id' => $template->id, 'name' => 'Original']);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/RubricTemplateControllerTest.php"`
Expected: FAIL — routes don't exist.

- [ ] **Step 3: Write `RubricTemplateController`**

`app/Http/Controllers/Learn/RubricTemplateController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\RubricTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RubricTemplateController extends Controller
{
    /** PUT /learn/rubric-templates/{template} */
    public function update(Request $request, RubricTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        $validated = $request->validate(['name' => 'required|string|max:255']);
        $template->update($validated);

        return back()->with('success', 'Template renamed.');
    }

    /** DELETE /learn/rubric-templates/{template} */
    public function destroy(RubricTemplate $template)
    {
        abort_unless($template->user_id === Auth::id(), 403);

        $template->delete();

        return back()->with('success', 'Template deleted.');
    }
}
```

- [ ] **Step 4: Add routes**

Add inside the `learn.` route group in `routes/web.php`, immediately after the `assignments.push` line from Phase 2b:

```php
    Route::put('/rubric-templates/{template}', [\App\Http\Controllers\Learn\RubricTemplateController::class, 'update'])->name('rubric-templates.update');
    Route::delete('/rubric-templates/{template}', [\App\Http\Controllers\Learn\RubricTemplateController::class, 'destroy'])->name('rubric-templates.destroy');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/RubricTemplateControllerTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/RubricTemplateController.php routes/web.php \
        tests/Feature/Learn/RubricTemplateControllerTest.php
git commit -m "feat(learn): add rubric template rename/delete endpoints"
```

---

### Task 4: Save a rubric as a template from `storeAssignment`

**Files:**
- Modify: `app/Http/Controllers/Learn/ModuleItemController.php`
- Test: `tests/Feature/Learn/StoreAssignmentSavesRubricTemplateTest.php`

**Interfaces:**
- Consumes: `RubricTemplate` (Task 2).
- Extends `storeAssignment`'s accepted request fields with `save_as_template` (bool) and `template_name` (string, required when `save_as_template` is true).

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
use App\Models\Learn\RubricTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreAssignmentSavesRubricTemplateTest extends TestCase
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

    public function test_save_as_template_creates_an_independent_copy(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-assignment', $this->module), [
            'title' => 'Lab', 'submission_type' => 'file',
            'rubric_criteria' => [
                ['description' => 'Accuracy', 'max_points' => 15],
                ['description' => 'Neatness', 'max_points' => 5],
            ],
            'save_as_template' => true, 'template_name' => 'Lab Rubric',
        ])->assertRedirect();

        $template = RubricTemplate::where('name', 'Lab Rubric')->firstOrFail();
        $this->assertSame($this->teacher->id, $template->user_id);
        $this->assertCount(2, $template->criteria);

        $assignment = Assignment::where('title', 'Lab')->firstOrFail();
        $this->assertEmpty(array_intersect(
            $assignment->rubric->criteria->pluck('id')->all(),
            $template->criteria->pluck('id')->all()
        ));
    }

    public function test_omitting_save_as_template_creates_no_template(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-assignment', $this->module), [
            'title' => 'Lab', 'submission_type' => 'file',
            'rubric_criteria' => [['description' => 'Accuracy', 'max_points' => 15]],
        ]);

        $this->assertDatabaseCount('learn_rubric_templates', 0);
    }

    public function test_editing_either_side_afterward_never_affects_the_other(): void
    {
        $this->actingAs($this->teacher)->post(route('learn.items.store-assignment', $this->module), [
            'title' => 'Lab', 'submission_type' => 'file',
            'rubric_criteria' => [['description' => 'Accuracy', 'max_points' => 15]],
            'save_as_template' => true, 'template_name' => 'Lab Rubric',
        ]);

        $assignment = Assignment::where('title', 'Lab')->firstOrFail();
        $template = RubricTemplate::where('name', 'Lab Rubric')->firstOrFail();

        $assignment->rubric->criteria->first()->update(['max_points' => 99]);
        $this->assertSame('15.00', $template->fresh()->criteria->first()->max_points);

        $template->update(['name' => 'Renamed']);
        $this->assertSame('Lab', $assignment->fresh()->title);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/StoreAssignmentSavesRubricTemplateTest.php"`
Expected: FAIL — `save_as_template`/`template_name` are silently ignored (not in validation rules), no template ever created.

- [ ] **Step 3: Update `ModuleItemController::storeAssignment`**

Add the import:

```php
use App\Models\Learn\RubricTemplate;
```

Update the validation array in `storeAssignment` to add two new rules after `rubric_criteria.*.max_points`:

```php
            'save_as_template' => 'nullable|boolean',
            'template_name' => 'required_if:save_as_template,true|nullable|string|max:255',
```

Add this block immediately after the existing rubric-creation `if ($hasRubric) { ... }` block, before `$this->attachItem($module, $assignment);`:

```php
        if ($hasRubric && ($validated['save_as_template'] ?? false)) {
            $template = RubricTemplate::create([
                'user_id' => $user->id,
                'name' => $validated['template_name'],
            ]);
            foreach ($validated['rubric_criteria'] as $position => $criterion) {
                $template->criteria()->create([
                    'description' => $criterion['description'],
                    'max_points' => $criterion['max_points'],
                    'position' => $position,
                ]);
            }
        }
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/StoreAssignmentSavesRubricTemplateTest.php"`
Expected: PASS (3 tests).

- [ ] **Step 5: Run Phase 2's existing assignment-authoring test to confirm no regression**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ModuleItemAssignmentControllerTest.php"`
Expected: PASS (still 3 tests — the new optional fields must not break existing assignment creation without a rubric or without `save_as_template`).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/ModuleItemController.php \
        tests/Feature/Learn/StoreAssignmentSavesRubricTemplateTest.php
git commit -m "feat(learn): save a rubric as a reusable template when creating an assignment"
```

---

### Task 5: Surface the instructor's templates on the course page

**Files:**
- Modify: `app/Http/Controllers/Learn/CourseController.php`
- Test: `tests/Feature/Learn/CourseShowSurfacesRubricTemplatesTest.php`

**Interfaces:**
- Consumes: `RubricTemplate` (Task 2).
- Extends the existing `Inertia::render('Learn/Show', [...])` payload from Phase 1 with a new top-level `rubric_templates` prop.

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
use App\Models\Learn\RubricTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseShowSurfacesRubricTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_includes_only_the_instructors_own_rubric_templates(): void
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

        $template = RubricTemplate::create(['user_id' => $teacher->id, 'name' => 'Essay Rubric']);
        $template->criteria()->create(['description' => 'Content', 'max_points' => 20, 'position' => 0]);

        $otherTeacher = User::factory()->create();
        RubricTemplate::create(['user_id' => $otherTeacher->id, 'name' => 'Not mine']);

        $response = $this->actingAs($teacher)->get(route('learn.show', $course));

        $response->assertInertia(fn ($page) => $page
            ->has('rubric_templates', 1)
            ->where('rubric_templates.0.name', 'Essay Rubric')
            ->where('rubric_templates.0.criteria.0.description', 'Content')
        );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseShowSurfacesRubricTemplatesTest.php"`
Expected: FAIL — no `rubric_templates` key in the payload.

- [ ] **Step 3: Update `CourseController`**

Add the import:

```php
use App\Models\Learn\RubricTemplate;
```

In `show()`, change the return statement from:

```php
        return Inertia::render('Learn/Show', [
            'course' => $this->serializeCourse($course, $user),
        ]);
```

to:

```php
        return Inertia::render('Learn/Show', [
            'course' => $this->serializeCourse($course, $user),
            'rubric_templates' => RubricTemplate::where('user_id', $user->id)
                ->with('criteria')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'criteria' => $t->criteria->map(fn ($c) => [
                        'description' => $c->description, 'max_points' => (float) $c->max_points,
                    ])->values(),
                ])->values(),
        ]);
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseShowSurfacesRubricTemplatesTest.php"`
Expected: PASS (1 test).

- [ ] **Step 5: Run Phase 1/2's existing course-controller tests to confirm no regression**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseControllerTest.php tests/Feature/Learn/CourseAssignmentSerializationTest.php"`
Expected: PASS (still 5 + 1 tests — the new prop must not break existing course-page assertions).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/CourseController.php \
        tests/Feature/Learn/CourseShowSurfacesRubricTemplatesTest.php
git commit -m "feat(learn): surface the instructor's own rubric templates on the course page"
```

---

### Task 6: Show.vue — template dropdown, save checkbox, manage list

**Files:**
- Modify: `resources/js/Pages/Learn/Show.vue`

**Interfaces:**
- Consumes `rubric_templates` prop from Task 5 (`[{id, name, criteria: [{description, max_points}]}]`).
- Uses named routes: `learn.rubric-templates.update`, `learn.rubric-templates.destroy`.

- [ ] **Step 1: Add `rubric_templates` to props and template-handling functions**

In `resources/js/Pages/Learn/Show.vue`, change the props declaration:

```js
const props = defineProps({ course: Object, rubric_templates: Array })
```

Add these functions after the existing `removeRubricCriterion` function:

```js
function applyTemplate(moduleId, templateId) {
  const template = props.rubric_templates.find(t => t.id === Number(templateId))
  if (! template) return
  assignmentForm(moduleId).rubric_criteria = template.criteria.map(c => ({
    description: c.description, max_points: c.max_points,
  }))
}

const renameTemplateDrafts = ref({})
function startRenameTemplate(template) {
  renameTemplateDrafts.value[template.id] = template.name
}
function saveTemplateRename(template) {
  router.put(route('learn.rubric-templates.update', template.id), {
    name: renameTemplateDrafts.value[template.id],
  }, {
    preserveScroll: true,
    onSuccess: () => { delete renameTemplateDrafts.value[template.id] },
  })
}
function deleteTemplate(template) {
  router.delete(route('learn.rubric-templates.destroy', template.id), { preserveScroll: true })
}
```

Update the `assignmentForm` factory to include the two new fields:

```js
function assignmentForm(moduleId) {
  if (! assignmentForms.value[moduleId]) {
    assignmentForms.value[moduleId] = useForm({
      title: '', instructions: '', submission_type: 'text',
      points_possible: '', due_at: '', rubric_criteria: [],
      save_as_template: false, template_name: '',
    })
  }
  return assignmentForms.value[moduleId]
}
```

- [ ] **Step 2: Add the template UI to the rubric-builder section**

In the template, immediately before the existing `<button @click="addRubricCriterion(module.id)" ...>+ Add rubric criterion</button>` line, add:

```html
                <div v-if="rubric_templates.length" class="flex gap-2 items-center">
                  <select @change="e => applyTemplate(module.id, e.target.value)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1">
                    <option value="" disabled selected>Start from a saved template</option>
                    <option v-for="t in rubric_templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                  </select>
                </div>
```

Immediately after the `+ Add rubric criterion` button, add the save-as-template controls:

```html
                <div v-if="assignmentForm(module.id).rubric_criteria.length > 0" class="flex items-center gap-2">
                  <input type="checkbox" v-model="assignmentForm(module.id).save_as_template" :id="`save-template-${module.id}`" />
                  <label :for="`save-template-${module.id}`" class="text-xs text-slate-600">Save these criteria as a template</label>
                </div>
                <input v-if="assignmentForm(module.id).save_as_template" v-model="assignmentForm(module.id).template_name" placeholder="Template name" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />

                <div v-if="rubric_templates.length" class="border-t border-slate-100 pt-2 space-y-1">
                  <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">My templates</p>
                  <div v-for="t in rubric_templates" :key="t.id" class="flex items-center gap-2">
                    <input v-if="renameTemplateDrafts[t.id] !== undefined" v-model="renameTemplateDrafts[t.id]" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs flex-1" />
                    <span v-else class="text-xs text-slate-600 flex-1">{{ t.name }}</span>
                    <button v-if="renameTemplateDrafts[t.id] !== undefined" @click="saveTemplateRename(t)" class="text-xs text-indigo-600 underline">Save</button>
                    <button v-else @click="startRenameTemplate(t)" class="text-xs text-slate-500 underline">Rename</button>
                    <button @click="deleteTemplate(t)" class="text-xs text-red-500 underline">Delete</button>
                  </div>
                </div>
```

- [ ] **Step 3: Build frontend assets and verify no compile errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing `Learn/Show.vue`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Learn/Show.vue
git commit -m "feat(learn): add rubric template dropdown, save checkbox, and manage list to Show.vue"
```

---

### Task 7: Full test suite + manual verification

**Files:** none created — verification only.

- [ ] **Step 1: Run all Phase 2c tests together with the full Learn suite**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=512M vendor/bin/phpunit tests/Feature/Learn tests/Feature/StudentPortal/LearnControllerTest.php tests/Feature/StudentPortal/LearnAssignmentSerializationTest.php tests/Feature/StudentPortal/LearnSubmissionControllerTest.php"`
Expected: all Phase 1 + 2 + 2b + 2c Learn tests pass together (no regressions in either direction).

- [ ] **Step 2: Run the full project regression suite**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -d memory_limit=1G vendor/bin/phpunit"` (run in the background and wait — 15-20+ minutes; do not run any other command that touches the database while it's running)
Expected: no new failures beyond the known pre-existing baseline — cross-check failing test names against prior Phase 1/2/2b runs; none should mention Learn or this plan's files.

- [ ] **Step 3: Manual browser verification — golden path**

As a faculty member with a current-SY teaching `LoadAssignment`:

1. Create an assignment with a rubric (2-3 criteria), check "Save these criteria as a template," name it, submit.
2. Confirm the new template appears in "My templates" on the course page.
3. Create a second assignment; select the saved template from the "Start from a saved template" dropdown — confirm the rubric criteria fields pre-fill correctly, then edit one of them before submitting.
4. Confirm the first assignment's rubric criteria are unchanged by the edit in step 3.
5. Rename the template — confirm the new name shows immediately and neither assignment's rubric changed.
6. Delete the template — confirm both assignments' rubrics are still intact and gradable normally.
7. As a different instructor, confirm this template never appeared in their own course page's template list.

- [ ] **Step 4: Report results**

Note any issues found during manual verification; fix and re-verify before considering Phase 2c complete. Do not commit for this task — it is verification only.

---

## Phase 2c Complete — Next Steps

Once all 7 tasks pass, Learn Phase 2c (Reusable Rubric Bank) is done, completing the full Phase 2 line (2, 2b, 2c) of the roadmap. Phase 3 (Quizzes/assessment engine) needs its own `superpowers:brainstorming` → design → plan cycle before implementation, per the roadmap in `docs/superpowers/specs/2026-08-09-learn-module-phase2c-design.md`.
