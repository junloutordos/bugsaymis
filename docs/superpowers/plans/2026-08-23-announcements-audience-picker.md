# Announcements Audience Picker Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expose "Employees", "Students", and "Parents" as selectable audience groups in the Announcements admin UI, closing the gap between what the backend already supports and what an admin can actually select.

**Architecture:** Backend logic (`NoticeAudienceResolver`, `NotifyAnnouncementJob`, `Announcement::visibleTo()`/`visibleToAudienceGroup()`, mobile notice endpoints) already fully supports `audience` values `all|employees|students|parents|specific` and is already covered by passing tests (`tests/Feature/Notices/AnnouncementAudienceScopesTest.php`, `tests/Feature/Notices/NotifyAnnouncementJobTest.php`, `tests/Feature/Mobile/MobileNoticeControllerTest.php`). This plan changes exactly one Vue file: `resources/js/Pages/Administration/Announcements/Index.vue`. No PHP changes, no migrations, no new tests (the JS layer has no unit-test harness in this project — verification is a manual dev-server click-through, per project convention).

**Tech Stack:** Vue 3 `<script setup>`, Inertia.js, Tailwind CSS.

**Spec:** `docs/superpowers/specs/2026-08-23-announcements-sos-atlasgo-enhancements-design.md` (Section 1 — "Announcements: audience picker extension").

## Global Constraints

- No individual student/parent targeting — whole-group only (`employees`, `students`, `parents`, `all`, `specific`-employees-only). This was explicitly decided during design approval.
- Do not touch the migration comment (`// all | specific` in `2026_07_10_130000_create_announcements_tables.php`) — historical migrations are left alone per project convention even when a comment is stale.
- Never use `FormData`/multipart for the poster upload — this file doesn't touch that code path, but don't introduce one if refactoring nearby code.

---

### Task 1: Audience picker — expose Employees/Students/Parents, fix recipient badge

**Files:**
- Modify: `resources/js/Pages/Administration/Announcements/Index.vue:45-47` (feed badge)
- Modify: `resources/js/Pages/Administration/Announcements/Index.vue:142-155` (audience picker buttons)

**Interfaces:**
- Consumes: `form.audience` (existing `useForm` field, already validated server-side as `all|employees|students|parents|specific` — see `app/Http/Controllers/Administration/AnnouncementController.php:130`), `a.audience` / `a.target_count` (existing per-announcement props already sent by `AnnouncementController::index()`).
- Produces: nothing new — this task only changes what's rendered from existing data.

This is a single UI task — right-sized as one deliverable because the picker buttons and the feed badge that displays the chosen audience must change together (an admin could otherwise create a `students` announcement with no visual way to confirm it, since the current badge logic falls through to `null recipient(s)` for any non-`all`, non-`specific` audience).

- [ ] **Step 1: Fix the feed badge so it labels every audience value correctly**

Current code (`resources/js/Pages/Administration/Announcements/Index.vue:45-47`):

```vue
                  <AppBadge v-if="canManage" :color="a.audience === 'all' ? 'indigo' : 'blue'">
                    {{ a.audience === 'all' ? 'All users' : `${a.target_count} recipient(s)` }}
                  </AppBadge>
```

Replace with a small helper function plus a simplified template expression. First, add the helper in the `<script setup>` block, right after the existing `employeeName()` function (`resources/js/Pages/Administration/Announcements/Index.vue:257-259`):

```js
function audienceLabel(a) {
  if (a.audience === 'specific') return `${a.target_count} recipient(s)`
  return { all: 'All users', employees: 'Employees', students: 'Students', parents: 'Parents' }[a.audience] ?? a.audience
}
```

Then update the template badge to:

```vue
                  <AppBadge v-if="canManage" :color="a.audience === 'all' ? 'indigo' : 'blue'">
                    {{ audienceLabel(a) }}
                  </AppBadge>
```

- [ ] **Step 2: Extend the audience picker to 5 options**

Current code (`resources/js/Pages/Administration/Announcements/Index.vue:142-155`):

```vue
      <!-- Audience -->
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1.5">Audience *</label>
        <div class="inline-flex rounded-lg border border-slate-200 overflow-hidden text-sm">
          <button type="button" @click="form.audience = 'all'"
            :class="['px-4 py-2 font-medium transition-colors', form.audience === 'all' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            All Users
          </button>
          <button type="button" @click="form.audience = 'specific'"
            :class="['px-4 py-2 font-medium border-l border-slate-200 transition-colors', form.audience === 'specific' ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            Specific Users
          </button>
        </div>
      </div>
```

Replace with a 5-button variant driven by a small options array (keeps the template DRY instead of hand-repeating 5 near-identical buttons):

```vue
      <!-- Audience -->
      <div>
        <label class="block text-xs font-medium text-slate-600 mb-1.5">Audience *</label>
        <div class="inline-flex flex-wrap rounded-lg border border-slate-200 overflow-hidden text-sm">
          <button v-for="(opt, i) in audienceOptions" :key="opt.value" type="button"
            @click="form.audience = opt.value"
            :class="['px-4 py-2 font-medium transition-colors', i > 0 ? 'border-l border-slate-200' : '',
                     form.audience === opt.value ? 'bg-indigo-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50']">
            {{ opt.label }}
          </button>
        </div>
      </div>
```

Add the options array in `<script setup>`, right before the `form` declaration (`resources/js/Pages/Administration/Announcements/Index.vue:265`):

```js
const audienceOptions = [
  { value: 'all', label: 'All' },
  { value: 'employees', label: 'Employees' },
  { value: 'students', label: 'Students' },
  { value: 'parents', label: 'Parents' },
  { value: 'specific', label: 'Specific Users' },
]
```

- [ ] **Step 3: Manual verification (no JS test harness exists in this project)**

Start the dev environment and rebuild frontend assets:

```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"
```

(Or run `npm run dev` for hot-reload while checking manually — either is fine, `build` is shown for a one-shot check.)

In the browser at `http://localhost:8080`, navigate to Announcements as a user with `announcements.manage` (or `isSuperAdmin()`), open "New Announcement", and verify:
- The Audience row now shows 5 buttons: All, Employees, Students, Parents, Specific Users.
- Clicking each of "Employees", "Students", "Parents" selects it (highlighted indigo) and does **not** show the recipient search box (that box must only appear when "Specific Users" is selected — confirm the existing `v-if="form.audience === 'specific'"` on `resources/js/Pages/Administration/Announcements/Index.vue:158` still gates it correctly, unchanged by this task).
- Save one announcement per new audience value (`employees`, `students`, `parents`) as a draft, then confirm the feed badge reads "Employees" / "Students" / "Parents" respectively (not "null recipient(s)").
- Confirm an existing `all`-audience announcement still shows "All users" and a `specific`-audience one still shows "`N` recipient(s)" — the fix must not regress the two audience values that already worked.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Administration/Announcements/Index.vue
git commit -m "feat(announcements): expose employees/students/parents in the audience picker"
```

---

## Self-Review

**Spec coverage:** Section 1 of the spec ("Announcements: audience picker extension") is fully covered by Task 1 — 5-option picker, "Specific" behavior unchanged, no backend changes (confirmed already correct and already tested). The spec's testing note ("PHP feature test... likely already covered") was verified during planning: `AnnouncementAudienceScopesTest`, `NotifyAnnouncementJobTest`, and `MobileNoticeControllerTest` already assert `employees`/`students`/`parents` audience routing end-to-end, so no new backend test task was added.

**Placeholder scan:** No TBD/TODO; every step has literal, pasteable code.

**Type consistency:** `audienceLabel(a)` and `audienceOptions` are both defined and consumed within the same single task/file — no cross-task signature risk.
