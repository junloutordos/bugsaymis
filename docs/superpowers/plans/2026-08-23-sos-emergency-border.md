# SOS Emergency Border Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a blinking, red-glowing visual indicator that (a) shows site-wide on every authenticated employee page while a campus-wide emergency broadcast is active, and (b) shows on the SOS Command Center page specifically the moment a raw SOS is triggered, ahead of any broadcast decision.

**Architecture:** One new reusable Vue component (`EmergencyBorderOverlay.vue`, a `position: fixed` animated red border, `pointer-events: none`) is mounted twice: once in `AdminLayout.vue` (site-wide, driven by the already-open-to-everyone `emergency-alerts` Echo channel plus a new bootstrap endpoint for page-load state), and once in `CommandCenter.vue` (page-local, driven by the existing `activeAlerts` state that page already computes from the permission-gated `sos-responders` channel). The only backend change is one small new endpoint, `GET /sos/emergency-status`, gated only by `auth` — no `sos.respond` permission — because the site-wide border must work for every employee, matching the existing channel authorization policy at `routes/channels.php:54-56`.

**Tech Stack:** Laravel 12 / PHP 8.4, PHPUnit (`RefreshDatabase`), Vue 3 `<script setup>`, Laravel Echo.

**Spec:** `docs/superpowers/specs/2026-08-23-announcements-sos-atlasgo-enhancements-design.md` (Section 2 — "SOS: site-wide + Command Center emergency border").

## Global Constraints

- v1 uses one consistent red visual treatment — no severity-based styling differentiation (explicitly deferred in the spec's "Out of scope" section).
- The site-wide border must NOT be tied to the `NoticeQueueModal` acknowledgment state — dismissing that modal must never clear the border. The border reflects campus-wide alert status, not personal unread state.
- `GET /sos/emergency-status` must be reachable by any authenticated employee, not just `sos.respond` holders — this mirrors the channel comment at `routes/channels.php:47-50` ("intentionally open to every logged-in employee").
- Respect `prefers-reduced-motion` — fall back to a static (non-animated) border rather than removing the indicator entirely.

---

### Task 1: Backend — `GET /sos/emergency-status` endpoint

**Files:**
- Modify: `app/Http/Controllers/Sos/EmergencyAlertController.php` (add `status()` method)
- Modify: `routes/web.php:385-387` (add route)
- Modify: `tests/Feature/Sos/EmergencyAlertControllerTest.php` (add 2 tests)

**Interfaces:**
- Produces: `GET /sos/emergency-status` (route name `sos.emergency-status`) → JSON `{ active: bool, severity: string|null }`, based on `EmergencyAlert::active()` (existing scope, `app/Models/Sos/EmergencyAlert.php:39-42`, `where('status', 'active')`). Consumed by Task 3 (`AdminLayout.vue`).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Sos/EmergencyAlertControllerTest.php`, inside the `EmergencyAlertControllerTest` class (after the existing `test_escalate_from_sos_requires_sos_respond_permission` method):

```php
    public function test_emergency_status_reports_active_alert_and_severity_to_any_employee(): void
    {
        EmergencyAlert::create([
            'title' => 'Test', 'message' => 'Body', 'severity' => 'critical',
            'audience' => 'all', 'status' => 'active', 'source' => 'manual', 'created_by' => $this->responder()->id,
        ]);

        // A plain employee with no sos.respond permission must still get 200 —
        // this endpoint is intentionally open, mirroring the emergency-alerts
        // Echo channel's own authorization policy.
        $response = $this->actingAs(User::factory()->create())->getJson(route('sos.emergency-status'));

        $response->assertOk()->assertJson(['active' => true, 'severity' => 'critical']);
    }

    public function test_emergency_status_reports_inactive_when_nothing_is_active(): void
    {
        $response = $this->actingAs(User::factory()->create())->getJson(route('sos.emergency-status'));

        $response->assertOk()->assertJson(['active' => false, 'severity' => null]);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EmergencyAlertControllerTest"
```

Expected: the two new tests FAIL (route `sos.emergency-status` doesn't exist yet — `RouteNotFoundException` or 404).

- [ ] **Step 3: Add the route**

In `routes/web.php`, inside the `Route::prefix('sos')->name('sos.')->group(...)` block, add the new route right after the existing `trigger` route and before the `permission:sos.respond` sub-group (so it inherits only the outer `auth` middleware, not the permission gate):

```php
    Route::prefix('sos')->name('sos.')->group(function () {
        Route::post('/trigger', [\App\Http\Controllers\Sos\SosAlertController::class, 'trigger'])->name('trigger');

        // Open to any authenticated employee (no permission gate) — mirrors
        // the emergency-alerts Echo channel's own authorization policy, since
        // this feeds the site-wide emergency border every employee needs to see.
        Route::get('/emergency-status', [\App\Http\Controllers\Sos\EmergencyAlertController::class, 'status'])->name('emergency-status');

        Route::middleware('permission:sos.respond')->group(function () {
```

(Leave everything from `Route::middleware('permission:sos.respond')->group(function () {` onward exactly as-is — this task only inserts the one new line above it.)

- [ ] **Step 4: Add the controller method**

In `app/Http/Controllers/Sos/EmergencyAlertController.php`, add a new public method right after `index()`:

```php
    public function status(): JsonResponse
    {
        $alert = EmergencyAlert::active()->latest()->first();

        return response()->json([
            'active'   => $alert !== null,
            'severity' => $alert?->severity,
        ]);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=EmergencyAlertControllerTest"
```

Expected: all tests in `EmergencyAlertControllerTest` PASS, including the 2 new ones.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Sos/EmergencyAlertController.php routes/web.php tests/Feature/Sos/EmergencyAlertControllerTest.php
git commit -m "feat(sos): add GET /sos/emergency-status, open to any authenticated employee"
```

---

### Task 2: `EmergencyBorderOverlay.vue` component

**Files:**
- Create: `resources/js/Components/Sos/EmergencyBorderOverlay.vue`

**Interfaces:**
- Consumes: nothing (pure presentational component).
- Produces: a Vue component accepting one prop `active: Boolean`, rendering a fixed, non-interactive, animated red border overlay when `true`. Consumed by Task 3 (`AdminLayout.vue`) and Task 4 (`CommandCenter.vue`).

This is a standalone, no-dependency visual component — right-sized as its own task because it can be built and eyeballed in isolation before either consumer wires it up.

- [ ] **Step 1: Create the component**

```vue
<script setup>
defineProps({
  active: { type: Boolean, default: false },
})
</script>

<template>
  <div v-if="active" class="emergency-border-overlay" aria-hidden="true" />
</template>

<style scoped>
.emergency-border-overlay {
  position: fixed;
  inset: 0;
  z-index: 90;
  pointer-events: none;
  border: 6px solid rgba(220, 38, 38, 0.85);
  box-shadow: inset 0 0 40px rgba(220, 38, 38, 0.55);
  animation: emergency-pulse 1.6s ease-in-out infinite;
}

@keyframes emergency-pulse {
  0%, 100% { opacity: 0.55; }
  50% { opacity: 1; }
}

@media (prefers-reduced-motion: reduce) {
  .emergency-border-overlay {
    animation: none;
    opacity: 1;
  }
}
</style>
```

`pointer-events: none` ensures the overlay never blocks clicks on underlying page content or on any modal (`NoticeQueueModal`, the broadcast form, etc.) that might render above it — it is purely a status indicator, never an interactive element, so it needs no ARIA role beyond `aria-hidden="true"` (the actual emergency content is already announced through `NoticeQueueModal`, which this overlay is additive to, not a replacement for).

- [ ] **Step 2: Manual verification (no JS test harness exists in this project)**

Temporarily set `active: { type: Boolean, default: true }` in a local scratch check (or use Vue devtools to force the prop), load any admin page, and confirm: a pulsing red border appears around the full viewport, doesn't block clicking anything underneath it, and switching the OS/browser "reduce motion" accessibility setting makes it static instead of pulsing. Revert the temporary default back to `false` before committing.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/Sos/EmergencyBorderOverlay.vue
git commit -m "feat(sos): add EmergencyBorderOverlay component"
```

---

### Task 3: Wire the site-wide border into `AdminLayout.vue`

**Files:**
- Modify: `resources/js/Layouts/AdminLayout.vue`

**Interfaces:**
- Consumes: `GET /sos/emergency-status` (Task 1), `EmergencyBorderOverlay.vue` (Task 2, prop `active: Boolean`), existing `emergency-alerts` Echo channel already subscribed in `setupEmergencyAlertListener()` (`resources/js/Layouts/AdminLayout.vue:81-88`).
- Produces: nothing new for other tasks — this is the site-wide leaf.

- [ ] **Step 1: Add the import and reactive state**

In `resources/js/Layouts/AdminLayout.vue`, add the import next to the existing `NoticeQueueModal` import (line 26):

```js
import NoticeQueueModal from '@/Components/Notices/NoticeQueueModal.vue'
import EmergencyBorderOverlay from '@/Components/Sos/EmergencyBorderOverlay.vue'
```

Add the new reactive ref next to the existing `noticeQueueModal` ref (line 31):

```js
const noticeQueueModal = ref(null);
const hasActiveEmergency = ref(false);
```

- [ ] **Step 2: Replace `setupEmergencyAlertListener()` and add the bootstrap fetch**

Current code (`resources/js/Layouts/AdminLayout.vue:81-88`):

```js
function setupEmergencyAlertListener() {
  if (!window.Echo) return;

  window.Echo.private('emergency-alerts')
    .listen('.emergency.alert.broadcast', (payload) => {
      noticeQueueModal.value?.receiveEmergencyAlert(payload);
    });
}
```

Replace with:

```js
async function fetchEmergencyStatus() {
  try {
    const res = await window.axios.get(route('sos.emergency-status'));
    hasActiveEmergency.value = res.data.active;
  } catch {
    // Fail closed — a failed bootstrap fetch must not leave the border
    // stuck on. A real active emergency will still arrive via the live
    // Echo listener below or is already visible elsewhere (Command Center,
    // NoticeQueueModal).
    hasActiveEmergency.value = false;
  }
}

function setupEmergencyAlertListener() {
  if (!window.Echo) return;

  window.Echo.private('emergency-alerts')
    .listen('.emergency.alert.broadcast', (payload) => {
      noticeQueueModal.value?.receiveEmergencyAlert(payload);
      hasActiveEmergency.value = true;
    })
    .listen('.emergency.alert.resolved', () => {
      // Re-fetch rather than blindly clearing — another emergency alert
      // could still be active if more than one was ever broadcast at once.
      fetchEmergencyStatus();
    });
}
```

- [ ] **Step 3: Call the bootstrap fetch on mount**

In the `onMounted(() => { ... })` block (`resources/js/Layouts/AdminLayout.vue:113-158`), find this existing line:

```js
  fetchChatUnread();
  setupChatNotifications();
  setupEmergencyAlertListener();
```

Replace with:

```js
  fetchChatUnread();
  setupChatNotifications();
  setupEmergencyAlertListener();
  fetchEmergencyStatus();
```

- [ ] **Step 4: Render the overlay**

In the template, find (`resources/js/Layouts/AdminLayout.vue:791-793`):

```vue
  <SosFloatingButton trigger-route="sos.trigger" />

  <NoticeQueueModal ref="noticeQueueModal" />
```

Replace with:

```vue
  <SosFloatingButton trigger-route="sos.trigger" />

  <NoticeQueueModal ref="noticeQueueModal" />

  <EmergencyBorderOverlay :active="hasActiveEmergency" />
```

- [ ] **Step 5: Manual verification (no JS test harness exists in this project)**

Rebuild assets (`npm run build` or use `npm run dev`), then:
1. As a responder, broadcast a test emergency alert from the SOS Command Center (`sos.broadcast.store`).
2. On a **second** browser session/tab logged in as a different, non-responder employee, navigate to any page other than the Command Center (e.g. Payroll) — confirm the pulsing red border appears there too, without that user doing anything.
3. Confirm the `NoticeQueueModal` popup for the same alert can be dismissed/acknowledged independently — the border must stay on.
4. Resolve the alert from the Command Center — confirm the border clears on the other tab (may take up to the next Echo delivery; no polling is involved).
5. Reload a page while an alert is still active (simulating a fresh page load mid-emergency) — confirm the border appears immediately via the bootstrap fetch, not only after a live event.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Layouts/AdminLayout.vue
git commit -m "feat(sos): show site-wide emergency border while a broadcast is active"
```

---

### Task 4: Wire the Command Center-local border into `CommandCenter.vue`

**Files:**
- Modify: `resources/js/Pages/Sos/CommandCenter.vue`

**Interfaces:**
- Consumes: `EmergencyBorderOverlay.vue` (Task 2), existing `activeAlerts` computed (`resources/js/Pages/Sos/CommandCenter.vue:14`, already derived from the `sos-responders` channel this page already subscribes to).
- Produces: nothing new for other tasks.

- [ ] **Step 1: Add the import**

In `resources/js/Pages/Sos/CommandCenter.vue`, add next to the existing `AdminLayout` import (line 4):

```js
import AdminLayout from '@/Layouts/AdminLayout.vue'
import EmergencyBorderOverlay from '@/Components/Sos/EmergencyBorderOverlay.vue'
```

- [ ] **Step 2: Render the overlay, driven by the page's own `activeAlerts`**

Current template close (`resources/js/Pages/Sos/CommandCenter.vue:255-258`):

```vue
    </div>
  </AdminLayout>
</template>
```

Replace with:

```vue
    </div>
  </AdminLayout>

  <!-- Second, independent overlay instance: reacts to a raw un-triaged SOS
       trigger (sos-responders channel, responder-only), ahead of any
       decision to promote it to the site-wide emergency-alerts broadcast
       that AdminLayout's own overlay reacts to. -->
  <EmergencyBorderOverlay :active="activeAlerts.length > 0" />
</template>
```

No new reactive state is needed — `activeAlerts` already excludes `resolved`/`false_alarm` statuses (`resources/js/Pages/Sos/CommandCenter.vue:14`) and is already kept live by the existing `sos-responders` subscription (`subscribe()`, lines 38-43) via `upsertAlert()`.

- [ ] **Step 3: Manual verification (no JS test harness exists in this project)**

As a responder on the Command Center page, trigger a test SOS from another session (or via `POST /sos/trigger`). Confirm the pulsing red border appears on the Command Center page immediately — before broadcasting anything. Acknowledge, verify, and finally resolve the alert from the detail panel — confirm the border clears once its status becomes `resolved` (or `false_alarm`), and stays visible while it's merely `acknowledged`/`verified`/`escalated`.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Sos/CommandCenter.vue
git commit -m "feat(sos): show emergency border on Command Center for un-triaged SOS alerts"
```

---

## Self-Review

**Spec coverage:** Section 2 of the spec is fully covered: the bootstrap endpoint (Task 1), the reusable overlay component (Task 2), the site-wide wiring independent of `NoticeQueueModal` acknowledgment (Task 3), and the Command Center-local raw-trigger wiring (Task 4). The spec's documented error-handling requirement ("fail closed on a failed bootstrap fetch") is implemented in Task 3 Step 2. The spec's `prefers-reduced-motion` requirement is implemented in Task 2 Step 1.

**Placeholder scan:** No TBD/TODO; every step has literal, pasteable code, including exact route placement and exact existing-line context for every modification.

**Type consistency:** `EmergencyBorderOverlay`'s single prop `active: Boolean` is used identically by both consumers (Task 3: `hasActiveEmergency` ref; Task 4: `activeAlerts.length > 0` computed expression) — no signature drift between tasks. The `GET /sos/emergency-status` response shape (`{ active, severity }`) defined in Task 1 matches exactly what Task 3's `fetchEmergencyStatus()` reads (`res.data.active`).
