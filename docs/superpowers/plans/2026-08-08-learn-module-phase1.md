# Learn Module Phase 1: Course Shell + Content — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the Learn module's course shell — auto-resolved courses, Modules/Pages/Files, syllabus, course announcements, publish/draft — wired to Faculty Loading and Student Portal, per `docs/superpowers/specs/2026-08-08-learn-module-phase1-design.md`.

**Architecture:** Courses are lazily `firstOrCreate()`d from teaching `LoadAssignment` tuples (subject, section, school year, academic term) — no sync job, no observer. Instructor lists and read-only locking are computed live off `LoadAssignment` and `SchoolYear.is_current`, mirroring `ClassRecord::teacherIdsFor()`/`isCurrentSchoolYear()`. Content is polymorphic: `Module` → `ModuleItem` (`itemable_type/id`) → `Page` or `File`. Faculty use the main Inertia app; students get a read-only view inside the existing Firebase-authenticated Student Portal, gated by `student_enrollments`.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia 2, Tailwind, Tiptap (`RichTextEditor.vue`), S3 via `Storage::disk('s3')`.

## Global Constraints

- File uploads: base64 JSON body only, never `FormData`/`multipart` (Cloudflare WAF blocks it app-wide).
- Storage: `Storage::disk('s3')` only, never `disk('public')`.
- Controllers return `Inertia::render(...)`, never Blade views.
- Permission strings: `module.submodule.action` pattern; check via `$user->hasPermission()`.
- Eager-load relations to avoid N+1.
- Migrations: `YYYY_MM_DD_HHMMSS_description.php`, always write `down()`.
- `sections.id` is an **int** PK, not bigint — FK columns referencing it use `unsignedInteger`, not `foreignId`.
- `load_assignments.academic_term_id` is a required (non-nullable) FK — so is `learn_courses.academic_term_id`.
- Student Portal auth is a separate session (`student_pisaysystemID`), not the main app's `Auth`/Google OAuth — never mix the two in one controller.
- Roster/enrollment source of truth is `student_enrollments` (`status = enrolled`), **not** the legacy `section_students` mirror.
- No Eloquent Observers or scheduled sync jobs in this module — course/instructor resolution is computed live on read, matching `ClassRecord::teacherIdsFor()` and `RosterService`.

---

### Task 1: Learn schema (6 migrations)

**Files:**
- Create: `database/migrations/2026_08_08_100001_create_learn_courses_table.php`
- Create: `database/migrations/2026_08_08_100002_create_learn_modules_table.php`
- Create: `database/migrations/2026_08_08_100003_create_learn_module_items_table.php`
- Create: `database/migrations/2026_08_08_100004_create_learn_pages_table.php`
- Create: `database/migrations/2026_08_08_100005_create_learn_files_table.php`
- Create: `database/migrations/2026_08_08_100006_create_learn_course_announcements_table.php`
- Test: `tests/Feature/Learn/LearnSchemaTest.php`

**Interfaces:**
- Produces tables: `learn_courses(id, subject_id, section_id, school_year_id, academic_term_id, status, syllabus_body, timestamps)`, `learn_modules(id, learn_course_id, title, position, published_at, timestamps)`, `learn_module_items(id, learn_module_id, itemable_type, itemable_id, position, published_at, timestamps)`, `learn_pages(id, title, body, video_url, timestamps)`, `learn_files(id, title, s3_key, mime_type, size_bytes, timestamps)`, `learn_course_announcements(id, learn_course_id, title, body, posted_by, posted_at, timestamps)`.

- [ ] **Step 1: Write the failing schema test**

```php
<?php

namespace Tests\Feature\Learn;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LearnSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_learn_courses_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_courses'));
        $this->assertTrue(Schema::hasColumns('learn_courses', [
            'id', 'subject_id', 'section_id', 'school_year_id', 'academic_term_id',
            'status', 'syllabus_body', 'created_at', 'updated_at',
        ]));
    }

    public function test_learn_modules_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_modules'));
        $this->assertTrue(Schema::hasColumns('learn_modules', [
            'id', 'learn_course_id', 'title', 'position', 'published_at',
        ]));
    }

    public function test_learn_module_items_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_module_items'));
        $this->assertTrue(Schema::hasColumns('learn_module_items', [
            'id', 'learn_module_id', 'itemable_type', 'itemable_id', 'position', 'published_at',
        ]));
    }

    public function test_learn_pages_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_pages'));
        $this->assertTrue(Schema::hasColumns('learn_pages', ['id', 'title', 'body', 'video_url']));
    }

    public function test_learn_files_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_files'));
        $this->assertTrue(Schema::hasColumns('learn_files', ['id', 'title', 's3_key', 'mime_type', 'size_bytes']));
    }

    public function test_learn_course_announcements_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('learn_course_announcements'));
        $this->assertTrue(Schema::hasColumns('learn_course_announcements', [
            'id', 'learn_course_id', 'title', 'body', 'posted_by', 'posted_at',
        ]));
    }

    public function test_learn_courses_tuple_is_unique(): void
    {
        \App\Models\FacultyLoading\SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        \Illuminate\Support\Facades\DB::table('learn_courses')->insert([
            ['subject_id' => 1, 'section_id' => 1, 'school_year_id' => 1, 'academic_term_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['subject_id' => 1, 'section_id' => 1, 'school_year_id' => 1, 'academic_term_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnSchemaTest.php"`
Expected: FAIL — tables don't exist.

- [ ] **Step 3: Write the migrations**

`database/migrations/2026_08_08_100001_create_learn_courses_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->unsignedInteger('section_id')->comment('FK to sections.id (int PK, not bigint)');
            $table->foreignId('school_year_id')->constrained('school_years')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->longText('syllabus_body')->nullable();
            $table->timestamps();

            $table->unique(
                ['subject_id', 'section_id', 'school_year_id', 'academic_term_id'],
                'learn_courses_tuple_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_courses');
    }
};
```

`database/migrations/2026_08_08_100002_create_learn_modules_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_course_id')->constrained('learn_courses')->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['learn_course_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_modules');
    }
};
```

`database/migrations/2026_08_08_100003_create_learn_module_items_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_module_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_module_id')->constrained('learn_modules')->cascadeOnDelete();
            $table->string('itemable_type');
            $table->unsignedBigInteger('itemable_id');
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['learn_module_id', 'position']);
            $table->index(['itemable_type', 'itemable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_module_items');
    }
};
```

`database/migrations/2026_08_08_100004_create_learn_pages_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->string('video_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_pages');
    }
};
```

`database/migrations/2026_08_08_100005_create_learn_files_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_files', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('s3_key');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();

            $table->unique('s3_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_files');
    }
};
```

`database/migrations/2026_08_08_100006_create_learn_course_announcements_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learn_course_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learn_course_id')->constrained('learn_courses')->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at');
            $table->timestamps();

            $table->index(['learn_course_id', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learn_course_announcements');
    }
};
```

- [ ] **Step 4: Run migrations and the test**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_08_100001_create_learn_courses_table.php --path=database/migrations/2026_08_08_100002_create_learn_modules_table.php --path=database/migrations/2026_08_08_100003_create_learn_module_items_table.php --path=database/migrations/2026_08_08_100004_create_learn_pages_table.php --path=database/migrations/2026_08_08_100005_create_learn_files_table.php --path=database/migrations/2026_08_08_100006_create_learn_course_announcements_table.php"`
Then: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnSchemaTest.php"`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_08_100001_create_learn_courses_table.php \
        database/migrations/2026_08_08_100002_create_learn_modules_table.php \
        database/migrations/2026_08_08_100003_create_learn_module_items_table.php \
        database/migrations/2026_08_08_100004_create_learn_pages_table.php \
        database/migrations/2026_08_08_100005_create_learn_files_table.php \
        database/migrations/2026_08_08_100006_create_learn_course_announcements_table.php \
        tests/Feature/Learn/LearnSchemaTest.php
git commit -m "feat(learn): add Phase 1 schema — courses, modules, items, pages, files, announcements"
```

---

### Task 2: Eloquent models + relations

**Files:**
- Create: `app/Models/Learn/Course.php`
- Create: `app/Models/Learn/Module.php`
- Create: `app/Models/Learn/ModuleItem.php`
- Create: `app/Models/Learn/Page.php`
- Create: `app/Models/Learn/File.php`
- Create: `app/Models/Learn/CourseAnnouncement.php`
- Test: `tests/Feature/Learn/LearnModelRelationsTest.php`

**Interfaces:**
- Consumes: tables from Task 1.
- Produces: `Course::modules()`, `Course::announcements()`, `Module::course()`, `Module::items()`, `Module::isPublished(): bool`, `ModuleItem::module()`, `ModuleItem::itemable()` (morphTo), `ModuleItem::isPublished(): bool`, `Page::moduleItem()` (morphOne), `File::moduleItem()` (morphOne), `CourseAnnouncement::course()`, `CourseAnnouncement::postedBy()`. `Course::instructorIds()`/`canEdit()`/`canView()`/`isReadOnly()`/`isVisibleToStudent()` are added in Task 3 — do not add them here.

- [ ] **Step 1: Write the failing relations test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Learn\CourseAnnouncement;
use App\Models\Learn\File as LearnFile;
use App\Models\Learn\Module;
use App\Models\Learn\ModuleItem;
use App\Models\Learn\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnModelRelationsTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(): Course
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

        return Course::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
    }

    public function test_course_has_modules_and_announcements(): void
    {
        $course = $this->makeCourse();
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $user = User::factory()->create();
        $course->announcements()->create([
            'title' => 'Welcome', 'body' => 'Hi class', 'posted_by' => $user->id, 'posted_at' => now(),
        ]);

        $this->assertCount(1, $course->fresh()->modules);
        $this->assertCount(1, $course->fresh()->announcements);
        $this->assertTrue($module->course->is($course));
    }

    public function test_module_item_resolves_page_via_polymorphic_relation(): void
    {
        $course = $this->makeCourse();
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $page = Page::create(['title' => 'Syllabus', 'body' => '<p>Hi</p>']);
        $item = $page->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);

        $this->assertInstanceOf(Page::class, $item->itemable);
        $this->assertSame('Syllabus', $item->itemable->title);
        $this->assertFalse($item->isPublished());

        $item->update(['published_at' => now()]);
        $this->assertTrue($item->fresh()->isPublished());
    }

    public function test_module_item_resolves_file_via_polymorphic_relation(): void
    {
        $course = $this->makeCourse();
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $file = LearnFile::create([
            'title' => 'Handout.pdf', 's3_key' => 'Learn/1/abc.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 1024,
        ]);
        $item = $file->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 1]);

        $this->assertInstanceOf(LearnFile::class, $item->itemable);
        $this->assertSame('Learn/1/abc.pdf', $item->itemable->s3_key);
    }

    public function test_module_is_published_reflects_published_at(): void
    {
        $course = $this->makeCourse();
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 0]);

        $this->assertFalse($module->isPublished());
        $module->update(['published_at' => now()]);
        $this->assertTrue($module->fresh()->isPublished());
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnModelRelationsTest.php"`
Expected: FAIL — model classes don't exist.

- [ ] **Step 3: Write the models**

`app/Models/Learn/Course.php`:

```php
<?php

namespace App\Models\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    protected $table = 'learn_courses';

    protected $fillable = [
        'subject_id', 'section_id', 'school_year_id', 'academic_term_id',
        'status', 'syllabus_body',
    ];

    protected $casts = [
        'subject_id' => 'integer',
        'section_id' => 'integer',
        'school_year_id' => 'integer',
        'academic_term_id' => 'integer',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('position');
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(CourseAnnouncement::class)->orderByDesc('posted_at');
    }
}
```

`app/Models/Learn/Module.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Module extends Model
{
    protected $table = 'learn_modules';

    protected $fillable = ['learn_course_id', 'title', 'position', 'published_at'];

    protected $casts = [
        'position' => 'integer',
        'published_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'learn_course_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ModuleItem::class, 'learn_module_id')->orderBy('position');
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
```

`app/Models/Learn/ModuleItem.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ModuleItem extends Model
{
    protected $table = 'learn_module_items';

    protected $fillable = ['learn_module_id', 'itemable_type', 'itemable_id', 'position', 'published_at'];

    protected $casts = [
        'position' => 'integer',
        'published_at' => 'datetime',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'learn_module_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null;
    }
}
```

`app/Models/Learn/Page.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Page extends Model
{
    protected $table = 'learn_pages';

    protected $fillable = ['title', 'body', 'video_url'];

    public function moduleItem(): MorphOne
    {
        return $this->morphOne(ModuleItem::class, 'itemable');
    }
}
```

`app/Models/Learn/File.php`:

```php
<?php

namespace App\Models\Learn;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class File extends Model
{
    protected $table = 'learn_files';

    protected $fillable = ['title', 's3_key', 'mime_type', 'size_bytes'];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    public function moduleItem(): MorphOne
    {
        return $this->morphOne(ModuleItem::class, 'itemable');
    }
}
```

`app/Models/Learn/CourseAnnouncement.php`:

```php
<?php

namespace App\Models\Learn;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseAnnouncement extends Model
{
    protected $table = 'learn_course_announcements';

    protected $fillable = ['learn_course_id', 'title', 'body', 'posted_by', 'posted_at'];

    protected $casts = [
        'posted_at' => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'learn_course_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnModelRelationsTest.php"`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Models/Learn/ tests/Feature/Learn/LearnModelRelationsTest.php
git commit -m "feat(learn): add Course/Module/ModuleItem/Page/File/CourseAnnouncement models"
```

---

### Task 3: CourseResolver service + Course authorization/visibility methods

**Files:**
- Create: `app/Services/Learn/CourseResolver.php`
- Modify: `app/Models/Learn/Course.php` (add `instructorIds()`, `canEdit()`, `canView()`, `isReadOnly()`, `isCurrentSchoolYear()`, `isVisibleToStudent()`)
- Test: `tests/Feature/Learn/CourseResolverTest.php`

**Interfaces:**
- Consumes: `App\Models\FacultyLoading\LoadAssignment` (`scopeTeaching()`, `user_id`, `subject_id`, `section_id`, `school_year_id`, `academic_term_id`), `App\Models\FacultyLoading\SchoolYear::where('is_current', true)`, `App\Models\Registrar\StudentEnrollment` (`student_id`, `school_year_id`, `section_id`, `status`).
- Produces: `CourseResolver::coursesForFaculty(User $user): Collection<Course>`, `CourseResolver::allCoursesForCurrentSchoolYear(): Collection<Course>`. `Course::instructorIds(): array<int>`, `Course::canEdit(User $user): bool`, `Course::canView(User $user): bool`, `Course::isReadOnly(): bool`, `Course::isCurrentSchoolYear(): bool`, `Course::isVisibleToStudent(int $studentId): bool`.

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
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\Learn\CourseResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseResolverTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private AcademicTerm $term;
    private Section $section;
    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
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
    }

    private function assignTeaching(User $user, ?Subject $subject = null, ?Section $section = null): LoadAssignment
    {
        $facultyLoad = FacultyLoad::create([
            'user_id' => $user->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        return LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $user->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'assignment_type' => 'teaching',
            'subject_id' => ($subject ?? $this->subject)->id,
            'section_id' => ($section ?? $this->section)->id,
            'load_units' => 3,
        ]);
    }

    public function test_resolver_creates_a_course_for_a_teaching_assignment(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);

        $courses = app(CourseResolver::class)->coursesForFaculty($teacher);

        $this->assertCount(1, $courses);
        $this->assertSame($this->subject->id, $courses->first()->subject_id);
        $this->assertDatabaseCount('learn_courses', 1);
    }

    public function test_resolver_is_idempotent_and_does_not_duplicate_the_course(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);

        app(CourseResolver::class)->coursesForFaculty($teacher);
        app(CourseResolver::class)->coursesForFaculty($teacher);

        $this->assertDatabaseCount('learn_courses', 1);
    }

    public function test_co_teachers_both_resolve_to_the_same_course_and_instructor_ids(): void
    {
        $peTeacher = User::factory()->create();
        $musicTeacher = User::factory()->create();
        $this->assignTeaching($peTeacher);
        $this->assignTeaching($musicTeacher);

        $peCourses = app(CourseResolver::class)->coursesForFaculty($peTeacher);
        $musicCourses = app(CourseResolver::class)->coursesForFaculty($musicTeacher);

        $this->assertTrue($peCourses->first()->is($musicCourses->first()));

        $ids = $peCourses->first()->instructorIds();
        $this->assertContains($peTeacher->id, $ids);
        $this->assertContains($musicTeacher->id, $ids);
    }

    public function test_can_edit_true_for_instructor_false_for_stranger(): void
    {
        $teacher = User::factory()->create();
        $stranger = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = app(CourseResolver::class)->coursesForFaculty($teacher)->first();

        $this->assertTrue($course->canEdit($teacher));
        $this->assertFalse($course->canEdit($stranger));
    }

    public function test_past_school_year_course_is_read_only_even_for_its_instructor(): void
    {
        $this->sy->update(['is_current' => false]);
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = app(CourseResolver::class)->coursesForFaculty($teacher)->first();

        $this->assertTrue($course->isReadOnly());
        $this->assertFalse($course->canEdit($teacher));
    }

    public function test_is_visible_to_student_requires_published_and_active_enrollment(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = app(CourseResolver::class)->coursesForFaculty($teacher)->first();

        $studentId = 555;
        $this->assertFalse($course->isVisibleToStudent($studentId), 'draft course must not be visible');

        $course->update(['status' => 'published']);
        $this->assertFalse($course->isVisibleToStudent($studentId), 'not enrolled yet');

        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $this->sy->id,
            'section_id' => $this->section->id, 'grade_level' => 8, 'status' => 'enrolled',
        ]);
        $this->assertTrue($course->fresh()->isVisibleToStudent($studentId));
    }

    public function test_all_courses_for_current_school_year_includes_every_teaching_tuple(): void
    {
        $teacherA = User::factory()->create();
        $teacherB = User::factory()->create();
        $subjectB = Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'MATH8', 'name' => 'Math 8',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 4, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
        $this->assignTeaching($teacherA);
        $this->assignTeaching($teacherB, $subjectB);

        $courses = app(CourseResolver::class)->allCoursesForCurrentSchoolYear();

        $this->assertCount(2, $courses);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseResolverTest.php"`
Expected: FAIL — `CourseResolver` and the new `Course` methods don't exist.

- [ ] **Step 3: Write `CourseResolver`**

`app/Services/Learn/CourseResolver.php`:

```php
<?php

namespace App\Services\Learn;

use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Learn\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Resolves Learn courses from Faculty Loading's teaching assignments. There
 * is no sync job or Observer — a course row is find-or-created the moment
 * someone asks to see it, so it can never be stale or fail to run.
 */
class CourseResolver
{
    /** @return Collection<int, Course> */
    public function coursesForFaculty(User $user): Collection
    {
        $schoolYearId = $this->currentSchoolYearId();
        if (! $schoolYearId) {
            return collect();
        }

        return $this->resolveFromAssignments(
            LoadAssignment::teaching()
                ->where('user_id', $user->id)
                ->where('school_year_id', $schoolYearId)
        );
    }

    /** @return Collection<int, Course> */
    public function allCoursesForCurrentSchoolYear(): Collection
    {
        $schoolYearId = $this->currentSchoolYearId();
        if (! $schoolYearId) {
            return collect();
        }

        return $this->resolveFromAssignments(
            LoadAssignment::teaching()->where('school_year_id', $schoolYearId)
        );
    }

    /** @return Collection<int, Course> */
    private function resolveFromAssignments(Builder $query): Collection
    {
        $tuples = $query
            ->get(['subject_id', 'section_id', 'school_year_id', 'academic_term_id'])
            ->unique(fn ($a) => "{$a->subject_id}-{$a->section_id}-{$a->school_year_id}-{$a->academic_term_id}");

        return $tuples->map(fn ($tuple) => Course::firstOrCreate([
            'subject_id' => $tuple->subject_id,
            'section_id' => $tuple->section_id,
            'school_year_id' => $tuple->school_year_id,
            'academic_term_id' => $tuple->academic_term_id,
        ]))->values();
    }

    private function currentSchoolYearId(): ?int
    {
        return SchoolYear::where('is_current', true)->value('id');
    }
}
```

- [ ] **Step 4: Add authorization/visibility methods to `Course`**

Add to `app/Models/Learn/Course.php` (inside the class, after `announcements()`):

```php
    use App\Models\FacultyLoading\LoadAssignment;
    use App\Models\Registrar\StudentEnrollment;
    use App\Models\User;

    public function isCurrentSchoolYear(): bool
    {
        return $this->schoolYear?->is_current === true;
    }

    public function isReadOnly(): bool
    {
        return ! $this->isCurrentSchoolYear();
    }

    /** @return array<int> */
    public function instructorIds(): array
    {
        return LoadAssignment::teaching()
            ->where('subject_id', $this->subject_id)
            ->where('section_id', $this->section_id)
            ->where('school_year_id', $this->school_year_id)
            ->where('academic_term_id', $this->academic_term_id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function canEdit(User $user): bool
    {
        if ($this->isReadOnly()) {
            return false;
        }
        if ($user->hasPermission('learn.course.view.all')) {
            return true;
        }

        return in_array((int) $user->id, $this->instructorIds(), true);
    }

    public function canView(User $user): bool
    {
        if ($user->hasPermission('learn.course.view.all')) {
            return true;
        }

        return in_array((int) $user->id, $this->instructorIds(), true);
    }

    public function isVisibleToStudent(int $studentId): bool
    {
        if ($this->status !== 'published') {
            return false;
        }

        return StudentEnrollment::where('student_id', $studentId)
            ->where('school_year_id', $this->school_year_id)
            ->where('section_id', $this->section_id)
            ->where('status', 'enrolled')
            ->exists();
    }
```

(Move the four `use` statements to the top of the file with the existing imports rather than inline — inline is shown here only to make the diff location unambiguous.)

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseResolverTest.php"`
Expected: PASS (7 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Services/Learn/CourseResolver.php app/Models/Learn/Course.php tests/Feature/Learn/CourseResolverTest.php
git commit -m "feat(learn): resolve courses live from LoadAssignment, compute instructors/access/visibility"
```

---

### Task 4: CourseFileService — base64 upload, S3 proxy id, stream response

**Files:**
- Create: `app/Services/Learn/CourseFileService.php`
- Test: `tests/Feature/Learn/CourseFileServiceTest.php`

**Interfaces:**
- Consumes: `App\Models\Learn\File`, `Storage::disk('s3')`.
- Produces: `CourseFileService::upload(int $courseId, string $title, string $dataUri): File`, `CourseFileService::encodeFileId(string $s3Key): string`, `CourseFileService::decodeFileId(string $fileId): ?string`, `CourseFileService::streamResponse(File $file): \Illuminate\Http\Response`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Services\Learn\CourseFileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CourseFileServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
    }

    public function test_upload_decodes_base64_and_stores_to_s3_never_public_disk(): void
    {
        $service = app(CourseFileService::class);
        $dataUri = 'data:application/pdf;base64,' . base64_encode('%PDF-1.4 fake pdf bytes');

        $file = $service->upload(1, 'Handout.pdf', $dataUri);

        $this->assertSame('Handout.pdf', $file->title);
        $this->assertSame('application/pdf', $file->mime_type);
        Storage::disk('s3')->assertExists($file->s3_key);
        $this->assertStringStartsWith('Learn/1/', $file->s3_key);
        $this->assertSame('%PDF-1.4 fake pdf bytes', Storage::disk('s3')->get($file->s3_key));
    }

    public function test_encode_and_decode_file_id_round_trip(): void
    {
        $service = app(CourseFileService::class);
        $s3Key = 'Learn/1/abc-def.pdf';

        $fileId = $service->encodeFileId($s3Key);

        $this->assertStringStartsWith('s3.', $fileId);
        $this->assertSame($s3Key, $service->decodeFileId($fileId));
    }

    public function test_decode_file_id_returns_null_for_non_s3_ids(): void
    {
        $service = app(CourseFileService::class);

        $this->assertNull($service->decodeFileId('legacy-drive-id-123'));
    }

    public function test_stream_response_serves_file_bytes_and_content_type(): void
    {
        $service = app(CourseFileService::class);
        $file = $service->upload(1, 'Notes.pdf', 'data:application/pdf;base64,' . base64_encode('hello'));

        $response = $service->streamResponse($file);

        $this->assertSame('hello', $response->getContent());
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_stream_response_404s_when_s3_object_is_missing(): void
    {
        $file = \App\Models\Learn\File::create([
            'title' => 'Ghost.pdf', 's3_key' => 'Learn/1/does-not-exist.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 10,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);

        app(CourseFileService::class)->streamResponse($file);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseFileServiceTest.php"`
Expected: FAIL — class doesn't exist.

- [ ] **Step 3: Write `CourseFileService`**

`app/Services/Learn/CourseFileService.php`:

```php
<?php

namespace App\Services\Learn;

use App\Models\Learn\File as LearnFile;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Base64 → S3 upload and private-proxy serving for Learn files, following
 * the same encoding WFH photos use (Storage::disk('s3') only — never
 * disk('public'), since S3 Block Public Access silently drops that ACL).
 */
class CourseFileService
{
    public function upload(int $courseId, string $title, string $dataUri): LearnFile
    {
        if (str_contains($dataUri, ',')) {
            [$meta, $base64] = explode(',', $dataUri, 2);
        } else {
            $meta = '';
            $base64 = $dataUri;
        }

        $binary = base64_decode($base64, true);
        if ($binary === false) {
            throw ValidationException::withMessages(['file' => 'Invalid file data.']);
        }

        $mime = $this->mimeFromMeta($meta) ?? 'application/octet-stream';
        $extension = $this->extensionFromMime($mime);
        $s3Key = "Learn/{$courseId}/" . uniqid() . ($extension ? ".{$extension}" : '');

        Storage::disk('s3')->put($s3Key, $binary);

        return LearnFile::create([
            'title' => $title,
            's3_key' => $s3Key,
            'mime_type' => $mime,
            'size_bytes' => strlen($binary),
        ]);
    }

    /** 's3.<base64url(s3Key)>' — same encoding WFH photos use. */
    public function encodeFileId(string $s3Key): string
    {
        return 's3.' . rtrim(strtr(base64_encode($s3Key), '+/', '-_'), '=');
    }

    public function decodeFileId(string $fileId): ?string
    {
        if (! str_starts_with($fileId, 's3.')) {
            return null;
        }

        $padded = strtr(substr($fileId, 3), '-_', '+/');
        $pad = strlen($padded) % 4;
        if ($pad) {
            $padded .= str_repeat('=', 4 - $pad);
        }

        $decoded = base64_decode($padded, true);

        return $decoded ?: null;
    }

    public function streamResponse(LearnFile $file): Response
    {
        if (! Storage::disk('s3')->exists($file->s3_key)) {
            abort(404);
        }

        return response(Storage::disk('s3')->get($file->s3_key), 200)
            ->header('Content-Type', $file->mime_type)
            ->header('Content-Disposition', 'inline; filename="' . addslashes($file->title) . '"');
    }

    private function mimeFromMeta(string $meta): ?string
    {
        if (preg_match('/^data:([a-zA-Z0-9\/\+\.\-]+);base64$/', $meta, $m)) {
            return $m[1];
        }

        return null;
    }

    private function extensionFromMime(string $mime): ?string
    {
        return match ($mime) {
            'application/pdf' => 'pdf',
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            default => null,
        };
    }
}
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseFileServiceTest.php"`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Services/Learn/CourseFileService.php tests/Feature/Learn/CourseFileServiceTest.php
git commit -m "feat(learn): add base64-to-S3 file upload and proxy-id encode/decode"
```

---

### Task 5: Permissions seeder

**Files:**
- Create: `database/seeders/LearnPermissionSeeder.php`
- Test: `tests/Feature/Learn/LearnPermissionSeederTest.php`

**Interfaces:**
- Produces permissions: `learn.course.view.all`, `learn.admin`. Grants `learn.course.view.all` to `Administrator`, `AUH`, `CID Chief`; grants `learn.admin` to `Administrator`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\Permission;
use App\Models\Role;
use Database\Seeders\LearnPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnPermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_permissions_and_grants_them_to_expected_roles(): void
    {
        Role::create(['name' => 'Administrator']);
        Role::create(['name' => 'AUH']);
        Role::create(['name' => 'CID Chief']);

        (new LearnPermissionSeeder())->run();

        $this->assertDatabaseHas('permissions', ['name' => 'learn.course.view.all']);
        $this->assertDatabaseHas('permissions', ['name' => 'learn.admin']);

        $viewAll = Permission::where('name', 'learn.course.view.all')->first();
        $admin = Role::where('name', 'Administrator')->first();
        $auh = Role::where('name', 'AUH')->first();

        $this->assertTrue($admin->permissions->contains($viewAll));
        $this->assertTrue($auh->permissions->contains($viewAll));
    }

    public function test_seeder_is_idempotent(): void
    {
        Role::create(['name' => 'Administrator']);

        (new LearnPermissionSeeder())->run();
        (new LearnPermissionSeeder())->run();

        $this->assertSame(1, Permission::where('name', 'learn.admin')->count());
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnPermissionSeederTest.php"`
Expected: FAIL — seeder class doesn't exist.

- [ ] **Step 3: Write the seeder**

`database/seeders/LearnPermissionSeeder.php`:

```php
<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Learn (LMS) module permissions.
 *
 * NOTE: production never auto-seeds — run this via ECS exec after deploy:
 *   php /var/www/artisan db:seed --class=Database\\Seeders\\LearnPermissionSeeder --force
 */
class LearnPermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        'learn.course.view.all' => 'View every Learn course regardless of teaching assignment (admin/AUH oversight)',
        'learn.admin' => 'Manage Learn module-wide settings',
    ];

    private const VIEW_ALL_ROLES = ['Administrator', 'AUH', 'CID Chief'];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $name => $description) {
            Permission::firstOrCreate(['name' => $name], [
                'module' => 'Learn',
                'description' => $description,
            ]);
        }

        $this->grant(self::VIEW_ALL_ROLES, ['learn.course.view.all']);
        $this->grant(['Administrator'], ['learn.admin']);
    }

    private function grant(array $roleNames, array $permNames): void
    {
        $ids = Permission::whereIn('name', $permNames)->pluck('id')->all();
        if (empty($ids)) {
            return;
        }
        foreach ($roleNames as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->permissions()->syncWithoutDetaching($ids);
            }
        }
    }
}
```

- [ ] **Step 4: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/LearnPermissionSeederTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add database/seeders/LearnPermissionSeeder.php tests/Feature/Learn/LearnPermissionSeederTest.php
git commit -m "feat(learn): add permission seeder for learn.course.view.all and learn.admin"
```

---

### Task 6: Faculty CourseController (index/show/syllabus/status) + routes

**Files:**
- Create: `app/Http/Controllers/Learn/CourseController.php`
- Create: `app/Http/Controllers/Learn/FileController.php`
- Modify: `routes/web.php` (add `learn.*` route group)
- Test: `tests/Feature/Learn/CourseControllerTest.php`

**Interfaces:**
- Consumes: `CourseResolver`, `CourseFileService`, `Course` model methods from Task 3.
- Produces routes: `learn.index` (GET `/learn`), `learn.show` (GET `/learn/{course}`), `learn.syllabus.update` (PUT `/learn/{course}/syllabus`), `learn.status.update` (PATCH `/learn/{course}/status`), `learn.files.show` (GET `/learn/file/{fileId}`).

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

class CourseControllerTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private AcademicTerm $term;
    private Section $section;
    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
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
    }

    private function assignTeaching(User $user): LoadAssignment
    {
        $facultyLoad = FacultyLoad::create([
            'user_id' => $user->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        return LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $user->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'assignment_type' => 'teaching', 'subject_id' => $this->subject->id,
            'section_id' => $this->section->id, 'load_units' => 3,
        ]);
    }

    public function test_index_lists_the_teachers_courses_and_creates_them_lazily(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);

        $response = $this->actingAs($teacher)->get(route('learn.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Learn/Index')
            ->has('courses', 1)
            ->where('courses.0.subject_name', 'Science 8')
        );
        $this->assertDatabaseCount('learn_courses', 1);
    }

    public function test_show_403s_for_a_non_instructor(): void
    {
        $teacher = User::factory()->create();
        $stranger = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        $this->actingAs($stranger)->get(route('learn.show', $course))->assertForbidden();
    }

    public function test_instructor_can_update_syllabus_but_stranger_cannot(): void
    {
        $teacher = User::factory()->create();
        $stranger = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        $this->actingAs($teacher)
            ->put(route('learn.syllabus.update', $course), ['syllabus_body' => '<p>Welcome</p>'])
            ->assertRedirect();
        $this->assertSame('<p>Welcome</p>', $course->fresh()->syllabus_body);

        $this->actingAs($stranger)
            ->put(route('learn.syllabus.update', $course), ['syllabus_body' => '<p>Hacked</p>'])
            ->assertForbidden();
    }

    public function test_instructor_can_publish_and_unpublish_the_course(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        $this->actingAs($teacher)
            ->patch(route('learn.status.update', $course), ['status' => 'published'])
            ->assertRedirect();
        $this->assertSame('published', $course->fresh()->status);
    }

    public function test_past_school_year_course_cannot_be_edited_even_by_its_instructor(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);
        $this->sy->update(['is_current' => false]);

        $this->actingAs($teacher)
            ->put(route('learn.syllabus.update', $course), ['syllabus_body' => '<p>Too late</p>'])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseControllerTest.php"`
Expected: FAIL — routes/controller don't exist.

- [ ] **Step 3: Write `CourseController` and `FileController`**

`app/Http/Controllers/Learn/CourseController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Course;
use App\Models\Learn\File as LearnFile;
use App\Models\Learn\Page as LearnPage;
use App\Services\Learn\CourseFileService;
use App\Services\Learn\CourseResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function __construct(
        private CourseResolver $resolver,
        private CourseFileService $files,
    ) {
    }

    /** GET /learn — "My Courses" for the signed-in faculty member. */
    public function index(): Response
    {
        $user = Auth::user();

        $courses = $user->hasPermission('learn.course.view.all')
            ? $this->resolver->allCoursesForCurrentSchoolYear()
            : $this->resolver->coursesForFaculty($user);

        $courses->load(['subject', 'section', 'schoolYear']);

        return Inertia::render('Learn/Index', [
            'courses' => $courses->map(fn (Course $c) => [
                'id' => $c->id,
                'subject_name' => $c->subject->name,
                'section_name' => $c->section->sectionname,
                'grade_level' => $c->section->levelid,
                'status' => $c->status,
                'is_read_only' => $c->isReadOnly(),
            ])->values(),
        ]);
    }

    /** GET /learn/{course} */
    public function show(Course $course): Response
    {
        $user = Auth::user();
        abort_unless($course->canView($user), 403);

        $course->load(['subject', 'section', 'schoolYear', 'modules.items.itemable', 'announcements.postedBy']);

        return Inertia::render('Learn/Show', [
            'course' => $this->serializeCourse($course, $user),
        ]);
    }

    /** PUT /learn/{course}/syllabus */
    public function updateSyllabus(Request $request, Course $course)
    {
        $user = Auth::user();
        abort_unless($course->canEdit($user), 403);

        $validated = $request->validate(['syllabus_body' => 'nullable|string']);
        $course->update($validated);

        return back()->with('success', 'Syllabus updated.');
    }

    /** PATCH /learn/{course}/status */
    public function updateStatus(Request $request, Course $course)
    {
        $user = Auth::user();
        abort_unless($course->canEdit($user), 403);

        $validated = $request->validate(['status' => 'required|in:draft,published']);
        $course->update($validated);

        return back()->with('success', $validated['status'] === 'published' ? 'Course published.' : 'Course moved back to draft.');
    }

    private function serializeCourse(Course $course, $user): array
    {
        return [
            'id' => $course->id,
            'subject_name' => $course->subject->name,
            'section_name' => $course->section->sectionname,
            'grade_level' => $course->section->levelid,
            'status' => $course->status,
            'syllabus_body' => $course->syllabus_body,
            'is_read_only' => $course->isReadOnly(),
            'can_edit' => $course->canEdit($user),
            'modules' => $course->modules->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'position' => $m->position,
                'is_published' => $m->isPublished(),
                'items' => $m->items->map(fn ($i) => $this->serializeItem($i))->values(),
            ])->values(),
            'announcements' => $course->announcements->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'posted_by' => $a->postedBy?->name,
                'posted_at' => $a->posted_at?->toIso8601String(),
            ])->values(),
        ];
    }

    private function serializeItem($item): array
    {
        $itemable = $item->itemable;

        return [
            'id' => $item->id,
            'type' => $itemable instanceof LearnPage ? 'page' : 'file',
            'position' => $item->position,
            'is_published' => $item->isPublished(),
            'title' => $itemable?->title,
            'body' => $itemable instanceof LearnPage ? $itemable->body : null,
            'video_url' => $itemable instanceof LearnPage ? $itemable->video_url : null,
            'file_url' => $itemable instanceof LearnFile
                ? route('learn.files.show', ['fileId' => $this->files->encodeFileId($itemable->s3_key)])
                : null,
        ];
    }
}
```

`app/Http/Controllers/Learn/FileController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\File as LearnFile;
use App\Services\Learn\CourseFileService;
use Illuminate\Support\Facades\Auth;

class FileController extends Controller
{
    public function __construct(private CourseFileService $files)
    {
    }

    /** GET /learn/file/{fileId} */
    public function show(string $fileId)
    {
        $s3Key = $this->files->decodeFileId($fileId);
        abort_if(! $s3Key, 404);

        $file = LearnFile::where('s3_key', $s3Key)->firstOrFail();
        $course = $file->moduleItem?->module->course;
        abort_if(! $course, 404);
        abort_unless($course->canView(Auth::user()), 403);

        return $this->files->streamResponse($file);
    }
}
```

- [ ] **Step 4: Add routes**

Add to `routes/web.php` (new block, after the Class Record routes block):

```php
/*
|--------------------------------------------------------------------------
| Learn Module (LMS) — Phase 1: Course Shell + Content
|--------------------------------------------------------------------------
*/
Route::middleware(['auth'])->prefix('learn')->name('learn.')->group(function () {
    Route::get('/', [\App\Http\Controllers\Learn\CourseController::class, 'index'])->name('index');

    // Must be registered before the {course} wildcard.
    Route::get('/file/{fileId}', [\App\Http\Controllers\Learn\FileController::class, 'show'])
        ->name('files.show')->where('fileId', '[a-zA-Z0-9_.=-]+');

    Route::get('/{course}', [\App\Http\Controllers\Learn\CourseController::class, 'show'])->name('show');
    Route::put('/{course}/syllabus', [\App\Http\Controllers\Learn\CourseController::class, 'updateSyllabus'])->name('syllabus.update');
    Route::patch('/{course}/status', [\App\Http\Controllers\Learn\CourseController::class, 'updateStatus'])->name('status.update');
});
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseControllerTest.php"`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/CourseController.php app/Http/Controllers/Learn/FileController.php \
        routes/web.php tests/Feature/Learn/CourseControllerTest.php
git commit -m "feat(learn): add faculty CourseController (index/show/syllabus/status) and file proxy route"
```

---

### Task 7: ModuleController + ModuleItemController (CRUD, reorder, publish toggle) + routes

**Files:**
- Create: `app/Http/Controllers/Learn/ModuleController.php`
- Create: `app/Http/Controllers/Learn/ModuleItemController.php`
- Modify: `routes/web.php` (extend `learn.*` group)
- Test: `tests/Feature/Learn/ModuleAndItemControllerTest.php`

**Interfaces:**
- Consumes: `Course::canEdit()`, `CourseFileService::upload()`.
- Produces routes: `learn.modules.store`, `learn.modules.update`, `learn.modules.publish`, `learn.modules.reorder`, `learn.modules.destroy`, `learn.items.store-page`, `learn.items.store-file`, `learn.items.publish`, `learn.items.reorder`, `learn.items.destroy`.

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
use App\Models\Learn\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ModuleAndItemControllerTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private AcademicTerm $term;
    private Section $section;
    private Subject $subject;
    private User $teacher;
    private User $stranger;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
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
        $this->stranger = User::factory()->create();

        $facultyLoad = FacultyLoad::create([
            'user_id' => $this->teacher->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);
        LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $this->teacher->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'assignment_type' => 'teaching', 'subject_id' => $this->subject->id,
            'section_id' => $this->section->id, 'load_units' => 3,
        ]);

        $this->course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);
    }

    public function test_instructor_can_create_a_module_stranger_cannot(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('learn.modules.store', $this->course), ['title' => 'Week 1'])
            ->assertRedirect();
        $this->assertDatabaseHas('learn_modules', ['learn_course_id' => $this->course->id, 'title' => 'Week 1']);

        $this->actingAs($this->stranger)
            ->post(route('learn.modules.store', $this->course), ['title' => 'Hack'])
            ->assertForbidden();
    }

    public function test_instructor_can_toggle_module_publish(): void
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);

        $this->actingAs($this->teacher)->patch(route('learn.modules.publish', $module))->assertRedirect();
        $this->assertTrue($module->fresh()->isPublished());

        $this->actingAs($this->teacher)->patch(route('learn.modules.publish', $module))->assertRedirect();
        $this->assertFalse($module->fresh()->isPublished());
    }

    public function test_instructor_can_reorder_modules(): void
    {
        $first = $this->course->modules()->create(['title' => 'A', 'position' => 0]);
        $second = $this->course->modules()->create(['title' => 'B', 'position' => 1]);

        $this->actingAs($this->teacher)
            ->put(route('learn.modules.reorder', $this->course), ['module_ids' => [$second->id, $first->id]])
            ->assertRedirect();

        $this->assertSame(0, $second->fresh()->position);
        $this->assertSame(1, $first->fresh()->position);
    }

    public function test_instructor_can_delete_a_module(): void
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);

        $this->actingAs($this->teacher)->delete(route('learn.modules.destroy', $module))->assertRedirect();
        $this->assertDatabaseMissing('learn_modules', ['id' => $module->id]);
    }

    public function test_instructor_can_add_a_page_item(): void
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);

        $this->actingAs($this->teacher)->post(route('learn.items.store-page', $module), [
            'title' => 'Intro', 'body' => '<p>Hello</p>', 'video_url' => null,
        ])->assertRedirect();

        $this->assertDatabaseHas('learn_pages', ['title' => 'Intro']);
        $this->assertDatabaseHas('learn_module_items', ['learn_module_id' => $module->id, 'itemable_type' => \App\Models\Learn\Page::class]);
    }

    public function test_instructor_can_add_a_file_item(): void
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $dataUri = 'data:application/pdf;base64,' . base64_encode('fake pdf');

        $this->actingAs($this->teacher)->post(route('learn.items.store-file', $module), [
            'title' => 'Handout.pdf', 'file_base64' => $dataUri,
        ])->assertRedirect();

        $this->assertDatabaseHas('learn_files', ['title' => 'Handout.pdf']);
        $this->assertDatabaseHas('learn_module_items', ['learn_module_id' => $module->id, 'itemable_type' => \App\Models\Learn\File::class]);
    }

    public function test_stranger_cannot_add_items_to_a_module(): void
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);

        $this->actingAs($this->stranger)->post(route('learn.items.store-page', $module), [
            'title' => 'Intro', 'body' => '<p>Hi</p>',
        ])->assertForbidden();
    }

    public function test_instructor_can_toggle_item_publish_reorder_and_delete(): void
    {
        $module = $this->course->modules()->create(['title' => 'Week 1', 'position' => 0]);
        $page = \App\Models\Learn\Page::create(['title' => 'Intro']);
        $item1 = $page->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 0]);
        $page2 = \App\Models\Learn\Page::create(['title' => 'Outro']);
        $item2 = $page2->moduleItem()->create(['learn_module_id' => $module->id, 'position' => 1]);

        $this->actingAs($this->teacher)->patch(route('learn.items.publish', $item1))->assertRedirect();
        $this->assertTrue($item1->fresh()->isPublished());

        $this->actingAs($this->teacher)
            ->put(route('learn.items.reorder', $module), ['item_ids' => [$item2->id, $item1->id]])
            ->assertRedirect();
        $this->assertSame(0, $item2->fresh()->position);
        $this->assertSame(1, $item1->fresh()->position);

        $this->actingAs($this->teacher)->delete(route('learn.items.destroy', $item1))->assertRedirect();
        $this->assertDatabaseMissing('learn_module_items', ['id' => $item1->id]);
        $this->assertDatabaseMissing('learn_pages', ['id' => $page->id]);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ModuleAndItemControllerTest.php"`
Expected: FAIL — routes/controllers don't exist.

- [ ] **Step 3: Write `ModuleController`**

`app/Http/Controllers/Learn/ModuleController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Course;
use App\Models\Learn\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModuleController extends Controller
{
    /** POST /learn/{course}/modules */
    public function store(Request $request, Course $course)
    {
        $user = Auth::user();
        abort_unless($course->canEdit($user), 403);

        $validated = $request->validate(['title' => 'required|string|max:255']);
        $position = (int) ($course->modules()->max('position')) + 1;

        $course->modules()->create([
            'title' => $validated['title'],
            'position' => $position,
        ]);

        return back()->with('success', 'Module added.');
    }

    /** PUT /learn/modules/{module} */
    public function update(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate(['title' => 'required|string|max:255']);
        $module->update($validated);

        return back()->with('success', 'Module updated.');
    }

    /** PATCH /learn/modules/{module}/publish */
    public function togglePublish(Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $module->update(['published_at' => $module->isPublished() ? null : now()]);

        return back()->with('success', $module->fresh()->isPublished() ? 'Module published.' : 'Module unpublished.');
    }

    /** PUT /learn/{course}/modules/reorder */
    public function reorder(Request $request, Course $course)
    {
        $user = Auth::user();
        abort_unless($course->canEdit($user), 403);

        $validated = $request->validate([
            'module_ids' => 'required|array',
            'module_ids.*' => 'integer|exists:learn_modules,id',
        ]);

        foreach ($validated['module_ids'] as $position => $moduleId) {
            Module::where('id', $moduleId)->where('learn_course_id', $course->id)->update(['position' => $position]);
        }

        return back()->with('success', 'Modules reordered.');
    }

    /** DELETE /learn/modules/{module} */
    public function destroy(Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $module->delete();

        return back()->with('success', 'Module deleted.');
    }
}
```

`app/Http/Controllers/Learn/ModuleItemController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Module;
use App\Models\Learn\ModuleItem;
use App\Models\Learn\Page;
use App\Services\Learn\CourseFileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModuleItemController extends Controller
{
    public function __construct(private CourseFileService $files)
    {
    }

    /** POST /learn/modules/{module}/items/page */
    public function storePage(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'video_url' => 'nullable|url',
        ]);

        $page = Page::create($validated);
        $this->attachItem($module, $page);

        return back()->with('success', 'Page added.');
    }

    /** POST /learn/modules/{module}/items/file */
    public function storeFile(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'file_base64' => 'required|string',
        ]);

        $file = $this->files->upload($module->learn_course_id, $validated['title'], $validated['file_base64']);
        $this->attachItem($module, $file);

        return back()->with('success', 'File added.');
    }

    /** PATCH /learn/items/{item}/publish */
    public function togglePublish(ModuleItem $item)
    {
        $user = Auth::user();
        abort_unless($item->module->course->canEdit($user), 403);

        $item->update(['published_at' => $item->isPublished() ? null : now()]);

        return back()->with('success', $item->fresh()->isPublished() ? 'Item published.' : 'Item unpublished.');
    }

    /** PUT /learn/modules/{module}/items/reorder */
    public function reorder(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer|exists:learn_module_items,id',
        ]);

        foreach ($validated['item_ids'] as $position => $itemId) {
            ModuleItem::where('id', $itemId)->where('learn_module_id', $module->id)->update(['position' => $position]);
        }

        return back()->with('success', 'Items reordered.');
    }

    /** DELETE /learn/items/{item} */
    public function destroy(ModuleItem $item)
    {
        $user = Auth::user();
        abort_unless($item->module->course->canEdit($user), 403);

        $itemable = $item->itemable;
        $item->delete();
        $itemable?->delete();

        return back()->with('success', 'Item deleted.');
    }

    private function attachItem(Module $module, $itemable): ModuleItem
    {
        $position = (int) ($module->items()->max('position')) + 1;

        return $itemable->moduleItem()->create([
            'learn_module_id' => $module->id,
            'position' => $position,
        ]);
    }
}
```

- [ ] **Step 4: Add routes**

Add inside the `learn.` route group from Task 6, after the `status.update` route:

```php
    Route::post('/{course}/modules', [\App\Http\Controllers\Learn\ModuleController::class, 'store'])->name('modules.store');
    Route::put('/{course}/modules/reorder', [\App\Http\Controllers\Learn\ModuleController::class, 'reorder'])->name('modules.reorder');
    Route::put('/modules/{module}', [\App\Http\Controllers\Learn\ModuleController::class, 'update'])->name('modules.update');
    Route::patch('/modules/{module}/publish', [\App\Http\Controllers\Learn\ModuleController::class, 'togglePublish'])->name('modules.publish');
    Route::delete('/modules/{module}', [\App\Http\Controllers\Learn\ModuleController::class, 'destroy'])->name('modules.destroy');

    Route::post('/modules/{module}/items/page', [\App\Http\Controllers\Learn\ModuleItemController::class, 'storePage'])->name('items.store-page');
    Route::post('/modules/{module}/items/file', [\App\Http\Controllers\Learn\ModuleItemController::class, 'storeFile'])->name('items.store-file');
    Route::put('/modules/{module}/items/reorder', [\App\Http\Controllers\Learn\ModuleItemController::class, 'reorder'])->name('items.reorder');
    Route::patch('/items/{item}/publish', [\App\Http\Controllers\Learn\ModuleItemController::class, 'togglePublish'])->name('items.publish');
    Route::delete('/items/{item}', [\App\Http\Controllers\Learn\ModuleItemController::class, 'destroy'])->name('items.destroy');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/ModuleAndItemControllerTest.php"`
Expected: PASS (8 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/ModuleController.php app/Http/Controllers/Learn/ModuleItemController.php \
        routes/web.php tests/Feature/Learn/ModuleAndItemControllerTest.php
git commit -m "feat(learn): add module/module-item CRUD, reorder, and publish-toggle endpoints"
```

---

### Task 8: CourseAnnouncementController + routes

**Files:**
- Create: `app/Http/Controllers/Learn/CourseAnnouncementController.php`
- Modify: `routes/web.php` (extend `learn.*` group)
- Test: `tests/Feature/Learn/CourseAnnouncementControllerTest.php`

**Interfaces:**
- Produces routes: `learn.announcements.store`, `learn.announcements.update`, `learn.announcements.destroy`.

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

class CourseAnnouncementControllerTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;
    private User $teacher;
    private User $stranger;

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
    }

    public function test_instructor_can_post_announcement_stranger_cannot(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('learn.announcements.store', $this->course), ['title' => 'Welcome', 'body' => 'Hi class'])
            ->assertRedirect();
        $this->assertDatabaseHas('learn_course_announcements', ['title' => 'Welcome', 'posted_by' => $this->teacher->id]);

        $this->actingAs($this->stranger)
            ->post(route('learn.announcements.store', $this->course), ['title' => 'Hack', 'body' => 'x'])
            ->assertForbidden();
    }

    public function test_instructor_can_update_and_delete_announcement(): void
    {
        $announcement = $this->course->announcements()->create([
            'title' => 'Welcome', 'body' => 'Hi', 'posted_by' => $this->teacher->id, 'posted_at' => now(),
        ]);

        $this->actingAs($this->teacher)
            ->put(route('learn.announcements.update', $announcement), ['title' => 'Welcome!', 'body' => 'Hi again'])
            ->assertRedirect();
        $this->assertSame('Welcome!', $announcement->fresh()->title);

        $this->actingAs($this->teacher)
            ->delete(route('learn.announcements.destroy', $announcement))
            ->assertRedirect();
        $this->assertDatabaseMissing('learn_course_announcements', ['id' => $announcement->id]);
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseAnnouncementControllerTest.php"`
Expected: FAIL — controller/routes don't exist.

- [ ] **Step 3: Write `CourseAnnouncementController`**

`app/Http/Controllers/Learn/CourseAnnouncementController.php`:

```php
<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Course;
use App\Models\Learn\CourseAnnouncement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseAnnouncementController extends Controller
{
    /** POST /learn/{course}/announcements */
    public function store(Request $request, Course $course)
    {
        $user = Auth::user();
        abort_unless($course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $course->announcements()->create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);

        return back()->with('success', 'Announcement posted.');
    }

    /** PUT /learn/announcements/{announcement} */
    public function update(Request $request, CourseAnnouncement $announcement)
    {
        $user = Auth::user();
        abort_unless($announcement->course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);
        $announcement->update($validated);

        return back()->with('success', 'Announcement updated.');
    }

    /** DELETE /learn/announcements/{announcement} */
    public function destroy(CourseAnnouncement $announcement)
    {
        $user = Auth::user();
        abort_unless($announcement->course->canEdit($user), 403);

        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }
}
```

- [ ] **Step 4: Add routes**

Add inside the `learn.` route group, after the item routes from Task 7:

```php
    Route::post('/{course}/announcements', [\App\Http\Controllers\Learn\CourseAnnouncementController::class, 'store'])->name('announcements.store');
    Route::put('/announcements/{announcement}', [\App\Http\Controllers\Learn\CourseAnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [\App\Http\Controllers\Learn\CourseAnnouncementController::class, 'destroy'])->name('announcements.destroy');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseAnnouncementControllerTest.php"`
Expected: PASS (2 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Learn/CourseAnnouncementController.php routes/web.php \
        tests/Feature/Learn/CourseAnnouncementControllerTest.php
git commit -m "feat(learn): add course announcement CRUD endpoints"
```

---

### Task 9: Student Portal — read-only Learn views + routes

**Files:**
- Create: `app/Http/Controllers/StudentPortal/LearnController.php`
- Modify: `routes/web.php` (extend the `student.portal`-middleware block inside the `student-portal` group)
- Test: `tests/Feature/StudentPortal/LearnControllerTest.php`

**Interfaces:**
- Consumes: `Course::isVisibleToStudent()`, `CourseFileService`.
- Produces routes: `student-portal.learn.index` (GET), `student-portal.learn.file` (GET), `student-portal.learn.show` (GET).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\StudentPortal;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Models\Registrar\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LearnControllerTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private Section $section;
    private Course $course;
    private int $studentId;
    private string $studentPisaysystemID = 'PS-0001';

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
            'status' => 'published',
        ]);

        // Student model has `guarded = ['*']` (read-only elsewhere in the app) —
        // every existing test seeds the `students` table directly via DB::table(),
        // never `Student::create()`.
        $this->studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => $this->studentPisaysystemID, 'lastname' => 'Cruz', 'firstname' => 'Juan', 'sex' => 'M',
        ]);
        StudentEnrollment::create([
            'student_id' => $this->studentId, 'school_year_id' => $this->sy->id,
            'section_id' => $this->section->id, 'grade_level' => 8, 'status' => 'enrolled',
        ]);
    }

    private function loginAsStudent(): void
    {
        session(['student_pisaysystemID' => $this->studentPisaysystemID]);
    }

    public function test_enrolled_student_sees_the_published_course_in_the_index(): void
    {
        $this->loginAsStudent();

        $response = $this->get(route('student-portal.learn.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('StudentPortal/Learn/Index')
            ->has('courses', 1)
        );
    }

    public function test_draft_course_is_hidden_from_the_index(): void
    {
        $this->course->update(['status' => 'draft']);
        $this->loginAsStudent();

        $response = $this->get(route('student-portal.learn.index'));

        $response->assertInertia(fn ($page) => $page->has('courses', 0));
    }

    public function test_show_403s_when_student_is_not_enrolled_in_the_section(): void
    {
        $otherPisaysystemID = 'PS-0002';
        DB::table('students')->insert([
            'pisaysystemID' => $otherPisaysystemID, 'lastname' => 'Reyes', 'firstname' => 'Ana', 'sex' => 'F',
        ]);
        session(['student_pisaysystemID' => $otherPisaysystemID]);

        $this->get(route('student-portal.learn.show', $this->course))->assertForbidden();
    }

    public function test_show_hides_unpublished_modules_and_items_even_in_a_published_course(): void
    {
        $publishedModule = $this->course->modules()->create(['title' => 'Visible', 'position' => 0, 'published_at' => now()]);
        $this->course->modules()->create(['title' => 'Hidden', 'position' => 1]);
        $page = \App\Models\Learn\Page::create(['title' => 'Intro', 'body' => '<p>Hi</p>']);
        $page->moduleItem()->create(['learn_module_id' => $publishedModule->id, 'position' => 0, 'published_at' => now()]);
        $page2 = \App\Models\Learn\Page::create(['title' => 'Hidden item']);
        $page2->moduleItem()->create(['learn_module_id' => $publishedModule->id, 'position' => 1]);

        $this->loginAsStudent();

        $response = $this->get(route('student-portal.learn.show', $this->course));

        $response->assertInertia(fn ($page) => $page
            ->has('course.modules', 1)
            ->has('course.modules.0.items', 1)
            ->where('course.modules.0.items.0.title', 'Intro')
        );
    }
}
```

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/LearnControllerTest.php"`
Expected: FAIL — controller/routes don't exist.

- [ ] **Step 3: Write `StudentPortal\LearnController`**

`app/Http/Controllers/StudentPortal/LearnController.php`:

```php
<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Learn\Course;
use App\Models\Learn\File as LearnFile;
use App\Models\Learn\Page as LearnPage;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Services\Learn\CourseFileService;
use Inertia\Inertia;
use Inertia\Response;

class LearnController extends Controller
{
    public function __construct(private CourseFileService $files)
    {
    }

    /** GET /student-portal/learn */
    public function index(): Response
    {
        $student = $this->currentStudent();

        $enrollments = StudentEnrollment::where('student_id', $student->id)
            ->where('status', 'enrolled')
            ->get(['school_year_id', 'section_id']);

        $courses = Course::with(['subject', 'section'])
            ->where('status', 'published')
            ->where(function ($query) use ($enrollments) {
                foreach ($enrollments as $enrollment) {
                    $query->orWhere(function ($q) use ($enrollment) {
                        $q->where('school_year_id', $enrollment->school_year_id)
                          ->where('section_id', $enrollment->section_id);
                    });
                }
            })
            ->get();

        return Inertia::render('StudentPortal/Learn/Index', [
            'courses' => $courses->map(fn (Course $c) => [
                'id' => $c->id,
                'subject_name' => $c->subject->name,
                'section_name' => $c->section->sectionname,
            ])->values(),
        ]);
    }

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

        return Inertia::render('StudentPortal/Learn/Show', [
            'course' => [
                'id' => $course->id,
                'subject_name' => $course->subject->name,
                'section_name' => $course->section->sectionname,
                'syllabus_body' => $course->syllabus_body,
                'modules' => $course->modules->map(fn ($m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'items' => $m->items->map(fn ($i) => $this->serializeItem($i))->values(),
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

    /** GET /student-portal/learn/file/{fileId} */
    public function file(string $fileId)
    {
        $student = $this->currentStudent();

        $s3Key = $this->files->decodeFileId($fileId);
        abort_if(! $s3Key, 404);

        $file = LearnFile::where('s3_key', $s3Key)->firstOrFail();
        $course = $file->moduleItem?->module->course;
        abort_if(! $course, 404);
        abort_unless($course->isVisibleToStudent($student->id), 403);

        return $this->files->streamResponse($file);
    }

    private function serializeItem($item): array
    {
        $itemable = $item->itemable;

        return [
            'id' => $item->id,
            'type' => $itemable instanceof LearnPage ? 'page' : 'file',
            'title' => $itemable?->title,
            'body' => $itemable instanceof LearnPage ? $itemable->body : null,
            'video_url' => $itemable instanceof LearnPage ? $itemable->video_url : null,
            'file_url' => $itemable instanceof LearnFile
                ? route('student-portal.learn.file', ['fileId' => $this->files->encodeFileId($itemable->s3_key)])
                : null,
        ];
    }

    private function currentStudent(): Student
    {
        return Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();
    }
}
```

- [ ] **Step 4: Add routes**

Add inside the existing `Route::middleware('student.portal')->group(function () { ... })` block in `routes/web.php` (the block that already contains `/dashboard`, `/profile`, `/lost-found`, etc.):

```php
        // Must be registered before the {course} wildcard.
        Route::get('/learn/file/{fileId}', [\App\Http\Controllers\StudentPortal\LearnController::class, 'file'])
            ->name('learn.file')->where('fileId', '[a-zA-Z0-9_.=-]+');
        Route::get('/learn', [\App\Http\Controllers\StudentPortal\LearnController::class, 'index'])->name('learn.index');
        Route::get('/learn/{course}', [\App\Http\Controllers\StudentPortal\LearnController::class, 'show'])->name('learn.show');
```

- [ ] **Step 5: Run the test**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/StudentPortal/LearnControllerTest.php"`
Expected: PASS (4 tests).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/StudentPortal/LearnController.php routes/web.php \
        tests/Feature/StudentPortal/LearnControllerTest.php
git commit -m "feat(learn): add read-only Student Portal Learn views (My Courses, course detail, file proxy)"
```

---

### Task 10: Faculty Vue pages — My Courses + Course detail

**Files:**
- Create: `resources/js/Pages/Learn/Index.vue`
- Create: `resources/js/Pages/Learn/Show.vue`

**Interfaces:**
- Consumes props from `CourseController::index` (`courses: [{id, subject_name, section_name, grade_level, status, is_read_only}]`) and `CourseController::show` (`course: {id, subject_name, section_name, grade_level, status, syllabus_body, is_read_only, can_edit, modules: [{id, title, position, is_published, items: [{id, type, position, is_published, title, body, video_url, file_url}]}], announcements: [{id, title, body, posted_by, posted_at}]}`).
- Uses named routes: `learn.show`, `learn.syllabus.update`, `learn.status.update`, `learn.modules.store`, `learn.modules.update`, `learn.modules.publish`, `learn.modules.reorder`, `learn.modules.destroy`, `learn.items.store-page`, `learn.items.store-file`, `learn.items.publish`, `learn.items.reorder`, `learn.items.destroy`, `learn.announcements.store`, `learn.announcements.update`, `learn.announcements.destroy`.

- [ ] **Step 1: Write `Learn/Index.vue`**

`resources/js/Pages/Learn/Index.vue`:

```vue
<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import { BookOpenIcon, LockClosedIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ courses: Array })

function statusBadgeClass(status) {
  return status === 'published'
    ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
    : 'bg-slate-100 text-slate-600 border-slate-200'
}
</script>

<template>
  <Head title="Learn — My Courses" />
  <AdminLayout title="Learn">
    <div class="max-w-5xl mx-auto py-6 px-4">
      <h1 class="text-lg font-semibold text-slate-800 mb-4">My Courses</h1>

      <div v-if="courses.length === 0" class="text-sm text-slate-500 border border-dashed border-slate-200 rounded-lg p-8 text-center">
        No courses yet — courses appear automatically once you have a teaching load for the current school year.
      </div>

      <div v-else class="grid gap-3 sm:grid-cols-2">
        <Link
          v-for="course in courses"
          :key="course.id"
          :href="route('learn.show', course.id)"
          class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white p-4 hover:border-indigo-300 hover:shadow-sm transition"
        >
          <BookOpenIcon class="h-6 w-6 text-indigo-600 shrink-0" />
          <div class="min-w-0">
            <p class="text-sm font-medium text-slate-800 truncate">{{ course.subject_name }}</p>
            <p class="text-xs text-slate-500">Grade {{ course.grade_level }} — {{ course.section_name }}</p>
            <div class="mt-2 flex items-center gap-2">
              <span :class="['inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-medium', statusBadgeClass(course.status)]">
                {{ course.status === 'published' ? 'Published' : 'Draft' }}
              </span>
              <span v-if="course.is_read_only" class="inline-flex items-center gap-1 text-xs text-slate-400">
                <LockClosedIcon class="h-3.5 w-3.5" /> Read-only
              </span>
            </div>
          </div>
        </Link>
      </div>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 2: Write `Learn/Show.vue`**

`resources/js/Pages/Learn/Show.vue`:

```vue
<script setup>
import { ref } from 'vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import RichTextEditor from '@/Components/RichTextEditor.vue'
import {
  PlusIcon, TrashIcon, EyeIcon, EyeSlashIcon,
  ArrowUpIcon, ArrowDownIcon, DocumentIcon, PaperClipIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({ course: Object })

// ── Syllabus ──────────────────────────────────────────────────────────────
const syllabus = ref(props.course.syllabus_body || '')
function saveSyllabus() {
  router.put(route('learn.syllabus.update', props.course.id), { syllabus_body: syllabus.value }, { preserveScroll: true })
}

// ── Publish toggle ───────────────────────────────────────────────────────
function toggleCourseStatus() {
  const next = props.course.status === 'published' ? 'draft' : 'published'
  router.patch(route('learn.status.update', props.course.id), { status: next }, { preserveScroll: true })
}

// ── Modules ──────────────────────────────────────────────────────────────
const newModuleTitle = ref('')
function addModule() {
  if (! newModuleTitle.value.trim()) return
  router.post(route('learn.modules.store', props.course.id), { title: newModuleTitle.value }, {
    preserveScroll: true,
    onSuccess: () => { newModuleTitle.value = '' },
  })
}
function toggleModulePublish(moduleId) {
  router.patch(route('learn.modules.publish', moduleId), {}, { preserveScroll: true })
}
function deleteModule(moduleId) {
  router.delete(route('learn.modules.destroy', moduleId), { preserveScroll: true })
}
function moveModule(index, direction) {
  const ids = props.course.modules.map(m => m.id)
  const target = index + direction
  if (target < 0 || target >= ids.length) return
  ;[ids[index], ids[target]] = [ids[target], ids[index]]
  router.put(route('learn.modules.reorder', props.course.id), { module_ids: ids }, { preserveScroll: true })
}

// ── Module items ─────────────────────────────────────────────────────────
const pageForms = ref({})
function pageForm(moduleId) {
  if (! pageForms.value[moduleId]) {
    pageForms.value[moduleId] = useForm({ title: '', body: '', video_url: '' })
  }
  return pageForms.value[moduleId]
}
function addPage(moduleId) {
  pageForm(moduleId).post(route('learn.items.store-page', moduleId), {
    preserveScroll: true,
    onSuccess: () => pageForm(moduleId).reset(),
  })
}

function readFileAsBase64(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(reader.result)
    reader.onerror = reject
    reader.readAsDataURL(file)
  })
}

const fileTitles = ref({})
async function addFile(moduleId, event) {
  const file = event.target.files[0]
  if (! file) return
  const base64 = await readFileAsBase64(file)
  router.post(route('learn.items.store-file', moduleId), {
    title: fileTitles.value[moduleId] || file.name,
    file_base64: base64,
  }, { preserveScroll: true, onSuccess: () => { fileTitles.value[moduleId] = '' } })
  event.target.value = ''
}

function toggleItemPublish(itemId) {
  router.patch(route('learn.items.publish', itemId), {}, { preserveScroll: true })
}
function deleteItem(itemId) {
  router.delete(route('learn.items.destroy', itemId), { preserveScroll: true })
}
function moveItem(module, index, direction) {
  const ids = module.items.map(i => i.id)
  const target = index + direction
  if (target < 0 || target >= ids.length) return
  ;[ids[index], ids[target]] = [ids[target], ids[index]]
  router.put(route('learn.items.reorder', module.id), { item_ids: ids }, { preserveScroll: true })
}

// ── Announcements ────────────────────────────────────────────────────────
const announcementForm = useForm({ title: '', body: '' })
function postAnnouncement() {
  announcementForm.post(route('learn.announcements.store', props.course.id), {
    preserveScroll: true,
    onSuccess: () => announcementForm.reset(),
  })
}
function deleteAnnouncement(id) {
  router.delete(route('learn.announcements.destroy', id), { preserveScroll: true })
}
</script>

<template>
  <Head :title="`Learn — ${course.subject_name}`" />
  <AdminLayout :title="course.subject_name">
    <div class="max-w-4xl mx-auto py-6 px-4 space-y-8">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-lg font-semibold text-slate-800">{{ course.subject_name }}</h1>
          <p class="text-sm text-slate-500">Grade {{ course.grade_level }} — {{ course.section_name }}</p>
        </div>
        <button
          v-if="course.can_edit"
          @click="toggleCourseStatus"
          class="rounded-lg px-4 py-2 text-sm font-medium"
          :class="course.status === 'published' ? 'bg-slate-100 text-slate-700' : 'bg-indigo-600 hover:bg-indigo-700 text-white'"
        >
          {{ course.status === 'published' ? 'Unpublish' : 'Publish course' }}
        </button>
      </div>

      <p v-if="course.is_read_only" class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
        This course is from a past school year and is read-only.
      </p>

      <!-- Syllabus -->
      <section>
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Syllabus</h2>
        <RichTextEditor v-if="course.can_edit" v-model="syllabus" />
        <div v-else class="prose prose-sm max-w-none" v-html="course.syllabus_body || '<p class=\'text-slate-400\'>No syllabus yet.</p>'" />
        <button v-if="course.can_edit" @click="saveSyllabus" class="mt-2 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">
          Save syllabus
        </button>
      </section>

      <!-- Modules -->
      <section>
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Modules</h2>

        <div v-for="(module, index) in course.modules" :key="module.id" class="border border-slate-200 rounded-lg mb-3">
          <div class="flex items-center justify-between px-4 py-3 bg-slate-50 rounded-t-lg">
            <span class="text-sm font-medium text-slate-800">{{ module.title }}</span>
            <div v-if="course.can_edit" class="flex items-center gap-1">
              <button @click="moveModule(index, -1)" class="p-1 text-slate-400 hover:text-slate-700"><ArrowUpIcon class="h-4 w-4" /></button>
              <button @click="moveModule(index, 1)" class="p-1 text-slate-400 hover:text-slate-700"><ArrowDownIcon class="h-4 w-4" /></button>
              <button @click="toggleModulePublish(module.id)" class="p-1 text-slate-400 hover:text-slate-700">
                <EyeIcon v-if="!module.is_published" class="h-4 w-4" />
                <EyeSlashIcon v-else class="h-4 w-4" />
              </button>
              <button @click="deleteModule(module.id)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
            </div>
          </div>

          <div class="p-4 space-y-3">
            <div v-for="(item, itemIndex) in module.items" :key="item.id" class="flex items-start gap-2 border border-slate-100 rounded-lg p-3">
              <DocumentIcon v-if="item.type === 'page'" class="h-5 w-5 text-slate-400 shrink-0" />
              <PaperClipIcon v-else class="h-5 w-5 text-slate-400 shrink-0" />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-slate-700">{{ item.title }}</p>
                <div v-if="item.type === 'page' && item.body" class="prose prose-sm max-w-none mt-1" v-html="item.body" />
                <a v-if="item.type === 'page' && item.video_url" :href="item.video_url" target="_blank" class="text-xs text-indigo-600 underline">Watch video</a>
                <a v-if="item.type === 'file'" :href="item.file_url" target="_blank" class="text-xs text-indigo-600 underline">Download file</a>
              </div>
              <div v-if="course.can_edit" class="flex items-center gap-1 shrink-0">
                <button @click="moveItem(module, itemIndex, -1)" class="p-1 text-slate-400 hover:text-slate-700"><ArrowUpIcon class="h-3.5 w-3.5" /></button>
                <button @click="moveItem(module, itemIndex, 1)" class="p-1 text-slate-400 hover:text-slate-700"><ArrowDownIcon class="h-3.5 w-3.5" /></button>
                <button @click="toggleItemPublish(item.id)" class="p-1 text-slate-400 hover:text-slate-700">
                  <EyeIcon v-if="!item.is_published" class="h-3.5 w-3.5" />
                  <EyeSlashIcon v-else class="h-3.5 w-3.5" />
                </button>
                <button @click="deleteItem(item.id)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-3.5 w-3.5" /></button>
              </div>
            </div>

            <div v-if="course.can_edit" class="border-t border-slate-100 pt-3 space-y-2">
              <div class="flex gap-2">
                <input v-model="pageForm(module.id).title" placeholder="Page title" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
                <button @click="addPage(module.id)" class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium">Add page</button>
              </div>
              <textarea v-model="pageForm(module.id).body" placeholder="Page body (optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="2" />
              <input v-model="pageForm(module.id).video_url" placeholder="Video URL (YouTube/Drive, optional)" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />

              <div class="flex gap-2 items-center">
                <input v-model="fileTitles[module.id]" placeholder="File title" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
                <label class="bg-slate-100 hover:bg-slate-200 text-slate-700 px-3 py-2 rounded-lg text-sm font-medium cursor-pointer">
                  Upload file
                  <input type="file" class="hidden" @change="e => addFile(module.id, e)" />
                </label>
              </div>
            </div>
          </div>
        </div>

        <div v-if="course.can_edit" class="flex gap-2">
          <input v-model="newModuleTitle" placeholder="New module title" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm flex-1" />
          <button @click="addModule" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium flex items-center gap-1">
            <PlusIcon class="h-4 w-4" /> Add module
          </button>
        </div>
      </section>

      <!-- Announcements -->
      <section>
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Announcements</h2>

        <div v-for="announcement in course.announcements" :key="announcement.id" class="border border-slate-200 rounded-lg p-4 mb-2">
          <div class="flex items-start justify-between">
            <div>
              <p class="text-sm font-medium text-slate-800">{{ announcement.title }}</p>
              <p class="text-xs text-slate-500">{{ announcement.posted_by }} — {{ new Date(announcement.posted_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
            </div>
            <button v-if="course.can_edit" @click="deleteAnnouncement(announcement.id)" class="p-1 text-red-400 hover:text-red-600"><TrashIcon class="h-4 w-4" /></button>
          </div>
          <p class="text-sm text-slate-600 mt-2 whitespace-pre-line">{{ announcement.body }}</p>
        </div>

        <div v-if="course.can_edit" class="border border-slate-200 rounded-lg p-4 space-y-2">
          <input v-model="announcementForm.title" placeholder="Announcement title" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" />
          <textarea v-model="announcementForm.body" placeholder="Announcement body" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm w-full" rows="3" />
          <button @click="postAnnouncement" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Post announcement</button>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 3: Build frontend assets and verify no compile errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing `Learn/Index.vue` or `Learn/Show.vue`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Learn/Index.vue resources/js/Pages/Learn/Show.vue
git commit -m "feat(learn): add faculty My Courses and course detail Vue pages"
```

---

### Task 11: Student Portal Vue pages — My Courses + Course detail (read-only)

**Files:**
- Create: `resources/js/Pages/StudentPortal/Learn/Index.vue`
- Create: `resources/js/Pages/StudentPortal/Learn/Show.vue`

**Interfaces:**
- Consumes props from `StudentPortal\LearnController::index` (`courses: [{id, subject_name, section_name}]`) and `::show` (`course: {id, subject_name, section_name, syllabus_body, modules: [{id, title, items: [{id, type, title, body, video_url, file_url}]}], announcements: [{title, body, posted_by, posted_at}]}`).
- Uses named routes: `student-portal.learn.index`, `student-portal.learn.show`.

- [ ] **Step 1: Write `StudentPortal/Learn/Index.vue`**

`resources/js/Pages/StudentPortal/Learn/Index.vue`:

```vue
<script setup>
import { Head, Link } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import { BookOpenIcon } from '@heroicons/vue/24/outline'

defineProps({ courses: Array })
</script>

<template>
  <Head title="My Courses" />
  <StudentPortalLayout>
    <div class="max-w-3xl mx-auto py-6 px-4">
      <h1 class="text-lg font-semibold text-slate-800 mb-4">My Courses</h1>

      <div v-if="courses.length === 0" class="text-sm text-slate-500 border border-dashed border-slate-200 rounded-lg p-8 text-center">
        No published courses yet.
      </div>

      <div v-else class="grid gap-3 sm:grid-cols-2">
        <Link
          v-for="course in courses"
          :key="course.id"
          :href="route('student-portal.learn.show', course.id)"
          class="flex items-start gap-3 rounded-lg border border-slate-200 bg-white p-4 hover:border-indigo-300 hover:shadow-sm transition"
        >
          <BookOpenIcon class="h-6 w-6 text-indigo-600 shrink-0" />
          <div class="min-w-0">
            <p class="text-sm font-medium text-slate-800 truncate">{{ course.subject_name }}</p>
            <p class="text-xs text-slate-500">{{ course.section_name }}</p>
          </div>
        </Link>
      </div>
    </div>
  </StudentPortalLayout>
</template>
```

- [ ] **Step 2: Write `StudentPortal/Learn/Show.vue`**

`resources/js/Pages/StudentPortal/Learn/Show.vue`:

```vue
<script setup>
import { Head } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import { DocumentIcon, PaperClipIcon } from '@heroicons/vue/24/outline'

defineProps({ course: Object })
</script>

<template>
  <Head :title="course.subject_name" />
  <StudentPortalLayout>
    <div class="max-w-3xl mx-auto py-6 px-4 space-y-8">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">{{ course.subject_name }}</h1>
        <p class="text-sm text-slate-500">{{ course.section_name }}</p>
      </div>

      <section v-if="course.syllabus_body">
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Syllabus</h2>
        <div class="prose prose-sm max-w-none" v-html="course.syllabus_body" />
      </section>

      <section>
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Modules</h2>
        <div v-for="module in course.modules" :key="module.id" class="border border-slate-200 rounded-lg mb-3">
          <div class="px-4 py-3 bg-slate-50 rounded-t-lg text-sm font-medium text-slate-800">{{ module.title }}</div>
          <div class="p-4 space-y-3">
            <div v-for="item in module.items" :key="item.id" class="flex items-start gap-2 border border-slate-100 rounded-lg p-3">
              <DocumentIcon v-if="item.type === 'page'" class="h-5 w-5 text-slate-400 shrink-0" />
              <PaperClipIcon v-else class="h-5 w-5 text-slate-400 shrink-0" />
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-slate-700">{{ item.title }}</p>
                <div v-if="item.type === 'page' && item.body" class="prose prose-sm max-w-none mt-1" v-html="item.body" />
                <a v-if="item.type === 'page' && item.video_url" :href="item.video_url" target="_blank" class="text-xs text-indigo-600 underline">Watch video</a>
                <a v-if="item.type === 'file'" :href="item.file_url" target="_blank" class="text-xs text-indigo-600 underline">Download file</a>
              </div>
            </div>
            <p v-if="module.items.length === 0" class="text-xs text-slate-400">No content yet.</p>
          </div>
        </div>
      </section>

      <section v-if="course.announcements.length">
        <h2 class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Announcements</h2>
        <div v-for="(announcement, i) in course.announcements" :key="i" class="border border-slate-200 rounded-lg p-4 mb-2">
          <p class="text-sm font-medium text-slate-800">{{ announcement.title }}</p>
          <p class="text-xs text-slate-500">{{ announcement.posted_by }} — {{ new Date(announcement.posted_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
          <p class="text-sm text-slate-600 mt-2 whitespace-pre-line">{{ announcement.body }}</p>
        </div>
      </section>
    </div>
  </StudentPortalLayout>
</template>
```

- [ ] **Step 3: Build frontend assets and verify no compile errors**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npm run build`
Expected: build succeeds with no errors referencing `StudentPortal/Learn/Index.vue` or `StudentPortal/Learn/Show.vue`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/StudentPortal/Learn/Index.vue resources/js/Pages/StudentPortal/Learn/Show.vue
git commit -m "feat(learn): add read-only Student Portal My Courses and course detail Vue pages"
```

---

### Task 12: Full backend test suite + manual end-to-end browser verification

**Files:** none created — verification only.

- [ ] **Step 1: Run the full Learn test suite**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=Learn"`
Expected: all Learn + StudentPortal/LearnControllerTest tests pass (should total ~38 tests across Tasks 1–9).

- [ ] **Step 2: Run the full project test suite to check for regressions**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test"`
Expected: no failures outside what was already failing before this change (compare against a baseline run if the suite has pre-existing failures unrelated to Learn).

- [ ] **Step 3: Seed permissions in dev**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan db:seed --class=Database\\\\Seeders\\\\LearnPermissionSeeder"`
Expected: `learn.course.view.all` and `learn.admin` permissions exist and are granted to Administrator/AUH/CID Chief.

- [ ] **Step 4: Manual browser verification — golden path**

Start the dev server (nginx/php containers already running via `docker compose up`), then in a browser:

1. Log in as a faculty member with a current-SY teaching `LoadAssignment`. Visit `/learn` — confirm the course appears (auto-created).
2. Open the course, add a module, add a Page item (with body text) and a File item (upload a small PDF/image), post an announcement.
3. Confirm the file downloads correctly from its proxy URL and is **not** a public S3 URL.
4. Toggle the module published, toggle the course status to Published.
5. Log in to the Student Portal as a student enrolled in that course's section for the current SY. Visit "My Courses" — confirm the course appears, open it, confirm the published module/items and announcement are visible, syllabus renders, and the file downloads.
6. As a different student (or same student before enrollment), confirm the course does **not** appear and direct navigation to its URL 403s.
7. As a faculty member with no teaching assignment for that course, confirm `/learn/{course}` 403s.
8. Flip the school year's `is_current` to false (or use a past-SY fixture) and confirm the course becomes read-only — edit actions 403 for the instructor, but the course is still viewable.

- [ ] **Step 5: Report results**

Note any issues found during manual verification; fix and re-verify before considering Phase 1 complete. Do not commit for this task — it is verification only.

---

## Phase 1 Complete — Next Steps

Once all 12 tasks pass, Learn Phase 1 (Course Shell + Content) is done. Phases 2 (Assignments + submissions), 3 (Quizzes), and 4 (Discussions) each need their own `superpowers:brainstorming` → design → plan cycle before implementation, per the roadmap in `docs/superpowers/specs/2026-08-08-learn-module-phase1-design.md`.
