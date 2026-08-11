# Learn Module — Premium UI/UX Redesign

## Problem

The Learn module (`resources/js/Pages/Learn/Index.vue`, `Show.vue`, and the read-only mirror at `resources/js/Pages/StudentPortal/Learn/Index.vue`, `Show.vue`) was never touched by the app-wide Atlas UI harmonization pass. All four pages still use raw `<div>`/`<input>`/`<button>` markup with no `AppCard`/`AppTabs`/`AppBadge`/`AppButton`/`AppInput` components, no visual hierarchy, and no course imagery — they read as a first draft next to modules like ALP or AMS.

`Show.vue` in particular is a 648-line page with every section (Syllabus, Modules-with-nested-authoring-forms, Announcements) expanded at once in one long scroll — the Modules section alone contains page/file/assignment/quiz/discussion authoring UIs stacked on top of each other.

There is also no course "cover photo" anywhere (the class card grid on Index is a plain bordered row with a generic book icon) and no indication to a teacher of how far along they are in setting up a new class (write a syllabus, add modules, add content, publish).

## Scope

- `resources/js/Pages/Learn/Index.vue` — full redesign (card grid, cover photos, setup-progress indicator)
- `resources/js/Pages/Learn/Show.vue` — full redesign (hero header with cover banner, `AppTabs` split into Overview / Modules / Announcements, all sections reskinned with `App*` components)
- `resources/js/Pages/StudentPortal/Learn/Index.vue` and `Show.vue` — matching card + cover-banner visual treatment (read-only, no tabs — content there is already short and linear, no setup UI applies to students)
- `app/Models/Learn/Course.php`, `app/Http/Controllers/Learn/CourseController.php` — new `cover_photo_s3_key` / `cover_preset` columns, setup-progress computation, cover upload/select endpoints
- New `app/Services/Learn/CourseCoverService.php`, mirroring the existing `CourseFileService` base64→S3 pattern
- One additive migration (2 nullable columns on `learn_courses`)

Out of scope: any change to module/quiz/assignment/discussion business logic, validation rules, or route names — this is a visual + additive-feature pass. `LnD/LearningPrograms/*` (a different, already-modernized module) is untouched.

## Design

### 1. Data model (additive migration)

```php
Schema::table('learn_courses', function (Blueprint $table) {
    $table->string('cover_photo_s3_key')->nullable()->after('syllabus_body');
    $table->string('cover_preset')->nullable()->after('cover_photo_s3_key');
});
```

Both nullable — safe as a single-deploy additive change, no expand/contract needed. `cover_preset` stores a key like `indigo-diagonal`; the preset's actual gradient/pattern definition lives in frontend code (see §4), not the DB — swapping a preset's look later needs no migration. If both are null, the frontend renders a deterministic default (subject-initials on the first preset gradient) so cards never look broken pre-setup.

### 2. Cover photo service (backend)

New `CourseCoverService`, structurally identical to `CourseFileService`:
- `upload(int $courseId, string $dataUri): string` — decodes base64, validates `image/png|jpeg|webp` only (tighter allowlist than `CourseFileService`'s doc+image set — a cover is always an image), stores at `Learn/{courseId}/cover.{ext}`, returns the S3 key. Overwrites any previous cover key (old S3 object deleted).
- Reuses `CourseFileService`'s existing `encodeFileId`/`decodeFileId`/proxy-streaming pattern rather than duplicating it — `CourseFileService` gets a small `streamByKey(string $s3Key, string $mime)` extraction both services share, OR `CourseCoverService` composes `CourseFileService` for the streaming half. (Exact split decided during implementation — behaviorally both land on "private S3, proxied, never `disk('public')`".)

New routes (`web.php`, under existing `learn.` group, `can_edit`-gated):
- `PUT /learn/{course}/cover` — body is either `{ preset: 'indigo-diagonal' }` or `{ photo_base64: 'data:image/...' }`. Setting one clears the other (a course has exactly one active cover source).
- `GET /learn/{course}/cover` — private proxy stream, only reachable/meaningful when `cover_photo_s3_key` is set (route returns 404 otherwise; frontend never calls it in the preset case).

### 3. Setup-progress computation (backend)

New method on `Course` (or a small `CourseSetupProgressService` if it outgrows a model method):

```php
public function setupProgress(): array
{
    $steps = [
        ['key' => 'syllabus',  'label' => 'Write a syllabus',        'complete' => filled($this->syllabus_body)],
        ['key' => 'modules',   'label' => 'Add a module',            'complete' => $this->modules->isNotEmpty()],
        ['key' => 'content',   'label' => 'Add content to a module', 'complete' => $this->modules->contains(fn ($m) => $m->items->isNotEmpty())],
        ['key' => 'published', 'label' => 'Publish the course',      'complete' => $this->status === 'published'],
    ];

    return [
        'steps' => $steps,
        'percent' => (int) round(collect($steps)->where('complete', true)->count() / count($steps) * 100),
    ];
}
```

`CourseController::index()` includes only `setup_percent` (cheap, one int) per card. `CourseController::show()` includes the full `steps` array for the Overview tab's step tracker. Read-only, additive to both payloads — no existing field changes shape.

### 4. Cover presets (frontend constant, no DB/config split needed)

New `resources/js/Constants/courseCoverPresets.js` — 6 presets, all within the existing indigo/blue/slate Atlas palette (no subject-color-coding — this app's convention reserves color for real status, not decoration):

```js
export const COURSE_COVER_PRESETS = [
  { key: 'indigo-diagonal', label: 'Indigo diagonal', class: 'bg-gradient-to-br from-indigo-600 to-indigo-900' },
  { key: 'sky-wave',        label: 'Sky wave',         class: 'bg-gradient-to-tr from-sky-500 to-indigo-700' },
  { key: 'navy-radial',     label: 'Navy radial',      class: 'bg-[radial-gradient(circle_at_30%_20%,#0867DB,#0A2A5E)]' },
  { key: 'slate-grid',      label: 'Slate grid',       class: 'bg-slate-800' /* + subtle dot-grid overlay via ::before */ },
  { key: 'indigo-sunrise',  label: 'Indigo sunrise',   class: 'bg-gradient-to-b from-indigo-400 to-indigo-800' },
  { key: 'ocean-deep',      label: 'Ocean deep',       class: 'bg-gradient-to-br from-blue-600 via-indigo-700 to-slate-900' },
]
```

Default (no `cover_preset`/`cover_photo_s3_key` set) = `indigo-diagonal`. Every card/banner renders subject initials (e.g. "MATH") centered on the gradient as a watermark, so presets never look empty.

A small `CourseCover.vue` component wraps the render logic (`photo_url` present → `<img>`; else preset gradient + initials) so Index card, Show hero banner, and Student Portal both call one component instead of duplicating the branching.

### 5. `Learn/Index.vue` (faculty)

- `AppPageHeader` for "My Courses" (replaces the bare `<h1>`).
- Grid becomes `grid gap-4 sm:grid-cols-2 xl:grid-cols-3` (cards get visually heavier with a cover banner, so 3-up reads better than the current 2-up-only at desktop width).
- Each card: `AppCard :padded="false"` shell →
  - `CourseCover` banner, `h-28` object-cover/gradient, rounded top corners only.
  - Padded body (`p-4`): subject name, grade/section, status `AppBadge`, read-only `LockClosedIcon` badge (unchanged logic) — plus, **only for draft/incomplete courses**, a thin progress sliver (`h-1` bar + "3/4 set up") under the meta line. Hidden once `setup_percent === 100`, so a fully set-up card stays clean — matches the project's "status colors only for real status, don't over-signal" convention.
- Empty state (no courses yet) keeps its current copy, restyled into the `EmptyState` component used elsewhere instead of a bare bordered div.

### 6. `Learn/Show.vue` (faculty)

- `AppPageHeader hero` — but with the course's `CourseCover` rendered as the header's background (banner behind the title/subtitle, similar visual weight to ALP's hero header). This needs a new optional `cover` prop/slot on the shared `AppPageHeader.vue` component — additive and opt-in (undefined by default), so every other page currently using `AppPageHeader` renders exactly as it does today. Title = subject name, subtitle = "Grade X — Section", actions = status `AppBadge` + Publish/Unpublish `AppButton` + "Quiz trend" link (unchanged).
- Read-only banner (`is_read_only`) stays as an always-visible alert directly under the header, outside the tabs, restyled to the `bg-warning-50 border-warning-200` convention already used in ALP instead of its current ad-hoc amber classes.
- `AppTabs` with 3 tabs:
  - **Overview** — setup-progress step tracker (new `SetupProgressBar.vue`: 4 circles/checkmarks connected by a line, matching the visual language of a typical stepper, `complete`/`label` from `setupProgress()`), a "Course cover" card (upload button + the 6-preset picker grid, `can_edit`-gated), and the Syllabus card (existing `RichTextEditor`, unchanged behavior, now in an `AppCard`).
  - **Modules** — the existing authoring UI verbatim in behavior: same `router.post/put/delete` calls, same form state (`pageForm`, `assignmentForm`, `quizForm`, `discussionForm`, etc.), same validation. Pure re-skin: `AppCard` per module, `AppBadge` for publish state, `AppInput`/`AppSelect`/`AppTextarea`/`AppButton`/`AppIconButton` replacing every raw form element, icons kept. This is the highest line-count, highest-risk section — no logic branches change, only markup/classes.
  - **Announcements** — existing list + composer, same `router.post/delete` calls, reskinned into `AppCard`s.
- Tab-switching safety: all form state already lives in top-level `ref()`s keyed by module/quiz id (not local to a section that would unmount), so moving Modules content behind a `v-else-if="activeTab === 'modules'"` (same pattern the ALP fix just used) does not lose in-progress form input when a teacher switches tabs and back.

### 7. `StudentPortal/Learn/Index.vue` and `Show.vue`

Read-only mirror of the visual treatment, no tabs (content is already short/linear and there's no setup UI to gate):
- Index: same `CourseCover`-banner card grid as faculty Index, minus the progress sliver (not relevant to a student).
- Show: same `CourseCover` hero banner behind the title, minus the edit/publish controls it already doesn't have. Content below stays a single scroll (syllabus → modules → items), just reskinned into `AppCard`s for visual consistency — no functional change.

## Testing

Pure additive backend (2 new nullable columns, 2 new routes, 1 new read-only computed field) plus a large but behavior-preserving frontend re-skin. Plan:
- Any existing PHP feature tests covering `CourseController`/module/quiz/assignment/discussion endpoints must keep passing unchanged — no existing endpoint changes request/response shape except additive fields.
- New: feature test for the cover upload/select endpoint (preset happy path, photo upload happy path, rejects non-image mime, `can_edit`-gated 403 for non-instructors).
- Manual verification in dev browser once implemented: card grid (with and without cover set), preset picker, photo upload round-trip through the private proxy, tab switching in Show.vue preserves in-progress module/quiz form state, setup-progress bar advances correctly through all 4 steps, read-only course (past school year) hides all edit controls in every tab, Student Portal parity.
