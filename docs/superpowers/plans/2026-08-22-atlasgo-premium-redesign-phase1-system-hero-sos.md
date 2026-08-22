# AtlasGo Premium Redesign — Phase 1a: Design Primitives, Hero Card, SOS Live Status Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the reusable design-system primitives (per-feature gradients, `Pressable`, `StaggeredList`, `AppPageTransition`), replace the greeting `AppHeader` with a `HeroHeader` on Home and Student Dashboard, and add the SOS live-status + end-SOS feature (backend endpoints + Flutter full-screen experience).

**Architecture:** Two repos change together: `bugsaymis` (Laravel backend) gets two new student-scoped SOS endpoints on top of the existing `SosAlertService`, no schema changes. `bugsaymis-mobile` (Flutter) gets new shared widgets built bottom-up (tokens → primitives → `HeroHeader` → SOS status screen), each wired into a real screen as soon as it exists so nothing sits unused.

**Tech Stack:** Laravel 12 / PHPUnit (backend); Flutter 3 / Riverpod 2.6 / go_router 14 / dio (mobile). No new mobile dependencies.

**Spec:** `docs/superpowers/specs/2026-08-22-atlasgo-premium-redesign-phase-b2-design.md` (this plan implements the Phase 1 "system + hero screens" + "SOS live status" sections; Auth/Portal-dashboard visual application and illustration empty-states are a separate follow-up plan — see that spec's Non-goals/Scope for why).

## Global Constraints

- Silent/duress alerts (`is_silent: true`) must **never** navigate to or show the new SOS status screen — zero visible UI is a safety property, not a style choice.
- No dark mode in this pass.
- No new realtime/WebSocket dependency — SOS status uses polling (default 4s, via an overridable `sosPollIntervalProvider`), not Pusher/sockets.
- No new `sos_alerts` columns/statuses — reuse the existing lifecycle (`triggered`, `acknowledged`, `verified`, `escalated`, `resolved`, `false_alarm`).
- No destructive/schema-changing migrations in this plan (none are needed).
- Backend SOS endpoints stay under `/api/mobile/student/portal/sos/*`, `auth:sanctum`-guarded, with an explicit per-request ownership check (a student may only read/end their own alert) — this check is the actual security boundary, not a client-side assumption.
- Flutter changes land on `bugsaymis-mobile` local `main` only — no APK/IPA rebuild or store distribution as part of this plan.
- Working directories: backend tasks run from `/Users/junlou/bugsaymis-docker/src/bugsaymis` (see repo's `docker compose exec php ...` convention for running `artisan`/`phpunit`); Flutter tasks run from `/Users/junlou/bugsaymis-mobile`.

---

## Task 1: `SosAlertService::endByReporter()`

**Files:**
- Modify: `app/Services/Sos/SosAlertService.php`
- Test: `tests/Feature/Sos/SosAlertServiceEndByReporterTest.php`

**Interfaces:**
- Consumes: existing `SosAlert`, `SosAlertEvent`, `SosAlertUpdated`, `Model` (all already imported in this file).
- Produces: `SosAlertService::endByReporter(SosAlert $alert, Model $reporter): SosAlertEvent` — used by Task 2's controller.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Sos/SosAlertServiceEndByReporterTest.php`:

```php
<?php

namespace Tests\Feature\Sos;

use App\Models\Sos\SosAlert;
use App\Models\Student;
use App\Services\Sos\SosAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SosAlertServiceEndByReporterTest extends TestCase
{
    use RefreshDatabase;

    private function student(): Student
    {
        $id = DB::table('students')->insertGetId(['lastname' => 'Santos', 'firstname' => 'Liza', 'status' => 'active']);

        return Student::find($id);
    }

    private function alertFor(Student $student, array $overrides = []): SosAlert
    {
        return SosAlert::create(array_merge([
            'triggerable_type'   => Student::class,
            'triggerable_id'     => $student->id,
            'alert_type'         => 'medical',
            'status'             => 'triggered',
            'current_tier_order' => 1,
            'triggered_at'       => now(),
        ], $overrides));
    }

    public function test_reporter_can_end_an_active_alert(): void
    {
        $student = $this->student();
        $alert = $this->alertFor($student);

        app(SosAlertService::class)->endByReporter($alert, $student);

        $fresh = $alert->fresh();
        $this->assertSame('resolved', $fresh->status);
        $this->assertNotNull($fresh->resolved_at);
        $this->assertNull($fresh->resolved_by);
        $this->assertSame('Ended by reporting student.', $fresh->resolution_notes);
        $this->assertDatabaseHas('sos_alert_events', [
            'sos_alert_id' => $alert->id,
            'type'         => 'resolved',
            'actor_type'   => Student::class,
            'actor_id'     => $student->id,
        ]);
    }

    public function test_cannot_end_an_already_resolved_alert(): void
    {
        $student = $this->student();
        $alert = $this->alertFor($student, ['status' => 'resolved']);

        $this->expectException(\RuntimeException::class);

        app(SosAlertService::class)->endByReporter($alert, $student);
    }

    public function test_cannot_end_an_alert_already_marked_false_alarm(): void
    {
        $student = $this->student();
        $alert = $this->alertFor($student, ['status' => 'false_alarm']);

        $this->expectException(\RuntimeException::class);

        app(SosAlertService::class)->endByReporter($alert, $student);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=SosAlertServiceEndByReporterTest"`
Expected: FAIL — `Call to undefined method App\Services\Sos\SosAlertService::endByReporter()`

- [ ] **Step 3: Implement `endByReporter()`**

In `app/Services/Sos/SosAlertService.php`, add this method immediately after `resolve()` (before `processEscalations()`):

```php
    public function endByReporter(SosAlert $alert, Model $reporter): SosAlertEvent
    {
        if (in_array($alert->status, ['resolved', 'false_alarm'], true)) {
            throw new \RuntimeException("Alert #{$alert->id} is already closed.");
        }

        $alert->update([
            'status'           => 'resolved',
            'resolved_at'      => now(),
            'resolved_by'      => null,
            'resolution_notes' => 'Ended by reporting student.',
        ]);

        $event = SosAlertEvent::create([
            'sos_alert_id' => $alert->id,
            'type'         => 'resolved',
            'actor_type'   => get_class($reporter),
            'actor_id'     => $reporter->getKey(),
            'payload'      => ['ended_by' => 'reporter'],
        ]);

        event(new SosAlertUpdated($this->broadcastPayload($alert->fresh())));

        return $event;
    }
```

`resolved_by` is deliberately `null` (not `$reporter->getKey()`) — that FK references `users.id`, and a `Student` reporter has no row there; attribution lives entirely in the `SosAlertEvent`'s `actor_type`/`actor_id`.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=SosAlertServiceEndByReporterTest"`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add app/Services/Sos/SosAlertService.php tests/Feature/Sos/SosAlertServiceEndByReporterTest.php
git commit -m "feat(sos): add SosAlertService::endByReporter for student self-resolution"
```

---

## Task 2: Student-scoped SOS status/end endpoints

**Files:**
- Modify: `app/Http/Controllers/StudentAttendance/Api/StudentSosController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Mobile/StudentSosStatusEndTest.php`

**Interfaces:**
- Consumes: `SosAlertService::endByReporter()` (Task 1).
- Produces: `GET /api/mobile/student/portal/sos/{alert}` (route name `mobile.student.portal.sos.show`), `POST /api/mobile/student/portal/sos/{alert}/end` (route name `mobile.student.portal.sos.end`) — both consumed by the Flutter side in Tasks 11–13.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Mobile/StudentSosStatusEndTest.php`:

```php
<?php

namespace Tests\Feature\Mobile;

use App\Models\Sos\SosAlert;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentSosStatusEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // See StudentSosTriggerTest for why this is required for any
        // Student-authenticated Feature test.
        config(['opentelemetry.user_context' => false]);
    }

    private function tokenFor(Student $student): string
    {
        return $student->createToken('test')->plainTextToken;
    }

    private function makeStudent(): Student
    {
        $id = DB::table('students')->insertGetId(['lastname' => 'Santos', 'firstname' => 'Liza', 'status' => 'active']);

        return Student::find($id);
    }

    private function alertFor(Student $student, array $overrides = []): SosAlert
    {
        return SosAlert::create(array_merge([
            'triggerable_type'   => Student::class,
            'triggerable_id'     => $student->id,
            'alert_type'         => 'medical',
            'status'             => 'triggered',
            'current_tier_order' => 1,
            'triggered_at'       => now(),
        ], $overrides));
    }

    public function test_owner_can_view_their_alert_status(): void
    {
        $student = $this->makeStudent();
        $alert = $this->alertFor($student);
        $token = $this->tokenFor($student);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/mobile/student/portal/sos/{$alert->id}")
            ->assertOk()
            ->assertJson(['id' => $alert->id, 'status' => 'triggered']);
    }

    public function test_a_different_student_cannot_view_someone_elses_alert(): void
    {
        $owner = $this->makeStudent();
        $other = $this->makeStudent();
        $alert = $this->alertFor($owner);
        $token = $this->tokenFor($other);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/mobile/student/portal/sos/{$alert->id}")
            ->assertStatus(403);
    }

    public function test_owner_can_end_an_active_alert(): void
    {
        $student = $this->makeStudent();
        $alert = $this->alertFor($student);
        $token = $this->tokenFor($student);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/mobile/student/portal/sos/{$alert->id}/end")
            ->assertOk()
            ->assertJson(['status' => 'resolved']);

        $this->assertDatabaseHas('sos_alerts', ['id' => $alert->id, 'status' => 'resolved']);
    }

    public function test_a_different_student_cannot_end_someone_elses_alert(): void
    {
        $owner = $this->makeStudent();
        $other = $this->makeStudent();
        $alert = $this->alertFor($owner);
        $token = $this->tokenFor($other);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/mobile/student/portal/sos/{$alert->id}/end")
            ->assertStatus(403);

        $this->assertDatabaseHas('sos_alerts', ['id' => $alert->id, 'status' => 'triggered']);
    }

    public function test_ending_an_already_resolved_alert_returns_409(): void
    {
        $student = $this->makeStudent();
        $alert = $this->alertFor($student, ['status' => 'resolved']);
        $token = $this->tokenFor($student);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/mobile/student/portal/sos/{$alert->id}/end")
            ->assertStatus(409);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentSosStatusEndTest"`
Expected: FAIL — 404s (routes don't exist yet)

- [ ] **Step 3: Add the routes**

In `routes/api.php`, immediately after the existing `sos.trigger` route (the block starting `Route::get('/sos/config', ...)`), add:

```php
                Route::get('/sos/{alert}', [StudentSosController::class, 'show'])->name('sos.show')
                    ->whereNumber('alert')->middleware('throttle:30,1');
                Route::post('/sos/{alert}/end', [StudentSosController::class, 'end'])->name('sos.end')
                    ->whereNumber('alert')->middleware('throttle:10,1');
```

- [ ] **Step 4: Implement `show()` and `end()`**

Replace the full contents of `app/Http/Controllers/StudentAttendance/Api/StudentSosController.php` with:

```php
<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\Sos\SosAlert;
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

    /**
     * GET /api/mobile/student/portal/sos/{alert}
     */
    public function show(Request $request, SosAlert $alert): JsonResponse
    {
        $user = $request->user();
        if ($alert->triggerable_type !== get_class($user) || $alert->triggerable_id !== $user->getKey()) {
            abort(403);
        }

        return response()->json($this->serialize($alert->load('events')));
    }

    /**
     * POST /api/mobile/student/portal/sos/{alert}/end
     */
    public function end(Request $request, SosAlert $alert, SosAlertService $service): JsonResponse
    {
        $user = $request->user();
        if ($alert->triggerable_type !== get_class($user) || $alert->triggerable_id !== $user->getKey()) {
            abort(403);
        }

        try {
            $service->endByReporter($alert, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json($this->serialize($alert->fresh()->load('events')));
    }

    private function serialize(SosAlert $alert): array
    {
        return [
            'id' => $alert->id,
            'alert_type' => $alert->alert_type,
            'is_silent' => $alert->is_silent,
            'status' => $alert->status,
            'triggered_at' => $alert->triggered_at->toIso8601String(),
            'resolved_at' => $alert->resolved_at?->toIso8601String(),
            'events' => $alert->relationLoaded('events')
                ? $alert->events->map(fn ($e) => [
                    'type' => $e->type,
                    'payload' => $e->payload,
                    'created_at' => $e->created_at->toIso8601String(),
                ])->values()
                : [],
        ];
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentSosStatusEndTest"`
Expected: PASS (5 tests)

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/StudentAttendance/Api/StudentSosController.php routes/api.php tests/Feature/Mobile/StudentSosStatusEndTest.php
git commit -m "feat(sos): add student-scoped SOS status and end-SOS mobile endpoints"
```

---

## Task 3: `AppGradients` feature-area extension + `FeatureIconChip`

**Files:**
- Modify: `lib/src/core/theme.dart`
- Create: `lib/src/shared/widgets/feature_icon_chip.dart`
- Test: `test/shared/widgets/feature_icon_chip_test.dart`

**Interfaces:**
- Produces: `AppGradients.portal`, `AppGradients.attendance`, `AppGradients.grades` (consumed by the deferred visual-sweep plan); `FeatureIconChip({icon, gradient, size})`.

- [ ] **Step 1: Write the failing test**

Create `test/shared/widgets/feature_icon_chip_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/core/theme.dart';
import 'package:atlasgo/src/shared/widgets/feature_icon_chip.dart';

void main() {
  testWidgets('renders the icon on a gradient-filled circle at the given size', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: FeatureIconChip(
            icon: Icons.school_rounded,
            gradient: AppGradients.grades,
            size: 48,
          ),
        ),
      ),
    );

    expect(find.byIcon(Icons.school_rounded), findsOneWidget);

    final container = tester.widget<Container>(find.byType(Container).first);
    expect(container.constraints?.maxWidth, 48);
    final decoration = container.decoration as BoxDecoration;
    expect(decoration.shape, BoxShape.circle);
    expect(decoration.gradient, AppGradients.grades);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/bugsaymis-mobile && flutter test test/shared/widgets/feature_icon_chip_test.dart`
Expected: FAIL — `feature_icon_chip.dart` does not exist / `AppGradients.grades` undefined

- [ ] **Step 3: Add the gradients and the widget**

In `lib/src/core/theme.dart`, inside the existing `AppGradients` class (after `authDecoration`), add:

```dart
  /// Per-feature-area identity gradients — used on section headers, stat
  /// cards, and icon chips for that area. Distinct from [button] (the one
  /// universal action-gradient) and [authDecoration] (the app's own brand
  /// chrome, reused by HeroHeader).
  static const portal = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF7C3AED), Color(0xFF4F46E5)],
  );

  static const attendance = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF0D9488), Color(0xFF10B981)],
  );

  static const grades = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFFD97706), Color(0xFFF59E0B)],
  );
```

Create `lib/src/shared/widgets/feature_icon_chip.dart`:

```dart
import 'package:flutter/material.dart';

/// A round, gradient-filled icon chip used to give a feature area (Portal,
/// Attendance, Grades, ...) a colored identity — replaces plain gray icons
/// as part of the bold/vibrant visual pass.
class FeatureIconChip extends StatelessWidget {
  final IconData icon;
  final Gradient gradient;
  final double size;

  const FeatureIconChip({
    super.key,
    required this.icon,
    required this.gradient,
    this.size = 44,
  });

  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        decoration: BoxDecoration(gradient: gradient, shape: BoxShape.circle),
        child: Icon(icon, color: Colors.white, size: size * 0.5),
      );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ~/bugsaymis-mobile && flutter test test/shared/widgets/feature_icon_chip_test.dart`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add lib/src/core/theme.dart lib/src/shared/widgets/feature_icon_chip.dart test/shared/widgets/feature_icon_chip_test.dart
git commit -m "feat(design-system): add per-feature gradients and FeatureIconChip"
```

---

## Task 4: `Pressable` widget

**Files:**
- Create: `lib/src/shared/widgets/pressable.dart`
- Test: `test/shared/widgets/pressable_test.dart`

**Interfaces:**
- Consumes: `AppMotion` (from `theme.dart`).
- Produces: `Pressable({child, onTap, borderRadius})` — a standalone tap-feedback wrapper for anything that isn't an `AppCard` (chips, list rows, nav items). Not consumed by any other task in this plan; available for the deferred visual-sweep plan.

- [ ] **Step 1: Write the failing test**

Create `test/shared/widgets/pressable_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/shared/widgets/pressable.dart';

void main() {
  testWidgets('wraps a tappable child in a press-scale animation and calls onTap', (tester) async {
    var tapped = false;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Pressable(
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

  testWidgets('scales down while pressed', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Pressable(onTap: () {}, child: const Text('content')),
        ),
      ),
    );

    final gesture = await tester.startGesture(tester.getCenter(find.text('content')));
    await tester.pump();

    final scale = tester.widget<AnimatedScale>(find.byType(AnimatedScale));
    expect(scale.scale, lessThan(1.0));

    await gesture.up();
    await tester.pumpAndSettle();
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/bugsaymis-mobile && flutter test test/shared/widgets/pressable_test.dart`
Expected: FAIL — file does not exist

- [ ] **Step 3: Implement `Pressable`**

Create `lib/src/shared/widgets/pressable.dart`:

```dart
import 'package:flutter/material.dart';
import '../../core/theme.dart';

/// The app's canonical tap feedback — ink ripple + a subtle press-scale —
/// for tappables that aren't already an [AppCard] (chips, list rows, nav
/// items). Extracted from AppCard's existing press-scale so both share one
/// implementation instead of duplicating the state-management boilerplate.
class Pressable extends StatefulWidget {
  final Widget child;
  final VoidCallback? onTap;
  final BorderRadius borderRadius;

  const Pressable({
    super.key,
    required this.child,
    required this.onTap,
    this.borderRadius = BorderRadius.zero,
  });

  @override
  State<Pressable> createState() => _PressableState();
}

class _PressableState extends State<Pressable> {
  bool _pressed = false;

  @override
  Widget build(BuildContext context) {
    return AnimatedScale(
      scale: _pressed ? 0.96 : 1.0,
      duration: AppMotion.fast,
      curve: AppMotion.standard,
      child: Material(
        color: Colors.transparent,
        borderRadius: widget.borderRadius,
        child: InkWell(
          onTap: widget.onTap,
          onTapDown: (_) => setState(() => _pressed = true),
          onTapCancel: () => setState(() => _pressed = false),
          onTapUp: (_) => setState(() => _pressed = false),
          borderRadius: widget.borderRadius,
          child: widget.child,
        ),
      ),
    );
  }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ~/bugsaymis-mobile && flutter test test/shared/widgets/pressable_test.dart`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add lib/src/shared/widgets/pressable.dart test/shared/widgets/pressable_test.dart
git commit -m "feat(design-system): add Pressable tap-feedback wrapper"
```

---

## Task 5: `StaggeredList` widget

**Files:**
- Create: `lib/src/shared/widgets/staggered_list.dart`
- Test: `test/shared/widgets/staggered_list_test.dart`

**Interfaces:**
- Consumes: `AppMotion`.
- Produces: `StaggeredList({children, itemDelay})` — not consumed by any other task in this plan; available for the deferred visual-sweep plan.

- [ ] **Step 1: Write the failing test**

Create `test/shared/widgets/staggered_list_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/shared/widgets/staggered_list.dart';

void main() {
  testWidgets('renders all children immediately and fades them in', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: StaggeredList(children: [Text('one'), Text('two'), Text('three')]),
        ),
      ),
    );

    expect(find.text('one'), findsOneWidget);
    expect(find.text('two'), findsOneWidget);
    expect(find.text('three'), findsOneWidget);

    // Before any item's delayed start, every FadeTransition is still at 0.
    final firstOpacity =
        tester.widget<FadeTransition>(find.byType(FadeTransition).first).opacity.value;
    expect(firstOpacity, 0.0);

    await tester.pumpAndSettle();

    for (final finder in find.byType(FadeTransition).evaluate()) {
      final widget = finder.widget as FadeTransition;
      expect(widget.opacity.value, 1.0);
    }
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/bugsaymis-mobile && flutter test test/shared/widgets/staggered_list_test.dart`
Expected: FAIL — file does not exist

- [ ] **Step 3: Implement `StaggeredList`**

Create `lib/src/shared/widgets/staggered_list.dart`:

```dart
import 'package:flutter/material.dart';
import '../../core/theme.dart';

/// Fades + slides each child in with a small per-item delay, for list-style
/// content (dashboard lists, grade rows, notification rows). Not itself
/// scrollable — wrap it in the surrounding ListView/Column.
class StaggeredList extends StatelessWidget {
  final List<Widget> children;
  final Duration itemDelay;

  const StaggeredList({
    super.key,
    required this.children,
    this.itemDelay = const Duration(milliseconds: 40),
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (var i = 0; i < children.length; i++)
          _StaggeredItem(index: i, itemDelay: itemDelay, child: children[i]),
      ],
    );
  }
}

class _StaggeredItem extends StatefulWidget {
  final int index;
  final Duration itemDelay;
  final Widget child;

  const _StaggeredItem({
    required this.index,
    required this.itemDelay,
    required this.child,
  });

  @override
  State<_StaggeredItem> createState() => _StaggeredItemState();
}

class _StaggeredItemState extends State<_StaggeredItem>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _fade;
  late final Animation<Offset> _slide;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: AppMotion.base);
    _fade = CurvedAnimation(parent: _controller, curve: AppMotion.standard);
    _slide = Tween(begin: const Offset(0, 0.08), end: Offset.zero).animate(
        CurvedAnimation(parent: _controller, curve: AppMotion.standard));
    Future.delayed(widget.itemDelay * widget.index, () {
      if (mounted) _controller.forward();
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => FadeTransition(
        opacity: _fade,
        child: SlideTransition(position: _slide, child: widget.child),
      );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ~/bugsaymis-mobile && flutter test test/shared/widgets/staggered_list_test.dart`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add lib/src/shared/widgets/staggered_list.dart test/shared/widgets/staggered_list_test.dart
git commit -m "feat(design-system): add StaggeredList entrance-animation wrapper"
```

---

## Task 6: `AppPageTransition` helper + refactor `/student/id` to use it

**Files:**
- Create: `lib/src/core/page_transition.dart`
- Modify: `lib/src/core/router.dart`
- Test: `test/core/page_transition_test.dart`

**Interfaces:**
- Consumes: `AppMotion` (from `theme.dart`).
- Produces: `appPageTransition<T>({pageKey, child, fullscreenDialog})` returning a `CustomTransitionPage<T>` — consumed by Task 13's new SOS route.

- [ ] **Step 1: Write the failing test**

Create `test/core/page_transition_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:atlasgo/src/core/page_transition.dart';

void main() {
  testWidgets('wraps the pushed page in a Fade + Scale transition', (tester) async {
    final router = GoRouter(routes: [
      GoRoute(path: '/', builder: (c, s) => const Scaffold(body: Text('home'))),
      GoRoute(
        path: '/detail',
        pageBuilder: (c, s) => appPageTransition(
          pageKey: s.pageKey,
          child: const Scaffold(body: Text('detail')),
        ),
      ),
    ]);

    await tester.pumpWidget(MaterialApp.router(routerConfig: router));
    router.push('/detail');
    await tester.pump();

    expect(find.byType(FadeTransition), findsWidgets);
    expect(find.byType(ScaleTransition), findsWidgets);
    expect(find.text('detail'), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/bugsaymis-mobile && flutter test test/core/page_transition_test.dart`
Expected: FAIL — `page_transition.dart` does not exist

- [ ] **Step 3: Implement `appPageTransition` and refactor `/student/id`**

Create `lib/src/core/page_transition.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'theme.dart';

/// Shared full-screen route transition (fade + scale-in), replacing the
/// default platform push animation. Used for full-screen (non-tab) routes
/// so they share one consistent "premium" page-transition feel instead of
/// each hand-rolling its own CustomTransitionPage.
CustomTransitionPage<T> appPageTransition<T>({
  required LocalKey pageKey,
  required Widget child,
  bool fullscreenDialog = false,
}) {
  return CustomTransitionPage<T>(
    key: pageKey,
    fullscreenDialog: fullscreenDialog,
    transitionDuration: AppMotion.base,
    reverseTransitionDuration: AppMotion.fast,
    child: child,
    transitionsBuilder: (context, animation, secondaryAnimation, child) {
      final curved = CurvedAnimation(parent: animation, curve: AppMotion.standard);
      return FadeTransition(
        opacity: curved,
        child: ScaleTransition(
          scale: Tween(begin: 0.96, end: 1.0).animate(curved),
          child: child,
        ),
      );
    },
  );
}
```

In `lib/src/core/router.dart`, add the import:

```dart
import 'page_transition.dart';
```

Then replace the existing `/student/id` route:

```dart
      GoRoute(
        path: '/student/id',
        pageBuilder: (ctx, st) => CustomTransitionPage(
          key: st.pageKey,
          fullscreenDialog: true,
          transitionDuration: const Duration(milliseconds: 220),
          reverseTransitionDuration: const Duration(milliseconds: 180),
          child: const StudentIdScreen(),
          transitionsBuilder: (ctx, anim, _, child) {
            final curved =
                CurvedAnimation(parent: anim, curve: Curves.easeOutCubic);
            return FadeTransition(
              opacity: curved,
              child: ScaleTransition(
                scale: Tween(begin: 0.92, end: 1.0).animate(curved),
                child: child,
              ),
            );
          },
        ),
      ),
```

with:

```dart
      GoRoute(
        path: '/student/id',
        pageBuilder: (ctx, st) => appPageTransition(
          pageKey: st.pageKey,
          fullscreenDialog: true,
          child: const StudentIdScreen(),
        ),
      ),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ~/bugsaymis-mobile && flutter test test/core/page_transition_test.dart`
Expected: PASS

- [ ] **Step 5: Run the full test suite to confirm the router refactor didn't break anything**

Run: `cd ~/bugsaymis-mobile && flutter analyze && flutter test`
Expected: analyze clean, all tests pass

- [ ] **Step 6: Commit**

```bash
git add lib/src/core/page_transition.dart lib/src/core/router.dart test/core/page_transition_test.dart
git commit -m "feat(design-system): extract AppPageTransition, reuse it for /student/id"
```

---

## Task 7: `HeroHeader` widget

**Files:**
- Create: `lib/src/shared/widgets/hero_header.dart`
- Test: `test/shared/widgets/hero_header_test.dart`

**Interfaces:**
- Consumes: `AppGradients.authDecoration`, `AppSpacing`, `AppRadius`, `AppTextStyles` (all from `theme.dart`).
- Produces: `HeroHeader({greeting, name, subtitle, actionIcon, actionTooltip, onActionTap, trailing})` — consumed by Tasks 8 and 9.

- [ ] **Step 1: Write the failing test**

Create `test/shared/widgets/hero_header_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/shared/widgets/hero_header.dart';

void main() {
  testWidgets('renders greeting, name, subtitle, and an optional trailing stat', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: HeroHeader(
            greeting: 'Good morning,',
            name: 'Maria',
            subtitle: 'Monday, August 24',
            actionIcon: Icons.person_outline_rounded,
            actionTooltip: 'Profile',
            onActionTap: () {},
            trailing: const Text('3 children linked'),
          ),
        ),
      ),
    );

    expect(find.text('Good morning,'), findsOneWidget);
    expect(find.text('Maria'), findsOneWidget);
    expect(find.text('Monday, August 24'), findsOneWidget);
    expect(find.text('3 children linked'), findsOneWidget);
  });

  testWidgets('renders without a trailing widget when none is provided', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: HeroHeader(
            greeting: 'Good morning,',
            name: 'Maria',
            subtitle: 'Monday',
            actionIcon: Icons.person_outline_rounded,
            actionTooltip: 'Profile',
            onActionTap: () {},
          ),
        ),
      ),
    );

    expect(find.byType(HeroHeader), findsOneWidget);
  });

  testWidgets('tapping the action button invokes onActionTap', (tester) async {
    var tapped = false;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: HeroHeader(
            greeting: 'Good morning,',
            name: 'Maria',
            subtitle: 'Monday',
            actionIcon: Icons.logout_rounded,
            actionTooltip: 'Sign out',
            onActionTap: () => tapped = true,
          ),
        ),
      ),
    );

    await tester.tap(find.byTooltip('Sign out'));
    await tester.pump();
    expect(tapped, isTrue);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/bugsaymis-mobile && flutter test test/shared/widgets/hero_header_test.dart`
Expected: FAIL — file does not exist

- [ ] **Step 3: Implement `HeroHeader`**

Create `lib/src/shared/widgets/hero_header.dart`:

```dart
import 'package:flutter/material.dart';
import '../../core/theme.dart';

/// Replaces the pinned white `AppHeader` on screens that show a real
/// personalized greeting (Home, Student Dashboard) with a scrolling
/// gradient hero: greeting/name/date, a translucent circular action button
/// (profile on Home, sign-out on Student Dashboard — hence the generic
/// icon/tooltip/callback rather than a hardcoded "profile" action), and one
/// optional glanceable stat.
class HeroHeader extends StatelessWidget {
  final String greeting;
  final String name;
  final String subtitle;
  final IconData actionIcon;
  final String actionTooltip;
  final VoidCallback onActionTap;
  final Widget? trailing;

  const HeroHeader({
    super.key,
    required this.greeting,
    required this.name,
    required this.subtitle,
    required this.actionIcon,
    required this.actionTooltip,
    required this.onActionTap,
    this.trailing,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      decoration: const BoxDecoration(
        gradient: AppGradients.authDecoration,
        borderRadius: BorderRadius.only(
          bottomLeft: Radius.circular(AppRadius.sheet),
          bottomRight: Radius.circular(AppRadius.sheet),
        ),
      ),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: EdgeInsets.fromLTRB(
              AppSpacing.xl, AppSpacing.lg, AppSpacing.xl, AppSpacing.xxl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(greeting,
                            style: AppTextStyles.custom(
                                fontSize: 13,
                                fontWeight: FontWeight.w500,
                                color: Colors.white70)),
                        const SizedBox(height: 2),
                        Text(name,
                            style: AppTextStyles.custom(
                                fontSize: 26,
                                fontWeight: FontWeight.w800,
                                color: Colors.white,
                                letterSpacing: -0.3)),
                        const SizedBox(height: 2),
                        Text(subtitle,
                            style: AppTextStyles.custom(
                                fontSize: 13,
                                fontWeight: FontWeight.w400,
                                color: Colors.white70)),
                      ],
                    ),
                  ),
                  SizedBox(width: AppSpacing.md),
                  _HeroActionButton(
                    icon: actionIcon,
                    tooltip: actionTooltip,
                    onTap: onActionTap,
                  ),
                ],
              ),
              if (trailing != null) ...[
                SizedBox(height: AppSpacing.lg),
                trailing!,
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _HeroActionButton extends StatelessWidget {
  final IconData icon;
  final String tooltip;
  final VoidCallback onTap;

  const _HeroActionButton(
      {required this.icon, required this.tooltip, required this.onTap});

  @override
  Widget build(BuildContext context) => Semantics(
        label: tooltip,
        button: true,
        child: Container(
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.16),
            shape: BoxShape.circle,
          ),
          child: IconButton(
            icon: Icon(icon, size: 20),
            tooltip: tooltip,
            onPressed: onTap,
            style: IconButton.styleFrom(
              foregroundColor: Colors.white,
              shape: const CircleBorder(),
              minimumSize: const Size(40, 40),
              maximumSize: const Size(40, 40),
              padding: EdgeInsets.zero,
            ),
            constraints: const BoxConstraints(minWidth: 44, minHeight: 44),
          ),
        ),
      );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ~/bugsaymis-mobile && flutter test test/shared/widgets/hero_header_test.dart`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add lib/src/shared/widgets/hero_header.dart test/shared/widgets/hero_header_test.dart
git commit -m "feat(design-system): add HeroHeader replacing the greeting AppHeader"
```

---

## Task 8: Wire `HeroHeader` into `HomeScreen`

**Files:**
- Modify: `lib/src/features/home/home_screen.dart`
- Test: `test/features/home/home_screen_test.dart` (new)

**Interfaces:**
- Consumes: `HeroHeader` (Task 7), existing `linkedStudentsProvider`/`authStateProvider`/`LinkedStudent`.
- Produces: nothing new consumed elsewhere in this plan.

- [ ] **Step 1: Write the failing test**

Create `test/features/home/home_screen_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/core/theme.dart';
import 'package:atlasgo/src/features/auth/auth_provider.dart';
import 'package:atlasgo/src/features/home/home_provider.dart';
import 'package:atlasgo/src/features/home/home_screen.dart';
import 'package:atlasgo/src/shared/widgets/hero_header.dart';

class _FakeAuthNotifier extends AuthNotifier {
  @override
  Future<AuthUser?> build() async => const AuthUser(
        id: 1,
        name: 'Maria Santos',
        email: 'maria@crc.pshs.edu.ph',
        role: 'parent',
      );
}

void main() {
  testWidgets('shows a HeroHeader with the linked-student count, no AppHeader', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authStateProvider.overrideWith(() => _FakeAuthNotifier()),
          linkedStudentsProvider.overrideWith((ref) async => const [
                LinkedStudent(id: 1, barcode: 'B1', fullName: 'Juan Dela Cruz'),
              ]),
        ],
        child: const MaterialApp(home: HomeScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byType(HeroHeader), findsOneWidget);
    expect(find.byType(AppHeader), findsNothing);
    expect(find.textContaining('1 child linked'), findsOneWidget);
  });

  testWidgets('still shows the empty state when no students are linked', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authStateProvider.overrideWith(() => _FakeAuthNotifier()),
          linkedStudentsProvider.overrideWith((ref) async => const []),
        ],
        child: const MaterialApp(home: HomeScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('No children linked yet'), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/home/home_screen_test.dart`
Expected: FAIL — `AppHeader` still present, no `HeroHeader`

- [ ] **Step 3: Rewrite `HomeScreen`'s body**

In `lib/src/features/home/home_screen.dart`, add the import:

```dart
import '../../shared/widgets/hero_header.dart';
```

Replace the `build()` method's `Scaffold` body:

```dart
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Column(
        children: [
          // ── White header ──────────────────────────────────────────────
          AppHeader(
            greeting: greeting,
            name: firstName,
            subtitle: dateStr,
            actions: [
              _HeaderIconBtn(
                icon: Icons.person_outline_rounded,
                tooltip: 'Profile',
                onTap: () => context.push('/profile'),
              ),
            ],
          ),

          // ── Body ──────────────────────────────────────────────────────
          Expanded(
            child: RefreshIndicator(
              color: AppColors.accent,
              onRefresh: () async => ref.invalidate(linkedStudentsProvider),
              child: AnimatedSwitcher(
                duration: AppMotion.slow,
                switchInCurve: AppMotion.standard,
                switchOutCurve: AppMotion.standard,
                child: students.when(
                  loading: () => const ShimmerList(
                      key: ValueKey('loading'), count: 3, itemHeight: 130),
                  error: (e, _) => _ErrorView(
                      key: const ValueKey('error'),
                      onRetry: () => ref.invalidate(linkedStudentsProvider)),
                  data: (list) => list.isEmpty
                      ? _EmptyState(key: const ValueKey('empty'))
                      : _StudentList(key: const ValueKey('list'), students: list),
                ),
              ),
            ),
          ),
        ],
      ),
    );
```

with:

```dart
    return Scaffold(
      backgroundColor: AppColors.background,
      body: RefreshIndicator(
        color: AppColors.accent,
        onRefresh: () async => ref.invalidate(linkedStudentsProvider),
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            HeroHeader(
              greeting: greeting,
              name: firstName,
              subtitle: dateStr,
              actionIcon: Icons.person_outline_rounded,
              actionTooltip: 'Profile',
              onActionTap: () => context.push('/profile'),
              trailing: students.maybeWhen(
                data: (list) => _LinkedCountChip(count: list.length),
                orElse: () => null,
              ),
            ),
            AnimatedSwitcher(
              duration: AppMotion.slow,
              switchInCurve: AppMotion.standard,
              switchOutCurve: AppMotion.standard,
              child: students.when(
                loading: () => const _HomeLoadingList(key: ValueKey('loading')),
                error: (e, _) => _ErrorView(
                    key: const ValueKey('error'),
                    onRetry: () => ref.invalidate(linkedStudentsProvider)),
                data: (list) => list.isEmpty
                    ? _EmptyState(key: const ValueKey('empty'))
                    : _StudentListColumn(
                        key: const ValueKey('list'), students: list),
              ),
            ),
          ],
        ),
      ),
    );
```

Delete the now-unused `_HeaderIconBtn` class entirely (its only caller was the removed `AppHeader.actions`).

Replace the `_StudentList` class:

```dart
class _StudentList extends StatelessWidget {
  final List<LinkedStudent> students;
  const _StudentList({super.key, required this.students});

  @override
  Widget build(BuildContext context) => ListView.builder(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
        itemCount: students.length + 1,
        itemBuilder: (_, i) {
          if (i == 0) return const SectionLabel('YOUR CHILDREN TODAY');
          return Padding(
            padding: const EdgeInsets.only(bottom: 14),
            child: _StudentCard(student: students[i - 1]),
          );
        },
      );
}
```

with (now a non-scrolling section, since it lives inside the outer `ListView`):

```dart
class _StudentListColumn extends StatelessWidget {
  final List<LinkedStudent> students;
  const _StudentListColumn({super.key, required this.students});

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const SectionLabel('YOUR CHILDREN TODAY'),
            for (final s in students)
              Padding(
                padding: const EdgeInsets.only(bottom: 14),
                child: _StudentCard(student: s),
              ),
          ],
        ),
      );
}

class _HomeLoadingList extends StatelessWidget {
  const _HomeLoadingList({super.key});

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
        child: Column(
          children: List.generate(
            3,
            (_) => const Padding(
              padding: EdgeInsets.only(bottom: 14),
              child: ShimmerCard(height: 130),
            ),
          ),
        ),
      );
}

class _LinkedCountChip extends StatelessWidget {
  final int count;
  const _LinkedCountChip({required this.count});

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.16),
          borderRadius: BorderRadius.circular(20),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.family_restroom_rounded, size: 14, color: Colors.white),
            const SizedBox(width: 6),
            Text(
              count == 1 ? '1 child linked' : '$count children linked',
              style: AppTextStyles.custom(
                  fontSize: 12, fontWeight: FontWeight.w600, color: Colors.white),
            ),
          ],
        ),
      );
}
```

This nesting is safe because `_StudentListColumn` and `_HomeLoadingList` are no longer independently-scrolling `ListView`s — they're plain `Column`s inside the one outer scrollable, matching the pattern `StudentDashboardScreen` already uses for its own body.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/home/home_screen_test.dart`
Expected: PASS

- [ ] **Step 5: Run the full test suite and analyzer**

Run: `cd ~/bugsaymis-mobile && flutter analyze && flutter test`
Expected: analyze clean (no unused-import/unused-class warnings from the removed `_HeaderIconBtn`), all tests pass

- [ ] **Step 6: Commit**

```bash
git add lib/src/features/home/home_screen.dart test/features/home/home_screen_test.dart
git commit -m "feat(home): replace greeting AppHeader with HeroHeader"
```

---

## Task 9: Wire `HeroHeader` into `StudentDashboardScreen`

**Files:**
- Modify: `lib/src/features/student/student_dashboard_screen.dart`
- Test: `test/features/student/student_dashboard_screen_test.dart` (new)

**Interfaces:**
- Consumes: `HeroHeader` (Task 7), existing `studentProfileProvider`/`studentTodayProvider`/`studentGradesProvider`/`portalDashboardProvider`, `StatusBadge` (from `theme.dart`).

- [ ] **Step 1: Write the failing test**

Create `test/features/student/student_dashboard_screen_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/core/theme.dart';
import 'package:atlasgo/src/features/auth/auth_provider.dart';
import 'package:atlasgo/src/features/grades/grades_provider.dart';
import 'package:atlasgo/src/features/portal/portal_provider.dart';
import 'package:atlasgo/src/features/student/student_dashboard_screen.dart';
import 'package:atlasgo/src/features/student/student_provider.dart';
import 'package:atlasgo/src/shared/widgets/hero_header.dart';

class _FakeAuthNotifier extends AuthNotifier {
  @override
  Future<AuthUser?> build() async => const AuthUser(
        id: 2,
        name: 'Juan Dela Cruz',
        email: 'juan@crc.pshs.edu.ph',
        role: 'student',
      );
}

void main() {
  testWidgets('shows a HeroHeader with an attendance status badge, no AppHeader', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authStateProvider.overrideWith(() => _FakeAuthNotifier()),
          studentProfileProvider.overrideWith((ref) async => const StudentProfile(
                id: 2,
                name: 'Juan Dela Cruz',
                gradeLevel: 8,
                section: 'Curie',
                schoolYear: '2026-2027',
              )),
          studentTodayProvider.overrideWith((ref) async =>
              const StudentTodaySummary(lastStatus: 'in', totalScans: 1)),
          studentGradesProvider.overrideWith((ref) async => const GradesData(grades: [])),
          portalDashboardProvider.overrideWith((ref) async => const PortalDashboard(
                gradeLevel: 8,
                completion: [],
                totalDone: 0,
                total: 0,
                clearance: null,
                intern: null,
              )),
        ],
        child: const MaterialApp(home: StudentDashboardScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byType(HeroHeader), findsOneWidget);
    expect(find.byType(AppHeader), findsNothing);
    expect(find.byType(StatusBadge), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/student/student_dashboard_screen_test.dart`
Expected: FAIL — `AppHeader` still present, no `HeroHeader`, no `StatusBadge`

- [ ] **Step 3: Rewrite `StudentDashboardScreen`'s body**

In `lib/src/features/student/student_dashboard_screen.dart`, add the import:

```dart
import '../../shared/widgets/hero_header.dart';
```

Replace:

```dart
    return Scaffold(
      backgroundColor: AppColors.background,
      body: Column(
        children: [
          // ── Header ──────────────────────────────────────────────────────
          AppHeader(
            greeting: greeting,
            name: firstName,
            subtitle: dateStr,
            actions: [
              Padding(
                padding: const EdgeInsets.only(left: 4),
                child: IconButton(
                  icon: const Icon(Icons.logout_rounded, size: 20),
                  tooltip: 'Sign out',
                  style: IconButton.styleFrom(
                    foregroundColor: AppColors.textSecondary,
                    backgroundColor: AppColors.neutralBg,
                    shape: const CircleBorder(),
                    minimumSize: const Size(38, 38),
                    maximumSize: const Size(38, 38),
                    padding: EdgeInsets.zero,
                  ),
                  constraints: const BoxConstraints(minWidth: 44, minHeight: 44),
                  onPressed: () async {
                    await ref.read(authStateProvider.notifier).logout();
                    if (context.mounted) context.go('/login');
                  },
                ),
              ),
            ],
          ),

          // ── Body ──────────────────────────────────────────────────────
          Expanded(
            child: RefreshIndicator(
              color: AppColors.accent,
              onRefresh: () async {
                ref.invalidate(studentProfileProvider);
                ref.invalidate(studentTodayProvider);
                ref.invalidate(studentGradesProvider);
                ref.invalidate(portalDashboardProvider);
              },
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
                children: [
```

with:

```dart
    return Scaffold(
      backgroundColor: AppColors.background,
      body: RefreshIndicator(
        color: AppColors.accent,
        onRefresh: () async {
          ref.invalidate(studentProfileProvider);
          ref.invalidate(studentTodayProvider);
          ref.invalidate(studentGradesProvider);
          ref.invalidate(portalDashboardProvider);
        },
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            HeroHeader(
              greeting: greeting,
              name: firstName,
              subtitle: dateStr,
              actionIcon: Icons.logout_rounded,
              actionTooltip: 'Sign out',
              onActionTap: () async {
                await ref.read(authStateProvider.notifier).logout();
                if (context.mounted) context.go('/login');
              },
              trailing: today.maybeWhen(
                data: (t) => StatusBadge(status: t.lastStatus),
                orElse: () => null,
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
              child: Column(
                children: [
```

And close out the now-differently-nested widget tree: the original closes with

```dart
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
```

Replace with (one fewer `Expanded`/`RefreshIndicator` closing level, one extra `Padding`/`Column` closing level):

```dart
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
```

Everything between the two replaced blocks (the profile card, today card, grades, portal to-do section, quick actions) stays exactly as-is — only the opening/closing wrapper widgets change.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/student/student_dashboard_screen_test.dart`
Expected: PASS

- [ ] **Step 5: Run the full test suite and analyzer**

Run: `cd ~/bugsaymis-mobile && flutter analyze && flutter test`
Expected: analyze clean, all tests pass

- [ ] **Step 6: Commit**

```bash
git add lib/src/features/student/student_dashboard_screen.dart test/features/student/student_dashboard_screen_test.dart
git commit -m "feat(student-dashboard): replace greeting AppHeader with HeroHeader"
```

---

## Task 10: `SosStatusPoller`

**Files:**
- Create: `lib/src/features/sos/sos_status_poller.dart`
- Test: `test/features/sos/sos_status_poller_test.dart`

**Interfaces:**
- Produces: `SosStatusPoller({fetch, interval})`, `.poll(): Stream<Map<String, dynamic>>`, `kSosTerminalStatuses` — consumed by Task 11's provider and Task 12's screen.

- [ ] **Step 1: Write the failing test**

Create `test/features/sos/sos_status_poller_test.dart`:

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/features/sos/sos_status_poller.dart';

void main() {
  test('polls repeatedly, emitting each status, until a terminal one arrives', () async {
    var callCount = 0;
    final statuses = ['triggered', 'acknowledged', 'resolved'];
    final poller = SosStatusPoller(
      interval: const Duration(milliseconds: 1),
      fetch: () async {
        final status = statuses[callCount];
        callCount++;
        return {'status': status};
      },
    );

    final results = await poller.poll().toList();

    expect(results.map((r) => r['status']).toList(), ['triggered', 'acknowledged', 'resolved']);
    expect(callCount, 3);
  });

  test('stops immediately if the first fetch is already terminal', () async {
    final poller = SosStatusPoller(
      interval: const Duration(milliseconds: 1),
      fetch: () async => {'status': 'false_alarm'},
    );

    final results = await poller.poll().toList();

    expect(results.length, 1);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/sos/sos_status_poller_test.dart`
Expected: FAIL — file does not exist

- [ ] **Step 3: Implement `SosStatusPoller`**

Create `lib/src/features/sos/sos_status_poller.dart`:

```dart
const kSosTerminalStatuses = {'resolved', 'false_alarm'};

/// Polls [fetch] on [interval] until the returned status is terminal
/// (resolved/false_alarm), then closes the stream. Extracted from the
/// Riverpod provider (sos_status_provider.dart) so the polling/stop logic
/// is testable without a real Timer or network call.
class SosStatusPoller {
  final Future<Map<String, dynamic>> Function() fetch;
  final Duration interval;

  SosStatusPoller({
    required this.fetch,
    this.interval = const Duration(seconds: 4),
  });

  Stream<Map<String, dynamic>> poll() async* {
    while (true) {
      final status = await fetch();
      yield status;
      if (kSosTerminalStatuses.contains(status['status'])) return;
      await Future.delayed(interval);
    }
  }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/sos/sos_status_poller_test.dart`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/sos/sos_status_poller.dart test/features/sos/sos_status_poller_test.dart
git commit -m "feat(sos): add SosStatusPoller polling engine"
```

---

## Task 11: `sos_status_provider.dart`

**Files:**
- Create: `lib/src/features/sos/sos_status_provider.dart`
- Test: `test/features/sos/sos_status_provider_test.dart`

**Interfaces:**
- Consumes: `SosStatusPoller`/`kSosTerminalStatuses` (Task 10), `apiClientProvider`/`ApiClient` (from `core/api_client.dart`).
- Produces: `sosPollIntervalProvider` (overridable `Provider<Duration>`), `sosStatusProvider` (`StreamProvider.autoDispose.family<Map<String, dynamic>, int>`), `endSosAlert(ApiClient, int alertId)` — all consumed by Task 12's screen.

- [ ] **Step 1: Write the failing test**

Create `test/features/sos/sos_status_provider_test.dart`:

```dart
import 'dart:async';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/core/api_client.dart';
import 'package:atlasgo/src/features/sos/sos_status_provider.dart';

class _SequenceAdapter implements HttpClientAdapter {
  final List<String> bodies;
  int _call = 0;
  _SequenceAdapter(this.bodies);

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream,
      Future<void>? cancelFuture) async {
    final body = bodies[_call.clamp(0, bodies.length - 1)];
    _call++;
    return ResponseBody.fromString(body, 200, headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    });
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  test('emits each polled status and stops once terminal', () async {
    final apiClient = ApiClient();
    apiClient.dio.httpClientAdapter = _SequenceAdapter([
      '{"status": "triggered"}',
      '{"status": "acknowledged"}',
      '{"status": "resolved"}',
    ]);

    final container = ProviderContainer(overrides: [
      apiClientProvider.overrideWithValue(apiClient),
      sosPollIntervalProvider.overrideWithValue(const Duration(milliseconds: 5)),
    ]);
    addTearDown(container.dispose);

    final statuses = <String>[];
    final done = Completer<void>();
    final sub = container.listen(sosStatusProvider(1), (prev, next) {
      next.whenData((data) {
        statuses.add(data['status'] as String);
        if (data['status'] == 'resolved') done.complete();
      });
    }, fireImmediately: true);
    addTearDown(sub.close);

    await done.future.timeout(const Duration(seconds: 2));
    expect(statuses, ['triggered', 'acknowledged', 'resolved']);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/sos/sos_status_provider_test.dart`
Expected: FAIL — file does not exist

- [ ] **Step 3: Implement the provider**

Create `lib/src/features/sos/sos_status_provider.dart`:

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api_client.dart';
import 'sos_status_poller.dart';

/// How often the active-status screen polls for updates. A separate
/// provider (rather than a hardcoded default inside sosStatusProvider) so
/// tests can override it to a near-zero delay instead of waiting on the
/// real 4s cadence.
final sosPollIntervalProvider =
    Provider<Duration>((ref) => const Duration(seconds: 4));

final sosStatusProvider =
    StreamProvider.autoDispose.family<Map<String, dynamic>, int>((ref, alertId) {
  final client = ref.read(apiClientProvider);
  final interval = ref.watch(sosPollIntervalProvider);
  final poller = SosStatusPoller(
    interval: interval,
    fetch: () async {
      final response = await client.get('/student/portal/sos/$alertId');
      return response.data as Map<String, dynamic>;
    },
  );
  return poller.poll();
});

/// Calls the end-SOS endpoint. A plain function rather than a provider
/// method — invoked directly from the status screen's confirm-dialog
/// handler with whichever ApiClient the caller already has.
Future<void> endSosAlert(ApiClient apiClient, int alertId) =>
    apiClient.post('/student/portal/sos/$alertId/end');
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/sos/sos_status_provider_test.dart`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/sos/sos_status_provider.dart test/features/sos/sos_status_provider_test.dart
git commit -m "feat(sos): add sosStatusProvider polling wiring and endSosAlert"
```

---

## Task 12: `SosActiveStatusScreen`

**Files:**
- Create: `lib/src/features/sos/sos_active_status_screen.dart`
- Test: `test/features/sos/sos_active_status_screen_test.dart`

**Interfaces:**
- Consumes: `sosStatusProvider`, `endSosAlert`, `kSosTerminalStatuses`, `apiClientProvider`.
- Produces: `SosActiveStatusScreen({alertId})` — consumed by Task 13's router registration.

- [ ] **Step 1: Write the failing test**

Create `test/features/sos/sos_active_status_screen_test.dart`:

```dart
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/core/api_client.dart';
import 'package:atlasgo/src/features/sos/sos_active_status_screen.dart';
import 'package:atlasgo/src/features/sos/sos_status_provider.dart';

class _RecordingAdapter implements HttpClientAdapter {
  RequestOptions? lastRequest;

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream,
      Future<void>? cancelFuture) async {
    lastRequest = options;
    return ResponseBody.fromString('{}', 200, headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    });
  }

  @override
  void close({bool force = false}) {}
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const secureStorageChannel = MethodChannel('plugins.it_nomads.com/flutter_secure_storage');
  TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
      .setMockMethodCallHandler(secureStorageChannel, (call) async => null);

  testWidgets('shows "Help is on the way" and the end-SOS action for an active alert',
      (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          sosStatusProvider(1).overrideWith((ref) => Stream.value({'status': 'acknowledged'})),
        ],
        child: const MaterialApp(home: SosActiveStatusScreen(alertId: 1)),
      ),
    );
    await tester.pump();

    expect(find.text('Help is on the way'), findsOneWidget);
    expect(find.text("End SOS — I'm safe"), findsOneWidget);
  });

  testWidgets('shows the resolved end state and no end-SOS action once terminal',
      (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          sosStatusProvider(1).overrideWith((ref) => Stream.value({'status': 'resolved'})),
        ],
        child: const MaterialApp(home: SosActiveStatusScreen(alertId: 1)),
      ),
    );
    await tester.pump();

    expect(find.text('You are marked safe'), findsOneWidget);
    expect(find.text("End SOS — I'm safe"), findsNothing);
    expect(find.text('Done'), findsOneWidget);
  });

  testWidgets('End SOS requires confirmation before calling the end endpoint', (tester) async {
    final adapter = _RecordingAdapter();
    final apiClient = ApiClient();
    apiClient.dio.httpClientAdapter = adapter;

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          apiClientProvider.overrideWithValue(apiClient),
          sosStatusProvider(1).overrideWith((ref) => Stream.value({'status': 'triggered'})),
        ],
        child: const MaterialApp(home: SosActiveStatusScreen(alertId: 1)),
      ),
    );
    await tester.pump();

    await tester.tap(find.text("End SOS — I'm safe"));
    await tester.pumpAndSettle();

    expect(adapter.lastRequest, isNull);
    expect(find.text('End this SOS alert?'), findsOneWidget);

    await tester.tap(find.text("Yes, I'm safe"));
    await tester.pumpAndSettle();

    expect(adapter.lastRequest?.path, '/student/portal/sos/1/end');
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/sos/sos_active_status_screen_test.dart`
Expected: FAIL — file does not exist

- [ ] **Step 3: Implement `SosActiveStatusScreen`**

Create `lib/src/features/sos/sos_active_status_screen.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/api_client.dart';
import '../../core/theme.dart';
import 'sos_status_poller.dart';
import 'sos_status_provider.dart';

const _steps = ['triggered', 'acknowledged', 'verified', 'resolved'];
const _stepLabels = {
  'triggered': 'Alert sent',
  'acknowledged': 'Acknowledged by responders',
  'verified': 'Verified — help dispatched',
  'escalated': 'Escalated to next responder tier',
  'resolved': 'Resolved',
  'false_alarm': 'Marked as false alarm',
};

/// Full-screen "Help is on the way" experience, shown after a successful
/// non-silent SOS trigger. Never reached for silent/duress triggers — see
/// sos_trigger_sheet.dart, which only navigates here on the non-silent path.
class SosActiveStatusScreen extends ConsumerWidget {
  final int alertId;
  const SosActiveStatusScreen({super.key, required this.alertId});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final status = ref.watch(sosStatusProvider(alertId));

    return Scaffold(
      backgroundColor: Colors.red.shade600,
      body: SafeArea(
        child: status.when(
          loading: () => const Center(child: CircularProgressIndicator(color: Colors.white)),
          error: (_, _) => _ErrorState(onClose: () => context.go('/student/home')),
          data: (data) => _StatusBody(alertId: alertId, data: data),
        ),
      ),
    );
  }
}

class _StatusBody extends StatelessWidget {
  final int alertId;
  final Map<String, dynamic> data;
  const _StatusBody({required this.alertId, required this.data});

  bool get _isTerminal => kSosTerminalStatuses.contains(data['status']);

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.all(AppSpacing.xl),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          if (!_isTerminal)
            const _RadarPulse()
          else
            const Icon(Icons.check_circle_rounded, color: Colors.white, size: 96),
          SizedBox(height: AppSpacing.xl),
          Text(
            _isTerminal ? 'You are marked safe' : 'Help is on the way',
            textAlign: TextAlign.center,
            style: AppTextStyles.custom(
                fontSize: 22, fontWeight: FontWeight.w800, color: Colors.white),
          ),
          SizedBox(height: AppSpacing.xxl),
          _StatusStepper(currentStatus: data['status'] as String),
          const Spacer(),
          if (!_isTerminal)
            SizedBox(
              width: double.infinity,
              child: OutlinedButton(
                style: OutlinedButton.styleFrom(
                  foregroundColor: Colors.white,
                  side: const BorderSide(color: Colors.white),
                  minimumSize: const Size(88, 52),
                ),
                onPressed: () => _confirmEnd(context, alertId),
                child: const Text("End SOS — I'm safe"),
              ),
            )
          else
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.white, foregroundColor: Colors.red.shade600),
                onPressed: () => context.go('/student/home'),
                child: const Text('Done'),
              ),
            ),
        ],
      ),
    );
  }

  Future<void> _confirmEnd(BuildContext context, int alertId) async {
    // Capture the container before the await — context may not be safe to
    // derive values from afterward (see project convention re: async gaps).
    final container = ProviderScope.containerOf(context, listen: false);

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('End this SOS alert?'),
        content: const Text(
            'This tells responders you are safe. Only do this if the emergency has ended.'),
        actions: [
          TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
          TextButton(
              onPressed: () => Navigator.of(ctx).pop(true), child: const Text("Yes, I'm safe")),
        ],
      ),
    );

    if (confirmed != true) return;

    await endSosAlert(container.read(apiClientProvider), alertId);
  }
}

class _StatusStepper extends StatelessWidget {
  final String currentStatus;
  const _StatusStepper({required this.currentStatus});

  @override
  Widget build(BuildContext context) {
    final currentIndex = _steps
        .indexOf(currentStatus == 'false_alarm' ? 'resolved' : currentStatus)
        .clamp(0, _steps.length - 1);

    return Column(
      children: [
        for (var i = 0; i < _steps.length; i++)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Row(
              children: [
                Icon(
                  i <= currentIndex
                      ? Icons.check_circle_rounded
                      : Icons.radio_button_unchecked_rounded,
                  color: Colors.white,
                  size: 20,
                ),
                SizedBox(width: AppSpacing.sm),
                Text(
                  _stepLabels[_steps[i]] ?? _steps[i],
                  style: AppTextStyles.custom(
                    fontSize: 14,
                    fontWeight: i == currentIndex ? FontWeight.w700 : FontWeight.w400,
                    color: Colors.white,
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _RadarPulse extends StatefulWidget {
  const _RadarPulse();

  @override
  State<_RadarPulse> createState() => _RadarPulseState();
}

class _RadarPulseState extends State<_RadarPulse> with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(vsync: this, duration: const Duration(seconds: 2))
      ..repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => SizedBox(
        width: 160,
        height: 160,
        child: AnimatedBuilder(
          animation: _controller,
          builder: (context, _) => CustomPaint(
            painter: _RadarPainter(progress: _controller.value),
            child: const Center(
              child: Icon(Icons.emergency_rounded, color: Colors.white, size: 48),
            ),
          ),
        ),
      );
}

class _RadarPainter extends CustomPainter {
  final double progress;
  _RadarPainter({required this.progress});

  @override
  void paint(Canvas canvas, Size size) {
    final center = size.center(Offset.zero);
    final maxRadius = size.width / 2;
    for (final offset in [0.0, 0.5]) {
      final ringProgress = (progress + offset) % 1.0;
      final radius = maxRadius * ringProgress;
      final opacity = (1.0 - ringProgress).clamp(0.0, 1.0);
      canvas.drawCircle(
        center,
        radius,
        Paint()..color = Colors.white.withValues(alpha: opacity * 0.5),
      );
    }
    canvas.drawCircle(
        center, maxRadius * 0.35, Paint()..color = Colors.white.withValues(alpha: 0.9));
  }

  @override
  bool shouldRepaint(covariant _RadarPainter oldDelegate) => oldDelegate.progress != progress;
}

class _ErrorState extends StatelessWidget {
  final VoidCallback onClose;
  const _ErrorState({required this.onClose});

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: EdgeInsets.all(AppSpacing.xl),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(Icons.wifi_off_rounded, color: Colors.white, size: 48),
              SizedBox(height: AppSpacing.md),
              const Text('Could not load alert status.', style: TextStyle(color: Colors.white)),
              SizedBox(height: AppSpacing.lg),
              OutlinedButton(
                style: OutlinedButton.styleFrom(
                    foregroundColor: Colors.white, side: const BorderSide(color: Colors.white)),
                onPressed: onClose,
                child: const Text('Close'),
              ),
            ],
          ),
        ),
      );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/sos/sos_active_status_screen_test.dart`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/sos/sos_active_status_screen.dart test/features/sos/sos_active_status_screen_test.dart
git commit -m "feat(sos): add SosActiveStatusScreen with live stepper and end-SOS"
```

---

## Task 13: Wire the trigger flow to navigate to the new status screen

**Files:**
- Modify: `lib/src/features/sos/sos_trigger_sheet.dart`
- Modify: `lib/src/core/router.dart`

**Interfaces:**
- Consumes: `appPageTransition` (Task 6), `SosActiveStatusScreen` (Task 12).
- Produces: `/sos/active/:alertId` route.

- [ ] **Step 1: Update `_dispatch` to return the alert id**

In `lib/src/features/sos/sos_trigger_sheet.dart`, add the import:

```dart
import 'package:go_router/go_router.dart';
```

Replace the `_dispatch` function:

```dart
Future<(bool, String?, String?)> _dispatch(
  BuildContext context, {
  required String alertType,
  required bool isSilent,
}) async {
  final container = ProviderScope.containerOf(context, listen: false);
  final coords = await _captureLocation();

  try {
    await container.read(apiClientProvider).post(
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
```

with:

```dart
Future<(bool, String?, String?, int?)> _dispatch(
  BuildContext context, {
  required String alertType,
  required bool isSilent,
}) async {
  final container = ProviderScope.containerOf(context, listen: false);
  final coords = await _captureLocation();

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
    final alertId = (response.data as Map)['alert_id'] as int?;
    return (false, null, null, alertId);
  } on DioException catch (e) {
    if (e.response?.statusCode == 422 && e.response?.data?['blocked'] == true) {
      return (
        true,
        e.response?.data['message'] as String?,
        e.response?.data['emergency_hotline'] as String?,
        null,
      );
    }
    rethrow;
  }
}
```

- [ ] **Step 2: Navigate to the status screen on non-silent success**

In `_SosSheetState._startCountdown()`, replace:

```dart
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
```

with:

```dart
        _dispatch(context, alertType: _category!, isSilent: false).then((result) {
          if (!mounted) return;

          if (result.$1) {
            setState(() {
              _phase = _Phase.blocked;
              _blockedMessage = result.$2;
              _hotline = result.$3;
            });
            return;
          }

          final alertId = result.$4;
          if (alertId == null) {
            // Defensive fallback — the backend contract always returns
            // alert_id on success, but degrade to the static confirmation
            // rather than navigate nowhere if that ever changes.
            setState(() => _phase = _Phase.sent);
            return;
          }

          final router = GoRouter.of(context);
          Navigator.of(context).pop();
          router.push('/sos/active/$alertId');
        });
```

- [ ] **Step 3: Register the route**

In `lib/src/core/router.dart`, add the import:

```dart
import '../features/sos/sos_active_status_screen.dart';
```

In the "Full-screen routes" section (alongside `/student/id`), add:

```dart
      GoRoute(
        path: '/sos/active/:alertId',
        pageBuilder: (ctx, st) => appPageTransition(
          pageKey: st.pageKey,
          fullscreenDialog: true,
          child: SosActiveStatusScreen(
            alertId: int.parse(st.pathParameters['alertId']!),
          ),
        ),
      ),
```

- [ ] **Step 4: Run the existing SOS sheet tests to confirm no regression**

Run: `cd ~/bugsaymis-mobile && flutter test test/features/sos/sos_trigger_sheet_test.dart`
Expected: PASS — both existing tests only exercise the picker phase (they never reach `_dispatch()`), so they're unaffected by this change. The new hold→countdown→dispatch→navigate path is timer-driven and end-to-end (geolocation, real countdown, HTTP, navigation); per this file's own existing test boundary (it already stops at "Hold to confirm appears" rather than driving the real countdown), this integration is verified by the Simulator click-through called for in the spec's Testing section, not a new automated widget test — matches how the original countdown/dispatch path was never covered by an automated test either.

- [ ] **Step 5: Run the full analyzer and test suite**

Run: `cd ~/bugsaymis-mobile && flutter analyze && flutter test`
Expected: analyze clean, all tests pass

- [ ] **Step 6: Commit**

```bash
git add lib/src/features/sos/sos_trigger_sheet.dart lib/src/core/router.dart
git commit -m "feat(sos): navigate to the live status screen after a non-silent trigger"
```

---

## Post-plan verification (Simulator click-through)

Per the spec's Testing section, before considering Phase 1a done:

1. Trigger a real non-silent alert as the dev test student — confirm the app lands on `/sos/active/:id` showing "Help is on the way" and the pulsing radar.
2. From the Command Center (or a direct dev-DB status update), move the alert through `acknowledged` → `verified` — confirm the stepper updates within ~4-8s (one to two poll cycles) without the student needing to refresh.
3. Tap "End SOS — I'm safe", confirm the dialog, confirm the screen settles to "You are marked safe" and polling stops.
4. Trigger a **silent** alert (long-press) — confirm it never navigates to the status screen and shows zero visible UI, exactly as before this plan.
5. Confirm Home and Student Dashboard both render the new `HeroHeader` correctly on-device (gradient bleeds under the status bar, action button and trailing stat both work).
