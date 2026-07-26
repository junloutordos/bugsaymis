# Computer Lab Transfer — Room Swap Design

Date: 2026-07-27
Status: Approved, ready for implementation plan

## Problem

In the Computer Lab module, transferring a booking (`ComputerLabController::moveBooking` →
`ComputerLabSchedulingService::moveToRoom()`, route `computer-labs.bookings.move`) to a lab
that's already occupied during that period is **blocked outright** — the user gets a
validation error and has no way to resolve the conflict except cancelling and picking a
different room/time manually. There is no swap capability today anywhere in this module.

## Trigger scenario

The target lab slot is already booked by another booking at an overlapping day/time. This is
the *only* trigger for offering a swap — not a general "always offer swap" option.

## Approval model

Same-person, one-step swap. Whoever already has `computer_labs.manage` (or
`faculty_loading.manage`, or is SuperAdmin) permission to perform a transfer can also perform
a swap in one action — no separate approval workflow, no notification to the other booking's
owner. This matches the permission model already enforced by
`ComputerLabController::canManage()`.

## Scope

- **In scope:** the "Transfer Laboratory Schedule" modal (`resources/js/Pages/ITJobRequests/ComputerLabs/Index.vue`,
  `transferModal`/`openTransfer`/`submitTransfer`) and its backend
  (`ComputerLabSchedulingService::moveToRoom()`, route `computer-labs.bookings.move`).
- **Out of scope:** drag-and-drop transfer (`LabScheduleCalendarCard.vue`,
  `startBookingDrag`/`dropBooking` in `Index.vue`) stays move-only, blocked on conflict,
  exactly as today. No new database table. No new approval/notification workflow.

## When is a swap actually safe to offer?

A swap exchanges `room_id` between booking A (being transferred) and booking B (currently
occupying the target room). This is only offered — and only allowed — when:

1. **Exactly one** conflicting booking exists in the target room for A's day/time
   (`moveConflicts()` returns a single candidate). If there are multiple overlapping
   bookings, swap is ambiguous and stays block-only.
2. **Reverse feasibility holds**: once A vacates its original room, B must actually fit there
   for B's own day/time — i.e. running the equivalent of `moveConflicts()` for B against A's
   original room (excluding A, since A is leaving) must return no conflicts. This matters
   because a recurring `priority_class` booking (weekly, all-term) and a one-off `other`
   ad-hoc booking (`booking_date`-scoped) behave differently — the reverse check is what
   catches any conflict that would appear on other weeks or other bookings that the naive
   "just swap them" assumption would miss.
3. **Both bookings are independently movable** under the existing rule in `moveToRoom()`:
   confirmed priority class, or approved ad-hoc booking. If B fails this rule, swap is not
   offered even if 1 and 2 hold.

If any of these fail, the existing block-only error is shown, unchanged from today's
behavior.

## Backend design

Extend `ComputerLabSchedulingService::moveToRoom(ComputerLabBooking $booking, int $roomId, bool $swap = false)`:

- No conflict → move exactly as today (unchanged).
- Conflict, `$swap = false` → throw `ValidationException` as today, but additionally surface
  (as extra keys on the same error bag, not just the message string):
  - `conflict_booking_id`
  - `conflict_title`
  - `can_swap` (`'1'`/`'0'`) — computed via the reverse-feasibility check above
- Conflict, `$swap = true` → re-verify (never trust the client's earlier flag) that exactly
  one conflict exists, the reverse check passes, and both bookings are movable. If all hold,
  atomically exchange `room_id` between A and B within the existing `DB::transaction`, clear
  `conflict_note` on both. If any check now fails (e.g. a race), throw the normal validation
  error instead of silently partially applying anything.
- No status changes to either booking as part of a swap — both keep their current
  `status` (`confirmed`/`approved`), matching a normal transfer's behavior.

`ComputerLabController::moveBooking()` accepts the new optional `swap` boolean input and
passes it through; validation error responses carry the extra keys described above.

## Frontend design

In `Index.vue`'s transfer modal (`submitTransfer`):

1. Submit picks target room as today.
2. On success → close modal, unchanged.
3. On validation error:
   - If `can_swap === '1'` → show inline prompt: *"{Room} is occupied by {conflict_title}
     during this period. Swap rooms instead?"* with a **Swap Rooms** button alongside the
     existing Cancel.
   - Else → show the existing blocking message only, no swap button.
4. Clicking **Swap Rooms** resubmits the same transfer request with `swap: true` and the same
   target `room_id`. On success, close modal; on failure (race condition), fall back to the
   normal blocking message.

No changes to `LabScheduleCalendarCard.vue` or the drag-and-drop path.

## Testing expectations

- Backend: unit/feature tests on `ComputerLabSchedulingService` covering: swap offered on
  single conflict + reverse-feasible; swap not offered on multiple conflicts; swap not
  offered when reverse check fails; swap not offered when the conflicting booking isn't
  movable; successful swap exchanges `room_id` atomically and clears `conflict_note` on both
  rows; forcing `swap: true` when conditions no longer hold still throws.
- Frontend: no dedicated component tests exist for this module today (verify during
  implementation) — validate manually via the dev app (transfer into an occupied room, resolve
  via swap, confirm both bookings' rooms updated and calendar reflects it).
