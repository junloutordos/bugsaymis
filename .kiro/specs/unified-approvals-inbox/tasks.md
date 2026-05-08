# Implementation Plan: Unified Approvals Inbox

## Overview

Build the Unified Approvals Inbox as a purely additive feature. The implementation order is:
service class → controller → routes → shared prop → Vue page → sidebar integration → tests.
Each step is self-contained and verifiable before the next begins.

## Tasks

- [x] 1. Create `ApprovalInboxService` with role-scoped pending-count queries
  - Create `app/Services/ApprovalInboxService.php`
  - Constructor accepts a `User $user` instance
  - Implement `totalPendingCount(): int` — sums all role-applicable module counts
  - Implement `getPendingItems(): array` — returns the full `tabs` array with normalised item shapes
  - For Division Chief: query IT Job Requests (`status = 'Pending Division Chief Approval'`, `divisionchief_id = $user->id`), Vehicle Requests (`status = 'Pending'`, `division_chief_id = $user->id`), Facility Requests (`status = 'Pending'`, requester's division chief is `$user`), Work Requests (`status = 'Pending'`, `division_chief_id = $user->id` or requester in user's divisions), Service Requests (`status = 'Pending'`, requester in user's divisions), Messengerial Requests (`status = 'Pending Division Chief Approval'`, `division_chief_id = $user->id`), Gate Passes (`status = 'Pending'`, requester's division is user's), Leave Applications (`status = 'hr_verified'`, `division_chief_id = $user->id`)
  - For FAD Chief (position contains 'FAD'): Facility Requests (`status = 'Pending FAD Approval'`), Work Requests (`status = 'GSU Approved'`), Service Requests (`status = 'Approved'`)
  - For GSU Head: Vehicle Requests (`status = 'Approved'`, `driver_id IS NULL`), Facility Requests (`status = 'Pending FAD Approval'`), Work Requests (`status = 'GSU Approved'`)
  - For OCD: IT Job Requests (`status = 'Pending OCD Approval'`), Vehicle Requests (`status = 'Approved'`), Facility Requests (`status = 'Pending OCD Approval'`), Messengerial Requests (`status = 'Approved'`), Gate Passes (`status = 'Division Approved'`), Leave Applications (`status = 'forwarded'`)
  - For HR Officer (permission `hr.leave.approve`): Leave Applications (`status = 'pending'`)
  - Gate Pass queries use `DB::table('gatepass')` with a join on `users.badge_id = gatepass.badgeNumber` (CAST both to CHAR)
  - Each item is normalised to the common shape: `{ id, type, reference_no, requester_name, filed_at, status, summary, details }`
  - Use `select()` with only required columns and at most two levels of eager loading per query
  - Tabs with zero items are excluded from the returned array
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 9.1_

- [x] 2. Create `ApprovalInboxController` with `index()`, `approve()`, and `decline()` methods
  - Create `app/Http/Controllers/ApprovalInboxController.php`
  - Inject `ApprovalInboxService` via constructor (instantiated with `new ApprovalInboxService($user)` inside each method, or via a factory — keep it thin)
  - `index(Request $request)`:
    - Resolve authenticated user; abort 403 if user holds none of the five approver roles
    - Instantiate `ApprovalInboxService` and call `getPendingItems()` to build `$tabs`
    - Compute `$totalCount` as sum of all tab counts
    - Handle `page[{type}]` query params for per-tab pagination (20 items per page when count > 20)
    - Return `Inertia::render('Approvals/Inbox', compact('tabs', 'totalCount', 'filters'))`
  - `approve(Request $request, string $type, int $id)`:
    - Validate `$type` against the eight valid slugs; return 404 if invalid
    - Resolve the record (Eloquent model or `DB::table` for gate passes); return 404 if not found
    - Verify the authenticated user is authorised to act on this record; return 403 if not
    - Check the record is still in a pending state; return 409 if already acted upon
    - Delegate to the existing per-module method using `app(TargetController::class)->method($request, $model)` — see Delegation Map in design
    - Return `back()->with('success', ...)` on success
  - `decline(Request $request, string $type, int $id)`:
    - Same resolution and authorisation checks as `approve()`
    - Validate `reason` is present and non-empty (pass through to delegated method)
    - Delegate to the existing per-module decline method
    - Return `back()->with('success', ...)` on success
  - Wrap delegate calls in try/catch; log exceptions and return 500 on unexpected errors
  - _Requirements: 1.6, 4.1, 4.2, 4.5, 4.6, 5.1, 5.3, 5.6, 7.1, 7.2, 8.1, 8.2, 8.3, 8.4, 8.5_

- [x] 3. Register the three new routes in `routes/web.php`
  - Add inside the existing `auth` middleware group (do not create a new group):
    ```php
    Route::get('/approvals', [ApprovalInboxController::class, 'index'])->name('approvals.inbox');
    Route::post('/approvals/{type}/{id}/approve', [ApprovalInboxController::class, 'approve'])->name('approvals.approve');
    Route::post('/approvals/{type}/{id}/decline', [ApprovalInboxController::class, 'decline'])->name('approvals.decline');
    ```
  - Add the `use App\Http\Controllers\ApprovalInboxController;` import at the top of the file
  - Do not modify any existing route definitions
  - _Requirements: 8.1, 8.2, 8.3, 7.4_

- [x] 4. Add `approvalInboxCount` shared prop to `HandleInertiaRequests`
  - Open `app/Http/Middleware/HandleInertiaRequests.php`
  - Add `use App\Services\ApprovalInboxService;` import
  - Add the following entry to the `share()` return array, following the existing badge-count pattern:
    ```php
    'approvalInboxCount' => function () use ($request) {
        try {
            $user = $request->user();
            if (!$user) return 0;
            $cacheKey = 'badge.approvals_inbox.u' . $user->id;
            return Cache::remember($cacheKey, 60, function () use ($user) {
                return (new ApprovalInboxService($user))->totalPendingCount();
            });
        } catch (\Throwable $e) { return 0; }
    },
    ```
  - _Requirements: 6.2, 6.3, 6.4_

- [x] 5. Create the Vue page `resources/js/Pages/Approvals/Inbox.vue`
  - [x] 5.1 Scaffold the page shell with props, layout, and tab navigation
    - Create `resources/js/Pages/Approvals/Inbox.vue` using `<script setup>` (no TypeScript)
    - Import `AdminLayout`, `Head`, `usePage`, `router` from their standard paths
    - Define props: `tabs: Array`, `totalCount: Number`, `filters: Object`
    - Declare local state: `activeTab`, `search`, `showModal`, `selectedItem`, `showDecline`, `declineReason`, `isSubmitting`
    - Compute `visibleTabs` (tabs with count > 0), `activeTabData`, `filteredItems` (client-side search filter on `requester_name`, `reference_no`, `summary`)
    - Render tab bar: one button per `visibleTabs` entry with a numeric badge; clicking sets `activeTab`
    - Render empty-state message when `visibleTabs.length === 0`
    - Apply Tailwind classes consistent with existing pages (see `DivisionChiefApproval.vue` for reference)
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.6_

  - [x] 5.2 Implement the pending-items table and search bar
    - Render a `<table>` below the tab bar showing items from `filteredItems`
    - Columns: #, Requestor, Reference No., Summary, Filed At, Status, Actions
    - Status column uses `statusBadgeClass` / `badgeBase` composable (same as existing pages)
    - Actions column: "View" button (opens modal), "Approve" button, "Decline" button
    - Render a search input above the table; bind to `search` ref (client-side filter, no server round-trip)
    - Show a loading spinner on action buttons while `isSubmitting` is true; disable buttons during submission
    - Show empty-row message when `filteredItems.length === 0` but the tab has items (all filtered out)
    - _Requirements: 3.1, 9.3_

  - [x] 5.3 Implement the detail modal
    - Render a centred modal with backdrop overlay when `showModal && selectedItem`
    - Modal header: request type label + reference number
    - Modal body: grid of label/value pairs from `selectedItem.details` (iterate with `v-for` over `Object.entries(selectedItem.details)`)
    - Always show: Requestor, Filed At, Status, Summary
    - Modal footer: Approve button, Decline button, Close button
    - Clicking backdrop or Close button calls `closeModal()` which resets `showModal`, `selectedItem`, `showDecline`, `declineReason`
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5_

  - [x] 5.4 Implement the approve action
    - `confirmApprove(item)`: show `Swal.fire` confirm dialog; on confirmation set `isSubmitting = true` and call `router.post(route('approvals.approve', { type: item.type, id: item.id }), {}, { ... })`
    - On Inertia `onSuccess` callback: remove the item from the local `tabs` array reactively (find the tab by type, splice the item out, decrement `count`), close the modal, show a Swal success toast
    - On Inertia `onError` callback: show a Swal error alert with the server message; do not remove the item
    - Always set `isSubmitting = false` in `onFinish`
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 9.3_

  - [x] 5.5 Implement the decline action
    - When the Decline button is clicked inside the modal, set `showDecline = true` to reveal the textarea and "Submit Decline" button in the modal footer
    - Compute `canSubmitDecline = computed(() => declineReason.value.trim().length > 0)` — disable the Submit button when false
    - `submitDecline(item)`: validate `canSubmitDecline`; set `isSubmitting = true`; call `router.post(route('approvals.decline', { type: item.type, id: item.id }), { reason: declineReason.value }, { ... })`
    - On `onSuccess`: remove item from local tabs array, close modal, show Swal success toast
    - On `onError`: show Swal error alert; do not remove item
    - Always set `isSubmitting = false` in `onFinish`
    - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 9.3_

- [x] 6. Add the Approvals nav item and badge to `AdminLayout.vue`
  - Open `resources/js/Layouts/AdminLayout.vue`
  - Add `InboxIcon` to the existing `@heroicons/vue/24/outline` import list
  - Add the following entry to the `menuItems` array (place it near the top, after Dashboard, before the MIS section):
    ```js
    {
      label: 'Approvals',
      routeName: 'approvals.inbox',
      href: route('approvals.inbox'),
      icon: InboxIcon,
      roles: ['Administrator', 'DivisionChief', 'OCD', 'GSU Head'],
      permissions: ['hr.leave.approve'],
    },
    ```
  - Add a `case 'approvals.inbox':` branch to the `getBadge()` switch statement:
    ```js
    case 'approvals.inbox':
      return toBadgeInt(page.props.approvalInboxCount);
    ```
  - The badge is hidden automatically when the value is 0 (existing `toBadgeInt` + badge rendering logic already handles this)
  - Do not remove or modify any existing menu items
  - _Requirements: 6.1, 6.2, 6.3, 6.4, 6.5_

- [ ] 7. Checkpoint — verify the feature end-to-end before writing tests
  - Ensure all tests pass, ask the user if questions arise.

- [ ] 8. Write PHPUnit tests for `ApprovalInboxService` and `ApprovalInboxController`
  - [ ] 8.1 Write unit tests for role-scoped query logic in `ApprovalInboxService`
    - Test that a Division Chief only receives items with DC-pending statuses across all eight modules
    - Test that a FAD Chief only receives Facility (`Pending FAD Approval`), Work (`GSU Approved`), Service (`Approved`) items
    - Test that a GSU Head only receives Vehicle (`Approved`, no driver), Facility (`Pending FAD Approval`), Work (`GSU Approved`) items
    - Test that an OCD user only receives items with OCD-pending statuses
    - Test that an HR Officer only receives Leave Applications with `status = 'pending'`
    - Seed records with non-pending statuses and assert they are excluded
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [ ]* 8.2 Write property test for Property 1 (role-scoped query returns only role-appropriate items)
    - **Property 1: Role-scoped query returns only role-appropriate items**
    - Generate random sets of requests with random statuses for a random approver role; assert every returned item has a status in the role's allowed set
    - Run minimum 100 iterations
    - **Validates: Requirements 1.1, 1.2, 1.3, 1.4, 1.5**

  - [ ] 8.3 Write unit tests for `ApprovalInboxController` HTTP responses
    - Assert `index()` returns 403 for a user with no approver role
    - Assert `approve()` returns 404 for an unknown `{type}` slug
    - Assert `approve()` returns 404 when `{id}` does not exist
    - Assert `approve()` returns 409 when the record is no longer in a pending state
    - Assert `approve()` returns 403 when the authenticated user is not the assigned approver
    - Assert `decline()` returns 409 on already-acted records
    - _Requirements: 1.6, 4.5, 4.6, 5.6, 8.1, 8.2, 8.3, 8.4, 8.5_

  - [ ]* 8.4 Write property test for Property 2 (tab count equals item count)
    - **Property 2: Tab count equals item count**
    - Generate random item sets; assert `count` fields and `totalCount` are arithmetically consistent
    - Run minimum 100 iterations
    - **Validates: Requirements 2.3, 2.5**

  - [ ]* 8.5 Write property test for Property 4 (approve/decline produces identical DB state to per-module endpoint)
    - **Property 4: Approve/decline produces identical DB state to per-module endpoint**
    - For each module type, generate a valid pending record; approve via inbox and via per-module endpoint on separate DB copies; assert identical resulting `status` and `ApprovalSnapshot` records
    - Run minimum 100 iterations
    - **Validates: Requirements 4.2, 5.3, 7.2**

  - [ ]* 8.6 Write property test for Property 7 (tabs with zero items are not rendered)
    - **Property 7: Tabs with zero items are not rendered**
    - Generate random item sets with some types having zero items; assert the `tabs` array returned by `ApprovalInboxService::getPendingItems()` contains only types with count > 0
    - Run minimum 100 iterations
    - **Validates: Requirements 2.1, 2.4, 2.6**

  - [ ]* 8.7 Write property test for Property 8 (sidebar badge equals total pending count)
    - **Property 8: Sidebar badge equals total pending count**
    - Generate random pending item sets; assert `ApprovalInboxService::totalPendingCount()` equals the sum of all tab counts returned by `getPendingItems()`
    - Run minimum 100 iterations
    - **Validates: Requirements 6.2, 6.3**

- [ ] 9. Write integration / smoke tests
  - [ ] 9.1 Verify existing per-module approval routes still return 200 after inbox routes are added
    - Make authenticated GET requests to `vehicle-requests.dc-approval`, `job-requests.for-approval`, `job-requests.ocd-approval`, `gatepass.ocd-approval`, `messengerial.for-approval` and assert HTTP 200
    - _Requirements: 7.3, 7.4_

  - [ ]* 9.2 Verify `approvalInboxCount` shared prop is present in every Inertia response for approver users
    - Make a GET request to any Inertia page as a Division Chief; assert the JSON response contains `props.approvalInboxCount`
    - **Validates: Requirements 6.4**

  - [ ]* 9.3 Verify query count for `index()` does not exceed 10
    - Enable `DB::enableQueryLog()` before calling `ApprovalInboxController::index()` for each of the five roles; assert `count(DB::getQueryLog()) <= 10`
    - **Validates: Requirements 9.1**

- [ ] 10. Final checkpoint — Ensure all tests pass
  - Ensure all tests pass, ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP
- Gate Pass uses `DB::table('gatepass')` throughout — never introduce an Eloquent model for it
- The inbox controller delegates every approve/decline to the existing per-module method via `app(TargetController::class)->method($request, $model)` — no business logic is duplicated
- Cache key for the sidebar badge: `badge.approvals_inbox.u{userId}` (TTL 60 seconds, matching existing badge pattern)
- The `approvalInboxCount` cache must be invalidated (or allowed to expire naturally) after an approve/decline action — the 60-second TTL is acceptable per the design
- All Tailwind classes must follow the conventions in `CLAUDE.md` and match the style of `VehicleRequests/DivisionChiefApproval.vue`
- No new migrations, no schema changes, no modifications to existing controllers/models/mail classes
