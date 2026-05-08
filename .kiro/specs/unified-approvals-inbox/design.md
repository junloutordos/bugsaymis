# Design Document — Unified Approvals Inbox

## Overview

The Unified Approvals Inbox consolidates every pending approval action for a given approver role into a single page at `/approvals`. Instead of visiting up to eight separate per-module approval pages, a Division Chief, FAD Chief, GSU Head, OCD user, or HR Officer can see all their pending items in one place, organised by request type via tabs, and act on them through a detail modal.

The feature is **purely additive**: no existing controller, model, migration, route, mail class, or signed-URL flow is modified. The inbox controller delegates every approve/decline action to the existing per-module in-app action methods, so all business logic, `SnapshotService` calls, and email notifications remain identical to what the per-module pages already do.

### Key Design Decisions

1. **Delegation over duplication** — `ApprovalInboxController` calls the existing per-module `approveInApp` / `declineInApp` / `divisionChiefAction` / `ocdAction` methods (or their equivalents) rather than re-implementing status transitions. This guarantees identical DB state and audit trail.

2. **Shared prop for sidebar badge** — The total pending count is computed inside `HandleInertiaRequests::share()` using the same caching pattern already used for per-module badges. This avoids a separate AJAX call and keeps the badge consistent across all pages.

3. **Tab-first, modal-detail UX** — The list view is intentionally minimal (requestor, date, status, actions). Full details are shown only in the modal, matching the pattern established in `VehicleRequests/DivisionChiefApproval.vue`.

4. **Gate Pass special case** — The `gatepass` table is not an Eloquent model with a standard `id` primary key in the same way as other modules; it uses raw DB queries in `GatePassController`. The inbox controller handles this type with `DB::table('gatepass')` queries and routes actions to `GatePassController::approveByOCDInApp` / the in-app update endpoint.

5. **Messengerial OCD note** — The requirements list `Pending OCD Approval` for Messengerial under OCD, but the actual codebase uses `Approved` as the status that triggers OCD action (see `MessengerialController::ocdApproval`). The design follows the actual codebase status values.

---

## Architecture

```
Browser (Inertia SPA)
  └── GET /approvals
        └── ApprovalInboxController::index()
              ├── Queries per-role pending items (≤ 10 queries total)
              ├── Returns Inertia::render('Approvals/Inbox', [...])
              └── Sidebar badge via HandleInertiaRequests shared prop

  └── POST /approvals/{type}/{id}/approve
        └── ApprovalInboxController::approve()
              └── Delegates to existing per-module approve method

  └── POST /approvals/{type}/{id}/decline
        └── ApprovalInboxController::decline()
              └── Delegates to existing per-module decline method
```

The controller is intentionally thin. All business logic lives in the existing per-module controllers. The inbox controller's only responsibilities are:

- Aggregating pending items for the authenticated user's role(s)
- Routing approve/decline POSTs to the correct existing method
- Returning 403/404/409 HTTP responses for error cases

---

## Components and Interfaces

### Backend

#### `app/Http/Controllers/ApprovalInboxController.php`

```php
class ApprovalInboxController extends Controller
{
    // GET /approvals
    public function index(Request $request): \Inertia\Response

    // POST /approvals/{type}/{id}/approve
    public function approve(Request $request, string $type, int $id): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse

    // POST /approvals/{type}/{id}/decline
    public function decline(Request $request, string $type, int $id): \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
}
```

**`index()` responsibilities:**
- Determine the authenticated user's approver role(s)
- Return HTTP 403 if the user holds none of the five approver roles
- Execute role-scoped queries for each applicable module (see Data Models section)
- Shape results into a `tabs` array: `[{ type, label, count, items: [...] }]`
- Pass `tabs`, `totalCount`, and `filters` as Inertia props

**`approve()` / `decline()` responsibilities:**
- Validate `$type` against the eight valid slugs → 404 if invalid
- Resolve the model record by `$id` → 404 if not found
- Verify the authenticated user is authorised to act on this record → 403 if not
- Check the record is still in a pending state → 409 if already acted upon
- Delegate to the existing per-module method (see Delegation Map below)
- Return `back()->with('success', ...)` on success (Inertia handles the redirect)

#### Delegation Map

| Type slug | Approve delegates to | Decline delegates to |
|---|---|---|
| `it_job_requests` | `ITJobRequestController::approveByDivisionChief` (DC) or `::approveByOCD` (OCD) | same controller, reject action |
| `vehicle_requests` | `VehicleRequestController::approveInApp` (DC) or `::approveByOCDInApp` (OCD) | `::declineInApp` (DC) or OCD action |
| `facility_requests` | `FacilityRequestController::approveInApp` (DC) or `::ocdAction` (OCD) | `::declineInApp` (DC) or OCD action |
| `work_requests` | `WorkRequestController::approveInApp` (DC) or `::fadAction` (FAD) | `::declineInApp` (DC) or FAD action |
| `service_requests` | `ServiceRequestController::approveInApp` (DC) or `::fadAction` (FAD) | `::declineInApp` (DC) or FAD action |
| `messengerial_requests` | `MessengerialController::divisionChiefAction` (DC) or `::ocdAction` (OCD) | same, reject action |
| `gate_passes` | `GatePassController::update` with `status=Division Approved` (DC) or `::approveByOCDInApp` (OCD) | update with `status=Division Declined` (DC) or OCD action |
| `leave_applications` | `HR\LeaveApplicationController::approve` with appropriate stage | same, reject action |

The inbox controller instantiates the target controller via `app(TargetController::class)` and calls the method directly, passing the current `$request` and the resolved model instance. This avoids HTTP round-trips and ensures the same middleware-resolved user context is used.

#### New Routes in `routes/web.php`

```php
Route::middleware(['auth'])->group(function () {
    Route::get('/approvals', [ApprovalInboxController::class, 'index'])
        ->name('approvals.inbox');

    Route::post('/approvals/{type}/{id}/approve', [ApprovalInboxController::class, 'approve'])
        ->name('approvals.approve');

    Route::post('/approvals/{type}/{id}/decline', [ApprovalInboxController::class, 'decline'])
        ->name('approvals.decline');
});
```

#### Sidebar Badge — `HandleInertiaRequests`

A new shared prop `approvalInboxCount` is added to `HandleInertiaRequests::share()` following the existing caching pattern:

```php
'approvalInboxCount' => function () use ($request) {
    try {
        $user = $request->user();
        if (!$user) return 0;
        $cacheKey = 'badge.approvals_inbox.u' . $user->id;
        return Cache::remember($cacheKey, 60, function () use ($user) {
            return (new \App\Services\ApprovalInboxService($user))->totalPendingCount();
        });
    } catch (\Throwable $e) { return 0; }
},
```

An `ApprovalInboxService` helper class encapsulates the count queries so they can be reused by both the middleware and the controller's `index()` method without duplication.

### Frontend

#### `resources/js/Pages/Approvals/Inbox.vue`

Single-file Vue 3 component using `<script setup>`. No TypeScript, no Pinia/Vuex.

**Props received from controller:**
```js
defineProps({
  tabs: Array,        // [{ type, label, count, items: [...] }]
  totalCount: Number,
  filters: Object,    // { search, page }
})
```

**Local state:**
```js
const activeTab     = ref(tabs[0]?.type ?? null)
const showModal     = ref(false)
const selectedItem  = ref(null)
const showDecline   = ref(false)
const declineReason = ref('')
const isSubmitting  = ref(false)
const search        = ref(filters?.search ?? '')
```

**Key computed values:**
```js
const visibleTabs    = computed(() => tabs.filter(t => t.count > 0))
const activeTabData  = computed(() => tabs.find(t => t.type === activeTab.value))
const filteredItems  = computed(() => {
  const items = activeTabData.value?.items ?? []
  if (!search.value.trim()) return items
  const q = search.value.toLowerCase()
  return items.filter(i =>
    i.requester_name?.toLowerCase().includes(q) ||
    i.reference_no?.toLowerCase().includes(q) ||
    i.summary?.toLowerCase().includes(q)
  )
})
```

**Actions:**
- `openModal(item)` — sets `selectedItem`, opens modal
- `closeModal()` — resets modal state
- `confirmApprove()` — Swal confirm → `router.post(route('approvals.approve', ...))`
- `submitDecline()` — validates reason → `router.post(route('approvals.decline', ...))`

On Inertia success callbacks, the approved/declined item is removed from the local `tabs` array reactively (optimistic UI update), and the sidebar badge is refreshed on the next full page load via the shared prop.

#### `AdminLayout.vue` changes

Add the Approvals nav item to the `menuItems` array, visible only to approver roles:

```js
{
  label: 'Approvals',
  routeName: 'approvals.inbox',
  href: route('approvals.inbox'),
  icon: InboxIcon,   // from @heroicons/vue/24/outline
  roles: ['Administrator', 'DivisionChief', 'OCD', 'GSU Head'],
  permissions: ['hr.leave.approve'],  // HR Officer access via permission
},
```

The sidebar badge for this item is wired in `getBadge()`:
```js
case 'approvals.inbox':
  return toBadgeInt(page.props.approvalInboxCount);
```

---

## Data Models

### Role → Pending Query Map

The controller builds one query per applicable module for the authenticated user's role. All queries use eager loading with `select()` to fetch only the columns needed for the list view.

#### Division Chief

```php
// IT Job Requests
ITJobRequest::select(['id','itjr_no','title','category','status','user_id','created_at'])
    ->with('user:id,name')
    ->where('divisionchief_id', $user->id)
    ->where('status', 'Pending Division Chief Approval')

// Vehicle Requests
VehicleRequest::select(['id','purpose','destination','date_needed','status','requestor_id','created_at'])
    ->with('requester:id,name')
    ->where('division_chief_id', $user->id)
    ->where('status', 'Pending')

// Facility Requests
FacilityRequest::select(['id','activity','purpose','date_start','date_end','status','requestor_id','created_at'])
    ->with('requester:id,name,division_id')
    ->where('status', 'Pending')
    ->whereHas('requester', fn($q) => $q->whereIn('division_id', $divisionIds))

// Work Requests
WorkRequest::select(['id','issue','category','status','requester_id','division_chief_id','created_at'])
    ->with('requester:id,name')
    ->where('status', 'Pending')
    ->where(fn($q) => $q->where('division_chief_id', $user->id)
        ->orWhereHas('requester', fn($r) => $r->whereIn('division_id', $divisionIds)))

// Service Requests
ServiceRequest::select(['id','service_type','purposes','date_needed','status','requestor_id','created_at'])
    ->with('requester:id,name,division_id')
    ->where('status', 'Pending')
    ->whereHas('requester', fn($q) => $q->whereIn('division_id', $divisionIds))

// Messengerial Requests
MessengerialRequest::select(['id','reference_no','purpose','destination','status','requestor','email','created_at'])
    ->where('division_chief_id', $user->id)
    ->where('status', 'Pending Division Chief Approval')

// Gate Passes (raw DB — no Eloquent model)
DB::table('gatepass')
    ->join('users', DB::raw('CAST(users.badge_id AS CHAR)'), '=', DB::raw('CAST(gatepass.badgeNumber AS CHAR)'))
    ->select('gatepass.id','gatepass.controlno','gatepass.purpose','gatepass.destination',
             'gatepass.gatepass_date','gatepass.status','users.name as requester_name')
    ->where('gatepass.status', 'Pending')
    ->whereIn('users.division_id', $divisionIds)

// Leave Applications
LeaveApplication::select(['id','control_no','leave_type_id','date_from','date_to','days_applied','status','user_id','created_at'])
    ->with(['user:id,name', 'leaveType:id,name,code'])
    ->where('division_chief_id', $user->id)
    ->where('status', 'hr_verified')   // DC sees after HR has certified
```

> **Note on Leave Applications for DC:** The requirements document states `status = 'pending'` and `hr_officer_action = 'certified'`. Examining the actual `LeaveApplicationController` and `ApprovalService`, the status transitions are: `pending` → `hr_verified` → `forwarded` → `approved`. Division Chiefs act on `hr_verified` records. The design uses `hr_verified` to match the actual codebase.

#### FAD Chief

```php
// Facility Requests
FacilityRequest::where('status', 'Pending FAD Approval')

// Work Requests
WorkRequest::where('status', 'GSU Approved')

// Service Requests
ServiceRequest::where('status', 'Approved')  // DC-approved, awaiting FAD
```

#### GSU Head

```php
// Vehicle Requests (driver not yet assigned)
VehicleRequest::where('status', 'Approved')->whereNull('driver_id')

// Facility Requests (forwarded for GSU processing)
FacilityRequest::where('status', 'Pending FAD Approval')

// Work Requests (awaiting GSU assignment)
WorkRequest::where('status', 'GSU Approved')
```

#### OCD

```php
// IT Job Requests
ITJobRequest::where('status', 'Pending OCD Approval')

// Vehicle Requests
VehicleRequest::where('status', 'Approved')

// Facility Requests
FacilityRequest::where('status', 'Pending OCD Approval')

// Messengerial Requests
MessengerialRequest::where('status', 'Approved')  // actual codebase status for OCD queue

// Gate Passes
DB::table('gatepass')->where('status', 'Division Approved')

// Leave Applications
LeaveApplication::where('status', 'forwarded')
```

#### HR Officer

```php
// Leave Applications (initial certification)
LeaveApplication::where('status', 'pending')
```

### Inbox Item Shape (passed to frontend)

Each item in a tab's `items` array is normalised to a common shape plus a `details` object containing type-specific fields:

```js
{
  id: Number,
  type: String,           // e.g. 'vehicle_requests'
  reference_no: String,   // itjr_no, controlno, control_no, or "#id"
  requester_name: String,
  filed_at: String,       // ISO date string
  status: String,
  summary: String,        // short human-readable description
  details: Object,        // type-specific fields for the modal
}
```

The `details` object contains all fields needed for the modal display. The controller shapes this server-side so the Vue component does not need type-specific rendering logic beyond a simple `v-for` over the details entries.

### Pagination

When a tab's item count exceeds 20, the controller paginates at 20 items per page. Pagination is per-tab and driven by a `page[{type}]` query parameter (e.g., `?page[vehicle_requests]=2`). The frontend passes the current page for the active tab only.

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Role-scoped query returns only role-appropriate items

*For any* authenticated user with an approver role and *for any* set of request records in the database, every item returned by `ApprovalInboxController::index()` SHALL have a status value that is in the set of pending statuses defined for that user's role and module combination.

**Validates: Requirements 1.1, 1.2, 1.3, 1.4, 1.5**

### Property 2: Tab count equals item count

*For any* set of pending items returned by the controller, the `count` field on each tab object SHALL equal the number of items in that tab's `items` array, and the `totalCount` prop SHALL equal the sum of all tab counts.

**Validates: Requirements 2.3, 2.5**

### Property 3: Active tab filter is exclusive

*For any* active tab selection and *for any* set of items across all tabs, the items displayed in the list SHALL all have `type === activeTab` and no items from other tabs SHALL be visible.

**Validates: Requirements 2.2**

### Property 4: Approve/decline produces identical DB state to per-module endpoint

*For any* valid (type, id) pair where the record is in a pending state, approving or declining via the inbox (`POST /approvals/{type}/{id}/approve`) SHALL produce the same `status` column value, the same `ApprovalSnapshot` record (step, action, user_id), and the same outbound email notifications as approving or declining via the corresponding per-module in-app endpoint.

**Validates: Requirements 4.2, 5.3, 7.2**

### Property 5: Approved/declined item is removed from the tab list

*For any* item that is successfully approved or declined, that item SHALL no longer appear in the active tab's item list, the tab's count SHALL decrease by one, and the total sidebar badge count SHALL decrease by one.

**Validates: Requirements 4.3, 5.4**

### Property 6: Whitespace-only decline reason is rejected

*For any* string composed entirely of whitespace characters (spaces, tabs, newlines), the "Submit Decline" button SHALL remain disabled and no POST request SHALL be submitted.

**Validates: Requirements 5.2**

### Property 7: Tabs with zero items are not rendered

*For any* set of pending items, a tab SHALL appear in the tab list if and only if its item count is greater than zero.

**Validates: Requirements 2.1, 2.4, 2.6**

### Property 8: Sidebar badge equals total pending count

*For any* authenticated approver user, the `approvalInboxCount` shared prop SHALL equal the sum of all pending items across all modules for that user's role, matching the value that would be computed by summing all tab counts on the inbox page.

**Validates: Requirements 6.2, 6.3**

---

## Error Handling

### HTTP 403 — Unauthorised

Returned by `index()` when the authenticated user holds none of the five approver roles. Returned by `approve()` / `decline()` when the user is not authorised to act on the specific record (e.g., a Division Chief trying to approve a request from another division).

The Vue component handles 403 responses from action POSTs by displaying a Swal error alert and leaving the item in the list.

### HTTP 404 — Not Found

Returned when `{type}` is not one of the eight valid slugs, or when `{id}` does not correspond to an existing record of that type. The Vue component handles this with a Swal error alert.

### HTTP 409 — Conflict

Returned when the record is no longer in a pending state at the time the approve/decline POST is processed (i.e., it was acted upon by another approver or via the signed URL flow between the inbox page load and the action submission). The Vue component displays an error alert and does **not** remove the item from the list, allowing the user to refresh.

### Delegate method errors

If the delegated per-module method throws an exception (e.g., mail failure), the inbox controller catches it, logs it, and returns a 500 response. Mail failures are already caught inside the per-module methods themselves and only logged, so they do not propagate.

### Gate Pass special handling

Because `gatepass` uses raw DB queries and does not have a standard Eloquent model, the inbox controller uses `DB::table('gatepass')->find($id)` for existence checks and passes the raw stdClass object to `GatePassController` methods that accept `$id` rather than a model instance.

---

## Testing Strategy

### Unit Tests (PHPUnit)

Focus on the controller's query-scoping logic and the delegation routing:

- **Role scoping**: For each of the five roles, assert that `index()` returns only items with the correct pending statuses. Use database factories to seed records with various statuses and verify only the expected ones appear.
- **403 for non-approver**: Assert that a user with no approver role receives a 403 from `index()`.
- **404 for invalid type**: Assert that `approve()` and `decline()` return 404 for unknown type slugs.
- **409 for already-acted records**: Assert that `approve()` returns 409 when the record's status is no longer pending.
- **Delegation correctness**: For each module, assert that after calling `approve()` via the inbox, the record's status matches what the per-module `approveInApp` method would set.

### Property-Based Tests (Pest + `nunomaduro/collision` or PHPUnit with a PBT library)

The project uses PHPUnit. Property-based tests are implemented using **[`eris/eris`](https://github.com/giorgiosironi/eris)** (PHP PBT library) or, if not available, using data providers with randomised seeds. Each property test runs a minimum of **100 iterations**.

Tag format: `Feature: unified-approvals-inbox, Property {N}: {property_text}`

**Property 1 test** — Generate random sets of requests with random statuses for a random approver role. Assert every returned item has a status in the role's allowed set.

**Property 2 test** — Generate random item sets. Assert `count` fields and `totalCount` are arithmetically consistent.

**Property 3 test** — Generate random tab selections and item sets. Assert filtered items all belong to the active tab type.

**Property 4 test** — For each module type, generate random valid pending records. Approve via inbox and via per-module endpoint on separate DB copies. Assert identical resulting status and ApprovalSnapshot records.

**Property 5 test** — Generate random item arrays. Simulate approval of a random item. Assert the item is removed and counts are decremented.

**Property 6 test** — Generate random whitespace-only strings. Assert the Vue computed `canSubmitDecline` is false for all of them.

**Property 7 test** — Generate random item sets with some types having zero items. Assert tabs array contains only types with count > 0.

**Property 8 test** — Generate random pending item sets. Assert `approvalInboxCount` equals the sum of all tab counts.

### Integration / Smoke Tests

- Verify existing per-module approval routes still return 200 after inbox routes are added.
- Verify signed URL routes still work (GET with valid signature returns 200).
- Verify `approvalInboxCount` shared prop is present in every Inertia response for approver users.
- Verify query count for `index()` does not exceed 10 using `DB::getQueryLog()`.

### Frontend (manual / Cypress)

- Tab switching shows correct items.
- Modal opens with correct fields for each request type.
- Approve flow: Swal confirm → POST → item removed → badge decremented.
- Decline flow: Decline button → textarea appears → whitespace-only disables submit → valid reason → POST → item removed.
- Empty state shown when all items are resolved.
- Sidebar badge shows correct count and hides when zero.
