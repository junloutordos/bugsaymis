# Computer Lab Transfer Room-Swap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** When transferring a Computer Lab booking to an already-occupied lab, offer a one-step "swap rooms" resolution instead of only blocking the move.

**Architecture:** Extend `ComputerLabSchedulingService::moveToRoom()` with an optional `swap` flag and a reverse-feasibility safety check; extend the "Transfer Laboratory Schedule" modal in `Index.vue` to surface a "Swap Rooms Instead" action when the backend reports it's safe. Drag-and-drop and the database schema are untouched.

**Tech Stack:** Laravel 12 (PHP 8.4), Vue 3 `<script setup>` + Inertia.js 2, PHPUnit feature tests, `RefreshDatabase`.

## Global Constraints

- Swap is offered ONLY when: (1) exactly one conflicting booking exists in the target room/time, (2) a reverse-feasibility check confirms the conflicting booking would actually fit into the moving booking's original room once vacated, (3) both bookings independently satisfy the existing "movable" rule (confirmed priority class, or approved ad-hoc booking). All three must hold — do not simplify or drop any condition.
- No new database table or migration — a swap only exchanges `room_id` between the two existing `computer_lab_bookings` rows, and clears `conflict_note` on both.
- No status changes to either booking as part of a swap (`status` stays `confirmed`/`approved` as it already was).
- No approval/notification workflow changes — a swap is a one-step action under the same permission already enforced by `ComputerLabController::canManage()` (`computer_labs.manage` or `faculty_loading.manage` or SuperAdmin).
- Drag-and-drop (`LabScheduleCalendarCard.vue`, `startBookingDrag`/`dropBooking` in `Index.vue`) is out of scope — stays move-only, blocked on conflict, unchanged.
- Reuse the existing `router.patch(route('computer-labs.bookings.move', booking.id), {...})` JSON request pattern already used by this endpoint — do not switch to `FormData`/multipart.

---

### Task 1: Service-layer swap logic

**Files:**
- Modify: `app/Services/ComputerLabSchedulingService.php:16-60` (`moveToRoom`), `:272-313` (`moveConflicts`)
- Test: `tests/Feature/ComputerLabSchedulingTest.php` (append new test methods after the existing `test_recurring_priority_move_checks_approved_bookings_across_the_term` method, before `test_manager_can_move_a_booking_through_the_room_endpoint`)

**Interfaces:**
- Produces: `ComputerLabSchedulingService::moveToRoom(ComputerLabBooking $booking, int $roomId, bool $swap = false): bool` — returns `true` only when a swap was actually performed; returns `false` for a plain move or a same-room no-op. On an unresolved conflict, throws `Illuminate\Validation\ValidationException` with message key `booking` always present; when `$swap` was `false`, the error bag additionally carries `conflict_booking_id` (string), `conflict_title` (string), and `can_swap` (`'1'` or `'0'`).
- Consumes: nothing new — uses existing `ComputerLabBooking`, `Room` models and the existing private `moveConflicts()` helper (extended in this task).

- [ ] **Step 1: Write the failing tests**

Add these methods to `tests/Feature/ComputerLabSchedulingTest.php` (inside the `ComputerLabSchedulingTest` class, after `test_recurring_priority_move_checks_approved_bookings_across_the_term`):

```php
    public function test_swap_exchanges_rooms_between_two_approved_ad_hoc_bookings(): void
    {
        $rooms = Room::where('room_type', 'Computer Laboratory')->orderBy('id')->get();

        $bookingA = ComputerLabBooking::create([
            'room_id' => $rooms[0]->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-06-03',
            'day_of_week' => 'Tuesday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'booking_type' => 'other',
            'title' => 'Robotics Club',
            'purpose' => 'Training',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        $bookingB = ComputerLabBooking::create([
            'room_id' => $rooms[1]->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-06-03',
            'day_of_week' => 'Tuesday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'booking_type' => 'other',
            'title' => 'Chess Club',
            'purpose' => 'Practice',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        $roomA = $bookingA->room_id;
        $roomB = $bookingB->room_id;

        $swapped = app(ComputerLabSchedulingService::class)->moveToRoom($bookingA, $roomB, true);

        $this->assertTrue($swapped);
        $this->assertSame($roomB, $bookingA->refresh()->room_id);
        $this->assertSame($roomA, $bookingB->refresh()->room_id);
        $this->assertSame('approved', $bookingA->status);
        $this->assertSame('approved', $bookingB->status);
    }

    public function test_swap_exchanges_rooms_between_two_recurring_priority_classes(): void
    {
        foreach (range(1, 2) as $number) {
            ClassSchedule::create([
                'user_id' => $this->faculty->id,
                'subject_id' => $this->subject->id,
                'section_id' => null,
                'classroom_id' => null,
                'school_year_id' => $this->term->school_year_id,
                'academic_term_id' => $this->term->id,
                'entry_type' => 'class',
                'day_of_week' => 'Friday',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'status' => 'active',
            ]);
        }

        $service = app(ComputerLabSchedulingService::class);
        $service->synchronizeTerm($this->term->id);
        [$first, $second] = ComputerLabBooking::where('booking_type', 'priority_class')->orderBy('id')->get();
        $firstOriginalRoom = $first->room_id;
        $secondOriginalRoom = $second->room_id;

        $swapped = $service->moveToRoom($first, $secondOriginalRoom, true);

        $this->assertTrue($swapped);
        $this->assertSame($secondOriginalRoom, $first->refresh()->room_id);
        $this->assertSame($firstOriginalRoom, $second->refresh()->room_id);
    }

    public function test_swap_is_not_offered_when_multiple_bookings_conflict_in_target_room(): void
    {
        $rooms = Room::where('room_type', 'Computer Laboratory')->orderBy('id')->get();

        $moving = ComputerLabBooking::create([
            'room_id' => $rooms[0]->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-06-03',
            'day_of_week' => 'Tuesday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'booking_type' => 'other',
            'title' => 'Robotics Club',
            'purpose' => 'Training',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        // Two overlapping bookings placed directly in the target room to force
        // an ambiguous conflict. This state can't arise through the service
        // itself (it always checks before writing), only through direct
        // seeding — but the "exactly one conflict" guard must still hold.
        foreach ([['13:00', '14:00', 'Chess Club'], ['13:30', '14:30', 'Debate Society']] as [$start, $end, $title]) {
            ComputerLabBooking::create([
                'room_id' => $rooms[1]->id,
                'academic_term_id' => $this->term->id,
                'booking_date' => '2098-06-03',
                'day_of_week' => 'Tuesday',
                'start_time' => $start,
                'end_time' => $end,
                'booking_type' => 'other',
                'title' => $title,
                'purpose' => 'Practice',
                'requested_by' => $this->faculty->id,
                'status' => 'approved',
            ]);
        }

        try {
            app(ComputerLabSchedulingService::class)->moveToRoom($moving, $rooms[1]->id, true);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayNotHasKey('can_swap', $e->errors());
        }

        $this->assertSame($rooms[0]->id, $moving->refresh()->room_id);
    }

    public function test_swap_fails_when_reverse_room_is_not_actually_free(): void
    {
        $schedule = ClassSchedule::create([
            'user_id' => $this->faculty->id,
            'subject_id' => $this->subject->id,
            'section_id' => null,
            'classroom_id' => null,
            'school_year_id' => $this->term->school_year_id,
            'academic_term_id' => $this->term->id,
            'entry_type' => 'class',
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'status' => 'active',
        ]);

        $service = app(ComputerLabSchedulingService::class);
        $service->synchronizeTerm($this->term->id);
        $priority = ComputerLabBooking::where('class_schedule_id', $schedule->id)->firstOrFail();
        $priorityOriginalRoom = $priority->room_id;

        $targetRoom = Room::where('room_type', 'Computer Laboratory')->whereKeyNot($priorityOriginalRoom)->firstOrFail();
        $conflict = ComputerLabBooking::create([
            'room_id' => $targetRoom->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-07-07', // a Monday within the term
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'booking_type' => 'other',
            'title' => 'One-off robotics session',
            'purpose' => 'Training',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        // Force an inconsistent seed: another booking already sits in the
        // priority class's own room on the exact date the conflicting
        // booking would need to move to, so the reverse fit must fail even
        // though the forward conflict looks like a clean 1-for-1 swap.
        ComputerLabBooking::create([
            'room_id' => $priorityOriginalRoom,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-07-07',
            'day_of_week' => 'Monday',
            'start_time' => '08:00',
            'end_time' => '09:00',
            'booking_type' => 'other',
            'title' => 'Pre-existing session',
            'purpose' => 'Blocks the reverse swap',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        try {
            $service->moveToRoom($priority, $targetRoom->id, true);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayNotHasKey('can_swap', $e->errors());
        }

        $this->assertSame($priorityOriginalRoom, $priority->refresh()->room_id);
        $this->assertSame($targetRoom->id, $conflict->refresh()->room_id);
    }

    public function test_conflict_error_exposes_swap_metadata_when_swap_is_offered(): void
    {
        foreach (range(1, 2) as $number) {
            ClassSchedule::create([
                'user_id' => $this->faculty->id,
                'subject_id' => $this->subject->id,
                'section_id' => null,
                'classroom_id' => null,
                'school_year_id' => $this->term->school_year_id,
                'academic_term_id' => $this->term->id,
                'entry_type' => 'class',
                'day_of_week' => 'Friday',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'status' => 'active',
            ]);
        }

        $service = app(ComputerLabSchedulingService::class);
        $service->synchronizeTerm($this->term->id);
        [$first, $second] = ComputerLabBooking::where('booking_type', 'priority_class')->orderBy('id')->get();

        try {
            $service->moveToRoom($first, $second->room_id);
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->assertSame((string) $second->id, $errors['conflict_booking_id'][0]);
            $this->assertSame($second->title, $errors['conflict_title'][0]);
            $this->assertSame('1', $errors['can_swap'][0]);
        }
    }

    public function test_move_returns_false_and_swap_flag_is_a_no_op_when_destination_is_free(): void
    {
        $rooms = Room::where('room_type', 'Computer Laboratory')->orderBy('id')->get();
        $booking = ComputerLabBooking::create([
            'room_id' => $rooms[0]->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-06-03',
            'day_of_week' => 'Tuesday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'booking_type' => 'other',
            'title' => 'Approved training',
            'purpose' => 'Training',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        $swapped = app(ComputerLabSchedulingService::class)->moveToRoom($booking, $rooms[3]->id, true);

        $this->assertFalse($swapped);
        $this->assertSame($rooms[3]->id, $booking->refresh()->room_id);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ComputerLabSchedulingTest"`
Expected: FAIL — `moveToRoom()` does not yet accept a third argument / does not return a value / error bag lacks `conflict_booking_id`, `conflict_title`, `can_swap`.

- [ ] **Step 3: Implement `moveConflicts()` ignore-id support**

Replace `app/Services/ComputerLabSchedulingService.php:272-282` (the query portion of `moveConflicts`, keeping the rest of the method body — the filtering logic after `$candidates = ...->get();` at lines 284-313 — unchanged):

```php
    private function moveConflicts(ComputerLabBooking $booking, int $roomId, ?int $ignoreId = null): Collection
    {
        $candidates = ComputerLabBooking::query()
            ->where('room_id', $roomId)
            ->where('academic_term_id', $booking->academic_term_id)
            ->whereKeyNot($booking->id)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->occupying()
            ->where('start_time', '<', $booking->end_time)
            ->where('end_time', '>', $booking->start_time)
            ->lockForUpdate()
            ->get();
```

- [ ] **Step 4: Implement `canSwap()` and rewrite `moveToRoom()`**

Replace `app/Services/ComputerLabSchedulingService.php:16-60` with:

```php
    public function moveToRoom(ComputerLabBooking $booking, int $roomId, bool $swap = false): bool
    {
        return DB::transaction(function () use ($booking, $roomId, $swap) {
            $locked = ComputerLabBooking::with('academicTerm')
                ->whereKey($booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $movable = ($locked->isPriorityClass() && $locked->status === 'confirmed')
                || (! $locked->isPriorityClass() && $locked->status === 'approved');

            if (! $movable) {
                throw ValidationException::withMessages([
                    'booking' => 'Only confirmed priority classes and approved bookings can be moved.',
                ]);
            }

            $room = Room::whereKey($roomId)
                ->where('room_type', 'Computer Laboratory')
                ->lockForUpdate()
                ->first();

            if (! $room) {
                throw ValidationException::withMessages([
                    'room_id' => 'Select a valid computer laboratory.',
                ]);
            }

            if ((int) $locked->room_id === $roomId) {
                return false;
            }

            $conflicts = $this->moveConflicts($locked, $roomId);
            if ($conflicts->isEmpty()) {
                $locked->update([
                    'room_id' => $roomId,
                    'conflict_note' => null,
                ]);

                return false;
            }

            $conflict = $conflicts->first();
            $canSwap = $conflicts->count() === 1 && $this->canSwap($locked, $conflict);

            if ($swap && $canSwap) {
                $originalRoomId = (int) $locked->room_id;
                $locked->update(['room_id' => $roomId, 'conflict_note' => null]);
                $conflict->update(['room_id' => $originalRoomId, 'conflict_note' => null]);

                return true;
            }

            throw ValidationException::withMessages(array_merge([
                'booking' => "{$room->name} is already occupied by {$conflict->title} during this period.",
            ], $swap ? [] : [
                'conflict_booking_id' => (string) $conflict->id,
                'conflict_title' => $conflict->title,
                'can_swap' => $canSwap ? '1' : '0',
            ]));
        });
    }

    private function canSwap(ComputerLabBooking $locked, ComputerLabBooking $conflict): bool
    {
        $conflictMovable = ($conflict->isPriorityClass() && $conflict->status === 'confirmed')
            || (! $conflict->isPriorityClass() && $conflict->status === 'approved');

        if (! $conflictMovable) {
            return false;
        }

        return $this->moveConflicts($conflict, (int) $locked->room_id, $locked->id)->isEmpty();
    }
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ComputerLabSchedulingTest"`
Expected: PASS — all existing tests in this file plus the 6 new ones from Step 1.

- [ ] **Step 6: Lint and commit**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && ./vendor/bin/pint app/Services/ComputerLabSchedulingService.php tests/Feature/ComputerLabSchedulingTest.php"`

```bash
git add app/Services/ComputerLabSchedulingService.php tests/Feature/ComputerLabSchedulingTest.php
git commit -m "feat(computer-labs): add room-swap option to booking transfer service"
```

---

### Task 2: Controller wiring for the `swap` flag

**Files:**
- Modify: `app/Http/Controllers/ComputerLabController.php:213-230` (`moveBooking`)
- Test: `tests/Feature/ComputerLabSchedulingTest.php` (append after the Task 1 tests, before `test_manager_can_move_a_booking_through_the_room_endpoint`)

**Interfaces:**
- Consumes: `ComputerLabSchedulingService::moveToRoom(ComputerLabBooking $booking, int $roomId, bool $swap = false): bool` (from Task 1).
- Produces: route `computer-labs.bookings.move` (PATCH `/computer-labs/bookings/{booking}/room`) now accepts an optional `swap` boolean in the request body; the "success" flash message text differs between a plain move and a swap, which Task 3's frontend does not depend on (it only inspects error keys), so this is safe to add without coordinating with the Vue changes.

- [ ] **Step 1: Write the failing tests**

Add these methods to `tests/Feature/ComputerLabSchedulingTest.php`:

```php
    public function test_manager_can_swap_two_conflicting_priority_classes_through_the_room_endpoint(): void
    {
        foreach (range(1, 2) as $number) {
            ClassSchedule::create([
                'user_id' => $this->faculty->id,
                'subject_id' => $this->subject->id,
                'section_id' => null,
                'classroom_id' => null,
                'school_year_id' => $this->term->school_year_id,
                'academic_term_id' => $this->term->id,
                'entry_type' => 'class',
                'day_of_week' => 'Friday',
                'start_time' => '09:00',
                'end_time' => '10:00',
                'status' => 'active',
            ]);
        }

        app(ComputerLabSchedulingService::class)->synchronizeTerm($this->term->id);
        [$first, $second] = ComputerLabBooking::where('booking_type', 'priority_class')->orderBy('id')->get();
        $firstOriginalRoom = $first->room_id;
        $secondOriginalRoom = $second->room_id;

        $manager = $this->userWithPermission('computer_labs.manage');

        $this->actingAs($manager)
            ->patch(route('computer-labs.bookings.move', $first), ['room_id' => $secondOriginalRoom])
            ->assertSessionHasErrors('booking');

        $this->actingAs($manager)
            ->patch(route('computer-labs.bookings.move', $first), ['room_id' => $secondOriginalRoom, 'swap' => true])
            ->assertSessionHasNoErrors();

        $this->assertSame($secondOriginalRoom, $first->refresh()->room_id);
        $this->assertSame($firstOriginalRoom, $second->refresh()->room_id);
    }

    public function test_manager_cannot_force_a_swap_when_multiple_bookings_conflict_in_target_room(): void
    {
        $rooms = Room::where('room_type', 'Computer Laboratory')->orderBy('id')->get();

        $moving = ComputerLabBooking::create([
            'room_id' => $rooms[0]->id,
            'academic_term_id' => $this->term->id,
            'booking_date' => '2098-06-03',
            'day_of_week' => 'Tuesday',
            'start_time' => '13:00',
            'end_time' => '14:00',
            'booking_type' => 'other',
            'title' => 'Robotics Club',
            'purpose' => 'Training',
            'requested_by' => $this->faculty->id,
            'status' => 'approved',
        ]);

        foreach ([['13:00', '14:00', 'Chess Club'], ['13:30', '14:30', 'Debate Society']] as [$start, $end, $title]) {
            ComputerLabBooking::create([
                'room_id' => $rooms[1]->id,
                'academic_term_id' => $this->term->id,
                'booking_date' => '2098-06-03',
                'day_of_week' => 'Tuesday',
                'start_time' => $start,
                'end_time' => $end,
                'booking_type' => 'other',
                'title' => $title,
                'purpose' => 'Practice',
                'requested_by' => $this->faculty->id,
                'status' => 'approved',
            ]);
        }

        $this->actingAs($this->userWithPermission('computer_labs.manage'))
            ->patch(route('computer-labs.bookings.move', $moving), ['room_id' => $rooms[1]->id, 'swap' => true])
            ->assertSessionHasErrors('booking');

        $this->assertSame($rooms[0]->id, $moving->refresh()->room_id);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ComputerLabSchedulingTest"`
Expected: FAIL on the first new test — the endpoint has no `swap` input, so the second `patch(...)` call in
`test_manager_can_swap_two_conflicting_priority_classes_through_the_room_endpoint` still hits the plain-conflict path and `assertSessionHasNoErrors()` fails.

- [ ] **Step 3: Implement the controller change**

Replace `app/Http/Controllers/ComputerLabController.php:213-230`:

```php
    public function moveBooking(
        Request $request,
        ComputerLabBooking $booking,
        ComputerLabSchedulingService $scheduler,
    ): RedirectResponse {
        abort_unless($this->canManage($request), 403);

        $data = $request->validate([
            'room_id' => [
                'required',
                Rule::exists('rooms', 'id')->where(fn ($query) => $query->where('room_type', 'Computer Laboratory')),
            ],
            'swap' => ['sometimes', 'boolean'],
        ]);

        $swapped = $scheduler->moveToRoom($booking, (int) $data['room_id'], (bool) ($data['swap'] ?? false));

        return back()->with('success', $swapped
            ? 'Computer laboratory schedules swapped successfully.'
            : 'Computer laboratory schedule moved successfully.');
    }
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=ComputerLabSchedulingTest"`
Expected: PASS — all tests in the file, including the two new ones.

- [ ] **Step 5: Lint and commit**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && ./vendor/bin/pint app/Http/Controllers/ComputerLabController.php tests/Feature/ComputerLabSchedulingTest.php"`

```bash
git add app/Http/Controllers/ComputerLabController.php tests/Feature/ComputerLabSchedulingTest.php
git commit -m "feat(computer-labs): accept swap flag on the lab-transfer endpoint"
```

---

### Task 3: Transfer modal UI — offer the swap

**Files:**
- Modify: `resources/js/Pages/ITJobRequests/ComputerLabs/Index.vue:2` (imports), `:16-20` (icon imports), `:210-255` (transfer state/functions), `:430-459` (modal template)

**Interfaces:**
- Consumes: route `computer-labs.bookings.move` (PATCH) with body `{ room_id, swap? }` (from Task 2); on validation failure the Inertia error bag carries `errors.booking` (string), and — only when a swap was not yet requested — `errors.conflict_booking_id`, `errors.conflict_title`, `errors.can_swap` (`'1'`/`'0'`) (from Task 1/2).
- Produces: no new exports — this is a leaf page component.

No automated test runner exists for Vue components in this repo (no `vitest`/`jest` in `package.json`); this task ends with a manual dev-server verification instead of an automated test, per the design spec's testing section.

- [ ] **Step 1: Add the `watch` import and the swap icon**

In `resources/js/Pages/ITJobRequests/ComputerLabs/Index.vue`, change line 2:

```js
import { computed, reactive, ref, watch } from 'vue'
```

And change lines 16-20 to add `ArrowsRightLeftIcon`:

```js
import {
  ArrowLeftIcon, ArrowPathIcon, ArrowRightIcon, ArrowsRightLeftIcon, CalendarDaysIcon,
  CheckIcon, ComputerDesktopIcon, ExclamationTriangleIcon,
  PlusIcon, PrinterIcon, SignalIcon, XMarkIcon,
} from '@heroicons/vue/24/outline'
```

- [ ] **Step 2: Replace the transfer modal state and functions**

Replace `resources/js/Pages/ITJobRequests/ComputerLabs/Index.vue:210-255` (from `const transferModal = ref(false)` through the closing brace of `submitTransfer`) with:

```js
const transferModal = ref(false)
const transferBooking = ref(null)
const transferRoomId = ref(null)
const transferError = ref(null)
const transferSwapOffer = ref(null)
const swapPending = ref(false)

const transferLabOptions = computed(() => {
  if (!transferBooking.value) return []
  return props.labs.filter(lab => lab.room.id !== transferBooking.value.room_id)
})

const transferConflict = computed(() => {
  const booking = transferBooking.value
  if (!booking || !transferRoomId.value) return null

  return props.bookings.find(candidate =>
    candidate.id !== booking.id
    && candidate.date === booking.date
    && candidate.room_id === Number(transferRoomId.value)
    && ['confirmed', 'approved'].includes(candidate.status)
    && overlaps(booking, candidate)
  ) ?? null
})

watch(transferRoomId, () => {
  transferError.value = null
  transferSwapOffer.value = null
})

function openTransfer(booking) {
  transferBooking.value = booking
  transferRoomId.value = transferLabOptions.value[0]?.room?.id ?? null
  transferError.value = null
  transferSwapOffer.value = null
  transferModal.value = true
}

function closeTransfer() {
  transferModal.value = false
  transferBooking.value = null
  transferRoomId.value = null
  transferError.value = null
  transferSwapOffer.value = null
}

function submitTransfer() {
  if (!transferBooking.value || !transferRoomId.value || movingBookingId.value) return

  const booking = transferBooking.value
  movingBookingId.value = booking.id
  swapPending.value = false
  transferError.value = null
  transferSwapOffer.value = null

  router.patch(route('computer-labs.bookings.move', booking.id), { room_id: Number(transferRoomId.value) }, {
    preserveScroll: true,
    onSuccess: () => closeTransfer(),
    onError: (errors) => {
      transferError.value = errors.booking ?? null
      transferSwapOffer.value = errors.can_swap === '1' ? { title: errors.conflict_title } : null
    },
    onFinish: () => { movingBookingId.value = null },
  })
}

function submitTransferSwap() {
  if (!transferBooking.value || !transferRoomId.value || movingBookingId.value) return

  const booking = transferBooking.value
  movingBookingId.value = booking.id
  swapPending.value = true

  router.patch(route('computer-labs.bookings.move', booking.id), { room_id: Number(transferRoomId.value), swap: true }, {
    preserveScroll: true,
    onSuccess: () => closeTransfer(),
    onError: (errors) => {
      transferError.value = errors.booking ?? null
      transferSwapOffer.value = null
    },
    onFinish: () => { movingBookingId.value = null; swapPending.value = false },
  })
}
```

- [ ] **Step 3: Replace the transfer modal template**

Replace `resources/js/Pages/ITJobRequests/ComputerLabs/Index.vue:430-459` (the `<AppModal :show="transferModal" ...>` block) with:

```html
    <AppModal :show="transferModal" title="Transfer Laboratory Schedule" subtitle="Move this schedule to another computer laboratory. The target must be vacant for the same day and time." @close="closeTransfer">
      <form v-if="transferBooking" class="space-y-4" @submit.prevent="submitTransfer">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
          <p class="font-medium text-slate-800">{{ transferBooking.title }}</p>
          <p class="mt-1 text-xs text-slate-500">
            Currently in {{ transferBooking.room_name }} · {{ transferBooking.date || transferBooking.day_of_week }} ·
            {{ formatTime(transferBooking.start_time) }}–{{ formatTime(transferBooking.end_time) }}
          </p>
        </div>

        <AppSelect v-model.number="transferRoomId" label="Transfer to Laboratory" :show-blank="false">
          <option v-for="lab in transferLabOptions" :key="lab.room.id" :value="lab.room.id">
            {{ lab.room.name }} (capacity {{ lab.room.capacity ?? 30 }})
          </option>
        </AppSelect>

        <div v-if="transferRoomId && !transferError" class="rounded-lg border px-3 py-2 text-sm"
          :class="transferConflict ? 'border-red-200 bg-red-50 text-red-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'">
          <span v-if="transferConflict">Occupied by "{{ transferConflict.title }}" during this period.</span>
          <span v-else>This laboratory is vacant for the requested day and time.</span>
        </div>

        <div v-if="transferError" class="space-y-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
          <p>{{ transferError }}</p>
          <AppButton v-if="transferSwapOffer" type="button" variant="secondary" :loading="swapPending" @click="submitTransferSwap">
            <ArrowsRightLeftIcon class="h-4 w-4" /> Swap Rooms Instead
          </AppButton>
        </div>

        <div class="flex justify-end gap-2 border-t border-slate-100 pt-3">
          <AppButton type="button" variant="secondary" @click="closeTransfer">Cancel</AppButton>
          <AppButton type="submit" :disabled="!transferRoomId" :loading="movingBookingId === transferBooking.id && !swapPending">
            <ArrowRightIcon class="h-4 w-4" /> Transfer
          </AppButton>
        </div>
      </form>
    </AppModal>
```

- [ ] **Step 4: Build frontend assets**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && npm run build"`
Expected: build succeeds with no errors (in particular, no "unresolved import" for `ArrowsRightLeftIcon` or `watch`).

- [ ] **Step 5: Manual verification in the dev app**

1. Open `http://localhost:8080` and log in as a user with `computer_labs.manage`.
2. Go to Computer Laboratories, pick a term with at least two bookings occupying the same day/time in different labs (or create two ad-hoc bookings in different labs at the same date/time and approve both).
3. Open "Transfer" on one of them, select the lab the other one occupies, click **Transfer**.
4. Confirm the modal now shows the occupied message AND a **Swap Rooms Instead** button (instead of previously just disabling the Transfer button with no way to proceed).
5. Click **Swap Rooms Instead** — confirm the modal closes, the success flash reads "Computer laboratory schedules swapped successfully.", and the calendar now shows each booking in the other's original lab.
6. Repeat step 3 targeting a lab that has 2+ overlapping bookings (if reachable in your test data) or a conflicting booking that isn't independently movable, and confirm no **Swap Rooms Instead** button appears — only the blocking message, matching today's behavior.
7. Confirm drag-and-drop transfer onto an occupied slot still just blocks the drop with no swap prompt (unchanged).

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/ITJobRequests/ComputerLabs/Index.vue
git commit -m "feat(computer-labs): offer a room-swap action in the transfer modal"
```
