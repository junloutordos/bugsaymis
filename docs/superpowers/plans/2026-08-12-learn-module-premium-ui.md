# Learn Module Premium UI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign the Learn module (faculty `Learn/Index.vue` + `Learn/Show.vue`, and their read-only `StudentPortal/Learn/*` mirror) to the app's "Atlas" premium visual standard: cover photos on class cards, a course setup-progress tracker, and a tabbed Show page (Overview / Modules / Announcements) replacing one long scroll.

**Architecture:** Two nullable columns on `learn_courses` (`cover_photo_s3_key`, `cover_preset`) store cover state; a new `CourseCoverService` (mirrors the existing `CourseFileService` base64→S3→private-proxy pattern) handles upload/preset-select/stream; `Course::setupProgress()` computes a 4-step completion checklist read-only. Frontend gets 2 new shared components (`CourseCover.vue`, `SetupProgressBar.vue`) and one small additive extension to `AppPageHeader.vue`. All Learn business logic (module/quiz/assignment/discussion authoring) is untouched — the Show.vue changes are a structural/visual re-skin only.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>`, Inertia.js 2, Tailwind CSS 3, `Storage::disk('s3')`.

## Global Constraints

- Never use `Storage::disk('public')` — S3 Block Public Access silently drops that ACL. Cover photos are private, served through a proxy route, exactly like WFH photos and existing Learn course files.
- File uploads go as base64 JSON (`photo_base64` in the request body), never `multipart/form-data` — Cloudflare WAF blocks multipart uploads on this app.
- Migrations must be additive-only (this ships via the blue-green pre-deploy migration step): both new columns are `nullable()`, no existing column changes.
- Never use `git add -A` / `git add .` — stage files by name. Never `--no-verify`. Only commit when the step below says to.
- Vue files in this project are plain JS — no TypeScript, no `@ts-check`.
- No JS test runner is configured in this project (`package.json` has no `test` script, no Vitest/Jest) — frontend verification is `npx vite build` (compile-safety) plus manual browser checks, not automated tests.
- PHP tests use `RefreshDatabase` and create fixtures via `Model::create()` (not factories, except `User::factory()`) — follow the exact setUp pattern already in `tests/Feature/Learn/CourseControllerTest.php`.

---

## File Structure

**Backend — new:**
- `database/migrations/2026_08_12_150000_add_cover_and_progress_fields_to_learn_courses_table.php` — 2 nullable columns
- `app/Services/Learn/CourseCoverService.php` — upload/setPreset/streamResponse, mirrors `CourseFileService`
- `tests/Feature/Learn/CourseCoverServiceTest.php`

**Backend — modified:**
- `app/Models/Learn/Course.php` — add 2 columns to `$fillable`, add `setupProgress(): array`
- `app/Http/Controllers/Learn/CourseController.php` — inject `CourseCoverService`, add `updateCover()`/`cover()` actions, wire cover/progress fields into `index()`/`show()` payloads
- `app/Http/Controllers/StudentPortal/LearnController.php` — inject `CourseCoverService`, add `cover()` action, wire cover fields into `index()`/`show()` payloads
- `routes/web.php` — 2 new routes under the `learn.` group, 1 new route under `student-portal.` group
- `tests/Feature/Learn/CourseControllerTest.php` — new test methods for cover endpoints + payload fields
- `tests/Feature/StudentPortal/LearnControllerTest.php` — new test methods for the student-side cover endpoint + payload fields

**Frontend — new:**
- `resources/js/Constants/courseCoverPresets.js` — 6 curated gradient presets
- `resources/js/Components/CourseCover.vue` — renders uploaded photo OR preset gradient + subject initials
- `resources/js/Components/SetupProgressBar.vue` — 4-step tracker with connecting progress bar

**Frontend — modified:**
- `resources/js/Components/AppPageHeader.vue` — additive optional `#cover` slot (backward-compatible; used by 190+ existing pages with `hero`, verified none currently use a `#cover` slot)
- `resources/js/Pages/Learn/Index.vue` — full rewrite: cover-banner card grid, setup-progress sliver
- `resources/js/Pages/Learn/Show.vue` — full rewrite: hero cover banner, `AppTabs` (Overview/Modules/Announcements), all sections re-skinned with `App*` components — `<script setup>` business logic (every `router.post/put/delete` call, every form ref) stays byte-identical
- `resources/js/Pages/StudentPortal/Learn/Index.vue` — full rewrite: cover-banner card grid (read-only, no progress sliver)
- `resources/js/Pages/StudentPortal/Learn/Show.vue` — cover banner header + `AppCard` re-skin, no tab restructuring (content stays linear)

---

### Task 1: Migration — cover and progress fields on `learn_courses`

**Files:**
- Create: `database/migrations/2026_08_12_150000_add_cover_and_progress_fields_to_learn_courses_table.php`

**Interfaces:**
- Produces: `learn_courses.cover_photo_s3_key` (nullable string), `learn_courses.cover_preset` (nullable string) — consumed by Task 2 (`Course` model) and Task 3 (`CourseCoverService`)

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
        Schema::table('learn_courses', function (Blueprint $table) {
            $table->string('cover_photo_s3_key')->nullable()->after('syllabus_body');
            $table->string('cover_preset')->nullable()->after('cover_photo_s3_key');
        });
    }

    public function down(): void
    {
        Schema::table('learn_courses', function (Blueprint $table) {
            $table->dropColumn(['cover_photo_s3_key', 'cover_preset']);
        });
    }
};
```

- [ ] **Step 2: Run the migration in dev**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_12_150000_add_cover_and_progress_fields_to_learn_courses_table.php"`
Expected: `Migrating: ... Migrated:` for the new file, no errors.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_08_12_150000_add_cover_and_progress_fields_to_learn_courses_table.php
git commit -m "feat(learn): add cover photo and progress columns to learn_courses"
```

---

### Task 2: `Course` model — fillable + `setupProgress()`

**Files:**
- Modify: `app/Models/Learn/Course.php`
- Test: `tests/Feature/Learn/CourseControllerTest.php`

**Interfaces:**
- Consumes: `learn_courses.cover_photo_s3_key`, `learn_courses.cover_preset` (Task 1)
- Produces: `Course::setupProgress(): array` returning `['steps' => [['key','label','complete'], ...], 'percent' => int]` — consumed by Task 6 (`CourseController::show()`/`index()` payload)

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/Learn/CourseControllerTest.php` (inside the existing class, after `test_past_school_year_course_cannot_be_edited_even_by_its_instructor`):

```php
    public function test_setup_progress_reports_each_step_and_overall_percent(): void
    {
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        $progress = $course->setupProgress();
        $this->assertSame(0, $progress['percent']);
        $this->assertFalse(collect($progress['steps'])->firstWhere('key', 'syllabus')['complete']);

        $course->update(['syllabus_body' => '<p>Welcome</p>', 'status' => 'published']);
        $module = $course->modules()->create(['title' => 'Week 1', 'position' => 1]);

        $progress = $course->fresh()->setupProgress();
        $this->assertSame(50, $progress['percent']); // syllabus + published, no module content yet
        $this->assertTrue(collect($progress['steps'])->firstWhere('key', 'modules')['complete']);
        $this->assertFalse(collect($progress['steps'])->firstWhere('key', 'content')['complete']);

        $module->items()->create(['itemable_type' => \App\Models\Learn\Page::class, 'itemable_id' => 1, 'position' => 1]);

        $progress = $course->fresh()->load('modules.items')->setupProgress();
        $this->assertSame(100, $progress['percent']);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_setup_progress_reports_each_step_and_overall_percent"`
Expected: FAIL — `Call to undefined method App\Models\Learn\Course::setupProgress()`

- [ ] **Step 3: Add `setupProgress()` to the model**

In `app/Models/Learn/Course.php`, update `$fillable` and add the new method (after `isReadOnly()`):

```php
    protected $fillable = [
        'subject_id', 'section_id', 'school_year_id', 'academic_term_id',
        'status', 'syllabus_body', 'cover_photo_s3_key', 'cover_preset',
    ];
```

```php
    /** @return array{steps: array<int, array{key: string, label: string, complete: bool}>, percent: int} */
    public function setupProgress(): array
    {
        $steps = [
            ['key' => 'syllabus', 'label' => 'Write a syllabus', 'complete' => filled($this->syllabus_body)],
            ['key' => 'modules', 'label' => 'Add a module', 'complete' => $this->modules->isNotEmpty()],
            ['key' => 'content', 'label' => 'Add content to a module', 'complete' => $this->modules->contains(fn ($m) => $m->items->isNotEmpty())],
            ['key' => 'published', 'label' => 'Publish the course', 'complete' => $this->status === 'published'],
        ];

        return [
            'steps' => $steps,
            'percent' => (int) round(collect($steps)->where('complete', true)->count() / count($steps) * 100),
        ];
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_setup_progress_reports_each_step_and_overall_percent"`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Models/Learn/Course.php tests/Feature/Learn/CourseControllerTest.php
git commit -m "feat(learn): add Course::setupProgress() completion tracker"
```

---

### Task 3: `CourseCoverService`

**Files:**
- Create: `app/Services/Learn/CourseCoverService.php`
- Test: `tests/Feature/Learn/CourseCoverServiceTest.php`

**Interfaces:**
- Consumes: `Course` model (Task 1/2), `Storage::disk('s3')`
- Produces: `CourseCoverService::upload(Course $course, string $dataUri): void`, `::setPreset(Course $course, string $presetKey): void`, `::streamResponse(Course $course): \Illuminate\Http\Response`, `CourseCoverService::PRESET_KEYS` (array constant) — consumed by Task 4 (`CourseController`), Task 5 (`StudentPortal\LearnController`)

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Learn;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Subject;
use App\Models\Learn\Course;
use App\Services\Learn\CourseCoverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CourseCoverServiceTest extends TestCase
{
    use RefreshDatabase;

    private Course $course;

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
            'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
        ]);
    }

    public function test_upload_decodes_base64_stores_to_s3_and_clears_any_preset(): void
    {
        $this->course->update(['cover_preset' => 'sky-wave']);
        $service = app(CourseCoverService::class);
        $dataUri = 'data:image/png;base64,' . base64_encode('fake png bytes');

        $service->upload($this->course, $dataUri);

        $this->course->refresh();
        Storage::disk('s3')->assertExists($this->course->cover_photo_s3_key);
        $this->assertStringStartsWith("Learn/{$this->course->id}/cover-", $this->course->cover_photo_s3_key);
        $this->assertNull($this->course->cover_preset);
    }

    public function test_upload_rejects_non_image_mime_types(): void
    {
        $service = app(CourseCoverService::class);

        foreach (['text/html', 'application/pdf', 'image/svg+xml'] as $mime) {
            $dataUri = "data:{$mime};base64," . base64_encode('<script>alert(1)</script>');

            try {
                $service->upload($this->course, $dataUri);
                $this->fail("Expected upload of {$mime} to be rejected.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('photo', $e->errors());
            }
        }
    }

    public function test_upload_deletes_the_previous_cover_object(): void
    {
        $service = app(CourseCoverService::class);
        $service->upload($this->course, 'data:image/png;base64,' . base64_encode('first'));
        $firstKey = $this->course->refresh()->cover_photo_s3_key;

        $service->upload($this->course, 'data:image/png;base64,' . base64_encode('second'));

        Storage::disk('s3')->assertMissing($firstKey);
    }

    public function test_set_preset_stores_the_key_and_clears_any_photo(): void
    {
        $service = app(CourseCoverService::class);
        $service->upload($this->course, 'data:image/png;base64,' . base64_encode('photo'));
        $photoKey = $this->course->refresh()->cover_photo_s3_key;

        $service->setPreset($this->course, 'ocean-deep');

        $this->course->refresh();
        $this->assertSame('ocean-deep', $this->course->cover_preset);
        $this->assertNull($this->course->cover_photo_s3_key);
        Storage::disk('s3')->assertMissing($photoKey);
    }

    public function test_set_preset_rejects_an_unknown_preset_key(): void
    {
        $service = app(CourseCoverService::class);

        $this->expectException(ValidationException::class);
        $service->setPreset($this->course, 'not-a-real-preset');
    }

    public function test_stream_response_serves_the_photo_bytes(): void
    {
        $service = app(CourseCoverService::class);
        $service->upload($this->course, 'data:image/jpeg;base64,' . base64_encode('jpeg bytes'));

        $response = $service->streamResponse($this->course->fresh());

        $this->assertSame('jpeg bytes', $response->getContent());
        $this->assertSame('image/jpeg', $response->headers->get('Content-Type'));
    }

    public function test_stream_response_404s_when_no_cover_photo_is_set(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        app(CourseCoverService::class)->streamResponse($this->course);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseCoverServiceTest.php"`
Expected: FAIL — `Class "App\Services\Learn\CourseCoverService" not found`

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services\Learn;

use App\Models\Learn\Course;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Base64 → S3 upload and private-proxy serving for course cover photos.
 * Same encoding as WFH photos and Learn course files (CourseFileService):
 * Storage::disk('s3') only, never disk('public') — S3 Block Public Access
 * silently drops that ACL.
 *
 * PRESET_KEYS must stay in sync with resources/js/Constants/courseCoverPresets.js —
 * the preset's visual definition (gradient class) lives entirely in the frontend,
 * this list only guards against storing a garbage key.
 */
class CourseCoverService
{
    public const PRESET_KEYS = [
        'indigo-diagonal', 'sky-wave', 'navy-radial', 'slate-grid', 'indigo-sunrise', 'ocean-deep',
    ];

    private const ALLOWED_MIME = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    public function upload(Course $course, string $dataUri): void
    {
        if (str_contains($dataUri, ',')) {
            [$meta, $base64] = explode(',', $dataUri, 2);
        } else {
            $meta = '';
            $base64 = $dataUri;
        }

        $binary = base64_decode($base64, true);
        if ($binary === false) {
            throw ValidationException::withMessages(['photo' => 'Invalid image data.']);
        }

        $mime = $this->mimeFromMeta($meta);
        $extension = $mime ? (self::ALLOWED_MIME[$mime] ?? null) : null;

        // Reject anything outside the image allowlist — an unrestricted MIME
        // (e.g. text/html, image/svg+xml) served inline on this app's origin
        // would be a stored-XSS vector.
        if ($extension === null) {
            throw ValidationException::withMessages(['photo' => 'Unsupported image type. Use PNG, JPEG, or WebP.']);
        }

        $this->deleteExisting($course);

        $s3Key = "Learn/{$course->id}/cover-" . uniqid() . ".{$extension}";
        Storage::disk('s3')->put($s3Key, $binary);

        $course->update(['cover_photo_s3_key' => $s3Key, 'cover_preset' => null]);
    }

    public function setPreset(Course $course, string $presetKey): void
    {
        if (! in_array($presetKey, self::PRESET_KEYS, true)) {
            throw ValidationException::withMessages(['preset' => 'Unknown cover preset.']);
        }

        $this->deleteExisting($course);

        $course->update(['cover_preset' => $presetKey, 'cover_photo_s3_key' => null]);
    }

    public function streamResponse(Course $course): Response
    {
        abort_if(! $course->cover_photo_s3_key, 404);
        abort_if(! Storage::disk('s3')->exists($course->cover_photo_s3_key), 404);

        return response(Storage::disk('s3')->get($course->cover_photo_s3_key), 200)
            ->header('Content-Type', $this->mimeFromExtension($course->cover_photo_s3_key))
            ->header('X-Content-Type-Options', 'nosniff');
    }

    private function deleteExisting(Course $course): void
    {
        if ($course->cover_photo_s3_key) {
            Storage::disk('s3')->delete($course->cover_photo_s3_key);
        }
    }

    private function mimeFromMeta(string $meta): ?string
    {
        if (preg_match('/^data:([a-zA-Z0-9\/\+\.\-]+);base64$/', $meta, $m)) {
            return $m[1];
        }

        return null;
    }

    private function mimeFromExtension(string $s3Key): string
    {
        return match (pathinfo($s3Key, PATHINFO_EXTENSION)) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn/CourseCoverServiceTest.php"`
Expected: PASS (7 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Learn/CourseCoverService.php tests/Feature/Learn/CourseCoverServiceTest.php
git commit -m "feat(learn): add CourseCoverService for cover photo upload/preset/streaming"
```

---

### Task 4: Faculty cover routes + `CourseController` actions

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Learn/CourseController.php`
- Test: `tests/Feature/Learn/CourseControllerTest.php`

**Interfaces:**
- Consumes: `CourseCoverService` (Task 3)
- Produces: routes `learn.cover.update` (PUT `/learn/{course}/cover`), `learn.cover.show` (GET `/learn/{course}/cover`) — consumed by Task 13 (`Learn/Show.vue`), Task 12 (`Learn/Index.vue`)

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Learn/CourseControllerTest.php`:

```php
    public function test_instructor_can_set_a_cover_preset_but_stranger_cannot(): void
    {
        $teacher = User::factory()->create();
        $stranger = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        $this->actingAs($teacher)
            ->put(route('learn.cover.update', $course), ['preset' => 'sky-wave'])
            ->assertRedirect();
        $this->assertSame('sky-wave', $course->fresh()->cover_preset);

        $this->actingAs($stranger)
            ->put(route('learn.cover.update', $course), ['preset' => 'ocean-deep'])
            ->assertForbidden();
    }

    public function test_instructor_can_upload_a_cover_photo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);
        $dataUri = 'data:image/png;base64,' . base64_encode('fake png bytes');

        $this->actingAs($teacher)
            ->put(route('learn.cover.update', $course), ['photo_base64' => $dataUri])
            ->assertRedirect();

        $this->assertNotNull($course->fresh()->cover_photo_s3_key);
    }

    public function test_cover_proxy_streams_the_photo_for_a_viewer_but_403s_a_stranger(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        $teacher = User::factory()->create();
        $stranger = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);
        app(\App\Services\Learn\CourseCoverService::class)->upload($course, 'data:image/png;base64,' . base64_encode('bytes'));

        $this->actingAs($teacher)->get(route('learn.cover.show', $course))->assertOk();
        $this->actingAs($stranger)->get(route('learn.cover.show', $course))->assertForbidden();
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CourseControllerTest"`
Expected: FAIL — `Route [learn.cover.update] not defined.`

- [ ] **Step 3: Add the routes**

In `routes/web.php`, inside the `learn.` group (right after the existing `status.update` line, currently line 2555):

```php
    Route::put('/{course}/syllabus', [\App\Http\Controllers\Learn\CourseController::class, 'updateSyllabus'])->name('syllabus.update');
    Route::patch('/{course}/status', [\App\Http\Controllers\Learn\CourseController::class, 'updateStatus'])->name('status.update');
    Route::put('/{course}/cover', [\App\Http\Controllers\Learn\CourseController::class, 'updateCover'])->name('cover.update');
    Route::get('/{course}/cover', [\App\Http\Controllers\Learn\CourseController::class, 'cover'])->name('cover.show');
```

(The `updateStatus` line already exists — add the two new lines directly after it, leave everything else in the file untouched.)

- [ ] **Step 4: Add the controller actions**

In `app/Http/Controllers/Learn/CourseController.php`:

Add the import near the top:

```php
use App\Services\Learn\CourseCoverService;
```

Update the constructor:

```php
    public function __construct(
        private CourseResolver $resolver,
        private CourseFileService $files,
        private CourseCoverService $covers,
    ) {
    }
```

Add two new actions (after `updateStatus()`):

```php
    /** PUT /learn/{course}/cover */
    public function updateCover(Request $request, Course $course)
    {
        $user = Auth::user();
        abort_unless($course->canEdit($user), 403);

        $validated = $request->validate([
            'preset' => 'nullable|string',
            'photo_base64' => 'nullable|string',
        ]);
        abort_if(empty($validated['preset']) && empty($validated['photo_base64']), 422, 'Provide a preset or a photo.');

        if (! empty($validated['photo_base64'])) {
            $this->covers->upload($course, $validated['photo_base64']);
        } else {
            $this->covers->setPreset($course, $validated['preset']);
        }

        return back()->with('success', 'Cover updated.');
    }

    /** GET /learn/{course}/cover */
    public function cover(Course $course)
    {
        abort_unless($course->canView(Auth::user()), 403);

        return $this->covers->streamResponse($course);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CourseControllerTest"`
Expected: PASS (all tests in the file, including the 3 new ones)

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/Learn/CourseController.php tests/Feature/Learn/CourseControllerTest.php
git commit -m "feat(learn): add faculty cover upload/preset and proxy endpoints"
```

---

### Task 5: Student Portal cover route + `LearnController` action

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/StudentPortal/LearnController.php`
- Test: `tests/Feature/StudentPortal/LearnControllerTest.php`

**Interfaces:**
- Consumes: `CourseCoverService` (Task 3)
- Produces: route `student-portal.learn.cover` (GET `/student-portal/learn/{course}/cover`) — consumed by Task 14/15 (StudentPortal pages)

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/StudentPortal/LearnControllerTest.php` (inside the class):

```php
    public function test_cover_proxy_streams_for_an_enrolled_student_but_403s_an_unenrolled_one(): void
    {
        \Illuminate\Support\Facades\Storage::fake('s3');
        app(\App\Services\Learn\CourseCoverService::class)->upload($this->course, 'data:image/png;base64,' . base64_encode('bytes'));

        session(['student_pisaysystemID' => $this->studentPisaysystemID]);
        $this->get(route('student-portal.learn.cover', $this->course))->assertOk();

        $otherPisaysystemID = 'PS' . str_pad((string) mt_rand(1, 999999999), 9, '0', STR_PAD_LEFT);
        \Illuminate\Support\Facades\DB::table('students')->insert([
            'pisaysystemID' => $otherPisaysystemID, 'lastname' => 'Reyes', 'firstname' => 'Ana', 'sex' => 'F',
        ]);
        session(['student_pisaysystemID' => $otherPisaysystemID]);
        $this->get(route('student-portal.learn.cover', $this->course))->assertForbidden();
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_cover_proxy_streams_for_an_enrolled_student_but_403s_an_unenrolled_one"`
Expected: FAIL — `Route [student-portal.learn.cover] not defined.`

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the `student-portal.` → `student.portal` middleware group, right after the existing learn-file route (currently lines 2683-2685):

```php
        // Must be registered before the {course} wildcard.
        Route::get('/learn/file/{fileId}', [\App\Http\Controllers\StudentPortal\LearnController::class, 'file'])
            ->name('learn.file')->where('fileId', '[a-zA-Z0-9_.=-]+');
        Route::get('/learn/{course}/cover', [\App\Http\Controllers\StudentPortal\LearnController::class, 'cover'])->name('learn.cover');
        Route::get('/learn', [\App\Http\Controllers\StudentPortal\LearnController::class, 'index'])->name('learn.index');
```

(Insert the new `learn.cover` line between the existing `learn.file` and `learn.index` lines — leave `learn.index`, `learn.show`, and everything else unchanged.)

- [ ] **Step 4: Add the controller action**

In `app/Http/Controllers/StudentPortal/LearnController.php`:

Add the import:

```php
use App\Services\Learn\CourseCoverService;
```

Update the constructor:

```php
    public function __construct(
        private CourseFileService $files,
        private CourseCoverService $covers,
    ) {
    }
```

Add the new action (after `show()`, before `file()`):

```php
    /** GET /student-portal/learn/{course}/cover */
    public function cover(Course $course)
    {
        $student = $this->currentStudent();
        abort_unless($course->isVisibleToStudent($student->id), 403);

        return $this->covers->streamResponse($course);
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_cover_proxy_streams_for_an_enrolled_student_but_403s_an_unenrolled_one"`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add routes/web.php app/Http/Controllers/StudentPortal/LearnController.php tests/Feature/StudentPortal/LearnControllerTest.php
git commit -m "feat(learn): add student portal cover photo proxy endpoint"
```

---

### Task 6: Wire cover/progress fields into faculty `CourseController` payloads

**Files:**
- Modify: `app/Http/Controllers/Learn/CourseController.php`
- Test: `tests/Feature/Learn/CourseControllerTest.php`

**Interfaces:**
- Consumes: `Course::setupProgress()` (Task 2), `learn_courses.cover_photo_s3_key`/`cover_preset` (Task 1)
- Produces: `courses[].cover_preset`, `courses[].cover_photo_url`, `courses[].setup_percent` in the `Learn/Index` payload; `course.cover_preset`, `course.cover_photo_url`, `course.setup_progress` in the `Learn/Show` payload — consumed by Task 12 (`Index.vue`), Task 13 (`Show.vue`)

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Learn/CourseControllerTest.php`:

```php
    public function test_index_payload_includes_cover_and_setup_percent(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);

        $response = $this->actingAs($teacher)->get(route('learn.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Learn/Index')
            ->where('courses.0.cover_preset', null)
            ->where('courses.0.cover_photo_url', null)
            ->where('courses.0.setup_percent', 0)
        );
    }

    public function test_show_payload_includes_cover_and_setup_progress(): void
    {
        $teacher = User::factory()->create();
        $this->assignTeaching($teacher);
        $course = Course::create([
            'subject_id' => $this->subject->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'cover_preset' => 'sky-wave',
        ]);

        $response = $this->actingAs($teacher)->get(route('learn.show', $course));

        $response->assertInertia(fn ($page) => $page
            ->component('Learn/Show')
            ->where('course.cover_preset', 'sky-wave')
            ->where('course.cover_photo_url', null)
            ->has('course.setup_progress.steps', 4)
            ->where('course.setup_progress.percent', 0)
        );
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CourseControllerTest"`
Expected: FAIL — the two new assertions fail because the keys don't exist in the payload yet.

- [ ] **Step 3: Wire the payload**

In `app/Http/Controllers/Learn/CourseController.php`, `index()` method — change the eager-load line and the map:

```php
        $courses->load(['subject', 'section', 'schoolYear', 'modules.items']);

        return Inertia::render('Learn/Index', [
            'courses' => $courses->map(fn (Course $c) => [
                'id' => $c->id,
                'subject_name' => $c->subject->name,
                'section_name' => $c->section->sectionname,
                'grade_level' => $c->section->levelid,
                'status' => $c->status,
                'is_read_only' => $c->isReadOnly(),
                'cover_preset' => $c->cover_preset,
                'cover_photo_url' => $c->cover_photo_s3_key ? route('learn.cover.show', $c->id) : null,
                'setup_percent' => $c->setupProgress()['percent'],
            ])->values(),
        ]);
```

In `serializeCourse()`, add the three new keys (right after `'is_read_only' => $course->isReadOnly(),`):

```php
            'is_read_only' => $course->isReadOnly(),
            'cover_preset' => $course->cover_preset,
            'cover_photo_url' => $course->cover_photo_s3_key ? route('learn.cover.show', $course->id) : null,
            'setup_progress' => $course->setupProgress(),
            'can_edit' => $course->canEdit($user),
```

(This inserts the 3 new lines between the existing `is_read_only` and `can_edit` lines — `can_edit` and everything below/above it stays exactly as-is.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=CourseControllerTest"`
Expected: PASS (all tests in the file)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Learn/CourseController.php tests/Feature/Learn/CourseControllerTest.php
git commit -m "feat(learn): expose cover photo and setup progress in course payloads"
```

---

### Task 7: Wire cover fields into `StudentPortal\LearnController` payloads

**Files:**
- Modify: `app/Http/Controllers/StudentPortal/LearnController.php`
- Test: `tests/Feature/StudentPortal/LearnControllerTest.php`

**Interfaces:**
- Consumes: `learn_courses.cover_photo_s3_key`/`cover_preset` (Task 1), `student-portal.learn.cover` route (Task 5)
- Produces: `courses[].cover_preset`, `courses[].cover_photo_url` in `StudentPortal/Learn/Index` payload; `course.cover_preset`, `course.cover_photo_url` in `StudentPortal/Learn/Show` payload — consumed by Task 14/15

- [ ] **Step 1: Write the failing test**

Add to `tests/Feature/StudentPortal/LearnControllerTest.php`:

```php
    public function test_index_and_show_payloads_include_cover_fields(): void
    {
        $this->course->update(['cover_preset' => 'navy-radial']);
        session(['student_pisaysystemID' => $this->studentPisaysystemID]);

        $this->get(route('student-portal.learn.index'))->assertInertia(fn ($page) => $page
            ->where('courses.0.cover_preset', 'navy-radial')
            ->where('courses.0.cover_photo_url', null)
        );

        $this->get(route('student-portal.learn.show', $this->course))->assertInertia(fn ($page) => $page
            ->where('course.cover_preset', 'navy-radial')
            ->where('course.cover_photo_url', null)
        );
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_index_and_show_payloads_include_cover_fields"`
Expected: FAIL — keys not present in payload.

- [ ] **Step 3: Wire the payload**

In `app/Http/Controllers/StudentPortal/LearnController.php`, `index()` — add to the map (after `'section_name' => $c->section->sectionname,`):

```php
            'courses' => $courses->map(fn (Course $c) => [
                'id' => $c->id,
                'subject_name' => $c->subject->name,
                'section_name' => $c->section->sectionname,
                'cover_preset' => $c->cover_preset,
                'cover_photo_url' => $c->cover_photo_s3_key ? route('student-portal.learn.cover', $c->id) : null,
            ])->values(),
```

In `show()`, add to the `course` array (after `'section_name' => $course->section->sectionname,`):

```php
            'course' => [
                'id' => $course->id,
                'subject_name' => $course->subject->name,
                'section_name' => $course->section->sectionname,
                'cover_preset' => $course->cover_preset,
                'cover_photo_url' => $course->cover_photo_s3_key ? route('student-portal.learn.cover', $course->id) : null,
                'syllabus_body' => $course->syllabus_body,
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=test_index_and_show_payloads_include_cover_fields"`
Expected: PASS

- [ ] **Step 5: Run the full Learn + StudentPortal test suites**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn tests/Feature/StudentPortal"`
Expected: PASS, zero failures, zero new regressions vs. before this plan started.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/StudentPortal/LearnController.php tests/Feature/StudentPortal/LearnControllerTest.php
git commit -m "feat(learn): expose cover photo fields in student portal course payloads"
```

---

### Task 8: Frontend — `courseCoverPresets.js` constant

**Files:**
- Create: `resources/js/Constants/courseCoverPresets.js`

**Interfaces:**
- Produces: `COURSE_COVER_PRESETS` (array of `{ key, label, class }`), `DEFAULT_COVER_PRESET_KEY` (string) — consumed by Task 9 (`CourseCover.vue`), Task 13 (`Show.vue` cover picker)

- [ ] **Step 1: Write the constant**

```js
// Must stay in sync with App\Services\Learn\CourseCoverService::PRESET_KEYS —
// the key here is what's stored in learn_courses.cover_preset; the visual
// definition (gradient class) lives only here, never in the backend.
export const COURSE_COVER_PRESETS = [
  { key: 'indigo-diagonal', label: 'Indigo diagonal', class: 'bg-gradient-to-br from-indigo-600 to-indigo-900' },
  { key: 'sky-wave', label: 'Sky wave', class: 'bg-gradient-to-tr from-sky-500 to-indigo-700' },
  { key: 'navy-radial', label: 'Navy radial', class: 'bg-[radial-gradient(circle_at_30%_20%,#0867DB,#0A2A5E)]' },
  { key: 'slate-grid', label: 'Slate grid', class: 'bg-slate-800' },
  { key: 'indigo-sunrise', label: 'Indigo sunrise', class: 'bg-gradient-to-b from-indigo-400 to-indigo-800' },
  { key: 'ocean-deep', label: 'Ocean deep', class: 'bg-gradient-to-br from-blue-600 via-indigo-700 to-slate-900' },
]

export const DEFAULT_COVER_PRESET_KEY = COURSE_COVER_PRESETS[0].key
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Constants/courseCoverPresets.js
git commit -m "feat(learn): add course cover preset constants"
```

---

### Task 9: Frontend — `CourseCover.vue`

**Files:**
- Create: `resources/js/Components/CourseCover.vue`

**Interfaces:**
- Consumes: `COURSE_COVER_PRESETS` (Task 8)
- Produces: `<CourseCover :photo-url :preset :initials />` — a single-root component (fallthrough `class` applies to its root `<div>`, so callers size it with e.g. `class="h-28 w-full"`) — consumed by Task 12, 13, 14, 15

- [ ] **Step 1: Write the component**

```vue
<script setup>
import { computed } from 'vue'
import { COURSE_COVER_PRESETS } from '@/Constants/courseCoverPresets'

const props = defineProps({
  photoUrl: { type: String, default: null },
  preset: { type: String, default: null },
  initials: { type: String, default: '' },
})

const activePreset = computed(() => COURSE_COVER_PRESETS.find(p => p.key === props.preset) ?? COURSE_COVER_PRESETS[0])
</script>

<template>
  <div class="relative overflow-hidden">
    <img v-if="photoUrl" :src="photoUrl" class="h-full w-full object-cover" alt="" />
    <div v-else :class="['flex h-full w-full items-center justify-center', activePreset.class]">
      <span class="font-heading text-3xl font-bold tracking-wide text-white/25">{{ initials }}</span>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Verify the component compiles**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npx vite build --outDir /tmp/learn-ui-build-check`
Expected: build succeeds with no errors mentioning `CourseCover.vue` (the component isn't imported anywhere yet, so it won't appear in the bundle — this step is just a compile-safety sanity check on the rest of the app).

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/CourseCover.vue
git commit -m "feat(learn): add CourseCover component for class card/banner imagery"
```

---

### Task 10: Frontend — `SetupProgressBar.vue`

**Files:**
- Create: `resources/js/Components/SetupProgressBar.vue`

**Interfaces:**
- Consumes: `steps: Array<{key, label, complete}>`, `percent: Number` (shape matches `Course::setupProgress()` from Task 2)
- Produces: `<SetupProgressBar :steps :percent />` — consumed by Task 13 (`Show.vue` Overview tab)

- [ ] **Step 1: Write the component**

```vue
<script setup>
import { CheckIcon } from '@heroicons/vue/24/solid'

defineProps({
  steps: { type: Array, required: true }, // [{ key, label, complete }]
  percent: { type: Number, required: true },
})
</script>

<template>
  <div>
    <p class="mb-3 text-xs font-medium text-slate-500">{{ percent }}% set up</p>
    <div class="mb-4 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
      <div class="h-full rounded-full bg-indigo-600 transition-all" :style="{ width: percent + '%' }" />
    </div>
    <ol class="flex flex-wrap gap-x-6 gap-y-2">
      <li v-for="step in steps" :key="step.key" class="flex items-center gap-2 text-sm">
        <span :class="['flex h-5 w-5 shrink-0 items-center justify-center rounded-full', step.complete ? 'bg-success-500 text-white' : 'border border-slate-300']">
          <CheckIcon v-if="step.complete" class="h-3 w-3" />
        </span>
        <span :class="step.complete ? 'text-slate-700' : 'text-slate-400'">{{ step.label }}</span>
      </li>
    </ol>
  </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/Components/SetupProgressBar.vue
git commit -m "feat(learn): add SetupProgressBar component for course setup checklist"
```

---

### Task 11: Frontend — `AppPageHeader.vue` optional `#cover` slot

**Files:**
- Modify: `resources/js/Components/AppPageHeader.vue`

**Interfaces:**
- Produces: optional `#cover` named slot on `AppPageHeader` (only rendered when `hero` is true AND the slot is provided) — consumed by Task 13 (`Show.vue`)
- **Backward compatibility requirement:** every page currently passing `hero` without a `#cover` slot must render byte-identical output after this change. There are 190+ such pages (`grep -rl "AppPageHeader" resources/js/Pages | xargs grep -l "hero"`).

- [ ] **Step 1: Read the current file to confirm no drift**

Run: `cat resources/js/Components/AppPageHeader.vue` — confirm it still matches:

```vue
<script setup>
import AppBreadcrumb from '@/Components/AppBreadcrumb.vue'

defineProps({
  title:      { type: String, required: true },
  subtitle:   { type: String, default: null },
  breadcrumb: { type: Array,  default: null }, // [{ label, href? }]
  // Dashboard hero treatment: white card with the indigo gradient accent bar
  hero:       { type: Boolean, default: false },
})
</script>

<template>
  <div :class="hero ? 'relative mb-6 overflow-hidden rounded-2xl bg-white px-4 py-5 shadow-sm ring-1 ring-slate-200/70 sm:px-6' : 'mb-6'">
    <div v-if="hero" class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-600 via-indigo-500 to-indigo-100" aria-hidden="true"></div>
    <AppBreadcrumb v-if="breadcrumb?.length" :items="breadcrumb" />
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
      <div>
        <h1 class="font-heading text-xl font-semibold text-slate-900 leading-tight tracking-tight">{{ title }}</h1>
        <p v-if="subtitle" class="mt-0.5 text-sm text-slate-500">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 sm:justify-end">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>
```

If it differs, stop and reconcile before proceeding — this task assumes exactly this starting point.

- [ ] **Step 2: Replace the file content**

Replace the entire file with:

```vue
<script setup>
import AppBreadcrumb from '@/Components/AppBreadcrumb.vue'

defineProps({
  title:      { type: String, required: true },
  subtitle:   { type: String, default: null },
  breadcrumb: { type: Array,  default: null }, // [{ label, href? }]
  // Dashboard hero treatment: white card with the indigo gradient accent bar
  hero:       { type: Boolean, default: false },
})
</script>

<template>
  <div :class="hero ? 'relative mb-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200/70' : 'mb-6'">
    <div v-if="hero && $slots.cover" class="h-28 w-full">
      <slot name="cover" />
    </div>
    <div v-else-if="hero" class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r from-indigo-600 via-indigo-500 to-indigo-100" aria-hidden="true"></div>
    <div :class="hero ? 'px-4 py-5 sm:px-6' : ''">
      <AppBreadcrumb v-if="breadcrumb?.length" :items="breadcrumb" />
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 class="font-heading text-xl font-semibold text-slate-900 leading-tight tracking-tight">{{ title }}</h1>
          <p v-if="subtitle" class="mt-0.5 text-sm text-slate-500">{{ subtitle }}</p>
        </div>
        <div v-if="$slots.actions" class="flex flex-wrap items-center gap-2 sm:justify-end">
          <slot name="actions" />
        </div>
      </div>
    </div>
  </div>
</template>
```

Why this is safe: for `hero: false` consumers, the outer class ternary is unchanged (`'mb-6'`), and the inner `px-4 py-5 sm:px-6` wrapper only adds an extra `<div>` around content that already had no padding at that level — visually identical. For `hero: true` consumers without a `#cover` slot, `$slots.cover` is falsy so the accent-bar branch renders exactly as before, and the padding that used to live on the outer div now lives on the inner wrapper — same total padding, same visual result, one extra non-visual wrapper `<div>`.

- [ ] **Step 3: Compile-check**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npx vite build --outDir /tmp/learn-ui-build-check`
Expected: build succeeds with no errors.

- [ ] **Step 4: Manual spot-check on an unrelated existing hero page**

This can't be automated (no JS test runner). Once Docker + a logged-in session are available, open a page that uses `hero` without a cover (e.g. `CID/ALP/Index.vue`) and confirm it looks unchanged from before this task.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/AppPageHeader.vue
git commit -m "feat(components): add optional cover slot to AppPageHeader (backward-compatible)"
```

---

### Task 12: Frontend — `Learn/Index.vue` full rewrite

**Files:**
- Modify: `resources/js/Pages/Learn/Index.vue`

**Interfaces:**
- Consumes: `courses[].cover_preset`, `courses[].cover_photo_url`, `courses[].setup_percent` (Task 6), `CourseCover` (Task 9), `AppPageHeader`/`AppCard`/`AppBadge`/`EmptyState` (existing shared components)

- [ ] **Step 1: Replace the entire file content**

```vue
<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppCard from '@/Components/AppCard.vue'
import AppBadge from '@/Components/AppBadge.vue'
import CourseCover from '@/Components/CourseCover.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { LockClosedIcon, BookOpenIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ courses: Array })

function statusColor(status) {
  return status === 'published' ? 'green' : 'slate'
}

function initialsFor(course) {
  return (course.subject_name || '').trim().split(/\s+/).map(w => w[0]).join('').slice(0, 3).toUpperCase()
}
</script>

<template>
  <Head title="Learn — My Courses" />
  <AdminLayout title="Learn">
    <div class="max-w-5xl mx-auto py-6 px-4">
      <AppPageHeader title="My Courses" subtitle="Courses appear automatically once you have a teaching load for the current school year." />

      <EmptyState v-if="courses.length === 0" :icon="BookOpenIcon" title="No courses yet" subtitle="Courses appear automatically once you have a teaching load for the current school year." />

      <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <Link
          v-for="course in courses"
          :key="course.id"
          :href="route('learn.show', course.id)"
          class="block"
        >
          <AppCard :padded="false" class="h-full transition hover:shadow-md hover:ring-indigo-200">
            <CourseCover :photo-url="course.cover_photo_url" :preset="course.cover_preset" :initials="initialsFor(course)" class="h-28 w-full" />
            <div class="p-4">
              <p class="text-sm font-medium text-slate-800 truncate">{{ course.subject_name }}</p>
              <p class="text-xs text-slate-500">Grade {{ course.grade_level }} — {{ course.section_name }}</p>
              <div class="mt-2 flex items-center gap-2">
                <AppBadge :color="statusColor(course.status)">{{ course.status === 'published' ? 'Published' : 'Draft' }}</AppBadge>
                <span v-if="course.is_read_only" class="inline-flex items-center gap-1 text-xs text-slate-400">
                  <LockClosedIcon class="h-3.5 w-3.5" /> Read-only
                </span>
              </div>
              <div v-if="!course.is_read_only && course.setup_percent < 100" class="mt-3">
                <div class="h-1 w-full overflow-hidden rounded-full bg-slate-100">
                  <div class="h-full rounded-full bg-indigo-600" :style="{ width: course.setup_percent + '%' }" />
                </div>
                <p class="mt-1 text-[11px] text-slate-400">{{ course.setup_percent }}% set up</p>
              </div>
            </div>
          </AppCard>
        </Link>
      </div>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 2: Compile-check**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npx vite build --outDir /tmp/learn-ui-build-check`
Expected: build succeeds with no errors mentioning `Learn/Index.vue`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Learn/Index.vue
git commit -m "feat(learn): redesign My Courses index with cover photos and setup progress"
```

---

### Task 13: Frontend — `Learn/Show.vue` full rewrite

**Files:**
- Modify: `resources/js/Pages/Learn/Show.vue`

**Interfaces:**
- Consumes: `course.cover_preset`/`cover_photo_url`/`setup_progress` (Task 6), `CourseCover` (Task 9), `SetupProgressBar` (Task 10), `AppPageHeader` `#cover` slot (Task 11), `learn.cover.update` route (Task 4)
- **Zero business-logic changes:** every function in `<script setup>` (all `router.post/put/delete` calls, all `useForm`/`ref` state, all validation) is preserved verbatim from the current file — only imports and template markup change.

- [ ] **Step 1: Read the current file to confirm no drift**

Run: `wc -l resources/js/Pages/Learn/Show.vue` — expect `648`. If the line count or content differs from what this task assumes, stop and reconcile (someone else may have touched this file) before proceeding.

- [ ] **Step 2: Replace the entire file content**

```vue
<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import DOMPurify from 'dompurify'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import RichTextEditor from '@/Components/RichTextEditor.vue'
import MathContent from '@/Components/MathContent.vue'
import AppBadge from '@/Components/AppBadge.vue'
import AppButton from '@/Components/AppButton.vue'
import AppCard from '@/Components/AppCard.vue'
import AppIconButton from '@/Components/AppIconButton.vue'
import AppInput from '@/Components/AppInput.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppSelect from '@/Components/AppSelect.vue'
import AppTabs from '@/Components/AppTabs.vue'
import AppTextarea from '@/Components/AppTextarea.vue'
import CourseCover from '@/Components/CourseCover.vue'
import SetupProgressBar from '@/Components/SetupProgressBar.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { COURSE_COVER_PRESETS } from '@/Constants/courseCoverPresets'
import {
  PlusIcon, TrashIcon, EyeIcon, EyeSlashIcon,
  ArrowUpIcon, ArrowDownIcon, DocumentIcon, PaperClipIcon, AcademicCapIcon, ChatBubbleLeftRightIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({ course: Object, rubric_templates: Array, quiz_question_bank: Array })

// Rich text (syllabus, page body) is stored as raw HTML — an instructor could
// bypass the RichTextEditor UI and POST a malicious payload directly to the
// API, so it must be sanitized at render time, not trusted as authored.
function sanitizeHtml(html) {
  return DOMPurify.sanitize(html || '')
}

// video_url is free-text input — reject anything but http(s) before it's
// ever used as a clickable href (blocks javascript:/data: scheme XSS).
function safeVideoUrl(url) {
  return url && /^https?:\/\//i.test(url) ? url : null
}

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

// ── Tabs ─────────────────────────────────────────────────────────────────
const tabs = [
  { key: 'overview', label: 'Overview' },
  { key: 'modules', label: 'Modules' },
  { key: 'announcements', label: 'Announcements' },
]
const activeTab = ref('overview')
const subjectInitials = computed(() => (props.course.subject_name || '').trim().split(/\s+/).map(w => w[0]).join('').slice(0, 3).toUpperCase())

// ── Cover photo ──────────────────────────────────────────────────────────
function selectCoverPreset(presetKey) {
  router.put(route('learn.cover.update', props.course.id), { preset: presetKey }, { preserveScroll: true })
}
async function uploadCoverPhoto(event) {
  const file = event.target.files[0]
  if (! file) return
  const base64 = await readFileAsBase64(file)
  router.put(route('learn.cover.update', props.course.id), { photo_base64: base64 }, { preserveScroll: true })
  event.target.value = ''
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

const assignmentForms = ref({})
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

// ── Quiz authoring ───────────────────────────────────────────────────────
const quizForms = ref({})
function quizForm(moduleId) {
  if (! quizForms.value[moduleId]) {
    quizForms.value[moduleId] = useForm({
      title: '', instructions: '', time_limit_minutes: '', max_attempts: '',
      questions_to_draw: '', shuffle_questions: false, shuffle_options: false, due_at: '',
      questions: [],
    })
  }
  return quizForms.value[moduleId]
}
function addQuizQuestion(moduleId) {
  quizForm(moduleId).questions.push({
    question_type: 'multiple_choice', prompt: '', points: 5, difficulty: '',
    options: [{ option_text: '', is_correct: true }, { option_text: '', is_correct: false }],
    accepted_answers: [],
    save_to_bank: false, bank_name: '',
  })
}
function removeQuizQuestion(moduleId, index) {
  quizForm(moduleId).questions.splice(index, 1)
}
function addQuizQuestionOption(moduleId, qIndex) {
  quizForm(moduleId).questions[qIndex].options.push({ option_text: '', is_correct: false })
}
function removeQuizQuestionOption(moduleId, qIndex, oIndex) {
  quizForm(moduleId).questions[qIndex].options.splice(oIndex, 1)
}
function addAcceptedAnswer(moduleId, qIndex) {
  quizForm(moduleId).questions[qIndex].accepted_answers.push('')
}
function removeAcceptedAnswer(moduleId, qIndex, aIndex) {
  quizForm(moduleId).questions[qIndex].accepted_answers.splice(aIndex, 1)
}
function applyQuizBankItem(moduleId, qIndex, bankItemId) {
  const item = props.quiz_question_bank.find(b => b.id === Number(bankItemId))
  if (! item) return
  const q = quizForm(moduleId).questions[qIndex]
  q.question_type = item.question_type
  q.prompt = item.prompt
  q.points = item.points
  q.difficulty = item.difficulty || ''
  if (['multiple_choice', 'true_false', 'multiple_select'].includes(item.question_type)) {
    q.options = item.options.map(o => ({ option_text: o.option_text, is_correct: o.is_correct }))
    q.accepted_answers = []
  } else if (item.question_type === 'short_answer') {
    q.accepted_answers = item.options.map(o => o.option_text)
    q.options = []
  } else {
    q.options = []
    q.accepted_answers = []
  }
}
function addQuiz(moduleId) {
  quizForm(moduleId).post(route('learn.items.store-quiz', moduleId), {
    preserveScroll: true,
    onSuccess: () => { quizForms.value[moduleId] = null },
  })
}

// Adding/deleting questions on an ALREADY-CREATED quiz — separate form state from quizForm
// above (which only ever builds a brand-new quiz's initial question set in one POST).
const newQuestionForms = ref({})
function newQuestionForm(quizId) {
  if (! newQuestionForms.value[quizId]) {
    newQuestionForms.value[quizId] = useForm({
      question_type: 'multiple_choice', prompt: '', points: 5, difficulty: '',
      options: [{ option_text: '', is_correct: true }, { option_text: '', is_correct: false }],
      accepted_answers: [],
      save_to_bank: false, bank_name: '',
    })
  }
  return newQuestionForms.value[quizId]
}
function addNewQuestionOption(quizId) {
  newQuestionForm(quizId).options.push({ option_text: '', is_correct: false })
}
function removeNewQuestionOption(quizId, index) {
  newQuestionForm(quizId).options.splice(index, 1)
}
function addNewAcceptedAnswer(quizId) {
  newQuestionForm(quizId).accepted_answers.push('')
}
function removeNewAcceptedAnswer(quizId, index) {
  newQuestionForm(quizId).accepted_answers.splice(index, 1)
}
function submitNewQuestion(quizId) {
  newQuestionForm(quizId).post(route('learn.quiz-questions.store', quizId), {
    preserveScroll: true,
    onSuccess: () => { newQuestionForms.value[quizId] = null },
  })
}
function deleteQuizQuestion(questionId) {
  router.delete(route('learn.quiz-questions.destroy', questionId), { preserveScroll: true })
}

const renameBankItemDrafts = ref({})
function startRenameBankItem(item) {
  renameBankItemDrafts.value[item.id] = item.name
}
function saveBankItemRename(item) {
  router.put(route('learn.quiz-question-bank.update', item.id), {
    name: renameBankItemDrafts.value[item.id],
  }, {
    preserveScroll: true,
    onSuccess: () => { delete renameBankItemDrafts.value[item.id] },
  })
}
function deleteBankItem(item) {
  router.delete(route('learn.quiz-question-bank.destroy', item.id), { preserveScroll: true })
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
</script>

<template>
  <Head :title="`Learn — ${course.subject_name}`" />
  <AdminLayout :title="course.subject_name">
    <div class="max-w-5xl mx-auto py-6 px-4 space-y-5">
      <AppPageHeader
        hero
        :title="course.subject_name"
        :subtitle="`Grade ${course.grade_level} — ${course.section_name}`"
      >
        <template #cover>
          <CourseCover :photo-url="course.cover_photo_url" :preset="course.cover_preset" :initials="subjectInitials" class="h-full w-full" />
        </template>
        <template #actions>
          <AppBadge :color="course.status === 'published' ? 'green' : 'slate'">
            {{ course.status === 'published' ? 'Published' : 'Draft' }}
          </AppBadge>
          <Link v-if="course.can_edit" :href="route('learn.course-trend', course.id)" class="text-xs font-medium text-indigo-600 hover:underline">
            Quiz trend
          </Link>
          <AppButton
            v-if="course.can_edit"
            :variant="course.status === 'published' ? 'secondary' : 'primary'"
            @click="toggleCourseStatus"
          >
            {{ course.status === 'published' ? 'Unpublish' : 'Publish course' }}
          </AppButton>
        </template>
      </AppPageHeader>

      <div v-if="course.is_read_only" class="rounded-xl border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-800">
        This course is from a past school year and is read-only.
      </div>

      <AppTabs v-model="activeTab" :tabs="tabs">
        <template v-if="activeTab === 'overview'">
          <div class="space-y-5">
            <AppCard title="Course setup">
              <SetupProgressBar :steps="course.setup_progress.steps" :percent="course.setup_progress.percent" />
            </AppCard>

            <AppCard v-if="course.can_edit" title="Course cover" subtitle="Shown on your class card and at the top of this page.">
              <div class="flex flex-wrap gap-3">
                <button
                  v-for="preset in COURSE_COVER_PRESETS"
                  :key="preset.key"
                  type="button"
                  :class="['h-16 w-24 rounded-lg ring-2 transition', preset.class, course.cover_preset === preset.key && !course.cover_photo_url ? 'ring-indigo-600' : 'ring-transparent hover:ring-slate-300']"
                  :aria-label="preset.label"
                  @click="selectCoverPreset(preset.key)"
                />
                <label class="flex h-16 w-24 cursor-pointer items-center justify-center rounded-lg border-2 border-dashed border-slate-300 text-center text-xs font-medium text-slate-500 hover:border-indigo-400 hover:text-indigo-600">
                  Upload photo
                  <input type="file" accept="image/png,image/jpeg,image/webp" class="hidden" @change="uploadCoverPhoto" />
                </label>
              </div>
            </AppCard>

            <AppCard title="Syllabus">
              <RichTextEditor v-if="course.can_edit" v-model="syllabus" />
              <div v-else class="prose prose-sm max-w-none" v-html="sanitizeHtml(course.syllabus_body) || '<p class=\'text-slate-400\'>No syllabus yet.</p>'" />
              <AppButton v-if="course.can_edit" class="mt-3" @click="saveSyllabus">Save syllabus</AppButton>
            </AppCard>
          </div>
        </template>

        <template v-else-if="activeTab === 'modules'">
          <div class="space-y-5">
            <AppCard v-for="(module, index) in course.modules" :key="module.id" :padded="false">
              <template #header>
                <div class="flex flex-1 items-center justify-between gap-3">
                  <div class="flex min-w-0 items-center gap-2">
                    <span class="truncate text-sm font-semibold text-slate-800">{{ module.title }}</span>
                    <AppBadge :color="module.is_published ? 'green' : 'slate'">{{ module.is_published ? 'Published' : 'Draft' }}</AppBadge>
                  </div>
                  <div v-if="course.can_edit" class="flex shrink-0 items-center gap-1">
                    <AppIconButton label="Move up" @click="moveModule(index, -1)"><ArrowUpIcon class="h-4 w-4" /></AppIconButton>
                    <AppIconButton label="Move down" @click="moveModule(index, 1)"><ArrowDownIcon class="h-4 w-4" /></AppIconButton>
                    <AppIconButton :label="module.is_published ? 'Unpublish module' : 'Publish module'" @click="toggleModulePublish(module.id)">
                      <EyeIcon v-if="!module.is_published" class="h-4 w-4" />
                      <EyeSlashIcon v-else class="h-4 w-4" />
                    </AppIconButton>
                    <AppIconButton label="Delete module" variant="danger" @click="deleteModule(module.id)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                  </div>
                </div>
              </template>

              <div class="space-y-3 p-5">
                <div v-for="(item, itemIndex) in module.items" :key="item.id" class="flex items-start gap-2 rounded-lg border border-slate-100 p-3">
                  <DocumentIcon v-if="item.type === 'page'" class="h-5 w-5 shrink-0 text-slate-400" />
                  <AcademicCapIcon v-else-if="item.type === 'quiz'" class="h-5 w-5 shrink-0 text-slate-400" />
                  <ChatBubbleLeftRightIcon v-else-if="item.type === 'discussion'" class="h-5 w-5 shrink-0 text-slate-400" />
                  <PaperClipIcon v-else class="h-5 w-5 shrink-0 text-slate-400" />
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                      <p class="text-sm font-medium text-slate-700">{{ item.title }}</p>
                      <AppBadge :color="item.is_published ? 'green' : 'slate'">{{ item.is_published ? 'Published' : 'Draft' }}</AppBadge>
                    </div>
                    <div v-if="item.type === 'page' && item.body" class="prose prose-sm mt-1 max-w-none" v-html="sanitizeHtml(item.body)" />
                    <a v-if="item.type === 'page' && safeVideoUrl(item.video_url)" :href="safeVideoUrl(item.video_url)" target="_blank" rel="noopener noreferrer" class="text-xs text-indigo-600 underline">Watch video</a>
                    <a v-if="item.type === 'file'" :href="item.file_url" target="_blank" rel="noopener noreferrer" class="text-xs text-indigo-600 underline">Download file</a>
                    <div v-if="item.type === 'assignment'" class="mt-1 space-y-1">
                      <div v-if="item.assignment.instructions" class="prose prose-sm max-w-none" v-html="sanitizeHtml(item.assignment.instructions)" />
                      <p class="text-xs text-slate-500">
                        {{ item.assignment.submission_type }} submission
                        <span v-if="item.assignment.due_at"> — due {{ new Date(item.assignment.due_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                        <span v-if="item.assignment.max_score !== null"> — {{ item.assignment.max_score }} pts{{ item.assignment.has_rubric ? ' (rubric)' : '' }}</span>
                      </p>
                      <Link :href="route('learn.assignments.submissions', item.assignment.id)" class="text-xs text-indigo-600 underline">View submissions</Link>
                    </div>
                    <div v-if="item.type === 'quiz'" class="mt-1 space-y-1">
                      <p class="text-xs text-slate-500">
                        {{ item.quiz.question_count }} question{{ item.quiz.question_count === 1 ? '' : 's' }}
                        <span v-if="item.quiz.time_limit_minutes"> — {{ item.quiz.time_limit_minutes }} min</span>
                        <span v-if="item.quiz.due_at"> — due {{ new Date(item.quiz.due_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</span>
                        <span v-if="item.quiz.max_score !== null"> — {{ item.quiz.max_score }} pts</span>
                      </p>
                      <div class="flex gap-2">
                        <Link :href="route('learn.quizzes.attempts', item.quiz.id)" class="text-xs text-indigo-600 underline">View attempts</Link>
                        <Link :href="route('learn.quizzes.analytics', item.quiz.id)" class="text-xs text-indigo-600 underline">Analytics</Link>
                      </div>

                      <div v-if="course.can_edit" class="space-y-2 border-t border-slate-100 pt-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Questions</p>
                        <p v-if="item.quiz.is_locked" class="text-xs text-warning-600">Locked — students have submitted attempts. Existing questions cannot be changed, but new ones can still be added.</p>

                        <div v-for="q in item.quiz.questions" :key="q.id" class="flex items-start gap-2 rounded-lg border border-slate-100 p-2">
                          <div class="min-w-0 flex-1">
                            <MathContent :html="sanitizeHtml(q.prompt)" class="prose prose-sm max-w-none" />
                            <p class="text-xs text-slate-400">{{ q.question_type }} — {{ q.points }} pts</p>
                          </div>
                          <AppIconButton v-if="!item.quiz.is_locked" label="Delete question" variant="danger" size="sm" @click="deleteQuizQuestion(q.id)"><TrashIcon class="h-3.5 w-3.5" /></AppIconButton>
                        </div>

                        <AppCard class="space-y-2">
                          <p class="text-xs text-slate-500">Add another question</p>
                          <div class="flex gap-2">
                            <AppSelect v-model="newQuestionForm(item.quiz.id).question_type" :show-blank="false" class="max-w-[200px]">
                              <option value="multiple_choice">Multiple choice</option>
                              <option value="true_false">True / False</option>
                              <option value="multiple_select">Multiple select</option>
                              <option value="short_answer">Short answer</option>
                              <option value="essay">Essay</option>
                            </AppSelect>
                            <AppInput v-model="newQuestionForm(item.quiz.id).points" type="number" min="0" placeholder="Points" class="w-24" />
                          </div>
                          <AppTextarea v-model="newQuestionForm(item.quiz.id).prompt" placeholder="Question prompt (supports $LaTeX$)" :rows="2" />

                          <div v-if="['multiple_choice', 'true_false', 'multiple_select'].includes(newQuestionForm(item.quiz.id).question_type)" class="space-y-1">
                            <div v-for="(o, oIndex) in newQuestionForm(item.quiz.id).options" :key="oIndex" class="flex items-center gap-2">
                              <AppInput v-model="o.option_text" placeholder="Option text" class="flex-1" />
                              <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="o.is_correct" /> Correct</label>
                              <AppIconButton label="Remove option" variant="danger" size="sm" @click="removeNewQuestionOption(item.quiz.id, oIndex)"><TrashIcon class="h-3.5 w-3.5" /></AppIconButton>
                            </div>
                            <button type="button" class="text-xs text-indigo-600 underline" @click="addNewQuestionOption(item.quiz.id)">+ Add option</button>
                          </div>
                          <div v-else-if="newQuestionForm(item.quiz.id).question_type === 'short_answer'" class="space-y-1">
                            <div v-for="(a, aIndex) in newQuestionForm(item.quiz.id).accepted_answers" :key="aIndex" class="flex items-center gap-2">
                              <AppInput v-model="newQuestionForm(item.quiz.id).accepted_answers[aIndex]" placeholder="Accepted answer" class="flex-1" />
                              <AppIconButton label="Remove accepted answer" variant="danger" size="sm" @click="removeNewAcceptedAnswer(item.quiz.id, aIndex)"><TrashIcon class="h-3.5 w-3.5" /></AppIconButton>
                            </div>
                            <button type="button" class="text-xs text-indigo-600 underline" @click="addNewAcceptedAnswer(item.quiz.id)">+ Add accepted answer</button>
                          </div>

                          <AppButton variant="secondary" size="sm" @click="submitNewQuestion(item.quiz.id)">Add question</AppButton>
                        </AppCard>
                      </div>
                    </div>
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
                  </div>
                  <div v-if="course.can_edit" class="flex shrink-0 items-center gap-1">
                    <AppIconButton label="Move up" size="sm" @click="moveItem(module, itemIndex, -1)"><ArrowUpIcon class="h-3.5 w-3.5" /></AppIconButton>
                    <AppIconButton label="Move down" size="sm" @click="moveItem(module, itemIndex, 1)"><ArrowDownIcon class="h-3.5 w-3.5" /></AppIconButton>
                    <AppIconButton :label="item.is_published ? 'Unpublish item' : 'Publish item'" size="sm" @click="toggleItemPublish(item.id)">
                      <EyeIcon v-if="!item.is_published" class="h-3.5 w-3.5" />
                      <EyeSlashIcon v-else class="h-3.5 w-3.5" />
                    </AppIconButton>
                    <AppIconButton label="Delete item" variant="danger" size="sm" @click="deleteItem(item.id)"><TrashIcon class="h-3.5 w-3.5" /></AppIconButton>
                  </div>
                </div>

                <div v-if="course.can_edit" class="space-y-3 border-t border-slate-100 pt-3">
                  <div class="flex gap-2">
                    <AppInput v-model="pageForm(module.id).title" placeholder="Page title" class="flex-1" />
                    <AppButton variant="secondary" @click="addPage(module.id)">Add page</AppButton>
                  </div>
                  <AppTextarea v-model="pageForm(module.id).body" placeholder="Page body (optional)" :rows="2" />
                  <AppInput v-model="pageForm(module.id).video_url" placeholder="Video URL (YouTube/Drive, optional)" />

                  <div class="flex items-center gap-2">
                    <AppInput v-model="fileTitles[module.id]" placeholder="File title" class="flex-1" />
                    <label class="cursor-pointer rounded-lg bg-slate-100 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200">
                      Upload file
                      <input type="file" class="hidden" @change="e => addFile(module.id, e)" />
                    </label>
                  </div>

                  <div class="space-y-2 border-t border-slate-100 pt-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">New assignment</p>
                    <AppInput v-model="assignmentForm(module.id).title" placeholder="Assignment title" />
                    <AppTextarea v-model="assignmentForm(module.id).instructions" placeholder="Instructions (optional)" :rows="2" />
                    <div class="flex gap-2">
                      <AppSelect v-model="assignmentForm(module.id).submission_type" :show-blank="false" class="max-w-[200px]">
                        <option value="text">Text entry</option>
                        <option value="file">File upload</option>
                        <option value="link">Link</option>
                      </AppSelect>
                      <AppInput v-model="assignmentForm(module.id).due_at" type="datetime-local" />
                    </div>

                    <div v-if="assignmentForm(module.id).rubric_criteria.length === 0">
                      <AppInput v-model="assignmentForm(module.id).points_possible" type="number" min="0" placeholder="Points possible" />
                    </div>
                    <div v-else class="space-y-1">
                      <div v-for="(criterion, i) in assignmentForm(module.id).rubric_criteria" :key="i" class="flex items-center gap-2">
                        <AppInput v-model="criterion.description" placeholder="Criterion" class="flex-1" />
                        <AppInput v-model="criterion.max_points" type="number" min="0" placeholder="Points" class="w-24" />
                        <AppIconButton label="Remove criterion" variant="danger" @click="removeRubricCriterion(module.id, i)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                      </div>
                    </div>

                    <AppSelect v-if="rubric_templates.length" :show-blank="false" placeholder="Start from a saved template" @update:model-value="value => applyTemplate(module.id, value)">
                      <option value="" disabled selected>Start from a saved template</option>
                      <option v-for="t in rubric_templates" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </AppSelect>

                    <button type="button" class="text-xs text-indigo-600 underline" @click="addRubricCriterion(module.id)">+ Add rubric criterion</button>

                    <div v-if="assignmentForm(module.id).rubric_criteria.length > 0" class="flex items-center gap-2">
                      <input type="checkbox" v-model="assignmentForm(module.id).save_as_template" :id="`save-template-${module.id}`" />
                      <label :for="`save-template-${module.id}`" class="text-xs text-slate-600">Save these criteria as a template</label>
                    </div>
                    <AppInput v-if="assignmentForm(module.id).save_as_template" v-model="assignmentForm(module.id).template_name" placeholder="Template name" />

                    <div v-if="rubric_templates.length" class="space-y-1 border-t border-slate-100 pt-2">
                      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">My templates</p>
                      <div v-for="t in rubric_templates" :key="t.id" class="flex items-center gap-2">
                        <AppInput v-if="renameTemplateDrafts[t.id] !== undefined" v-model="renameTemplateDrafts[t.id]" class="flex-1" />
                        <span v-else class="flex-1 text-xs text-slate-600">{{ t.name }}</span>
                        <button v-if="renameTemplateDrafts[t.id] !== undefined" type="button" class="text-xs text-indigo-600 underline" @click="saveTemplateRename(t)">Save</button>
                        <button v-else type="button" class="text-xs text-slate-500 underline" @click="startRenameTemplate(t)">Rename</button>
                        <button type="button" class="text-xs text-red-500 underline" @click="deleteTemplate(t)">Delete</button>
                      </div>
                    </div>

                    <AppButton @click="addAssignment(module.id)">Add assignment</AppButton>
                  </div>

                  <div class="space-y-2 border-t border-slate-100 pt-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">New quiz</p>
                    <AppInput v-model="quizForm(module.id).title" placeholder="Quiz title" />
                    <AppTextarea v-model="quizForm(module.id).instructions" placeholder="Instructions (optional)" :rows="2" />
                    <div class="grid grid-cols-2 gap-2">
                      <AppInput v-model="quizForm(module.id).time_limit_minutes" type="number" min="1" placeholder="Time limit (minutes, optional)" />
                      <AppInput v-model="quizForm(module.id).max_attempts" type="number" min="1" placeholder="Max attempts (optional)" />
                      <AppInput v-model="quizForm(module.id).questions_to_draw" type="number" min="1" placeholder="Draw N random questions (optional)" />
                      <AppInput v-model="quizForm(module.id).due_at" type="datetime-local" />
                    </div>
                    <div class="flex gap-4">
                      <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="quizForm(module.id).shuffle_questions" /> Shuffle questions</label>
                      <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="quizForm(module.id).shuffle_options" /> Shuffle options</label>
                    </div>

                    <AppCard v-for="(q, qIndex) in quizForm(module.id).questions" :key="qIndex" class="space-y-2">
                      <div class="flex gap-2">
                        <AppSelect v-model="q.question_type" :show-blank="false" class="max-w-[180px]">
                          <option value="multiple_choice">Multiple choice</option>
                          <option value="true_false">True / False</option>
                          <option value="multiple_select">Multiple select</option>
                          <option value="short_answer">Short answer</option>
                          <option value="essay">Essay</option>
                        </AppSelect>
                        <AppInput v-model="q.points" type="number" min="0" placeholder="Points" class="w-24" />
                        <AppSelect v-model="q.difficulty" placeholder="Difficulty (optional)" class="max-w-[160px]">
                          <option value="easy">Easy</option>
                          <option value="medium">Medium</option>
                          <option value="hard">Hard</option>
                        </AppSelect>
                        <AppIconButton label="Remove question" variant="danger" @click="removeQuizQuestion(module.id, qIndex)"><TrashIcon class="h-4 w-4" /></AppIconButton>
                      </div>
                      <AppTextarea v-model="q.prompt" placeholder="Question prompt (supports $LaTeX$)" :rows="2" />
                      <MathContent v-if="q.prompt" :html="sanitizeHtml(q.prompt)" class="prose prose-sm max-w-none border-l-2 border-slate-200 pl-2" />

                      <AppSelect v-if="quiz_question_bank.length" :show-blank="false" placeholder="Start from a saved question" @update:model-value="value => applyQuizBankItem(module.id, qIndex, value)">
                        <option value="" disabled selected>Start from a saved question</option>
                        <option v-for="b in quiz_question_bank" :key="b.id" :value="b.id">{{ b.name }}</option>
                      </AppSelect>

                      <div v-if="['multiple_choice', 'true_false', 'multiple_select'].includes(q.question_type)" class="space-y-1">
                        <div v-for="(o, oIndex) in q.options" :key="oIndex" class="flex items-center gap-2">
                          <AppInput v-model="o.option_text" placeholder="Option text" class="flex-1" />
                          <label class="flex items-center gap-1 text-xs text-slate-600"><input type="checkbox" v-model="o.is_correct" /> Correct</label>
                          <AppIconButton label="Remove option" variant="danger" size="sm" @click="removeQuizQuestionOption(module.id, qIndex, oIndex)"><TrashIcon class="h-3.5 w-3.5" /></AppIconButton>
                        </div>
                        <button type="button" class="text-xs text-indigo-600 underline" @click="addQuizQuestionOption(module.id, qIndex)">+ Add option</button>
                      </div>

                      <div v-else-if="q.question_type === 'short_answer'" class="space-y-1">
                        <div v-for="(a, aIndex) in q.accepted_answers" :key="aIndex" class="flex items-center gap-2">
                          <AppInput v-model="q.accepted_answers[aIndex]" placeholder="Accepted answer" class="flex-1" />
                          <AppIconButton label="Remove accepted answer" variant="danger" size="sm" @click="removeAcceptedAnswer(module.id, qIndex, aIndex)"><TrashIcon class="h-3.5 w-3.5" /></AppIconButton>
                        </div>
                        <button type="button" class="text-xs text-indigo-600 underline" @click="addAcceptedAnswer(module.id, qIndex)">+ Add accepted answer</button>
                      </div>

                      <div class="flex items-center gap-2">
                        <input type="checkbox" v-model="q.save_to_bank" :id="`save-qbank-${module.id}-${qIndex}`" />
                        <label :for="`save-qbank-${module.id}-${qIndex}`" class="text-xs text-slate-600">Save this question to my bank</label>
                      </div>
                      <AppInput v-if="q.save_to_bank" v-model="q.bank_name" placeholder="Bank name" />
                    </AppCard>
                    <button type="button" class="text-xs text-indigo-600 underline" @click="addQuizQuestion(module.id)">+ Add question</button>

                    <div v-if="quiz_question_bank.length" class="space-y-1 border-t border-slate-100 pt-2">
                      <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">My question bank</p>
                      <div v-for="b in quiz_question_bank" :key="b.id" class="flex items-center gap-2">
                        <AppInput v-if="renameBankItemDrafts[b.id] !== undefined" v-model="renameBankItemDrafts[b.id]" class="flex-1" />
                        <span v-else class="flex-1 text-xs text-slate-600">{{ b.name }}</span>
                        <button v-if="renameBankItemDrafts[b.id] !== undefined" type="button" class="text-xs text-indigo-600 underline" @click="saveBankItemRename(b)">Save</button>
                        <button v-else type="button" class="text-xs text-slate-500 underline" @click="startRenameBankItem(b)">Rename</button>
                        <button type="button" class="text-xs text-red-500 underline" @click="deleteBankItem(b)">Delete</button>
                      </div>
                    </div>

                    <AppButton @click="addQuiz(module.id)">Add quiz</AppButton>
                  </div>

                  <div class="space-y-2 border-t border-slate-100 pt-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">New discussion</p>
                    <AppInput v-model="discussionForm(module.id).title" placeholder="Discussion title" />
                    <AppTextarea v-model="discussionForm(module.id).prompt" placeholder="Discussion prompt" :rows="2" />
                    <AppInput v-model="discussionForm(module.id).points_possible" type="number" min="0" placeholder="Points possible (optional — leave blank for ungraded)" />
                    <AppButton @click="addDiscussion(module.id)">Add discussion</AppButton>
                  </div>
                </div>
              </div>
            </AppCard>

            <div v-if="course.can_edit" class="flex gap-2">
              <AppInput v-model="newModuleTitle" placeholder="New module title" class="flex-1" />
              <AppButton @click="addModule"><PlusIcon class="h-4 w-4" /> Add module</AppButton>
            </div>

            <EmptyState v-if="course.modules.length === 0" title="No modules yet" subtitle="Add your first module to start building this course." />
          </div>
        </template>

        <template v-else>
          <div class="space-y-5">
            <AppCard v-for="announcement in course.announcements" :key="announcement.id">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-medium text-slate-800">{{ announcement.title }}</p>
                  <p class="text-xs text-slate-500">{{ announcement.posted_by }} — {{ new Date(announcement.posted_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
                </div>
                <AppIconButton v-if="course.can_edit" label="Delete announcement" variant="danger" @click="deleteAnnouncement(announcement.id)"><TrashIcon class="h-4 w-4" /></AppIconButton>
              </div>
              <p class="mt-2 whitespace-pre-line text-sm text-slate-600">{{ announcement.body }}</p>
            </AppCard>

            <EmptyState v-if="course.announcements.length === 0" title="No announcements yet" />

            <AppCard v-if="course.can_edit" title="Post announcement">
              <div class="space-y-2">
                <AppInput v-model="announcementForm.title" placeholder="Announcement title" />
                <AppTextarea v-model="announcementForm.body" placeholder="Announcement body" :rows="3" />
                <AppButton @click="postAnnouncement">Post announcement</AppButton>
              </div>
            </AppCard>
          </div>
        </template>
      </AppTabs>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 3: Compile-check**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npx vite build --outDir /tmp/learn-ui-build-check`
Expected: build succeeds with no errors mentioning `Learn/Show.vue`.

- [ ] **Step 4: Diff-review the script section against the original**

Run: `git diff resources/js/Pages/Learn/Show.vue | grep '^[+-]' | grep -v '^+++\|^---'` and manually confirm every removed/added line in the `<script setup>` portion is an import addition or the 3 new blocks (tabs/subjectInitials, cover handlers) — no existing function body should show as changed/removed.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Learn/Show.vue
git commit -m "feat(learn): redesign course page with cover banner and Overview/Modules/Announcements tabs"
```

---

### Task 14: Frontend — `StudentPortal/Learn/Index.vue` full rewrite

**Files:**
- Modify: `resources/js/Pages/StudentPortal/Learn/Index.vue`

**Interfaces:**
- Consumes: `courses[].cover_preset`, `courses[].cover_photo_url` (Task 7), `CourseCover` (Task 9)

- [ ] **Step 1: Replace the entire file content**

```vue
<script setup>
import { Head, Link } from '@inertiajs/vue3'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import CourseCover from '@/Components/CourseCover.vue'
import EmptyState from '@/Components/EmptyState.vue'
import { BookOpenIcon } from '@heroicons/vue/24/outline'

defineProps({ courses: Array })

function initialsFor(course) {
  return (course.subject_name || '').trim().split(/\s+/).map(w => w[0]).join('').slice(0, 3).toUpperCase()
}
</script>

<template>
  <Head title="My Courses" />
  <StudentPortalLayout>
    <div class="max-w-3xl mx-auto py-6 px-4">
      <h1 class="mb-4 font-heading text-lg font-semibold text-slate-800">My Courses</h1>

      <EmptyState v-if="courses.length === 0" :icon="BookOpenIcon" title="No published courses yet" />

      <div v-else class="grid gap-4 sm:grid-cols-2">
        <Link v-for="course in courses" :key="course.id" :href="route('student-portal.learn.show', course.id)" class="block">
          <AppCard :padded="false" class="h-full transition hover:shadow-md hover:ring-indigo-200">
            <CourseCover :photo-url="course.cover_photo_url" :preset="course.cover_preset" :initials="initialsFor(course)" class="h-24 w-full" />
            <div class="p-4">
              <p class="truncate text-sm font-medium text-slate-800">{{ course.subject_name }}</p>
              <p class="text-xs text-slate-500">{{ course.section_name }}</p>
            </div>
          </AppCard>
        </Link>
      </div>
    </div>
  </StudentPortalLayout>
</template>
```

- [ ] **Step 2: Compile-check**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npx vite build --outDir /tmp/learn-ui-build-check`
Expected: build succeeds with no errors.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/StudentPortal/Learn/Index.vue
git commit -m "feat(learn): add cover photos to Student Portal My Courses list"
```

---

### Task 15: Frontend — `StudentPortal/Learn/Show.vue` cover banner + `AppCard` re-skin

**Files:**
- Modify: `resources/js/Pages/StudentPortal/Learn/Show.vue`

**Interfaces:**
- Consumes: `course.cover_preset`/`cover_photo_url` (Task 7), `CourseCover` (Task 9)
- **Zero business-logic changes** — same rule as Task 13: only the header/imports/markup change, every existing function stays as-is.

- [ ] **Step 1: Confirm the current file matches**

Run: `sed -n '1,10p' resources/js/Pages/StudentPortal/Learn/Show.vue` — expect exactly:

```vue
<script setup>
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import DOMPurify from 'dompurify'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import { DocumentIcon, PaperClipIcon, AcademicCapIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'

defineProps({ course: Object })
```

If it differs, stop and reconcile before proceeding.

- [ ] **Step 2: Add cover imports and the `props`/`subjectInitials` binding**

Replace:

```vue
<script setup>
import { ref } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import DOMPurify from 'dompurify'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import { DocumentIcon, PaperClipIcon, AcademicCapIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'

defineProps({ course: Object })
```

with:

```vue
<script setup>
import { ref, computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import DOMPurify from 'dompurify'
import StudentPortalLayout from '@/Layouts/StudentPortalLayout.vue'
import AppCard from '@/Components/AppCard.vue'
import CourseCover from '@/Components/CourseCover.vue'
import { DocumentIcon, PaperClipIcon, AcademicCapIcon, ChatBubbleLeftRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ course: Object })
const subjectInitials = computed(() => (props.course.subject_name || '').trim().split(/\s+/).map(w => w[0]).join('').slice(0, 3).toUpperCase())
```

(Every other line in the script — `sanitizeHtml`, `safeVideoUrl`, `safeLinkUrl`, `submissionForm`/`pickFile`/`submitAssignment`, `startAttempt`/`continueAttempt` — is untouched. The template already references the bare `course` identifier directly, e.g. `course.subject_name` — that still works unchanged: Vue's `<script setup>` compiler exposes declared props to the template by name regardless of whether the return value of `defineProps` was captured; capturing it as `props` here is only needed so the new `subjectInitials` computed, which runs in the script block, can read `props.course`.)

- [ ] **Step 3: Replace the title block with a cover banner**

Replace:

```vue
    <div class="max-w-3xl mx-auto py-6 px-4 space-y-8">
      <div>
        <h1 class="text-lg font-semibold text-slate-800">{{ course.subject_name }}</h1>
        <p class="text-sm text-slate-500">{{ course.section_name }}</p>
      </div>
```

with:

```vue
    <div class="max-w-3xl mx-auto py-6 px-4 space-y-8">
      <div class="overflow-hidden rounded-2xl shadow-sm ring-1 ring-slate-200/70">
        <CourseCover :photo-url="course.cover_photo_url" :preset="course.cover_preset" :initials="subjectInitials" class="h-28 w-full" />
        <div class="bg-white px-4 py-4 sm:px-6">
          <h1 class="font-heading text-lg font-semibold text-slate-900">{{ course.subject_name }}</h1>
          <p class="text-sm text-slate-500">{{ course.section_name }}</p>
        </div>
      </div>
```

Leave everything below this (syllabus `<section>`, modules `<section>`, and all module/item/quiz/assignment/discussion rendering and submission forms) exactly as it currently is — this task only touches the opening title block.

- [ ] **Step 4: Compile-check**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npx vite build --outDir /tmp/learn-ui-build-check`
Expected: build succeeds with no errors.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/StudentPortal/Learn/Show.vue
git commit -m "feat(learn): add cover banner to Student Portal course page"
```

---

### Task 16: Final verification

**Files:** none (verification only)

- [ ] **Step 1: Full backend test suite**

Run: `docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Learn tests/Feature/StudentPortal"`
Expected: PASS, zero failures.

- [ ] **Step 2: Full frontend build**

Run: `cd /Users/junlou/bugsaymis-docker/src/bugsaymis && npx vite build`
Expected: build succeeds. Note any chunk-size warnings are pre-existing and unrelated (confirmed in the earlier ALP session).

- [ ] **Step 3: Clean up any temp build output**

Run: `rm -rf /tmp/learn-ui-build-check`

- [ ] **Step 4: Manual browser checklist (once logged in)**

Not automatable — Learn module login is Google OAuth-only. Once available, verify:
- `Learn/Index.vue`: card grid shows cover banners (default preset + initials for courses with no cover set), setup-progress sliver appears only on incomplete draft courses and disappears at 100%.
- `Learn/Show.vue`: hero banner shows the course cover; Overview tab shows the 4-step progress tracker advancing correctly as syllabus/modules/content/publish steps complete; cover preset picker and photo upload both work and reflect immediately; Modules tab preserves all existing authoring functionality (add page/file/assignment/quiz/discussion, reorder, publish toggle, delete) with zero behavior change; switching away from Modules tab and back does not lose in-progress form input; Announcements tab post/delete works.
- Read-only (past school year) course: no edit controls appear in any tab, read-only banner shows above the tabs.
- Student Portal: `Index.vue` shows cover banners; `Show.vue` shows the banner + unchanged content below.
- An existing unrelated `hero`-mode page (e.g. `CID/ALP/Index.vue`) still renders identically to before this plan — confirms the `AppPageHeader.vue` change is truly backward-compatible.

- [ ] **Step 5: Final commit (if the manual pass above required any fixes)**

```bash
git add -- <any files touched during manual verification fixes>
git commit -m "fix(learn): address manual QA findings from premium UI redesign"
```

(Skip this step entirely if manual verification found nothing to fix.)
