# Issuance Module: Add Recipients After Release — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an admin (`issuances.manage`) add recipients to an issuance after it has been released, with the new recipients getting the same email + bell notification original recipients got, plus normal view access.

**Architecture:** A new additive `IssuanceService::addRecipients()` method resolves the same `all`/`office`/`individual`/`division` recipient-type shapes `buildRecipients()` already uses, but diffs against existing recipients instead of deleting them. A new controller endpoint (`POST /issuances/{issuance}/recipients`) validates the request, calls the service, and dispatches a new queued job (`NotifyAddedIssuanceRecipients`) that mirrors `ProcessIssuanceRelease`'s notify loop (email + bell/push) for just the newly-added recipient rows. The frontend adds an "Add Recipient" modal to `Issuances/Show.vue`, reusing the exact office/division/individual picker markup already proven in `Issuances/Create.vue`.

**Tech Stack:** Laravel 12 / PHP 8.4, Vue 3 `<script setup>` + Inertia.js 2, MySQL 8.0, Redis-backed queue.

## Global Constraints

- Backend controllers return `Inertia::render`/`back()->with(...)` — never Blade. This feature only adds a `back()->with('success', ...)` redirect, no new page.
- `back()->with(...)` flash keys are limited to `success`/`error` in this app (confirmed: `FlashMessage` component only reads `flash.success`/`flash.error`) — do not introduce a `'info'` key.
- Eager-load relations to avoid N+1 (`->with('user')` on recipients queries, matching existing jobs).
- `User::employees()` scope (already defined in `app/Models/User.php:316`) must gate every recipient resolution — mobile Parent/Student accounts must never become issuance recipients (see `[[project_employee_vs_mobile_accounts]]`).
- New route must sit inside the existing `Route::middleware('permission:issuances.manage')` group pattern in `routes/web.php` — admin-only, consistent with `recipients.resend`/`recipients.resendBulk`.
- Never use `FormData`/multipart uploads — not applicable here (no file upload in this feature), but no new upload code should violate this either way.
- Run tests via: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=<TestClass>"`.
- Stage git commits by explicit file path — never `git add -A` / `git add .`.

---

### Task 1: `IssuanceService::addRecipients()`

**Files:**
- Modify: `app/Services/IssuanceService.php` (add method after `buildRecipients()`, which ends at line 117)
- Test: `tests/Unit/IssuanceServiceAddRecipientsTest.php` (new)

**Interfaces:**
- Produces: `IssuanceService::addRecipients(Issuance $issuance, array $data): array` — returns the newly-inserted `issuance_recipients.id` values (plain `int[]`, empty array if nothing new was added). `$data` shape: `['recipient_type' => 'all'|'office'|'individual'|'division', 'office_ids' => ?array, 'user_ids' => ?array, 'division_ids' => ?array]`.
- Consumes: `App\Models\Issuance`, `App\Models\IssuanceRecipient`, `App\Models\User::employees()` (existing scope), all already imported in `IssuanceService.php`.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/IssuanceServiceAddRecipientsTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Models\Division;
use App\Models\Issuance;
use App\Models\IssuanceRecipient;
use App\Models\Office;
use App\Models\User;
use App\Services\IssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssuanceServiceAddRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private function releasedIssuance(): Issuance
    {
        $creator = User::factory()->create();

        return Issuance::create([
            'type' => 'MEMO',
            'control_number' => 'MEMO-2026-08-' . uniqid(),
            'series_no' => 1,
            'year' => 2026,
            'month' => 8,
            'title' => 'Test Memo',
            'recipient_type' => 'individual',
            'status' => 'released',
            'released_at' => now(),
            'created_by' => $creator->id,
        ]);
    }

    public function test_it_adds_new_individual_recipients_and_returns_their_ids(): void
    {
        $issuance = $this->releasedIssuance();
        $user = User::factory()->create();

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'recipient_type' => 'individual',
            'user_ids' => [$user->id],
        ]);

        $this->assertCount(1, $newIds);
        $this->assertDatabaseHas('issuance_recipients', [
            'issuance_id' => $issuance->id,
            'user_id' => $user->id,
        ]);
        $recipient = IssuanceRecipient::find($newIds[0]);
        $this->assertNotNull($recipient->notified_at);
    }

    public function test_it_skips_users_who_are_already_recipients(): void
    {
        $issuance = $this->releasedIssuance();
        $existing = User::factory()->create();
        $new = User::factory()->create();
        IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $existing->id]);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'recipient_type' => 'individual',
            'user_ids' => [$existing->id, $new->id],
        ]);

        $this->assertCount(1, $newIds);
        $this->assertSame($new->id, IssuanceRecipient::find($newIds[0])->user_id);
        $this->assertSame(2, $issuance->recipients()->count());
    }

    public function test_it_returns_empty_array_when_everyone_selected_is_already_a_recipient(): void
    {
        $issuance = $this->releasedIssuance();
        $existing = User::factory()->create();
        IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $existing->id]);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'recipient_type' => 'individual',
            'user_ids' => [$existing->id],
        ]);

        $this->assertSame([], $newIds);
        $this->assertSame(1, $issuance->recipients()->count());
    }

    public function test_it_adds_recipients_by_office(): void
    {
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Test Office ' . uniqid()]);
        $memberA = User::factory()->create(['office_id' => $office->id]);
        $memberB = User::factory()->create(['office_id' => $office->id]);
        User::factory()->create(); // unrelated user, must not be added

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'recipient_type' => 'office',
            'office_ids' => [$office->id],
        ]);

        $this->assertCount(2, $newIds);
        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $memberA->id]);
        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $memberB->id]);
    }

    public function test_it_adds_recipients_by_division(): void
    {
        $issuance = $this->releasedIssuance();
        $division = Division::create(['division_name' => 'Test Division ' . uniqid()]);
        $member = User::factory()->create(['division_id' => $division->id]);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'recipient_type' => 'division',
            'division_ids' => [$division->id],
        ]);

        $this->assertCount(1, $newIds);
        $this->assertSame($member->id, IssuanceRecipient::find($newIds[0])->user_id);
    }

    public function test_it_adds_all_active_employees_and_excludes_inactive_ones(): void
    {
        $issuance = $this->releasedIssuance();
        $active = User::factory()->create(['status' => 'active']);
        $inactive = User::factory()->create(['status' => 'inactive']);

        (new IssuanceService())->addRecipients($issuance, [
            'recipient_type' => 'all',
        ]);

        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $active->id]);
        $this->assertDatabaseMissing('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $inactive->id]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IssuanceServiceAddRecipientsTest"`
Expected: FAIL — `Call to undefined method App\Services\IssuanceService::addRecipients()`

- [ ] **Step 3: Implement `addRecipients()`**

In `app/Services/IssuanceService.php`, add this method immediately after `buildRecipients()` (after the closing `}` currently at line 117, before the `// ── QR helpers ──` comment):

```php
    /**
     * Additive recipient fan-out for an already-released issuance. Unlike
     * buildRecipients() this never deletes existing rows — it resolves the
     * requested target set the same way, diffs out anyone already a
     * recipient, and inserts only the new rows. Returns the newly-inserted
     * issuance_recipients IDs so the caller can notify just those people.
     */
    public function addRecipients(Issuance $issuance, array $data): array
    {
        $targetUserIds = match ($data['recipient_type']) {
            'all' => User::employees()->where('status', '<>', 'inactive')->pluck('id'),
            'office' => User::employees()
                ->whereIn('office_id', $data['office_ids'] ?? [])
                ->where('status', '<>', 'inactive')
                ->pluck('id'),
            'individual' => User::employees()
                ->whereIn('id', $data['user_ids'] ?? [])
                ->pluck('id'),
            'division' => User::employees()
                ->whereIn('division_id', $data['division_ids'] ?? [])
                ->where('status', '<>', 'inactive')
                ->pluck('id'),
            default => collect(),
        };

        $existingUserIds = $issuance->recipients()->pluck('user_id')->all();
        $newUserIds = $targetUserIds->diff($existingUserIds)->values();

        if ($newUserIds->isEmpty()) {
            return [];
        }

        $rows = $newUserIds->map(fn ($uid) => [
            'issuance_id' => $issuance->id,
            'user_id'     => $uid,
            'notified_at' => now(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ])->all();

        IssuanceRecipient::insert($rows);

        return $issuance->recipients()->whereIn('user_id', $newUserIds)->pluck('id')->all();
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IssuanceServiceAddRecipientsTest"`
Expected: PASS (6 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/IssuanceService.php tests/Unit/IssuanceServiceAddRecipientsTest.php
git commit -m "feat(issuances): add IssuanceService::addRecipients() for post-release additions"
```

---

### Task 2: `NotifyAddedIssuanceRecipients` job

**Files:**
- Create: `app/Jobs/NotifyAddedIssuanceRecipients.php`
- Test: `tests/Unit/NotifyAddedIssuanceRecipientsJobTest.php` (new)

**Interfaces:**
- Consumes: `IssuanceRecipient::id[]` produced by `IssuanceService::addRecipients()` (Task 1), `App\Mail\IssuanceReleasedMail` (existing, constructor `(Issuance $issuance, string $recipientName)`), `App\Services\NotificationService::notifyUser(User $user, string $requestType, string $referenceNo, string $newStatus, string $url, ?string $remarks = null): void` (existing, static).
- Produces: `NotifyAddedIssuanceRecipients::dispatch(int $issuanceId, array $recipientIds)` — used by Task 3's controller.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/NotifyAddedIssuanceRecipientsJobTest.php`:

```php
<?php

namespace Tests\Unit;

use App\Jobs\NotifyAddedIssuanceRecipients;
use App\Mail\IssuanceReleasedMail;
use App\Models\Issuance;
use App\Models\IssuanceRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotifyAddedIssuanceRecipientsJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_emails_and_notifies_each_targeted_recipient(): void
    {
        Mail::fake();

        $creator = User::factory()->create();
        $issuance = Issuance::create([
            'type' => 'MEMO',
            'control_number' => 'MEMO-2026-08-' . uniqid(),
            'series_no' => 1,
            'year' => 2026,
            'month' => 8,
            'title' => 'Test Memo',
            'recipient_type' => 'individual',
            'status' => 'released',
            'released_at' => now(),
            'created_by' => $creator->id,
        ]);

        $user = User::factory()->create();
        $recipient = IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $user->id]);

        (new NotifyAddedIssuanceRecipients($issuance->id, [$recipient->id]))->handle();

        Mail::assertSent(IssuanceReleasedMail::class, fn ($mail) => $mail->issuance->is($issuance) && $mail->recipientName === $user->name);
        $this->assertSame('sent', $recipient->fresh()->email_status);
        $this->assertNotNull($recipient->fresh()->emailed_at);
        $this->assertDatabaseHas('notifications', ['notifiable_id' => $user->id]);
    }

    public function test_it_marks_skipped_when_recipient_has_no_email(): void
    {
        Mail::fake();

        $creator = User::factory()->create();
        $issuance = Issuance::create([
            'type' => 'MEMO',
            'control_number' => 'MEMO-2026-08-' . uniqid(),
            'series_no' => 1,
            'year' => 2026,
            'month' => 8,
            'title' => 'Test Memo',
            'recipient_type' => 'individual',
            'status' => 'released',
            'released_at' => now(),
            'created_by' => $creator->id,
        ]);

        $user = User::factory()->create(['email' => '']);
        $recipient = IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $user->id]);

        (new NotifyAddedIssuanceRecipients($issuance->id, [$recipient->id]))->handle();

        $this->assertSame('skipped', $recipient->fresh()->email_status);
        Mail::assertNotSent(IssuanceReleasedMail::class);
    }

    public function test_it_only_touches_the_requested_recipient_ids(): void
    {
        Mail::fake();

        $creator = User::factory()->create();
        $issuance = Issuance::create([
            'type' => 'MEMO',
            'control_number' => 'MEMO-2026-08-' . uniqid(),
            'series_no' => 1,
            'year' => 2026,
            'month' => 8,
            'title' => 'Test Memo',
            'recipient_type' => 'individual',
            'status' => 'released',
            'released_at' => now(),
            'created_by' => $creator->id,
        ]);

        $targeted = IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => User::factory()->create()->id]);
        $untouched = IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => User::factory()->create()->id]);

        (new NotifyAddedIssuanceRecipients($issuance->id, [$targeted->id]))->handle();

        $this->assertSame('sent', $targeted->fresh()->email_status);
        $this->assertSame('pending', $untouched->fresh()->email_status);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=NotifyAddedIssuanceRecipientsJobTest"`
Expected: FAIL — `Class "App\Jobs\NotifyAddedIssuanceRecipients" not found`

- [ ] **Step 3: Implement the job**

Create `app/Jobs/NotifyAddedIssuanceRecipients.php`:

```php
<?php

namespace App\Jobs;

use App\Mail\IssuanceReleasedMail;
use App\Models\Issuance;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class NotifyAddedIssuanceRecipients implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // Mirrors ProcessIssuanceRelease/ResendIssuanceEmails — stay below the
    // queue connection retry_after so the job is never re-released to
    // another worker mid-flight.
    public int $timeout = 600;

    // Single attempt — re-running on a flaky failure would double-notify
    // recipients who already succeeded.
    public int $tries = 1;

    public function __construct(public int $issuanceId, public array $recipientIds) {}

    public function handle(): void
    {
        $issuance = Issuance::find($this->issuanceId);

        if (! $issuance) {
            logger()->error('NotifyAddedIssuanceRecipients: issuance not found', [
                'issuance_id' => $this->issuanceId,
            ]);
            return;
        }

        $recipients = $issuance->recipients()->whereIn('id', $this->recipientIds)->with('user')->get();
        $sent    = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($recipients as $recipient) {
            $u = $recipient->user;
            if (! $u || empty($u->email)) {
                $skipped++;
                $recipient->update([
                    'email_status' => 'skipped',
                    'email_error'  => 'No email on file for this recipient.',
                ]);
                continue;
            }

            try {
                Mail::to($u->email)->send(new IssuanceReleasedMail($issuance, $u->name));
                $sent++;
                $recipient->update(['email_status' => 'sent', 'emailed_at' => now(), 'email_error' => null]);
            } catch (\Throwable $e) {
                $failed++;
                $recipient->update(['email_status' => 'failed', 'email_error' => $e->getMessage()]);
                logger()->warning('NotifyAddedIssuanceRecipients: email failed', [
                    'issuance_id'  => $issuance->id,
                    'recipient_id' => $recipient->id,
                    'user_id'      => $u->id,
                    'email'        => $u->email,
                    'error'        => $e->getMessage(),
                ]);
            }

            try {
                NotificationService::notifyUser(
                    $u,
                    'Issuance',
                    $issuance->display_number,
                    ($issuance->isSupplement() ? $issuance->document_kind_label : $issuance->type_label) . ": {$issuance->title}",
                    route('issuances.show', $issuance->id),
                );
            } catch (\Throwable $e) {
                logger()->warning('NotifyAddedIssuanceRecipients: bell/push failed', [
                    'issuance_id'  => $issuance->id,
                    'recipient_id' => $recipient->id,
                    'error'        => $e->getMessage(),
                ]);
            }
        }

        logger()->info('NotifyAddedIssuanceRecipients: complete', [
            'issuance_id' => $issuance->id,
            'requested'   => count($this->recipientIds),
            'sent'        => $sent,
            'skipped'     => $skipped,
            'failed'      => $failed,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logger()->error('NotifyAddedIssuanceRecipients: job FAILED', [
            'issuance_id'   => $this->issuanceId,
            'recipient_ids' => $this->recipientIds,
            'error'         => $e->getMessage(),
            'trace'         => $e->getTraceAsString(),
        ]);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=NotifyAddedIssuanceRecipientsJobTest"`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/NotifyAddedIssuanceRecipients.php tests/Unit/NotifyAddedIssuanceRecipientsJobTest.php
git commit -m "feat(issuances): add NotifyAddedIssuanceRecipients job for post-release notifications"
```

---

### Task 3: Controller endpoint + route + feature tests

**Files:**
- Modify: `app/Http/Controllers/IssuanceController.php` (add `addRecipients()` method; add `use App\Jobs\NotifyAddedIssuanceRecipients;` import)
- Modify: `routes/web.php` (add route in the `issuances.` group)
- Test: `tests/Feature/IssuanceRecipientsAddTest.php` (new)

**Interfaces:**
- Consumes: `IssuanceService::addRecipients()` (Task 1), `NotifyAddedIssuanceRecipients::dispatch()` (Task 2), `AuditLogger::log(array $data): AuditLog` (existing, `app/Services/AuditLogger.php:11`).
- Produces: route `issuances.recipients.add` → `POST /issuances/{issuance}/recipients`, consumed by Task 4's frontend.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/IssuanceRecipientsAddTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Jobs\NotifyAddedIssuanceRecipients;
use App\Models\Division;
use App\Models\Issuance;
use App\Models\IssuanceRecipient;
use App\Models\Office;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class IssuanceRecipientsAddTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'issuances.manage'],
            ['module' => 'Issuances', 'description' => 'issuances.manage'],
        );
        $role = Role::create(['name' => 'IssuanceAdminTester_' . uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function releasedIssuance(?User $creator = null): Issuance
    {
        $creator ??= User::factory()->create();

        return Issuance::create([
            'type' => 'MEMO',
            'control_number' => 'MEMO-2026-08-' . uniqid(),
            'series_no' => 1,
            'year' => 2026,
            'month' => 8,
            'title' => 'Test Memo',
            'recipient_type' => 'individual',
            'status' => 'released',
            'released_at' => now(),
            'created_by' => $creator->id,
        ]);
    }

    public function test_admin_can_add_an_individual_recipient_to_a_released_issuance(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $issuance = $this->releasedIssuance();
        $newUser = User::factory()->create();

        $response = $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [
            'recipient_type' => 'individual',
            'user_ids' => [$newUser->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $newUser->id]);
        Queue::assertPushed(NotifyAddedIssuanceRecipients::class, fn ($job) => $job->issuanceId === $issuance->id && in_array(
            IssuanceRecipient::where('issuance_id', $issuance->id)->where('user_id', $newUser->id)->value('id'),
            $job->recipientIds,
        ));
    }

    public function test_adding_recipients_to_a_draft_issuance_is_rejected(): void
    {
        $admin = $this->admin();
        $creator = User::factory()->create();
        $issuance = Issuance::create([
            'type' => 'MEMO',
            'control_number' => 'MEMO-2026-08-' . uniqid(),
            'series_no' => 1,
            'year' => 2026,
            'month' => 8,
            'title' => 'Draft Memo',
            'recipient_type' => 'individual',
            'status' => 'draft',
            'created_by' => $creator->id,
        ]);
        $newUser = User::factory()->create();

        $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [
            'recipient_type' => 'individual',
            'user_ids' => [$newUser->id],
        ])->assertStatus(422);

        $this->assertDatabaseMissing('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $newUser->id]);
    }

    public function test_adding_recipients_to_an_archived_issuance_is_rejected(): void
    {
        $admin = $this->admin();
        $issuance = $this->releasedIssuance();
        $issuance->update(['archived_at' => now()]);
        $newUser = User::factory()->create();

        $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [
            'recipient_type' => 'individual',
            'user_ids' => [$newUser->id],
        ])->assertStatus(422);
    }

    public function test_non_admin_cannot_add_recipients(): void
    {
        $issuance = $this->releasedIssuance();
        $staff = User::factory()->create();
        $newUser = User::factory()->create();

        $this->actingAs($staff)->post(route('issuances.recipients.add', $issuance->id), [
            'recipient_type' => 'individual',
            'user_ids' => [$newUser->id],
        ])->assertStatus(403);
    }

    public function test_adding_an_office_only_notifies_the_newly_added_members(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Test Office ' . uniqid()]);
        $already = User::factory()->create(['office_id' => $office->id]);
        $new = User::factory()->create(['office_id' => $office->id]);
        IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $already->id]);

        $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [
            'recipient_type' => 'office',
            'office_ids' => [$office->id],
        ])->assertRedirect();

        $newRecipientId = IssuanceRecipient::where('issuance_id', $issuance->id)->where('user_id', $new->id)->value('id');
        Queue::assertPushed(NotifyAddedIssuanceRecipients::class, fn ($job) => $job->recipientIds === [$newRecipientId]);
    }

    public function test_adding_only_already_existing_recipients_dispatches_no_job(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $issuance = $this->releasedIssuance();
        $existing = User::factory()->create();
        IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $existing->id]);

        $this->actingAs($admin)->post(route('issuances.recipients.add', $issuance->id), [
            'recipient_type' => 'individual',
            'user_ids' => [$existing->id],
        ])->assertRedirect()->assertSessionHas('success');

        Queue::assertNotPushed(NotifyAddedIssuanceRecipients::class);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IssuanceRecipientsAddTest"`
Expected: FAIL — route `issuances.recipients.add` not defined

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the `issuances.` group, add the new route right after `Route::post('/{issuance}/recipients/resend-bulk', ...)` (currently line 387-388) and before the closing `});` (line 389):

```php
        Route::post('/{issuance}/recipients', [\App\Http\Controllers\IssuanceController::class, 'addRecipients'])
            ->name('recipients.add')->middleware('permission:issuances.manage');
```

- [ ] **Step 4: Add the controller method**

In `app/Http/Controllers/IssuanceController.php`, add the import next to the existing `use App\Jobs\ResendIssuanceEmails;` (line 7):

```php
use App\Jobs\NotifyAddedIssuanceRecipients;
```

Then add the method right before the `// ── Resend recipient email ──` section comment (currently line 612), i.e. immediately after `resendBulkEmails()`'s closing `}` — actually place it right after `acknowledge()` and before `downloadPdf()` is fine too, but for locality with the rest of the recipient-management endpoints, add it directly above `// ── Resend recipient email ───` :

```php
    // ── Add recipients (post-release) ───────────────────────────────────────

    /** Add more recipients to an already-released issuance; new recipients are emailed + bell-notified. */
    public function addRecipients(Request $request, Issuance $issuance)
    {
        abort_if(! $issuance->isReleased(), 422, 'Only released issuances can receive additional recipients.');
        abort_if($issuance->isArchived(), 422, 'Restore this issuance from the archive before adding recipients.');

        $validated = $request->validate([
            'recipient_type'  => ['required', Rule::in(['all', 'office', 'individual', 'division'])],
            'office_ids'      => 'nullable|array',
            'office_ids.*'    => 'exists:offices,id',
            'user_ids'        => 'nullable|array',
            'user_ids.*'      => 'exists:users,id',
            'division_ids'    => 'nullable|array',
            'division_ids.*'  => 'exists:divisions,id',
        ]);

        $newRecipientIds = $this->svc->addRecipients($issuance, $validated);

        if (empty($newRecipientIds)) {
            return back()->with('success', 'No new recipients — everyone selected already has this issuance.');
        }

        AuditLogger::log([
            'action'         => 'issuance_recipients_added',
            'auditable_type' => Issuance::class,
            'auditable_id'   => $issuance->id,
            'new_values'     => ['recipient_type' => $validated['recipient_type'], 'added_count' => count($newRecipientIds)],
        ]);

        NotifyAddedIssuanceRecipients::dispatch($issuance->id, $newRecipientIds);

        return back()->with('success', count($newRecipientIds) . ' new recipient(s) added and notified.');
    }

```

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=IssuanceRecipientsAddTest"`
Expected: PASS (6 tests)

- [ ] **Step 6: Run the full Issuance test surface to check for regressions**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=Issuance"`
Expected: PASS (all Issuance-related tests, including `IssuanceSupplementTest`, `IssuanceServiceAddRecipientsTest`, `NotifyAddedIssuanceRecipientsJobTest`, `IssuanceRecipientsAddTest`)

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/IssuanceController.php routes/web.php tests/Feature/IssuanceRecipientsAddTest.php
git commit -m "feat(issuances): add POST /issuances/{issuance}/recipients endpoint"
```

---

### Task 4: Frontend — "Add Recipient" modal on `Issuances/Show.vue`

**Files:**
- Modify: `app/Http/Controllers/IssuanceController.php` (`show()` method — pass `offices`/`users` props when admin)
- Modify: `resources/js/Pages/Issuances/Show.vue` (new button + modal + form state)

**Interfaces:**
- Consumes: route `issuances.recipients.add` (Task 3), existing `AppModal`, `AppButton`, `AppInput`, `AppCard` components (all already imported in `Show.vue`), the office/division/individual picker markup pattern from `resources/js/Pages/Issuances/Create.vue:286-352`.
- Produces: nothing consumed by a later task — this is the last task.

- [ ] **Step 1: Pass `offices` and `users` props from the controller**

In `app/Http/Controllers/IssuanceController.php`, in `show()`, find the `Inertia::render('Issuances/Show', [` block (starting at line 415) and change the `'divisions'` line (currently line 416) plus add two new keys right after it:

```php
            'divisions'  => $isAdmin ? Division::where('status', 'active')->orderBy('division_name')->get(['id', 'division_name', 'acronym']) : [],
            'offices'    => $isAdmin ? Office::orderBy('name')->get(['id', 'name']) : [],
            'users'      => $isAdmin ? User::employees()->where('status', '<>', 'inactive')
                ->orderBy('name')->get(['id', 'name', 'office_id', 'position']) : [],
```

(`Office` and `User` are already imported at the top of the controller — no new `use` statements needed.)

- [ ] **Step 2: Add props, state, and picker logic to `Show.vue`**

In `resources/js/Pages/Issuances/Show.vue`, add to the `defineProps({...})` block (currently lines 21-31), right after `supplements: Array,`:

```js
  offices:          Array,
  users:            Array,
```

Then, right after the existing `// ── Acknowledge ──` block (ends at line 81, before `// ── Scan preview ──` at line 83), add:

```js
// ── Add Recipient (post-release) ───────────────────────────────────────────
const showAddRecipientModal = ref(false)
const addRecipientType      = ref('individual')
const addOfficeIds          = ref([])
const addUserIds             = ref([])
const addDivisionIds         = ref([])
const addOfficeSearch        = ref('')
const addUserSearch          = ref('')
const addDivisionSearch      = ref('')
const addingRecipients       = ref(false)
const addRecipientErrors     = ref({})

const filteredAddOffices = computed(() => {
  const q = addOfficeSearch.value.toLowerCase()
  return (props.offices ?? []).filter(o => !q || o.name.toLowerCase().includes(q))
})

const filteredAddUsers = computed(() => {
  const q = addUserSearch.value.toLowerCase()
  const existingUserIds = new Set((props.recipients ?? []).map(r => r.user?.id).filter(Boolean))
  return (props.users ?? [])
    .filter(u => !existingUserIds.has(u.id))
    .filter(u => !q || u.name.toLowerCase().includes(q) || u.position?.toLowerCase().includes(q))
})

const filteredAddDivisions = computed(() => {
  const q = addDivisionSearch.value.toLowerCase()
  return (props.divisions ?? []).filter(d => !q || d.division_name.toLowerCase().includes(q) || d.acronym?.toLowerCase().includes(q))
})

function toggleAddOffice(id) {
  const idx = addOfficeIds.value.indexOf(id)
  if (idx === -1) addOfficeIds.value.push(id)
  else addOfficeIds.value.splice(idx, 1)
}

function toggleAddUser(id) {
  const idx = addUserIds.value.indexOf(id)
  if (idx === -1) addUserIds.value.push(id)
  else addUserIds.value.splice(idx, 1)
}

function toggleAddDivision(id) {
  const idx = addDivisionIds.value.indexOf(id)
  if (idx === -1) addDivisionIds.value.push(id)
  else addDivisionIds.value.splice(idx, 1)
}

function openAddRecipientModal() {
  addRecipientType.value = 'individual'
  addOfficeIds.value = []
  addUserIds.value = []
  addDivisionIds.value = []
  addRecipientErrors.value = {}
  showAddRecipientModal.value = true
}

function submitAddRecipients() {
  addingRecipients.value = true
  addRecipientErrors.value = {}
  router.post(route('issuances.recipients.add', props.issuance.id), {
    recipient_type: addRecipientType.value,
    office_ids: addOfficeIds.value,
    user_ids: addUserIds.value,
    division_ids: addDivisionIds.value,
  }, {
    preserveScroll: true,
    onSuccess: () => { addingRecipients.value = false; showAddRecipientModal.value = false },
    onError: e => { addRecipientErrors.value = e; addingRecipients.value = false },
  })
}
```

- [ ] **Step 3: Add the "Add Recipient" button**

In the Acknowledgments panel header (around line 391-398), add the button before the existing `Resend Selected`/`Resend All` buttons:

```html
                <div class="flex items-center gap-1.5">
                  <AppButton v-if="!issuance.archived_at" size="sm" variant="secondary" @click="openAddRecipientModal">
                    <PlusIcon class="h-3.5 w-3.5" /> Add Recipient
                  </AppButton>
                  <AppButton v-if="selectedRecipientIds.length" size="sm" variant="secondary" @click="resendSelected">
                    <ArrowPathIcon class="h-3.5 w-3.5" /> Resend Selected ({{ selectedRecipientIds.length }})
                  </AppButton>
                  <AppButton v-if="totalCount" size="sm" variant="secondary" @click="resendAll">
                    <ArrowPathIcon class="h-3.5 w-3.5" /> Resend All
                  </AppButton>
                </div>
```

(`PlusIcon` is already imported in `Show.vue`'s heroicons import block at line 18.)

- [ ] **Step 4: Add the modal**

Right after the existing `<!-- PIN Modal -->` block (ends around line 505) and before `<!-- Scan preview modal -->` (line 508), add:

```html
    <!-- Add Recipient Modal -->
    <AppModal :show="showAddRecipientModal" title="Add Recipient" size="lg" @close="showAddRecipientModal = false">
      <div class="space-y-5">
        <div>
          <label class="block text-xs font-medium text-slate-600 mb-2">Who should be added?</label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <button v-for="opt in [
              { key:'all', label:'All Staff' },
              { key:'office', label:'By Office' },
              { key:'division', label:'By Division' },
              { key:'individual', label:'Individual(s)' },
            ]" :key="opt.key"
              type="button"
              @click="addRecipientType = opt.key"
              class="flex flex-col items-center gap-1 p-3 rounded-xl border text-center transition-colors"
              :class="addRecipientType === opt.key ? 'border-indigo-500 bg-indigo-50' : 'border-slate-200 hover:border-slate-300'">
              <p class="text-xs font-semibold" :class="addRecipientType === opt.key ? 'text-indigo-700' : 'text-slate-700'">{{ opt.label }}</p>
            </button>
          </div>
        </div>

        <div v-if="addRecipientType === 'office'" class="space-y-2">
          <AppInput v-model="addOfficeSearch" type="text" placeholder="Search offices…" />
          <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
            <label v-for="o in filteredAddOffices" :key="o.id"
              class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
              <input type="checkbox" :checked="addOfficeIds.includes(o.id)"
                @change="toggleAddOffice(o.id)" class="rounded border-slate-300 text-indigo-600" />
              <span class="text-sm text-slate-700">{{ o.name }}</span>
            </label>
          </div>
          <p v-if="addOfficeIds.length" class="text-xs text-indigo-600 font-medium">{{ addOfficeIds.length }} office(s) selected</p>
        </div>

        <div v-if="addRecipientType === 'division'" class="space-y-2">
          <AppInput v-model="addDivisionSearch" type="text" placeholder="Search divisions…" />
          <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
            <label v-for="d in filteredAddDivisions" :key="d.id"
              class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
              <input type="checkbox" :checked="addDivisionIds.includes(d.id)"
                @change="toggleAddDivision(d.id)" class="rounded border-slate-300 text-indigo-600" />
              <div>
                <p class="text-sm text-slate-700">{{ d.division_name }}</p>
                <p v-if="d.acronym" class="text-xs text-slate-400">{{ d.acronym }}</p>
              </div>
            </label>
          </div>
          <p v-if="addDivisionIds.length" class="text-xs text-indigo-600 font-medium">{{ addDivisionIds.length }} division(s) selected</p>
        </div>

        <div v-if="addRecipientType === 'individual'" class="space-y-2">
          <AppInput v-model="addUserSearch" type="text" placeholder="Search by name or position…" />
          <div class="max-h-48 overflow-y-auto border border-slate-200 rounded-lg divide-y divide-slate-100">
            <label v-for="u in filteredAddUsers.slice(0, 50)" :key="u.id"
              class="flex items-center gap-3 px-3 py-2 hover:bg-slate-50 cursor-pointer">
              <input type="checkbox" :checked="addUserIds.includes(u.id)"
                @change="toggleAddUser(u.id)" class="rounded border-slate-300 text-indigo-600" />
              <div>
                <p class="text-sm font-medium text-slate-700">{{ u.name }}</p>
                <p v-if="u.position" class="text-xs text-slate-400">{{ u.position }}</p>
              </div>
            </label>
          </div>
          <p class="text-[10px] text-slate-400">Already-tagged recipients are hidden from this list.</p>
          <p v-if="addUserIds.length" class="text-xs text-indigo-600 font-medium">{{ addUserIds.length }} person(s) selected</p>
        </div>

        <p v-if="addRecipientErrors.recipient_type || addRecipientErrors.user_ids || addRecipientErrors.office_ids || addRecipientErrors.division_ids"
          class="text-xs text-red-600">
          {{ addRecipientErrors.recipient_type || addRecipientErrors.user_ids || addRecipientErrors.office_ids || addRecipientErrors.division_ids }}
        </p>
      </div>

      <template #footer>
        <AppButton variant="secondary" @click="showAddRecipientModal = false">Cancel</AppButton>
        <AppButton :disabled="addingRecipients" :loading="addingRecipients" @click="submitAddRecipients">
          {{ addingRecipients ? 'Adding…' : 'Add & Notify' }}
        </AppButton>
      </template>
    </AppModal>
```

- [ ] **Step 5: Build the frontend**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors (this project has no JS test runner — verification is build success + manual browser check).

- [ ] **Step 6: Manual verification in the browser**

1. Open `http://localhost:8080`, log in as an `issuances.manage` user.
2. Open an already-released issuance's Show page.
3. Confirm the "Add Recipient" button appears in the Acknowledgments panel header (and does NOT appear if the issuance is archived — unarchive/archive-toggle to check both states).
4. Click it, pick "Individual(s)", search for and select a user who is not already a recipient, submit.
5. Confirm: success flash message with the correct count, the new recipient appears in the Acknowledgments list with an `email_status` badge, and the dev mailbox (dev `.env` sends real mail per project convention) receives the issuance email for that user.
6. Confirm the newly-added user can log in and open the issuance from their own account (bell notification + `issuances.show` access).
7. Repeat once with "By Office" to confirm bulk add + dedup (pick an office where one member is already a recipient — confirm only the new member gets a fresh email).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/IssuanceController.php resources/js/Pages/Issuances/Show.vue
git commit -m "feat(issuances): add post-release Add Recipient modal to Issuances/Show.vue"
```
