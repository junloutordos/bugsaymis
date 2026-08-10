# Issuance Module: Add Recipients After Release

**Date:** 2026-08-10
**Status:** Approved

## Problem

Recipients for an Official Issuance are fixed once, at the moment it's released. `IssuanceController::release()` validates a `recipient_type` (`all`/`office`/`individual`/`division`) plus the matching ID list, and `IssuanceService::buildRecipients()` deletes any existing `issuance_recipients` rows and rebuilds the set from scratch. There is no way to add someone after that point.

In practice: an admin forgets to tag an office, or a new hire needs access to an issuance that was released before they joined, or a division head asks that their whole office also receive a memo that already went out. Today the only workaround is manually creating an `issuance_recipients` row via the DB, which sends no email/notification.

## Goal

Let an admin (`issuances.manage`) add more recipients to an issuance **after** it has been released (and while it is not archived), and have those newly-added recipients get the same email + bell/push notification that original recipients got at release time, plus normal view access to the issuance.

## Non-Goals

- Not touching the pre-existing quirk where the *release* panel in `Show.vue` only supports "All Staff"/"By Division" recipient types (office/individual there says "configure on create", because that panel never collects `office_ids`/`user_ids`). That gap is unrelated to this feature.
- Not cascading an add on a parent issuance down to its already-released supplements. Adding is scoped to the single document being viewed.
- Not adding a visible "added after release" badge per recipient row in the UI. The audit log captures who/when/how many were added; the recipient list will not visually distinguish original vs. later-added recipients.
- No new database columns/tables — `issuance_recipients` already has everything needed (`user_id`, `notified_at`, `acknowledged_at`, `email_status`, `emailed_at`, `email_error`).

## Design

### 1. `IssuanceService::addRecipients(Issuance $issuance, array $data): array`

New method alongside the existing `buildRecipients()`. Reuses the same per-type resolution logic (`all` → `User::employees()` active; `office` → active employees in `office_ids`; `individual` → active employees in `user_ids`; `division` → active employees in `division_ids`), but:

- Does **not** delete existing recipients (additive, not a rebuild).
- Diffs the resolved target user IDs against `$issuance->recipients()->pluck('user_id')` to get only the genuinely new IDs.
- Bulk-inserts `IssuanceRecipient` rows for the new IDs with `notified_at = now()` (they're notified immediately by the dispatched job below).
- Returns the newly-inserted recipient row IDs (queried back by `issuance_id` + `user_id IN (...)` after insert, since bulk insert doesn't reliably return IDs).
- If the diff is empty, returns `[]` — caller decides the "nothing to do" message.

### 2. `IssuanceController::addRecipients(Request $request, Issuance $issuance)` + route

`POST /issuances/{issuance}/recipients` → named `issuances.recipients.add`, added inside the existing `Route::prefix('issuances')` group under `Route::middleware('permission:issuances.manage')`, next to the other management-only issuance routes.

```php
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

Uses `back()->with(...)` per project convention (Do's list). `isReleased()`/`isArchived()` are existing `Issuance` model helpers already used elsewhere in the controller.

### 3. New job `App\Jobs\NotifyAddedIssuanceRecipients`

Mirrors the notify loop inside `ProcessIssuanceRelease::handle()` (step 3, "Notify all recipients"), but scoped to a specific list of recipient IDs instead of all of them — same shape as `ResendIssuanceEmails` (`issuanceId` + `recipientIds` constructor args, `tries = 1`, `timeout = 600`, same queue-timeout-vs-retry_after reasoning documented there).

Per recipient:
- Skip + mark `email_status = 'skipped'` if no user or no email on file.
- `Mail::to($u->email)->send(new IssuanceReleasedMail($issuance, $u->name))`, update `email_status`/`emailed_at`/`email_error` on success/failure — identical to the existing jobs.
- `NotificationService::notifyUser(...)` (bell/push) — this is the difference from `ResendIssuanceEmails`, which deliberately skips bell notification because a resend targets people who already received one. A newly-added recipient has never gotten a bell notification, so it's included here, wrapped in its own try/catch exactly like `ProcessIssuanceRelease` does.
- `failed()` handler logs the same way as the other two jobs.

Kept as its own job (rather than adding a `bool $notifyBell` flag to `ResendIssuanceEmails`) to keep each job's name accurately describing what it does, consistent with how `ProcessIssuanceRelease` and `ResendIssuanceEmails` are already two separate near-duplicate loops rather than one parameterized job — that's the established pattern in this codebase.

### 4. Frontend — `Issuances/Show.vue`

**Controller (`show()`):** when `isAdmin`, also pass:
```php
'offices' => Office::orderBy('name')->get(['id', 'name']),
'users'   => User::employees()->where('status', '<>', 'inactive')->orderBy('name')->get(['id', 'name', 'office_id', 'position']),
```
Same query shape `IssuanceController::create()` already uses for `Create.vue` — no new data-access pattern.

**UI:** New "Add Recipient" button in the "Acknowledgments" panel header (next to "Resend Selected"/"Resend All"), shown only when `isAdmin && issuance.status === 'released' && !issuance.archived_at`.

Clicking it opens a new `AppModal` containing a picker that reuses `Create.vue`'s existing recipient-type UI pattern:
- Radio: All Staff / By Office / By Division / Individual(s).
- For By Office / By Division / Individual: a search box + checkbox list, client-side filtered against the `offices`/`divisions`/`users` props (same as `Create.vue`'s `filteredDivisions` pattern already in `Show.vue`, extended with `filteredOffices`/`filteredUsers`).

Submit posts to `issuances.recipients.add` with `{ recipient_type, office_ids, user_ids, division_ids }` via `router.post(..., { preserveScroll: true, onSuccess: () => { closeModal(); } })`. Inertia's standard prop refresh on success re-renders the recipients list with the new rows and their live `email_status` — no manual state patching needed.

## Edge Cases

| Case | Behavior |
|---|---|
| Issuance is still a draft | 422 — "Only released issuances can receive additional recipients." (Add button not shown either.) |
| Issuance is archived | 422 — "Restore this issuance from the archive before adding recipients." (Add button not shown either.) |
| All selected people are already recipients | No DB writes, no job dispatched, flash message tells the admin nothing changed. |
| Selected office/division has some existing + some new recipients | Existing ones are silently skipped; only the new ones get inserted + notified — no duplicate email. |
| Selected user has no email on file | Row inserted, job marks `email_status = 'skipped'` with the same "No email on file" message already used elsewhere — visible in the recipient list. |
| Large office/division add (hundreds of users) | Handled by the queued job (`NotifyAddedIssuanceRecipients`), same 600s budget as `ProcessIssuanceRelease`/`ResendIssuanceEmails` — avoids the 60s web-request timeout. |
| Issuance is a supplement | Same rules apply to the supplement document itself; does not touch the parent's or siblings' recipient lists. |

## Testing Plan

- Feature test: adding an individual user to a released issuance inserts exactly one new `issuance_recipients` row and dispatches the notify job with that one ID.
- Feature test: adding an office where 2 of 5 members are already recipients only inserts the 3 new ones.
- Feature test: adding to a draft issuance returns 422.
- Feature test: adding to an archived issuance returns 422.
- Feature test: non-`issuances.manage` user gets 403 (route middleware).
- Unit/job test: `NotifyAddedIssuanceRecipients` sends mail + calls `NotificationService::notifyUser` for each recipient, marks `email_status` correctly on success/failure/skip.
- Manual/dev verification: release an issuance, add a recipient via the new modal, confirm the recipient appears in the Acknowledgments list with `email_status = sent` and receives the actual email (dev `.env` sends real mail per project memory) and bell notification, and can open the issuance.
