# AtlasGo Foundation Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship SOS Phase B (native AtlasGo trigger), restructure the AtlasGo bottom nav (SOS replaces Digital ID's center slot, Digital ID moves to Profile), lay the "premium" design-system foundation on the touched screens, and let a student self-submit personal-info updates to their `students` row for registrar approval.

**Architecture:** Backend adds one new additive table (`student_profile_change_requests`) and two new mobile API controllers that delegate to existing/new services — no changes to the SOS engine or the legacy `students` write path beyond reusing `StudentProfileService`'s existing validation/casting. Flutter adds a `sos` feature module, extends `theme.dart`'s token set, swaps the nav's center button, and adds two new Profile entries (Digital ID relocation + a self-service update screen).

**Tech Stack:** Laravel 12 / PHP 8.4, PHPUnit, MySQL (dev Docker); Flutter (Riverpod, go_router, dio, Sanctum bearer auth), `geolocator` (new dependency).

**Spec:** `docs/superpowers/specs/2026-08-22-atlasgo-foundation-redesign-design.md`

## Global Constraints

- File uploads (none in this feature) would need base64 JSON per CLAUDE.md — not applicable here, no file uploads in scope.
- Migration is additive-only (new table) — no changes to existing table shapes, per this project's blue-green migration discipline.
- Self-service edits touch only the allowlisted `students` columns listed below — never identity/academic/encoding columns.
- Reuse `manage-students` (existing permission) for change-request review — do not introduce a new permission string for the same resource.
- Reuse `App\Services\StudentProfileService` (`app/Services/StudentProfileService.php`) for validation rules, NOT-NULL normalization, and the writable-column check — do not reimplement legacy-column casting logic.
- Flutter: follow the existing pattern of `FutureProvider.autoDispose` for GET data in `*_provider.dart` files and inline `ref.read(apiClientProvider).post(...)` calls inside screen `_submit()` methods for mutations (see `lib/src/features/portal/lost_found_screen.dart:429-458` and `portal_provider.dart:154-160`) — do not introduce a different state-management pattern.
- Editable-field allowlist (student self-service, exact list): `studentcontact`, `contactno1`, `contactno2`, `contactperson`, `contactperson2`, `relation1`, `relation2`, `contact_address1`, `contact_address2`, `contact_ofc_address1`, `contact_ofc_address2`, `contact_ofc_telno1`, `contact_ofc_telno2`, `bloodtype`, `religion`, `ethnic`, `nationality`, `student_email`, `houseno`, `barangay`, `municipal`, `district`, `province`, `zipcode`, `homeaddresstype`, `mcpno`, `fcpno`, `memailaddress`, `femailaddress`, `moccupation`, `foccupation`.
- Dev Docker artisan invocation: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan COMMAND"`.
- PHPUnit runs against the real shared dev MySQL DB (`phpunit.xml` hardcodes `DB_CONNECTION=mysql`) — `RefreshDatabase` rolls back per test, but real pre-existing rows persist across runs; seed test rows via `DB::table(...)->insertGetId()`, never assume a clean table.

---

## Task 1: `student_profile_change_requests` migration + model

**Files:**
- Create: `database/migrations/2026_08_22_120000_create_student_profile_change_requests_table.php`
- Create: `app/Models/StudentProfileChangeRequest.php`
- Test: `tests/Feature/StudentProfileChangeRequestModelTest.php`

**Interfaces:**
- Produces: `StudentProfileChangeRequest` Eloquent model — `student_id` (int), `requested_changes` (array, cast), `status` (string: `pending`/`approved`/`rejected`), `reviewed_by` (nullable int), `reviewed_at` (nullable Carbon), `review_notes` (nullable string), `reviewer()` belongsTo `App\Models\User`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\StudentProfileChangeRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentProfileChangeRequestModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_casts_requested_changes_to_array_and_defaults_to_pending(): void
    {
        $studentId = DB::table('students')->insertGetId([
            'lastname' => 'Test', 'firstname' => 'Model', 'status' => 'active',
        ]);

        $request = StudentProfileChangeRequest::create([
            'student_id' => $studentId,
            'requested_changes' => ['contactno1' => '09171234567'],
        ]);

        $this->assertSame('pending', $request->fresh()->status);
        $this->assertIsArray($request->fresh()->requested_changes);
        $this->assertSame('09171234567', $request->fresh()->requested_changes['contactno1']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentProfileChangeRequestModelTest"`
Expected: FAIL — table/class doesn't exist yet.

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
        Schema::create('student_profile_change_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->json('requested_changes');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_profile_change_requests');
    }
};
```

- [ ] **Step 4: Write the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfileChangeRequest extends Model
{
    protected $fillable = [
        'student_id', 'requested_changes', 'status',
        'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected $casts = [
        'requested_changes' => 'array',
        'reviewed_at' => 'datetime',
    ];

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
```

- [ ] **Step 5: Run the migration**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan migrate --path=database/migrations/2026_08_22_120000_create_student_profile_change_requests_table.php"`
Expected: `Migrated: 2026_08_22_120000_create_student_profile_change_requests_table`

- [ ] **Step 6: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentProfileChangeRequestModelTest"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_22_120000_create_student_profile_change_requests_table.php app/Models/StudentProfileChangeRequest.php tests/Feature/StudentProfileChangeRequestModelTest.php
git commit -m "feat(students): add student_profile_change_requests table + model"
```

---

## Task 2: `StudentProfileChangeRequestService`

**Files:**
- Create: `app/Services/StudentProfileChangeRequestService.php`
- Test: `tests/Feature/StudentProfileChangeRequestServiceTest.php`

**Interfaces:**
- Consumes: `App\Services\StudentProfileService` (`writableColumns()`, `validationRules()`, `normalizeForStorage()`), `App\Models\StudentProfileChangeRequest` (Task 1).
- Produces: `StudentProfileChangeRequestService::EDITABLE_FIELDS` (array constant), `currentValues(int $studentId): array`, `pendingRequest(int $studentId): ?StudentProfileChangeRequest`, `submit(int $studentId, array $changes): array{ok: bool, message: ?string, request: ?StudentProfileChangeRequest}`, `approve(StudentProfileChangeRequest $request, \App\Models\User $reviewer): void`, `reject(StudentProfileChangeRequest $request, \App\Models\User $reviewer, string $notes): void`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\StudentProfileChangeRequest;
use App\Models\User;
use App\Services\StudentProfileChangeRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentProfileChangeRequestServiceTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): int
    {
        return DB::table('students')->insertGetId([
            'lastname' => 'Cruz', 'firstname' => 'Juan', 'status' => 'active',
            'contactno1' => '09170000000', 'lrn' => '999999999999',
        ]);
    }

    public function test_submit_rejects_a_field_outside_the_allowlist(): void
    {
        $studentId = $this->makeStudent();
        $service = app(StudentProfileChangeRequestService::class);

        $result = $service->submit($studentId, ['lrn' => '111111111111']);

        $this->assertFalse($result['ok']);
        $this->assertDatabaseCount('student_profile_change_requests', 0);
    }

    public function test_submit_creates_a_pending_request_for_allowlisted_fields(): void
    {
        $studentId = $this->makeStudent();
        $service = app(StudentProfileChangeRequestService::class);

        $result = $service->submit($studentId, ['contactno1' => '09171234567']);

        $this->assertTrue($result['ok']);
        $this->assertSame('pending', $result['request']->status);
        $this->assertSame('09171234567', $result['request']->requested_changes['contactno1']);
    }

    public function test_submit_rejects_a_second_request_while_one_is_pending(): void
    {
        $studentId = $this->makeStudent();
        $service = app(StudentProfileChangeRequestService::class);
        $service->submit($studentId, ['contactno1' => '09171234567']);

        $result = $service->submit($studentId, ['contactno1' => '09179999999']);

        $this->assertFalse($result['ok']);
        $this->assertDatabaseCount('student_profile_change_requests', 1);
    }

    public function test_approve_writes_the_diff_to_students_and_updates_date_updated(): void
    {
        $studentId = $this->makeStudent();
        $reviewer = User::factory()->create();
        $service = app(StudentProfileChangeRequestService::class);
        $submitted = $service->submit($studentId, ['contactno1' => '09171234567'])['request'];

        $service->approve($submitted, $reviewer);

        $student = DB::table('students')->where('id', $studentId)->first();
        $this->assertSame('09171234567', $student->contactno1);
        $this->assertSame('approved', $submitted->fresh()->status);
        $this->assertSame($reviewer->id, $submitted->fresh()->reviewed_by);
        $this->assertNotNull($submitted->fresh()->reviewed_at);
    }

    public function test_reject_requires_notes_and_leaves_students_row_untouched(): void
    {
        $studentId = $this->makeStudent();
        $reviewer = User::factory()->create();
        $service = app(StudentProfileChangeRequestService::class);
        $submitted = $service->submit($studentId, ['contactno1' => '09171234567'])['request'];

        $service->reject($submitted, $reviewer, 'Number does not match records on file.');

        $student = DB::table('students')->where('id', $studentId)->first();
        $this->assertSame('09170000000', $student->contactno1);
        $this->assertSame('rejected', $submitted->fresh()->status);
        $this->assertSame('Number does not match records on file.', $submitted->fresh()->review_notes);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentProfileChangeRequestServiceTest"`
Expected: FAIL — class doesn't exist yet.

- [ ] **Step 3: Write the service**

```php
<?php

namespace App\Services;

use App\Models\StudentProfileChangeRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class StudentProfileChangeRequestService
{
    /**
     * Personal/contact-info columns a student may self-request changes to.
     * Deliberately excludes identity, academic, and encoding columns — see
     * the design spec's non-goals and StudentProfileService::WRITABLE_COLUMNS,
     * of which this is a strict subset.
     */
    public const EDITABLE_FIELDS = [
        'studentcontact', 'contactno1', 'contactno2', 'contactperson', 'contactperson2',
        'relation1', 'relation2', 'contact_address1', 'contact_address2',
        'contact_ofc_address1', 'contact_ofc_address2', 'contact_ofc_telno1', 'contact_ofc_telno2',
        'bloodtype', 'religion', 'ethnic', 'nationality', 'student_email',
        'houseno', 'barangay', 'municipal', 'district', 'province', 'zipcode', 'homeaddresstype',
        'mcpno', 'fcpno', 'memailaddress', 'femailaddress', 'moccupation', 'foccupation',
    ];

    public function __construct(private readonly StudentProfileService $profileService) {}

    public function currentValues(int $studentId): array
    {
        $row = DB::table('students')->where('id', $studentId)->first(self::EDITABLE_FIELDS);

        return $row ? (array) $row : [];
    }

    public function pendingRequest(int $studentId): ?StudentProfileChangeRequest
    {
        return StudentProfileChangeRequest::where('student_id', $studentId)
            ->where('status', 'pending')
            ->first();
    }

    /**
     * @return array{ok: bool, message: ?string, request: ?StudentProfileChangeRequest}
     */
    public function submit(int $studentId, array $changes): array
    {
        if ($this->pendingRequest($studentId)) {
            return ['ok' => false, 'message' => 'You already have an update awaiting review. Please wait for a decision before submitting another.', 'request' => null];
        }

        $disallowed = array_diff(array_keys($changes), self::EDITABLE_FIELDS);
        if ($disallowed !== []) {
            return ['ok' => false, 'message' => 'One or more fields cannot be self-updated.', 'request' => null];
        }

        if ($changes === []) {
            return ['ok' => false, 'message' => 'No changes were submitted.', 'request' => null];
        }

        $columns = collect(DB::select('SHOW COLUMNS FROM students'));
        $rules = collect($this->profileService->validationRules($columns))
            ->only(self::EDITABLE_FIELDS)
            ->all();

        $validator = Validator::make($changes, $rules);
        if ($validator->fails()) {
            return ['ok' => false, 'message' => $validator->errors()->first(), 'request' => null];
        }

        $request = StudentProfileChangeRequest::create([
            'student_id' => $studentId,
            'requested_changes' => $changes,
        ]);

        return ['ok' => true, 'message' => null, 'request' => $request];
    }

    public function approve(StudentProfileChangeRequest $request, User $reviewer): void
    {
        $columns = collect(DB::select('SHOW COLUMNS FROM students'));
        $changes = $this->profileService->normalizeForStorage($request->requested_changes, $columns);

        if (Schema::hasColumn('students', 'date_updated')) {
            $changes['date_updated'] = now()->format('Y-m-d');
        }

        DB::table('students')->where('id', $request->student_id)->update($changes);

        $request->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }

    public function reject(StudentProfileChangeRequest $request, User $reviewer, string $notes): void
    {
        $request->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => $notes,
        ]);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentProfileChangeRequestServiceTest"`
Expected: PASS (5 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/StudentProfileChangeRequestService.php tests/Feature/StudentProfileChangeRequestServiceTest.php
git commit -m "feat(students): add self-service profile change-request service"
```

---

## Task 3: Registrar review UI (web) — controller, routes, Vue page

**Files:**
- Create: `app/Http/Controllers/StudentProfileChangeRequestController.php`
- Modify: `routes/web.php:1493` (insert after the existing students photo routes, before `// Library attendance (authenticated view)`)
- Create: `resources/js/Pages/Students/ChangeRequests/Index.vue`
- Modify: `resources/js/Pages/Students/Index.vue:380` (add actions slot button)
- Modify: `app/Http/Controllers/StudentController.php` `index()` method — add `pending_change_requests_count` prop
- Test: `tests/Feature/StudentProfileChangeRequestControllerTest.php`

**Interfaces:**
- Consumes: `StudentProfileChangeRequestService` (Task 2), `StudentProfileChangeRequest` model (Task 1).
- Produces: routes `students.change-requests.index` (GET `/students/change-requests`), `students.change-requests.approve` (POST `/students/change-requests/{changeRequest}/approve`), `students.change-requests.reject` (POST `/students/change-requests/{changeRequest}/reject`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\StudentProfileChangeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentProfileChangeRequestControllerTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    public function test_guest_cannot_view_change_requests(): void
    {
        $this->get(route('students.change-requests.index'))->assertRedirect(route('login'));
    }

    public function test_admin_can_approve_a_pending_request(): void
    {
        $studentId = DB::table('students')->insertGetId(['lastname' => 'Reyes', 'firstname' => 'Ana', 'status' => 'active', 'contactno1' => '09170000000']);
        $changeRequest = StudentProfileChangeRequest::create(['student_id' => $studentId, 'requested_changes' => ['contactno1' => '09171234567']]);

        $this->actingAs($this->adminUser())
            ->post(route('students.change-requests.approve', $changeRequest))
            ->assertRedirect();

        $this->assertSame('approved', $changeRequest->fresh()->status);
        $this->assertSame('09171234567', DB::table('students')->where('id', $studentId)->value('contactno1'));
    }

    public function test_admin_reject_requires_notes(): void
    {
        $studentId = DB::table('students')->insertGetId(['lastname' => 'Reyes', 'firstname' => 'Ana', 'status' => 'active']);
        $changeRequest = StudentProfileChangeRequest::create(['student_id' => $studentId, 'requested_changes' => ['contactno1' => '09171234567']]);

        $this->actingAs($this->adminUser())
            ->post(route('students.change-requests.reject', $changeRequest), [])
            ->assertSessionHasErrors('review_notes');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentProfileChangeRequestControllerTest"`
Expected: FAIL — route `students.change-requests.index` not defined.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\StudentProfileChangeRequest;
use App\Services\StudentProfileChangeRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StudentProfileChangeRequestController extends Controller
{
    public function index()
    {
        $this->authorize('manage-students');

        $requests = StudentProfileChangeRequest::with('reviewer')
            ->orderByRaw("status = 'pending' desc")
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($r) {
                $student = DB::table('students')->where('id', $r->student_id)->first(['firstname', 'lastname', 'pisaysystemID']);

                return [
                    'id' => $r->id,
                    'student_name' => $student ? trim("{$student->lastname}, {$student->firstname}") : 'Unknown',
                    'pisaysystemID' => $student->pisaysystemID ?? null,
                    'requested_changes' => $r->requested_changes,
                    'status' => $r->status,
                    'reviewer' => $r->reviewer?->name,
                    'reviewed_at' => $r->reviewed_at?->format('M d, Y g:i A'),
                    'review_notes' => $r->review_notes,
                    'submitted_at' => $r->created_at->format('M d, Y g:i A'),
                ];
            });

        return Inertia::render('Students/ChangeRequests/Index', [
            'requests' => $requests,
        ]);
    }

    public function approve(StudentProfileChangeRequest $changeRequest, StudentProfileChangeRequestService $service)
    {
        $this->authorize('manage-students');
        abort_unless($changeRequest->status === 'pending', 422, 'This request has already been reviewed.');

        $service->approve($changeRequest, auth()->user());

        return back()->with('success', 'Update approved and applied to the student record.');
    }

    public function reject(Request $request, StudentProfileChangeRequest $changeRequest, StudentProfileChangeRequestService $service)
    {
        $this->authorize('manage-students');
        abort_unless($changeRequest->status === 'pending', 422, 'This request has already been reviewed.');

        $validated = $request->validate(['review_notes' => 'required|string|max:500']);

        $service->reject($changeRequest, auth()->user(), $validated['review_notes']);

        return back()->with('success', 'Update request rejected.');
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/web.php`, immediately after line 1493 (`Route::post('/students/{id}/photo', ...)`):

```php
    Route::get('/students/change-requests', [\App\Http\Controllers\StudentProfileChangeRequestController::class, 'index'])->name('students.change-requests.index');
    Route::post('/students/change-requests/{changeRequest}/approve', [\App\Http\Controllers\StudentProfileChangeRequestController::class, 'approve'])->name('students.change-requests.approve');
    Route::post('/students/change-requests/{changeRequest}/reject', [\App\Http\Controllers\StudentProfileChangeRequestController::class, 'reject'])->name('students.change-requests.reject');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentProfileChangeRequestControllerTest"`
Expected: PASS (3 tests)

- [ ] **Step 6: Add the pending count prop to `StudentController::index()`**

In `app/Http/Controllers/StudentController.php`, find the `return Inertia::render('Students/Index', [` block inside `index()` and add a new key to the props array:

```php
            'pending_change_requests_count' => \App\Models\StudentProfileChangeRequest::where('status', 'pending')->count(),
```

- [ ] **Step 7: Write the Vue admin page**

```vue
<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import AppPageHeader from '@/Components/AppPageHeader.vue'
import AppTable from '@/Components/AppTable.vue'
import AppButton from '@/Components/AppButton.vue'
import { CheckIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ requests: Array })

const rejecting = ref(null)
const rejectNotes = ref('')

function approve(req) {
  router.post(route('students.change-requests.approve', req.id), {}, { preserveScroll: true })
}

function openReject(req) {
  rejecting.value = req
  rejectNotes.value = ''
}

function submitReject() {
  router.post(route('students.change-requests.reject', rejecting.value.id), { review_notes: rejectNotes.value }, {
    preserveScroll: true,
    onSuccess: () => { rejecting.value = null },
  })
}

function statusColor(status) {
  return { pending: 'text-amber-700 bg-amber-50', approved: 'text-emerald-700 bg-emerald-50', rejected: 'text-slate-500 bg-slate-100' }[status]
}
</script>

<template>
  <Head title="Student Update Requests" />
  <AdminLayout title="Student Update Requests">
    <div class="space-y-5">
      <AppPageHeader title="Student Update Requests" subtitle="Self-submitted personal-info changes awaiting registrar review" />

      <AppTable :is-empty="requests.length === 0" :skeleton-cols="5">
        <template #head>
          <tr>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Student</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Requested Changes</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Submitted</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Status</th>
            <th class="px-4 py-3 text-left text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
          </tr>
        </template>

        <tr v-for="req in requests" :key="req.id" class="hover:bg-indigo-50/40">
          <td class="px-4 py-3 text-sm">
            <div class="font-medium text-slate-800">{{ req.student_name }}</div>
            <div class="text-xs text-slate-400">{{ req.pisaysystemID }}</div>
          </td>
          <td class="px-4 py-3 text-sm text-slate-700">
            <ul class="space-y-0.5">
              <li v-for="(value, field) in req.requested_changes" :key="field">
                <span class="text-slate-400">{{ field }}:</span> {{ value || '—' }}
              </li>
            </ul>
          </td>
          <td class="px-4 py-3 text-sm text-slate-500">{{ req.submitted_at }}</td>
          <td class="px-4 py-3 text-sm">
            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusColor(req.status)">{{ req.status }}</span>
            <div v-if="req.status === 'rejected' && req.review_notes" class="mt-1 text-xs text-slate-400">{{ req.review_notes }}</div>
          </td>
          <td class="px-4 py-3 text-sm">
            <div v-if="req.status === 'pending'" class="flex gap-2">
              <AppButton size="sm" @click="approve(req)"><CheckIcon class="h-4 w-4" /> Approve</AppButton>
              <AppButton size="sm" variant="secondary" @click="openReject(req)"><XMarkIcon class="h-4 w-4" /> Reject</AppButton>
            </div>
            <span v-else class="text-xs text-slate-400">{{ req.reviewer }} · {{ req.reviewed_at }}</span>
          </td>
        </tr>
      </AppTable>
    </div>

    <div v-if="rejecting" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4">
      <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
        <h2 class="mb-3 text-lg font-semibold text-slate-900">Reject update for {{ rejecting.student_name }}</h2>
        <textarea v-model="rejectNotes" rows="3" placeholder="Reason for rejection (required)"
          class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
        <div class="mt-4 flex justify-end gap-2">
          <AppButton size="sm" variant="secondary" @click="rejecting = null">Cancel</AppButton>
          <AppButton size="sm" :disabled="!rejectNotes.trim()" @click="submitReject">Reject</AppButton>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
```

- [ ] **Step 8: Link the page from `Students/Index.vue`**

In `resources/js/Pages/Students/Index.vue`, at line 380, add an actions slot to `AppPageHeader`:

```vue
      <AppPageHeader title="Students" subtitle="Browse and view student records">
        <template #actions>
          <AppButton size="sm" variant="secondary" as="a" :href="route('students.change-requests.index')">
            Pending Update Requests
            <span v-if="pending_change_requests_count" class="ml-1.5 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700">{{ pending_change_requests_count }}</span>
          </AppButton>
        </template>
      </AppPageHeader>
```

(Replace the existing self-closing `<AppPageHeader title="Students" subtitle="Browse and view student records" />` line with this.) Also add `pending_change_requests_count` to the component's `defineProps`.

- [ ] **Step 9: Build frontend assets**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/StudentProfileChangeRequestController.php app/Http/Controllers/StudentController.php routes/web.php resources/js/Pages/Students/ChangeRequests/Index.vue resources/js/Pages/Students/Index.vue tests/Feature/StudentProfileChangeRequestControllerTest.php
git commit -m "feat(students): add registrar review UI for self-service change requests"
```

---

## Task 4: Mobile SOS trigger endpoint

**Files:**
- Create: `app/Http/Controllers/StudentAttendance/Api/StudentSosController.php`
- Modify: `routes/api.php` — add import + two routes inside the `student.portal.` group (after line 90, the `lost-found/photo/{item}` route)
- Test: `tests/Feature/Mobile/StudentSosTriggerTest.php`

**Interfaces:**
- Consumes: `App\Services\Sos\SosAlertService::trigger()` (existing, unchanged).
- Produces: `GET /api/mobile/student/portal/sos/config` (name `mobile.student.portal.sos.config`), `POST /api/mobile/student/portal/sos/trigger` (name `mobile.student.portal.sos.trigger`) — same request/response contract as `StudentPortal\SosAlertController::trigger()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Mobile;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentSosTriggerTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): Student
    {
        $id = DB::table('students')->insertGetId(['lastname' => 'Santos', 'firstname' => 'Liza', 'status' => 'active']);

        return Student::find($id);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->postJson('/api/mobile/student/portal/sos/trigger', ['alert_type' => 'medical'])
            ->assertStatus(401);
    }

    public function test_config_returns_hold_and_countdown_seconds(): void
    {
        Sanctum::actingAs($this->makeStudent(), ['*']);

        $this->getJson('/api/mobile/student/portal/sos/config')
            ->assertOk()
            ->assertJsonStructure(['hold_confirm_seconds', 'countdown_seconds', 'emergency_hotline_number']);
    }

    public function test_trigger_creates_an_alert_for_the_authenticated_student(): void
    {
        $student = $this->makeStudent();
        Sanctum::actingAs($student, ['*']);

        $response = $this->postJson('/api/mobile/student/portal/sos/trigger', [
            'alert_type' => 'medical',
            'is_silent' => false,
        ]);

        $response->assertStatus(201)->assertJson(['blocked' => false]);
        $this->assertDatabaseHas('sos_alerts', [
            'triggerable_type' => Student::class,
            'triggerable_id' => $student->id,
            'alert_type' => 'medical',
        ]);
    }

    public function test_invalid_alert_type_is_rejected(): void
    {
        Sanctum::actingAs($this->makeStudent(), ['*']);

        $this->postJson('/api/mobile/student/portal/sos/trigger', ['alert_type' => 'not_a_type'])
            ->assertStatus(422);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentSosTriggerTest"`
Expected: FAIL — 404, route not defined.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Services\Sos\SosAlertService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SOS trigger for the AtlasGo mobile app — mirrors
 * App\Http\Controllers\StudentPortal\SosAlertController::trigger() exactly,
 * calling the same SosAlertService, just resolving the student via the
 * Sanctum-authenticated request instead of a web session.
 */
class StudentSosController extends Controller
{
    /**
     * GET /api/mobile/student/portal/sos/config
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'hold_confirm_seconds' => config('sos.hold_confirm_seconds'),
            'countdown_seconds' => config('sos.countdown_seconds'),
            'emergency_hotline_number' => config('sos.emergency_hotline_number'),
        ]);
    }

    /**
     * POST /api/mobile/student/portal/sos/trigger
     */
    public function trigger(Request $request, SosAlertService $service): JsonResponse
    {
        $validated = $request->validate([
            'alert_type' => 'required|in:medical,security,fire_disaster,general',
            'is_silent' => 'boolean',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
            'accuracy' => 'nullable|numeric',
        ]);

        $result = $service->trigger(
            triggerable: $request->user(),
            alertType: $validated['alert_type'],
            isSilent: $validated['is_silent'] ?? false,
            lat: $validated['lat'] ?? null,
            lng: $validated['lng'] ?? null,
            accuracy: $validated['accuracy'] ?? null,
            ip: $request->ip(),
        );

        if ($result['blocked']) {
            return response()->json([
                'blocked' => true,
                'message' => config('sos.off_campus_message'),
                'emergency_hotline' => config('sos.emergency_hotline_number'),
            ], 422);
        }

        return response()->json(['blocked' => false, 'alert_id' => $result['alert']->id], 201);
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/api.php`, add the import near the other `StudentAttendance\Api` imports (line 12, after `StudentSelfController`):

```php
use App\Http\Controllers\StudentAttendance\Api\StudentSosController;
```

Then, inside the `Route::prefix('student')->name('student.')->group` → `Route::prefix('portal')->name('portal.')->group` block, immediately after the `lost-found/photo/{item}` route (the last route in that group):

```php
                Route::get('/sos/config', [StudentSosController::class, 'config'])->name('sos.config');
                Route::post('/sos/trigger', [StudentSosController::class, 'trigger'])->name('sos.trigger')
                    ->middleware('throttle:10,1');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentSosTriggerTest"`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/StudentAttendance/Api/StudentSosController.php routes/api.php tests/Feature/Mobile/StudentSosTriggerTest.php
git commit -m "feat(sos): add AtlasGo mobile SOS trigger endpoint (Phase B)"
```

---

## Task 5: Mobile self-service profile-update endpoints

**Files:**
- Create: `app/Http/Controllers/StudentAttendance/Api/StudentProfileChangeRequestApiController.php`
- Modify: `routes/api.php` — add import + two routes in the same `student.portal.` group
- Test: `tests/Feature/Mobile/StudentProfileChangeRequestApiTest.php`

**Interfaces:**
- Consumes: `StudentProfileChangeRequestService` (Task 2).
- Produces: `GET /api/mobile/student/portal/profile-update` (name `mobile.student.portal.profile-update.show`), `POST /api/mobile/student/portal/profile-update` (name `mobile.student.portal.profile-update.store`).

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Mobile;

use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StudentProfileChangeRequestApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeStudent(): Student
    {
        $id = DB::table('students')->insertGetId([
            'lastname' => 'Dizon', 'firstname' => 'Marco', 'status' => 'active', 'contactno1' => '09170000000',
        ]);

        return Student::find($id);
    }

    public function test_show_returns_current_values_and_editable_fields(): void
    {
        Sanctum::actingAs($this->makeStudent(), ['*']);

        $response = $this->getJson('/api/mobile/student/portal/profile-update');

        $response->assertOk()->assertJsonStructure(['current', 'editable_fields', 'pending']);
        $this->assertSame('09170000000', $response->json('current.contactno1'));
        $this->assertNull($response->json('pending'));
    }

    public function test_store_submits_a_pending_request(): void
    {
        Sanctum::actingAs($this->makeStudent(), ['*']);

        $this->postJson('/api/mobile/student/portal/profile-update', ['changes' => ['contactno1' => '09171234567']])
            ->assertStatus(201);

        $this->assertDatabaseHas('student_profile_change_requests', ['status' => 'pending']);
    }

    public function test_store_rejects_a_disallowed_field(): void
    {
        Sanctum::actingAs($this->makeStudent(), ['*']);

        $this->postJson('/api/mobile/student/portal/profile-update', ['changes' => ['lrn' => '111111111111']])
            ->assertStatus(422);
    }

    public function test_show_surfaces_a_pending_request_after_submission(): void
    {
        Sanctum::actingAs($student = $this->makeStudent(), ['*']);
        $this->postJson('/api/mobile/student/portal/profile-update', ['changes' => ['contactno1' => '09171234567']]);

        $response = $this->getJson('/api/mobile/student/portal/profile-update');

        $this->assertSame('09171234567', $response->json('pending.requested_changes.contactno1'));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentProfileChangeRequestApiTest"`
Expected: FAIL — 404, route not defined.

- [ ] **Step 3: Write the controller**

```php
<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Services\StudentProfileChangeRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentProfileChangeRequestApiController extends Controller
{
    /**
     * GET /api/mobile/student/portal/profile-update
     */
    public function show(Request $request, StudentProfileChangeRequestService $service): JsonResponse
    {
        $studentId = $request->user()?->id;
        if (! $studentId) {
            return response()->json(['message' => 'Student account not fully set up.'], 404);
        }

        $pending = $service->pendingRequest($studentId);

        return response()->json([
            'current' => $service->currentValues($studentId),
            'editable_fields' => StudentProfileChangeRequestService::EDITABLE_FIELDS,
            'pending' => $pending ? [
                'requested_changes' => $pending->requested_changes,
                'submitted_at' => $pending->created_at->toIso8601String(),
            ] : null,
        ]);
    }

    /**
     * POST /api/mobile/student/portal/profile-update
     */
    public function store(Request $request, StudentProfileChangeRequestService $service): JsonResponse
    {
        $studentId = $request->user()?->id;
        if (! $studentId) {
            return response()->json(['message' => 'Student account not fully set up.'], 404);
        }

        $changes = $request->input('changes', []);
        $result = $service->submit($studentId, is_array($changes) ? $changes : []);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], 422);
        }

        return response()->json(['message' => 'Update request submitted for registrar review.'], 201);
    }
}
```

- [ ] **Step 4: Add the routes**

In `routes/api.php`, add the import next to `StudentSosController`'s:

```php
use App\Http\Controllers\StudentAttendance\Api\StudentProfileChangeRequestApiController;
```

Then, in the same `student.portal.` group, immediately after the SOS routes added in Task 4:

```php
                Route::get('/profile-update', [StudentProfileChangeRequestApiController::class, 'show'])->name('profile-update.show');
                Route::post('/profile-update', [StudentProfileChangeRequestApiController::class, 'store'])->name('profile-update.store')
                    ->middleware('throttle:6,1');
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentProfileChangeRequestApiTest"`
Expected: PASS (4 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/StudentAttendance/Api/StudentProfileChangeRequestApiController.php routes/api.php tests/Feature/Mobile/StudentProfileChangeRequestApiTest.php
git commit -m "feat(students): add mobile self-service profile-update endpoints"
```

---

## Task 6: Flutter — add `geolocator` + platform location permissions

**Files:**
- Modify: `pubspec.yaml`
- Modify: `android/app/src/main/AndroidManifest.xml:2-3`
- Modify: `ios/Runner/Info.plist:40-43`

**Interfaces:**
- Produces: `geolocator` package available for import in Task 8.

- [ ] **Step 1: Add the dependency**

In `pubspec.yaml`, under `dependencies:`, add after the `image_picker` line:

```yaml
  # One-time location read for the SOS on-campus check (Phase B)
  geolocator: ^13.0.2
```

- [ ] **Step 2: Fetch packages**

Run: `cd ~/bugsaymis-mobile && flutter pub get`
Expected: `Got dependencies!` with `geolocator` resolved, no version conflicts.

- [ ] **Step 3: Add the Android permission**

In `android/app/src/main/AndroidManifest.xml`, after line 3 (`POST_NOTIFICATIONS`):

```xml
    <uses-permission android:name="android.permission.ACCESS_FINE_LOCATION"/>
    <uses-permission android:name="android.permission.ACCESS_COARSE_LOCATION"/>
```

- [ ] **Step 4: Add the iOS permission**

In `ios/Runner/Info.plist`, after line 43 (`NSPhotoLibraryUsageDescription` value):

```xml
	<key>NSLocationWhenInUseUsageDescription</key>
	<string>Used once, only when you trigger SOS, to confirm you're on campus so the right responders are notified.</string>
```

- [ ] **Step 5: Verify the app still builds**

Run: `cd ~/bugsaymis-mobile && flutter analyze`
Expected: no new errors (existing warnings, if any, unchanged).

- [ ] **Step 6: Commit**

```bash
cd ~/bugsaymis-mobile && git add pubspec.yaml pubspec.lock android/app/src/main/AndroidManifest.xml ios/Runner/Info.plist
git commit -m "chore(sos): add geolocator dependency + location permission declarations"
```

---

## Task 7: Flutter — design tokens (`AppElevation`, `AppSpacing`, `AppMotion`)

**Files:**
- Modify: `lib/src/core/theme.dart` (add three new token classes; existing `AppColors`/`AppRadius`/`AppGradients`/`AppTextStyles`/`kCardShadow`/`kFormShadow`/`kNavShadow` are unchanged and untouched — `AppElevation` names the same values plus one new tier, it does not replace them)

**Interfaces:**
- Produces: `AppElevation.resting/raised/floating` (`List<BoxShadow>` constants — `resting` = the existing `kCardShadow` values, `floating` = a new, deeper tier for elements that must stand out more, e.g. a raised nav button), `AppSpacing.xs/sm/md/lg/xl/xxl` (double constants: 4/8/12/16/24/32), `AppMotion.fast/base/slow` (Duration constants: 150ms/220ms/320ms) and `AppMotion.standard` (a `Curves.easeOutCubic` curve constant) — consumed by Task 8 and Tasks 9-12.

- [ ] **Step 1: Add the token classes**

In `lib/src/core/theme.dart`, immediately after the closing brace of `class AppRadius { ... }` (after line 50, before the `// ── Gradients ──` comment at line 52):

```dart
// ── Spacing scale ─────────────────────────────────────────────────────────────

/// Named spacing scale — replaces ad hoc EdgeInsets/SizedBox magic numbers
/// in screens built or upgraded as part of the design-system foundation.
class AppSpacing {
  AppSpacing._();

  static const double xs = 4;
  static const double sm = 8;
  static const double md = 12;
  static const double lg = 16;
  static const double xl = 24;
  static const double xxl = 32;
}

// ── Motion tokens ────────────────────────────────────────────────────────────

/// Named animation durations/curves — replaces scattered per-widget
/// `Duration(milliseconds: N)` literals in screens built or upgraded as
/// part of the design-system foundation.
class AppMotion {
  AppMotion._();

  static const Duration fast = Duration(milliseconds: 150);
  static const Duration base = Duration(milliseconds: 220);
  static const Duration slow = Duration(milliseconds: 320);
  static const Curve standard = Curves.easeOutCubic;
}
```

Then, in the `// ── Shadows ──` section (after line 333, following the existing `kNavShadow` constant — the file's shadow constants stay exactly as they are, this only adds a named wrapper around them plus one new tier):

```dart
// ── Elevation scale ──────────────────────────────────────────────────────────

/// Named elevation tiers for the design-system foundation. `resting` and
/// `raised` alias the existing [kCardShadow]/[kFormShadow] values so every
/// current usage of those constants stays visually identical — this only
/// gives the scale a name. `floating` is new: a deeper tier for elements
/// that must read as detached from the page (e.g. a raised nav button).
class AppElevation {
  AppElevation._();

  static const List<BoxShadow> resting = kCardShadow;
  static const List<BoxShadow> raised = kFormShadow;
  static const List<BoxShadow> floating = [
    BoxShadow(
      color: Color(0x1A000000),
      blurRadius: 28,
      spreadRadius: 0,
      offset: Offset(0, 10),
    ),
    BoxShadow(
      color: Color(0x0A000000),
      blurRadius: 8,
      spreadRadius: 0,
      offset: Offset(0, 2),
    ),
  ];
}
```

- [ ] **Step 2: Verify it compiles**

Run: `cd ~/bugsaymis-mobile && flutter analyze`
Expected: no new errors — `AppElevation`/`AppSpacing`/`AppMotion` are additive, nothing existing references them yet (Task 8 wires them up).

- [ ] **Step 3: Commit**

```bash
cd ~/bugsaymis-mobile && git add lib/src/core/theme.dart
git commit -m "feat(design-system): add AppElevation, AppSpacing, AppMotion tokens"
```

---

## Task 8: Flutter — upgrade shared widgets to the new token system

**Files:**
- Modify: `lib/src/shared/widgets/app_card.dart`
- Modify: `lib/src/core/theme.dart:408-479` (`AppHeader`)
- Modify: `lib/src/shared/widgets/app_shell.dart` (`_NavItem`'s `AnimatedContainer`)
- Test: `test/shared/widgets/app_card_test.dart`

**Interfaces:**
- Consumes: `AppElevation`, `AppSpacing`, `AppMotion` (Task 7).
- Produces: `AppCard` now animates a subtle press-scale when `onTap` is set (existing `AppCard(child:, onTap:)` API unchanged); `AppHeader` and `AppNavBar`'s tab indicator now use the named tokens instead of ad hoc values (existing APIs unchanged) — every screen using these widgets, including ones not otherwise touched this phase, inherits the change automatically.

- [ ] **Step 1: Write the failing test**

```dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:bugsaymis_mobile/src/shared/widgets/app_card.dart';

void main() {
  testWidgets('AppCard wraps a tappable child in a press-scale animation', (tester) async {
    var tapped = false;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: AppCard(
            onTap: () => tapped = true,
            child: const Text('content'),
          ),
        ),
      ),
    );

    expect(find.byType(AnimatedScale), findsOneWidget);

    await tester.tap(find.text('content'));
    await tester.pumpAndSettle();
    expect(tapped, isTrue);
  });

  testWidgets('AppCard without onTap renders no press-scale wrapper', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: AppCard(child: Text('static'))),
      ),
    );

    expect(find.byType(AnimatedScale), findsNothing);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/bugsaymis-mobile && flutter test test/shared/widgets/app_card_test.dart`
Expected: FAIL — `find.byType(AnimatedScale)` finds nothing in the current implementation.

- [ ] **Step 3: Upgrade `AppCard`**

Replace the full contents of `lib/src/shared/widgets/app_card.dart`:

```dart
import 'package:flutter/material.dart';
import '../../core/theme.dart';

/// The app's canonical white card: [AppRadius.card] corners + [AppElevation.resting].
/// Pass [onTap] to make the whole card tappable with a matching ink ripple
/// and a subtle press-scale for tactile feedback.
class AppCard extends StatefulWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;

  const AppCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.onTap,
  });

  @override
  State<AppCard> createState() => _AppCardState();
}

class _AppCardState extends State<AppCard> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    final content = Padding(padding: widget.padding, child: widget.child);

    final card = Container(
      decoration: const BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.all(Radius.circular(AppRadius.card)),
        boxShadow: AppElevation.resting,
      ),
      child: widget.onTap == null
          ? content
          : Material(
              color: Colors.transparent,
              borderRadius: const BorderRadius.all(Radius.circular(AppRadius.card)),
              child: InkWell(
                onTap: widget.onTap,
                onTapDown: (_) => setState(() => _pressed = true),
                onTapCancel: () => setState(() => _pressed = false),
                onTapUp: (_) => setState(() => _pressed = false),
                borderRadius: const BorderRadius.all(Radius.circular(AppRadius.card)),
                child: content,
              ),
            ),
    );

    if (widget.onTap == null) return card;

    return AnimatedScale(
      scale: _pressed ? 0.98 : 1.0,
      duration: AppMotion.fast,
      curve: AppMotion.standard,
      child: card,
    );
  }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ~/bugsaymis-mobile && flutter test test/shared/widgets/app_card_test.dart`
Expected: PASS (2 tests)

- [ ] **Step 5: Upgrade `AppHeader`**

In `lib/src/core/theme.dart`, replace the `AppHeader` class body (lines 427-478):

```dart
  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: Colors.white,
        boxShadow: AppElevation.resting,
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: EdgeInsets.fromLTRB(
              leading == null ? AppSpacing.xl : AppSpacing.sm,
              AppSpacing.lg, AppSpacing.sm, AppSpacing.lg),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              if (leading != null) ...[
                leading!,
                SizedBox(width: AppSpacing.xs),
              ],
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(greeting,
                        style: _pjs(
                            size: 12,
                            weight: FontWeight.w400,
                            color: AppColors.textSecondary)),
                    const SizedBox(height: 2),
                    Text(name,
                        style: _pjs(
                            size: 22,
                            weight: FontWeight.w800,
                            color: AppColors.textPrimary,
                            letterSpacing: -0.3)),
                    const SizedBox(height: 2),
                    Text(subtitle,
                        style: _pjs(
                            size: 12,
                            weight: FontWeight.w400,
                            color: AppColors.textSecondary)),
                  ],
                ),
              ),
              ...actions,
            ],
          ),
        ),
      ),
    );
  }
```

This replaces the header's flat `Divider` bottom border with a soft `AppElevation.resting` shadow (a layered look consistent with the rest of the "premium" pass) and swaps its magic-number padding for `AppSpacing` tokens. Note: this removes the `const Divider(height: 1)` line entirely — the shadow now does the separation job.

- [ ] **Step 6: Upgrade `AppNavBar`'s tab-indicator motion**

In `lib/src/shared/widgets/app_shell.dart`, in `_NavItem.build()`, replace:

```dart
              AnimatedContainer(
                duration: const Duration(milliseconds: 200),
                curve: Curves.easeOut,
```

with:

```dart
              AnimatedContainer(
                duration: AppMotion.base,
                curve: AppMotion.standard,
```

- [ ] **Step 7: Verify everything compiles and no regressions**

Run: `cd ~/bugsaymis-mobile && flutter analyze && flutter test`
Expected: no analyzer errors, all tests pass (Task 8's new test plus any pre-existing tests).

- [ ] **Step 8: Commit**

```bash
cd ~/bugsaymis-mobile && git add lib/src/shared/widgets/app_card.dart lib/src/core/theme.dart lib/src/shared/widgets/app_shell.dart test/shared/widgets/app_card_test.dart
git commit -m "feat(design-system): upgrade AppCard, AppHeader, AppNavBar to the new token system"
```

---

## Task 9: Flutter — SOS provider + trigger sheet widget

**Files:**
- Create: `lib/src/features/sos/sos_provider.dart`
- Create: `lib/src/features/sos/sos_trigger_sheet.dart`
- Test: `test/features/sos/sos_trigger_sheet_test.dart`

**Interfaces:**
- Consumes: `apiClientProvider` (`lib/src/core/api_client.dart`), `AppColors`/`AppTextStyles`/`AppRadius`/`AppSpacing`/`AppMotion` (`lib/src/core/theme.dart`).
- Produces: `sosConfigProvider` (`FutureProvider.autoDispose<Map<String, dynamic>>`), `showSosTriggerSheet(BuildContext context, {required bool silent})` — the function `AppShell`'s new center button (Task 10) calls.

- [ ] **Step 1: Write the failing widget test**

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:bugsaymis_mobile/src/features/sos/sos_trigger_sheet.dart';

void main() {
  testWidgets('category picker requires a selection before hold-to-confirm appears',
      (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        child: MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () => showSosTriggerSheet(context, silent: false),
                child: const Text('open'),
              ),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.text('Hold to confirm'), findsNothing);

    await tester.tap(find.text('Medical'));
    await tester.pump();

    expect(find.text('Hold to confirm'), findsOneWidget);
  });

  testWidgets('silent trigger shows no picker UI', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        child: MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) => ElevatedButton(
                onPressed: () => showSosTriggerSheet(context, silent: true),
                child: const Text('open'),
              ),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('open'));
    await tester.pump();

    expect(find.text('Emergency SOS'), findsNothing);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/sos/sos_trigger_sheet_test.dart`
Expected: FAIL — `sos_trigger_sheet.dart` doesn't exist yet.

- [ ] **Step 3: Write the provider**

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api_client.dart';

final sosConfigProvider =
    FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final response =
      await ref.read(apiClientProvider).get('/student/portal/sos/config');
  return response.data as Map<String, dynamic>;
});
```

- [ ] **Step 4: Write the trigger sheet**

```dart
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:geolocator/geolocator.dart';
import '../../core/api_client.dart';
import '../../core/theme.dart';
import 'sos_provider.dart';

const _categories = [
  ('medical', 'Medical', Icons.favorite_rounded),
  ('security', 'Security', Icons.shield_rounded),
  ('fire_disaster', 'Fire / Disaster', Icons.local_fire_department_rounded),
  ('general', 'General', Icons.pan_tool_rounded),
];

/// Opens the SOS flow. `silent: true` skips straight to dispatch with no
/// visible UI (the long-press/duress path) — `silent: false` opens the
/// normal category-picker sheet.
void showSosTriggerSheet(BuildContext context, {required bool silent}) {
  if (silent) {
    HapticFeedback.mediumImpact();
    _dispatch(context, alertType: 'security', isSilent: true);
    return;
  }

  showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (_) => const _SosSheet(),
  );
}

class _SosSheet extends ConsumerStatefulWidget {
  const _SosSheet();

  @override
  ConsumerState<_SosSheet> createState() => _SosSheetState();
}

enum _Phase { picking, holding, counting, sent, blocked }

class _SosSheetState extends ConsumerState<_SosSheet> {
  _Phase _phase = _Phase.picking;
  String? _category;
  double _holdProgress = 0;
  int _countdown = 8;
  String? _blockedMessage;
  String? _hotline;
  Timer? _timer;

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _selectCategory(String value) => setState(() => _category = value);

  void _startHold(int holdSeconds) {
    setState(() {
      _phase = _Phase.holding;
      _holdProgress = 0;
    });
    const stepMs = 50;
    final totalSteps = (holdSeconds * 1000) / stepMs;
    var step = 0;
    _timer = Timer.periodic(const Duration(milliseconds: stepMs), (t) {
      step++;
      setState(() => _holdProgress = (step / totalSteps).clamp(0, 1));
      if (step >= totalSteps) {
        t.cancel();
        _startCountdown();
      }
    });
  }

  void _cancelHold() {
    _timer?.cancel();
    setState(() => _phase = _Phase.picking);
  }

  void _startCountdown() {
    final countdownSeconds = ref.read(sosConfigProvider).value?['countdown_seconds'] as int? ?? 8;
    setState(() {
      _phase = _Phase.counting;
      _countdown = countdownSeconds;
    });
    _timer = Timer.periodic(const Duration(seconds: 1), (t) {
      setState(() => _countdown--);
      if (_countdown <= 0) {
        t.cancel();
        _dispatch(context, alertType: _category!, isSilent: false).then((result) {
          if (!mounted) return;
          setState(() {
            if (result.$1) {
              _phase = _Phase.blocked;
              _blockedMessage = result.$2;
              _hotline = result.$3;
            } else {
              _phase = _Phase.sent;
            }
          });
        });
      }
    });
  }

  void _cancelCountdown() {
    _timer?.cancel();
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final config = ref.watch(sosConfigProvider);
    final holdSeconds = config.value?['hold_confirm_seconds'] as int? ?? 3;

    return Container(
      padding: EdgeInsets.fromLTRB(AppSpacing.xl, AppSpacing.xl, AppSpacing.xl,
          AppSpacing.xl + MediaQuery.of(context).viewInsets.bottom),
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(AppRadius.sheet)),
      ),
      child: switch (_phase) {
        _Phase.blocked => _BlockedView(message: _blockedMessage!, hotline: _hotline!),
        _Phase.sent => const _SentView(),
        _Phase.counting => _CountdownView(seconds: _countdown, onCancel: _cancelCountdown),
        _ => _PickerView(
            selected: _category,
            holding: _phase == _Phase.holding,
            holdProgress: _holdProgress,
            onSelect: _selectCategory,
            onHoldStart: () => _category != null ? _startHold(holdSeconds) : null,
            onHoldCancel: _cancelHold,
          ),
      },
    );
  }
}

Future<(bool, String?, String?)> _dispatch(
  BuildContext context, {
  required String alertType,
  required bool isSilent,
}) async {
  final coords = await _captureLocation();

  final container = ProviderScope.containerOf(context, listen: false);
  try {
    final response = await container.read(apiClientProvider).post(
      '/student/portal/sos/trigger',
      data: {
        'alert_type': alertType,
        'is_silent': isSilent,
        'lat': coords?.latitude,
        'lng': coords?.longitude,
        'accuracy': coords?.accuracy,
      },
    );
    return (false, null, null);
  } on DioException catch (e) {
    if (e.response?.statusCode == 422 && e.response?.data?['blocked'] == true) {
      return (
        true,
        e.response?.data['message'] as String?,
        e.response?.data['emergency_hotline'] as String?,
      );
    }
    rethrow;
  }
}

Future<Position?> _captureLocation() async {
  try {
    final permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      final requested = await Geolocator.requestPermission();
      if (requested == LocationPermission.denied ||
          requested == LocationPermission.deniedForever) {
        return null;
      }
    }
    return await Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(accuracy: LocationAccuracy.high, timeLimit: Duration(seconds: 5)),
    );
  } catch (_) {
    return null;
  }
}

class _PickerView extends StatelessWidget {
  final String? selected;
  final bool holding;
  final double holdProgress;
  final ValueChanged<String> onSelect;
  final VoidCallback? Function() onHoldStart;
  final VoidCallback onHoldCancel;

  const _PickerView({
    required this.selected,
    required this.holding,
    required this.holdProgress,
    required this.onSelect,
    required this.onHoldStart,
    required this.onHoldCancel,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('Emergency SOS', style: AppTextStyles.title),
        SizedBox(height: AppSpacing.sm),
        Text('What kind of emergency is this?', style: AppTextStyles.body),
        SizedBox(height: AppSpacing.lg),
        GridView.count(
          shrinkWrap: true,
          crossAxisCount: 2,
          mainAxisSpacing: AppSpacing.sm,
          crossAxisSpacing: AppSpacing.sm,
          childAspectRatio: 2.4,
          physics: const NeverScrollableScrollPhysics(),
          children: _categories.map((c) {
            final isSelected = selected == c.$1;
            return OutlinedButton.icon(
              onPressed: () => onSelect(c.$1),
              icon: Icon(c.$3, size: 18),
              label: Text(c.$2),
              style: OutlinedButton.styleFrom(
                backgroundColor: isSelected ? AppColors.accentBg : null,
                foregroundColor: isSelected ? AppColors.accent : AppColors.textSecondary,
                side: BorderSide(color: isSelected ? AppColors.accent : AppColors.border),
              ),
            );
          }).toList(),
        ),
        if (selected != null) ...[
          SizedBox(height: AppSpacing.xl),
          GestureDetector(
            onLongPressStart: (_) => onHoldStart(),
            onLongPressEnd: (_) => onHoldCancel(),
            onLongPressCancel: onHoldCancel,
            child: Container(
              height: 52,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: Colors.red.shade600,
                borderRadius: BorderRadius.circular(AppRadius.button),
              ),
              child: Stack(
                alignment: Alignment.center,
                children: [
                  FractionallySizedBox(
                    widthFactor: holdProgress,
                    alignment: Alignment.centerLeft,
                    child: Container(color: Colors.red.shade900),
                  ),
                  Text(holding ? 'Keep holding…' : 'Hold to confirm', style: AppTextStyles.button),
                ],
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _CountdownView extends StatelessWidget {
  final int seconds;
  final VoidCallback onCancel;

  const _CountdownView({required this.seconds, required this.onCancel});

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text('Sending SOS alert in', style: AppTextStyles.body),
        SizedBox(height: AppSpacing.sm),
        Text('${seconds}s', style: AppTextStyles.custom(fontSize: 40, fontWeight: FontWeight.w800, color: Colors.red.shade600)),
        SizedBox(height: AppSpacing.lg),
        OutlinedButton(onPressed: onCancel, child: const Text('Cancel')),
      ],
    );
  }
}

class _SentView extends StatelessWidget {
  const _SentView();

  @override
  Widget build(BuildContext context) {
    return Builder(builder: (context) {
      return Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.check_circle_rounded, color: AppColors.success, size: 48),
          SizedBox(height: AppSpacing.md),
          Text('Help has been notified', style: AppTextStyles.title),
          SizedBox(height: AppSpacing.sm),
          Text('Responders and your emergency contact have been alerted.',
              textAlign: TextAlign.center, style: AppTextStyles.cardSubtitle),
          SizedBox(height: AppSpacing.lg),
          ElevatedButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Close')),
        ],
      );
    });
  }
}

class _BlockedView extends StatelessWidget {
  final String message;
  final String hotline;

  const _BlockedView({required this.message, required this.hotline});

  @override
  Widget build(BuildContext context) {
    return Builder(builder: (context) {
      return Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(message, style: AppTextStyles.body.copyWith(color: Colors.red.shade700)),
          SizedBox(height: AppSpacing.sm),
          Text.rich(TextSpan(children: [
            const TextSpan(text: 'Emergency hotline: '),
            TextSpan(text: hotline, style: AppTextStyles.bodySemibold),
          ]), style: AppTextStyles.body),
          SizedBox(height: AppSpacing.lg),
          ElevatedButton(onPressed: () => Navigator.of(context).pop(), child: const Text('Close')),
        ],
      );
    });
  }
}
```

Add `import 'dart:async';` and `import 'package:dio/dio.dart';` at the top of the file alongside the other imports.

- [ ] **Step 5: Run test to verify it passes**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/sos/sos_trigger_sheet_test.dart`
Expected: PASS (2 tests)

- [ ] **Step 6: Commit**

```bash
cd ~/bugsaymis-mobile && git add lib/src/features/sos/ test/features/sos/
git commit -m "feat(sos): add AtlasGo native SOS trigger flow (Phase B)"
```

---

## Task 10: Flutter — nav restructure (SOS replaces Digital ID center button)

**Files:**
- Modify: `lib/src/shared/widgets/app_shell.dart:203-241` (`_IdCenterButton` → `_SosCenterButton`), and the `centerButton:` wiring at lines 51-53

**Interfaces:**
- Consumes: `showSosTriggerSheet` (Task 9).
- Produces: `AppShell`'s student center slot now opens the SOS flow instead of `/student/id`.

- [ ] **Step 1: Replace the center button wiring**

In `lib/src/shared/widgets/app_shell.dart`, replace lines 51-53:

```dart
          centerButton: role == ShellRole.student
              ? _IdCenterButton(onTap: () => context.push('/student/id'))
              : null,
```

with:

```dart
          centerButton: role == ShellRole.student ? const _SosCenterButton() : null,
```

- [ ] **Step 2: Replace the button label**

At line 118 (`'My ID'` label inside `AppNavBar`), change to:

```dart
                        child: Text(
                          'SOS',
                          textAlign: TextAlign.center,
                          style: AppTextStyles.label
                              .copyWith(fontSize: 11, letterSpacing: 0),
                        ),
```

- [ ] **Step 3: Replace `_IdCenterButton` with `_SosCenterButton`**

Replace the entire `_IdCenterButton` class (lines 203-241) with:

```dart
/// Raised circular button docked in the nav's center — opens the SOS
/// trigger flow. Tap opens the category picker; long-press triggers the
/// silent/duress flow (no visible UI change beyond a haptic pulse).
class _SosCenterButton extends StatelessWidget {
  const _SosCenterButton();

  @override
  Widget build(BuildContext context) => Semantics(
        button: true,
        label: 'SOS Emergency',
        child: Container(
          width: 58,
          height: 58,
          decoration: BoxDecoration(
            color: Colors.red.shade600,
            shape: BoxShape.circle,
            border: Border.all(color: Colors.white, width: 3),
            boxShadow: [
              BoxShadow(
                color: Colors.red.shade600.withValues(alpha: 0.32),
                blurRadius: 14,
                offset: const Offset(0, 5),
              ),
            ],
          ),
          child: Material(
            color: Colors.transparent,
            shape: const CircleBorder(),
            child: InkWell(
              customBorder: const CircleBorder(),
              onTap: () => showSosTriggerSheet(context, silent: false),
              onLongPress: () => showSosTriggerSheet(context, silent: true),
              child: const Icon(Icons.warning_rounded, color: Colors.white, size: 28),
            ),
          ),
        ),
      );
}
```

- [ ] **Step 4: Update imports**

At the top of `app_shell.dart`, add:

```dart
import '../../features/sos/sos_trigger_sheet.dart';
```

- [ ] **Step 5: Verify it compiles and existing widget tests still pass**

Run: `cd ~/bugsaymis-mobile && flutter analyze && flutter test`
Expected: no analyzer errors; no test regressions (Digital ID's own screen and route are untouched, only its nav entry point moved).

- [ ] **Step 6: Commit**

```bash
cd ~/bugsaymis-mobile && git add lib/src/shared/widgets/app_shell.dart
git commit -m "feat(sos): replace nav center button with SOS trigger, per design spec"
```

---

## Task 11: Flutter — Profile screen: role-branch fix + Digital ID + self-service entries

**Files:**
- Modify: `lib/src/features/profile/profile_screen.dart`

**Interfaces:**
- Consumes: `user.isStudent`/`user.isParent` (`lib/src/features/auth/auth_provider.dart`, existing).
- Produces: role-correct badge/menu on `ProfileScreen`; new "Digital Student ID" and "Update My Information" entries for students, pushing `/student/id` (existing route) and `/student/profile-update` (Task 13) respectively.

- [ ] **Step 1: Fix the role badge (currently hardcoded to "Parent")**

In `lib/src/features/profile/profile_screen.dart`, replace line 114 (`'Parent',`) with:

```dart
                                  user?.isStudent == true ? 'Student' : 'Parent',
```

- [ ] **Step 2: Add role-branched ACCOUNT section entries**

Replace lines 125-137 (from `const SizedBox(height: 24),` through the `Notification Settings` `_MenuItem`) with:

```dart
                  const SizedBox(height: 24),
                  const SectionLabel('ACCOUNT'),

                  if (user?.isStudent == true) ...[
                    _MenuItem(
                      icon: Icons.badge_outlined,
                      label: 'Digital Student ID',
                      onTap: () => context.push('/student/id'),
                    ),
                    _MenuItem(
                      icon: Icons.edit_note_rounded,
                      label: 'Update My Information',
                      onTap: () => context.push('/student/profile-update'),
                    ),
                  ] else ...[
                    _MenuItem(
                      icon: Icons.people_alt_outlined,
                      label: 'Manage Children',
                      onTap: () => context.push('/children'),
                    ),
                  ],
                  _MenuItem(
                    icon: Icons.notifications_outlined,
                    label: 'Notification Settings',
                    onTap: () => context.push('/notification-preferences'),
                  ),
```

- [ ] **Step 3: Verify it compiles**

Run: `cd ~/bugsaymis-mobile && flutter analyze`
Expected: no new errors.

- [ ] **Step 4: Commit**

```bash
cd ~/bugsaymis-mobile && git add lib/src/features/profile/profile_screen.dart
git commit -m "fix(profile): role-branch account menu, add Digital ID + self-service update entries"
```

---

## Task 12: Flutter — elevate the Home/dashboard screen

**Files:**
- Modify: `lib/src/features/home/home_screen.dart`

**Interfaces:**
- Consumes: `AppElevation`, `AppMotion` (Task 7).
- Produces: no API change — `HomeScreen`'s existing structure, providers, and child widgets are untouched; only the two identified ad hoc styling spots adopt the named tokens.

- [ ] **Step 1: Replace the `AnimatedSwitcher`'s ad hoc motion values**

In `lib/src/features/home/home_screen.dart`, replace:

```dart
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 300),
                switchInCurve: Curves.easeOut,
                switchOutCurve: Curves.easeIn,
```

with:

```dart
              child: AnimatedSwitcher(
                duration: AppMotion.slow,
                switchInCurve: AppMotion.standard,
                switchOutCurve: AppMotion.standard,
```

- [ ] **Step 2: Elevate the header's profile icon button**

Replace the `_HeaderIconBtn` class body's `build()` method:

```dart
  @override
  Widget build(BuildContext context) => Semantics(
        label: tooltip,
        button: true,
        child: Padding(
          padding: const EdgeInsets.only(left: 4),
          child: Container(
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              boxShadow: AppElevation.resting,
            ),
            child: IconButton(
              icon: Icon(icon, size: 20),
              tooltip: tooltip,
              onPressed: onTap,
              style: IconButton.styleFrom(
                foregroundColor: AppColors.textSecondary,
                backgroundColor: AppColors.surface,
                shape: const CircleBorder(),
                minimumSize: const Size(38, 38),
                maximumSize: const Size(38, 38),
                padding: EdgeInsets.zero,
              ),
              constraints: const BoxConstraints(minWidth: 44, minHeight: 44),
            ),
          ),
        ),
      );
```

(Background changed from the flat `AppColors.neutralBg` to `AppColors.surface` + a soft shadow, so the button reads as a small raised chip rather than a flat tinted circle — consistent with the header's own new elevated treatment from Task 8.)

- [ ] **Step 3: Verify it compiles and existing tests still pass**

Run: `cd ~/bugsaymis-mobile && flutter analyze && flutter test`
Expected: no analyzer errors, no regressions.

- [ ] **Step 4: Commit**

```bash
cd ~/bugsaymis-mobile && git add lib/src/features/home/home_screen.dart
git commit -m "feat(design-system): elevate Home screen motion and header icon button"
```

---

## Task 13: Flutter — "Update My Information" screen

**Files:**
- Create: `lib/src/features/profile/profile_update_provider.dart`
- Create: `lib/src/features/profile/profile_update_screen.dart`
- Modify: `lib/src/core/router.dart` (add route + import, after line 164's `/profile` route)
- Test: `test/features/profile/profile_update_screen_test.dart`

**Interfaces:**
- Consumes: `apiClientProvider`, `StudentProfileChangeRequestService::EDITABLE_FIELDS`'s shape via the `GET /student/portal/profile-update` response (Task 5).
- Produces: `profileUpdateProvider` (`FutureProvider.autoDispose<Map<String, dynamic>>`), `ProfileUpdateScreen` widget registered at route `/student/profile-update`.

- [ ] **Step 1: Write the failing widget test**

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:bugsaymis_mobile/src/features/profile/profile_update_provider.dart';
import 'package:bugsaymis_mobile/src/features/profile/profile_update_screen.dart';

void main() {
  testWidgets('shows a pending banner instead of the form when a request is pending',
      (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          profileUpdateProvider.overrideWith((ref) async => {
                'current': {'contactno1': '09170000000'},
                'editable_fields': ['contactno1'],
                'pending': {
                  'requested_changes': {'contactno1': '09171234567'},
                  'submitted_at': '2026-08-22T00:00:00Z',
                },
              }),
        ],
        child: const MaterialApp(home: ProfileUpdateScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.textContaining('awaiting registrar review'), findsOneWidget);
    expect(find.byType(TextFormField), findsNothing);
  });

  testWidgets('shows an editable form when there is no pending request', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          profileUpdateProvider.overrideWith((ref) async => {
                'current': {'contactno1': '09170000000'},
                'editable_fields': ['contactno1'],
                'pending': null,
              }),
        ],
        child: const MaterialApp(home: ProfileUpdateScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byType(TextFormField), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/profile/profile_update_screen_test.dart`
Expected: FAIL — files don't exist yet.

- [ ] **Step 3: Write the provider**

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api_client.dart';

final profileUpdateProvider =
    FutureProvider.autoDispose<Map<String, dynamic>>((ref) async {
  final response =
      await ref.read(apiClientProvider).get('/student/portal/profile-update');
  return response.data as Map<String, dynamic>;
});

/// Human-readable labels for the editable students-table columns, in
/// display order, grouped for the form UI.
const kFieldGroups = {
  'Contact Info': {
    'studentcontact': 'Student Contact Number',
    'contactno1': 'Primary Contact Number',
    'contactno2': 'Secondary Contact Number',
    'student_email': 'Email Address',
  },
  'Address': {
    'houseno': 'House No. / Street',
    'barangay': 'Barangay',
    'municipal': 'Municipality/City',
    'district': 'District',
    'province': 'Province',
    'zipcode': 'Zip Code',
    'homeaddresstype': 'Address Type',
  },
  'Parent / Guardian Info': {
    'contactperson': 'Contact Person',
    'relation1': 'Relationship',
    'contact_address1': 'Contact Person Address',
    'contact_ofc_address1': 'Office Address',
    'contact_ofc_telno1': 'Office Telephone',
    'contactperson2': 'Secondary Contact Person',
    'relation2': 'Secondary Relationship',
    'contact_address2': 'Secondary Contact Address',
    'contact_ofc_address2': 'Secondary Office Address',
    'contact_ofc_telno2': 'Secondary Office Telephone',
    'mcpno': "Mother's Contact Number",
    'fcpno': "Father's Contact Number",
    'memailaddress': "Mother's Email",
    'femailaddress': "Father's Email",
    'moccupation': "Mother's Occupation",
    'foccupation': "Father's Occupation",
  },
  'Personal Details': {
    'bloodtype': 'Blood Type',
    'religion': 'Religion',
    'ethnic': 'Ethnicity',
    'nationality': 'Nationality',
  },
};
```

- [ ] **Step 4: Write the screen**

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/api_client.dart';
import '../../core/theme.dart';
import '../../shared/widgets/shimmer_card.dart';
import 'profile_update_provider.dart';

class ProfileUpdateScreen extends ConsumerStatefulWidget {
  const ProfileUpdateScreen({super.key});

  @override
  ConsumerState<ProfileUpdateScreen> createState() => _ProfileUpdateScreenState();
}

class _ProfileUpdateScreenState extends ConsumerState<ProfileUpdateScreen> {
  final Map<String, TextEditingController> _controllers = {};
  bool _saving = false;

  @override
  void dispose() {
    for (final c in _controllers.values) {
      c.dispose();
    }
    super.dispose();
  }

  TextEditingController _controllerFor(String field, String? current) {
    return _controllers.putIfAbsent(field, () => TextEditingController(text: current ?? ''));
  }

  Future<void> _submit() async {
    final changes = <String, String>{};
    for (final entry in _controllers.entries) {
      changes[entry.key] = entry.value.text.trim();
    }

    setState(() => _saving = true);
    try {
      await ref.read(apiClientProvider).post(
        '/student/portal/profile-update',
        data: {'changes': changes},
      );
      ref.invalidate(profileUpdateProvider);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Update request submitted for registrar review.')),
        );
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not submit your update. Please try again.')),
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final data = ref.watch(profileUpdateProvider);

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Update My Information'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 20),
          onPressed: () => context.canPop() ? context.pop() : context.go('/profile'),
        ),
      ),
      body: data.when(
        loading: () => const Padding(
          padding: EdgeInsets.all(20),
          child: ShimmerList(count: 4, itemHeight: 60),
        ),
        error: (e, _) => Padding(
          padding: const EdgeInsets.all(20),
          child: Text('Could not load your information.', style: AppTextStyles.body),
        ),
        data: (d) {
          final pending = d['pending'] as Map<String, dynamic>?;
          if (pending != null) {
            return _PendingBanner(pending: pending);
          }

          final current = (d['current'] as Map?)?.cast<String, dynamic>() ?? {};
          final editable = ((d['editable_fields'] as List?) ?? []).cast<String>().toSet();

          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 100),
            children: [
              Text(
                'Changes are reviewed by the registrar before they take effect.',
                style: AppTextStyles.cardSubtitle,
              ),
              const SizedBox(height: 20),
              for (final group in kFieldGroups.entries) ...[
                if (group.value.keys.any(editable.contains)) ...[
                  Text(group.key.toUpperCase(), style: AppTextStyles.label),
                  const SizedBox(height: 8),
                  for (final field in group.value.entries)
                    if (editable.contains(field.key))
                      Padding(
                        padding: const EdgeInsets.only(bottom: 14),
                        child: TextFormField(
                          controller: _controllerFor(field.key, current[field.key]?.toString()),
                          decoration: InputDecoration(labelText: field.value),
                        ),
                      ),
                  const SizedBox(height: 8),
                ],
              ],
              const SizedBox(height: 12),
              ElevatedButton(
                onPressed: _saving ? null : _submit,
                child: Text(_saving ? 'Submitting…' : 'Submit for Review'),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _PendingBanner extends StatelessWidget {
  final Map<String, dynamic> pending;

  const _PendingBanner({required this.pending});

  @override
  Widget build(BuildContext context) {
    final changes = (pending['requested_changes'] as Map?)?.cast<String, dynamic>() ?? {};

    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        Container(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            color: AppColors.warningBg,
            borderRadius: BorderRadius.circular(AppRadius.card),
          ),
          child: Text(
            'Your update is awaiting registrar review.',
            style: AppTextStyles.bodySemibold.copyWith(color: AppColors.warningText),
          ),
        ),
        const SizedBox(height: 20),
        Text('SUBMITTED CHANGES', style: AppTextStyles.label),
        const SizedBox(height: 8),
        for (final entry in changes.entries)
          Padding(
            padding: const EdgeInsets.only(bottom: 8),
            child: Text('${entry.key}: ${entry.value}', style: AppTextStyles.body),
          ),
      ],
    );
  }
}
```

- [ ] **Step 5: Register the route**

In `lib/src/core/router.dart`, add the import near line 16 (alongside `profile_screen.dart`):

```dart
import '../features/profile/profile_update_screen.dart';
```

Then add the route immediately after line 164 (`GoRoute(path: '/profile', ...)`):

```dart
      GoRoute(path: '/student/profile-update', builder: (ctx, st) => const ProfileUpdateScreen()),
```

- [ ] **Step 6: Run test to verify it passes**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/profile/profile_update_screen_test.dart`
Expected: PASS (2 tests)

- [ ] **Step 7: Full verification pass**

Run: `cd ~/bugsaymis-mobile && flutter analyze && flutter test`
Expected: no analyzer errors, all tests pass (including Tasks 8-10's tests).

- [ ] **Step 8: Commit**

```bash
cd ~/bugsaymis-mobile && git add lib/src/features/profile/profile_update_provider.dart lib/src/features/profile/profile_update_screen.dart lib/src/core/router.dart test/features/profile/profile_update_screen_test.dart
git commit -m "feat(students): add self-service Update My Information screen"
```

---

## Final verification

- [ ] **Backend:** `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test"` — full suite green, no regressions.
- [ ] **Flutter:** `cd ~/bugsaymis-mobile && flutter analyze && flutter test` — clean, all green.
- [ ] **Manual click-through (dev):** log in as a seeded student on AtlasGo (Android emulator or simulator, dev API), confirm: center nav button opens the SOS category picker and dispatches (verify a row lands in `sos_alerts` via `SHOW COLUMNS`/tinker or the Command Center); long-press triggers silently; Profile shows "Digital Student ID" (opens the existing ID screen) and "Update My Information" (submits, then shows the pending banner on next open); registrar can see and approve/reject the request at `/students/change-requests` on Atlas web, and the `students` row reflects the change after approval.
