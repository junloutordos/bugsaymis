# AtlasGo Reference-Driven Visual Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `RadialProgressRing` (hand-rolled) and `TrendChart` (via `fl_chart`), shift the palette to the reference's softer tones, restyle `HeroHeader` to a contained card, and wire rings/charts into Grades, Portal-todo/clearance, and Attendance — including one new additive backend endpoint for attendance summary data.

**Architecture:** Backend adds one read-only aggregate endpoint reusing the existing `SchoolCalendarService`. Mobile builds two new shared widgets bottom-up, then a small palette-token change, then `HeroHeader`'s container restyle, then wires each of the three target screens to its new visual — each wired in as soon as the underlying widget exists.

**Tech Stack:** Laravel 12 / PHPUnit (backend); Flutter 3 / Riverpod 2.6 / `fl_chart` (new dependency) / dio (mobile).

**Spec:** `docs/superpowers/specs/2026-08-22-atlasgo-reference-driven-visual-refresh-design.md`

## Global Constraints

- No new tables, no new columns, no migration — the one new backend endpoint is a read-only aggregate over existing `student_attendance_logs` rows plus the existing `SchoolCalendarService::isSchoolDay()`.
- Do not touch `homeroom_attendance_dates` or any Homeroom Attendance module code — that table is populated only when a teacher takes attendance and would silently break this endpoint if reused as a "school days" source.
- Auth screens and the broader Portal-dashboard visual sweep stay deferred (unchanged from Phase 1a's deferral) — this plan touches only `HeroHeader`, the Student Dashboard grade/portal-todo cards, `StudentGradesScreen`, and `StudentAttendanceScreen`.
- The SOS live-status screen and its radar-pulse animation are untouched.
- Working directories: backend tasks run from `/Users/junlou/bugsaymis-docker/src/bugsaymis` (PHPUnit via `docker compose exec php` from `/Users/junlou/bugsaymis-docker`); Flutter tasks run from `/Users/junlou/bugsaymis-mobile`.

---

## Task 1: `GET /api/mobile/student/attendance/summary`

**Files:**
- Modify: `app/Http/Controllers/StudentAttendance/Api/StudentSelfController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Mobile/StudentAttendanceSummaryTest.php`

**Interfaces:**
- Consumes: `App\Services\SchoolCalendarService::isSchoolDay(string $date, ?int $gradeLevel = null): bool` (existing, unchanged), `App\Models\StudentAttendance\StudentAttendanceLog` (existing), `App\Models\Registrar\StudentEnrollment` (existing, already imported in this controller), `App\Models\FacultyLoading\SchoolYear` (existing, already imported).
- Produces: `GET /api/mobile/student/attendance/summary` → `{month_present, month_school_days, month_rate, weekly: [{week_start, present, school_days, rate}, ...]}` (9 entries: the last 8 full weeks plus the current partial week, oldest first) — consumed by Task 10's Flutter provider.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Mobile/StudentAttendanceSummaryTest.php`:

```php
<?php

namespace Tests\Feature\Mobile;

use App\Models\SchoolCalendarEvent;
use App\Models\Student;
use App\Models\StudentAttendance\StudentAttendanceLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentAttendanceSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // See StudentSosTriggerTest for why this is required for any
        // Student-authenticated Feature test.
        config(['opentelemetry.user_context' => false]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
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

    private function scan(Student $student, string $dateTime): void
    {
        StudentAttendanceLog::create([
            'student_id' => $student->id,
            'raw_barcode' => 'TEST',
            'scan_time' => $dateTime,
            'type' => 'in',
            'source' => 'gate',
        ]);
    }

    public function test_month_present_counts_distinct_days_with_an_in_scan(): void
    {
        // 2026-08-19 is a Wednesday.
        Carbon::setTestNow('2026-08-19 10:00:00');
        $student = $this->makeStudent();
        $token = $this->tokenFor($student);

        $this->scan($student, '2026-08-17 07:00:00'); // Monday
        $this->scan($student, '2026-08-17 16:00:00'); // same day, second scan — must not double-count
        $this->scan($student, '2026-08-18 07:05:00'); // Tuesday

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/student/attendance/summary')
            ->assertOk();

        // Aug 1 (Sat) through Aug 19 (Wed): weekdays only, no holidays fixtured,
        // so month_school_days = every Mon-Fri in that range.
        $response->assertJson(['month_present' => 2]);
        $this->assertGreaterThan(0, $response->json('month_school_days'));
    }

    public function test_a_holiday_reduces_school_days_but_not_present_days(): void
    {
        Carbon::setTestNow('2026-08-19 10:00:00');
        $student = $this->makeStudent();
        $token = $this->tokenFor($student);

        $admin = \App\Models\User::factory()->create();
        SchoolCalendarEvent::create([
            'date' => '2026-08-18',
            'event_type' => 'holiday',
            'grade_level' => null,
            'title' => 'Test Holiday',
            'created_by' => $admin->id,
        ]);

        $withoutHoliday = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/student/attendance/summary')
            ->json('month_school_days');

        // Weekdays Aug 1-19 2026 excluding the fixtured holiday.
        $expectedWeekdays = 0;
        for ($d = Carbon::parse('2026-08-01'); $d->lessThanOrEqualTo(Carbon::parse('2026-08-19')); $d->addDay()) {
            if (! $d->isWeekend()) {
                $expectedWeekdays++;
            }
        }

        $this->assertSame($expectedWeekdays - 1, $withoutHoliday);
    }

    public function test_weekly_series_has_nine_entries_oldest_first(): void
    {
        Carbon::setTestNow('2026-08-19 10:00:00');
        $student = $this->makeStudent();
        $token = $this->tokenFor($student);

        $weekly = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/student/attendance/summary')
            ->json('weekly');

        $this->assertCount(9, $weekly);
        $starts = array_column($weekly, 'week_start');
        $sorted = $starts;
        sort($sorted);
        $this->assertSame($sorted, $starts);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/mobile/student/attendance/summary')->assertStatus(401);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentAttendanceSummaryTest 2>/dev/null"` | grep -E "PASS|FAIL|Tests:|Duration:"
Expected: FAIL — 404 (route doesn't exist yet)

- [ ] **Step 3: Add the route**

In `routes/api.php`, immediately after the existing `/attendance` route (the line `Route::get('/attendance', [StudentSelfController::class, 'attendance'])->name('attendance');`), add:

```php
            Route::get('/attendance/summary', [StudentSelfController::class, 'attendanceSummary'])->name('attendance.summary');
```

- [ ] **Step 4: Implement `attendanceSummary()`**

In `app/Http/Controllers/StudentAttendance/Api/StudentSelfController.php`, add these imports at the top:

```php
use App\Services\SchoolCalendarService;
use Carbon\Carbon;
```

Add this method after `attendance()` (before the closing class brace):

```php
    /**
     * GET /api/mobile/student/attendance/summary
     */
    public function attendanceSummary(Request $request, SchoolCalendarService $calendar): JsonResponse
    {
        $studentId = $this->resolveStudentId($request);

        if (! $studentId) {
            return response()->json(['message' => 'Student account not fully set up.'], 404);
        }

        $gradeLevel = $this->currentGradeLevel($studentId);
        $today = Carbon::now()->startOfDay();
        $monthStart = $today->copy()->startOfMonth();

        [$monthPresent, $monthSchoolDays] = $this->presentAndSchoolDays(
            $studentId, $monthStart, $today, $gradeLevel, $calendar,
        );

        $weekly = [];
        for ($i = 8; $i >= 0; $i--) {
            $weekStart = $today->copy()->subWeeks($i)->startOfWeek(Carbon::MONDAY);
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);
            if ($weekEnd->greaterThan($today)) {
                $weekEnd = $today->copy();
            }

            [$present, $schoolDays] = $this->presentAndSchoolDays(
                $studentId, $weekStart, $weekEnd, $gradeLevel, $calendar,
            );

            $weekly[] = [
                'week_start' => $weekStart->toDateString(),
                'present' => $present,
                'school_days' => $schoolDays,
                'rate' => $schoolDays > 0 ? round($present / $schoolDays, 4) : null,
            ];
        }

        return response()->json([
            'month_present' => $monthPresent,
            'month_school_days' => $monthSchoolDays,
            'month_rate' => $monthSchoolDays > 0 ? round($monthPresent / $monthSchoolDays, 4) : null,
            'weekly' => $weekly,
        ]);
    }

    private function currentGradeLevel(int $studentId): ?int
    {
        $schoolYear = SchoolYear::where('is_current', true)->first();
        if (! $schoolYear) {
            return null;
        }

        $enrollment = StudentEnrollment::where('student_id', $studentId)
            ->where('school_year_id', $schoolYear->id)
            ->where('status', 'enrolled')
            ->first();

        return $enrollment?->grade_level;
    }

    /**
     * @return array{0: int, 1: int} [present days, school days] in the
     *   inclusive [$start, $end] range.
     */
    private function presentAndSchoolDays(
        int $studentId,
        Carbon $start,
        Carbon $end,
        ?int $gradeLevel,
        SchoolCalendarService $calendar,
    ): array {
        $present = StudentAttendanceLog::where('student_id', $studentId)
            ->where('type', 'in')
            ->whereBetween('scan_time', [$start, $end->copy()->endOfDay()])
            ->get()
            ->map(fn ($log) => $log->scan_time->toDateString())
            ->unique()
            ->count();

        $schoolDays = 0;
        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            if ($calendar->isSchoolDay($date->toDateString(), $gradeLevel)) {
                $schoolDays++;
            }
        }

        return [$present, $schoolDays];
    }
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentAttendanceSummaryTest 2>/dev/null"` | grep -E "PASS|FAIL|Tests:|Duration:"
Expected: PASS (4 tests)

- [ ] **Step 6: Run the broader mobile/attendance test groups to confirm no regression**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test tests/Feature/Mobile 2>/dev/null"` | grep -E "PASS|FAIL|Tests:|Duration:"
Expected: all PASS

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/StudentAttendance/Api/StudentSelfController.php routes/api.php tests/Feature/Mobile/StudentAttendanceSummaryTest.php
git commit -m "feat(attendance): add student attendance-summary mobile endpoint"
```

---

## Task 2: Add `fl_chart` dependency

**Files:**
- Modify: `pubspec.yaml`

**Interfaces:**
- Produces: the `fl_chart` package, available to Task 6.

- [ ] **Step 1: Add the dependency**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter pub add fl_chart`

This resolves and pins a compatible version automatically — do not hand-edit a version string into `pubspec.yaml`.

- [ ] **Step 2: Verify the app still builds and analyzes clean**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter analyze`
Expected: no issues

- [ ] **Step 3: Commit**

```bash
git add pubspec.yaml pubspec.lock
git commit -m "chore(deps): add fl_chart for the reference-driven trend chart"
```

---

## Task 3: Palette tokens — `AppGradients.hero`, softer feature gradients, soft-danger colors

**Files:**
- Modify: `lib/src/core/theme.dart`
- Test: `test/core/theme_palette_test.dart`

**Interfaces:**
- Produces: `AppGradients.hero`, redefined `AppGradients.portal/attendance/grades`, `AppColors.dangerBg`/`AppColors.dangerText` — consumed by Task 4 (`HeroHeader`) and available for future use.

- [ ] **Step 1: Write the failing test**

Create `test/core/theme_palette_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/core/theme.dart';

void main() {
  test('hero gradient goes from brand navy to emerald', () {
    expect(AppGradients.hero.colors.first, const Color(0xFF1A3557));
    expect(AppGradients.hero.colors.last, const Color(0xFF34D399));
  });

  test('soft-danger status tokens exist and are distinct from warning', () {
    expect(AppColors.dangerBg, isNot(equals(AppColors.warningBg)));
    expect(AppColors.dangerText, isNot(equals(AppColors.warningText)));
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/core/theme_palette_test.dart`
Expected: FAIL — `AppGradients.hero`/`AppColors.dangerBg`/`AppColors.dangerText` undefined

- [ ] **Step 3: Add the tokens**

In `lib/src/core/theme.dart`, inside `AppColors` (after `warningText`), add:

```dart
  static const dangerBg   = Color(0xFFFEE2E2);
  static const dangerText = Color(0xFF991B1B);
```

Inside `AppGradients` (after `authDecoration`), add:

```dart
  /// Contained-hero gradient — used by HeroHeader. Navy start reuses the
  /// existing brand color for continuity; emerald end matches the
  /// reference design's soft data-visualization palette.
  static const hero = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF1A3557), Color(0xFF34D399)],
  );
```

Then replace the three Phase-1a per-feature gradients (`portal`, `attendance`, `grades` — added in the prior redesign phase, not yet consumed anywhere) with softer single-accent tones:

```dart
  static const portal = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF6366F1), Color(0xFF818CF8)],
  );

  static const attendance = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF10B981), Color(0xFF6EE7B7)],
  );

  static const grades = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFFF59E0B), Color(0xFFFCD34D)],
  );
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/core/theme_palette_test.dart`
Expected: PASS

- [ ] **Step 5: Run the existing `feature_icon_chip_test.dart` to confirm the redefinition doesn't break it**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/shared/widgets/feature_icon_chip_test.dart`
Expected: PASS — that test compares `decoration.gradient` against `AppGradients.grades` by reference/value, not a hardcoded color, so redefining the gradient's actual colors doesn't break it.

- [ ] **Step 6: Commit**

```bash
git add lib/src/core/theme.dart test/core/theme_palette_test.dart
git commit -m "feat(design-system): add hero gradient, soften feature gradients, add soft-danger tokens"
```

---

## Task 4: `HeroHeader` → contained card

**Files:**
- Modify: `lib/src/shared/widgets/hero_header.dart`
- Modify: `test/shared/widgets/hero_header_test.dart`

**Interfaces:**
- Consumes: `AppGradients.hero` (Task 3).
- Produces: no interface change — `HeroHeader`'s constructor/parameters are unchanged; only its internal container styling changes. Home and Student Dashboard (Phase 1a) need no code changes.

- [ ] **Step 1: Write the failing test**

Add this test to `test/shared/widgets/hero_header_test.dart` (append to the existing `main()`):

```dart
  testWidgets('renders as a fully-rounded, margined card using the hero gradient', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          backgroundColor: Colors.white,
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

    final container = tester.widget<Container>(find.byType(Container).first);
    final decoration = container.decoration as BoxDecoration;
    final radius = decoration.borderRadius as BorderRadius;

    expect(decoration.gradient, AppGradients.hero);
    expect(radius.topLeft, radius.bottomLeft);
    expect(radius.topLeft, radius.topRight);
    expect(radius.topLeft.x, greaterThan(0));
    expect(container.margin, isNotNull);
  });
```

Add the import at the top of the test file:

```dart
import 'package:atlasgo/src/core/theme.dart';
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/shared/widgets/hero_header_test.dart`
Expected: FAIL — current decoration uses `AppGradients.authDecoration` and only rounds the bottom corners, with no margin

- [ ] **Step 3: Restyle the container**

In `lib/src/shared/widgets/hero_header.dart`, replace:

```dart
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
```

with:

```dart
    return SafeArea(
      bottom: false,
      child: Container(
        width: double.infinity,
        margin: EdgeInsets.fromLTRB(
            AppSpacing.lg, AppSpacing.md, AppSpacing.lg, 0),
        decoration: BoxDecoration(
          gradient: AppGradients.hero,
          borderRadius: BorderRadius.circular(AppRadius.card),
        ),
        child: Padding(
          padding: EdgeInsets.fromLTRB(
              AppSpacing.xl, AppSpacing.lg, AppSpacing.xl, AppSpacing.xl),
```

And close out the now-differently-nested widget tree: the original closes with

```dart
            ],
          ),
        ),
      ),
    );
  }
}
```

(one `Column` close, one `Padding` close, one `SafeArea` close, one `Container` close). Replace with (one extra `Container` close since `SafeArea` now wraps `Container` instead of the reverse):

```dart
            ],
          ),
        ),
      ),
    );
  }
}
```

Re-check this against the actual file after editing — the net structural change is: `SafeArea` now wraps `Container` (was: `Container` wraps `SafeArea`), and the innermost `Padding`'s bottom inset changes from `AppSpacing.xxl` to `AppSpacing.xl` (no longer needs extra bottom breathing room now that the card doesn't bleed into the page). Read the file after this edit and confirm brace/paren balance with `flutter analyze` (Step 5) before moving on.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/shared/widgets/hero_header_test.dart`
Expected: PASS (4 tests)

- [ ] **Step 5: Run the full analyzer and test suite (this widget is reused by two already-shipped screens)**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter analyze && flutter test`
Expected: analyze clean, all tests pass — this specifically re-verifies Task 8/9 from the prior plan (Home and Student Dashboard) still render correctly with the restyled `HeroHeader`, without needing to change either screen's own code.

- [ ] **Step 6: Commit**

```bash
git add lib/src/shared/widgets/hero_header.dart test/shared/widgets/hero_header_test.dart
git commit -m "feat(design-system): restyle HeroHeader to a contained card with the hero gradient"
```

---

## Task 5: `RadialProgressRing`

**Files:**
- Create: `lib/src/shared/widgets/radial_progress_ring.dart`
- Test: `test/shared/widgets/radial_progress_ring_test.dart`

**Interfaces:**
- Produces: `RadialProgressRing({value, max, colors, size, strokeWidth, center})` — consumed by Tasks 7 (grade card), 9 (portal-todo), 11 (attendance).

- [ ] **Step 1: Write the failing test**

Create `test/shared/widgets/radial_progress_ring_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/shared/widgets/radial_progress_ring.dart';

void main() {
  testWidgets('renders the given center content', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: RadialProgressRing(
            value: 3,
            max: 4,
            colors: const [Colors.blue, Colors.green],
            center: const Text('75%'),
          ),
        ),
      ),
    );

    expect(find.text('75%'), findsOneWidget);
    expect(find.byType(CustomPaint), findsWidgets);
  });

  testWidgets('sizes itself to the given size', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: RadialProgressRing(
            value: 1,
            max: 2,
            colors: const [Colors.blue, Colors.green],
            size: 80,
          ),
        ),
      ),
    );

    final box = tester.getSize(find.byType(RadialProgressRing));
    expect(box.width, 80);
    expect(box.height, 80);
  });

  testWidgets('renders without error when max is zero', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: RadialProgressRing(
            value: 0,
            max: 0,
            colors: const [Colors.blue, Colors.green],
          ),
        ),
      ),
    );

    expect(find.byType(RadialProgressRing), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/shared/widgets/radial_progress_ring_test.dart`
Expected: FAIL — file does not exist

- [ ] **Step 3: Implement `RadialProgressRing`**

Create `lib/src/shared/widgets/radial_progress_ring.dart`:

```dart
import 'dart:math' as math;
import 'package:flutter/material.dart';

/// A circular progress gauge with a gradient stroke and an optional slot
/// for center content (a value + label). [value]/[max] are raw units, not
/// pre-normalized — callers pass whatever's natural for their stat (GWA
/// points, a completed-count, days present) and this widget computes the
/// fraction, clamped to [0, 1]. A [max] of zero renders an empty ring
/// rather than dividing by zero.
class RadialProgressRing extends StatelessWidget {
  final double value;
  final double max;
  final List<Color> colors;
  final double size;
  final double strokeWidth;
  final Widget? center;

  const RadialProgressRing({
    super.key,
    required this.value,
    required this.max,
    required this.colors,
    this.size = 120,
    this.strokeWidth = 12,
    this.center,
  });

  double get _fraction => max <= 0 ? 0 : (value / max).clamp(0.0, 1.0);

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: Stack(
        alignment: Alignment.center,
        children: [
          CustomPaint(
            size: Size(size, size),
            painter: _RingPainter(
              fraction: _fraction,
              colors: colors,
              strokeWidth: strokeWidth,
            ),
          ),
          if (center != null) center!,
        ],
      ),
    );
  }
}

class _RingPainter extends CustomPainter {
  final double fraction;
  final List<Color> colors;
  final double strokeWidth;

  _RingPainter({
    required this.fraction,
    required this.colors,
    required this.strokeWidth,
  });

  @override
  void paint(Canvas canvas, Size size) {
    final center = size.center(Offset.zero);
    final radius = (size.shortestSide - strokeWidth) / 2;
    final rect = Rect.fromCircle(center: center, radius: radius);

    final track = Paint()
      ..color = const Color(0xFFE2E8F0)
      ..style = PaintingStyle.stroke
      ..strokeWidth = strokeWidth
      ..strokeCap = StrokeCap.round;
    canvas.drawArc(rect, 0, 2 * math.pi, false, track);

    if (fraction <= 0) return;

    final sweep = 2 * math.pi * fraction;
    final gradient = SweepGradient(
      startAngle: -math.pi / 2,
      endAngle: -math.pi / 2 + sweep,
      colors: colors,
    );
    final foreground = Paint()
      ..shader = gradient.createShader(rect)
      ..style = PaintingStyle.stroke
      ..strokeWidth = strokeWidth
      ..strokeCap = StrokeCap.round;

    canvas.drawArc(rect, -math.pi / 2, sweep, false, foreground);
  }

  @override
  bool shouldRepaint(covariant _RingPainter oldDelegate) =>
      oldDelegate.fraction != fraction || oldDelegate.colors != colors;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/shared/widgets/radial_progress_ring_test.dart`
Expected: PASS (3 tests)

- [ ] **Step 5: Commit**

```bash
git add lib/src/shared/widgets/radial_progress_ring.dart test/shared/widgets/radial_progress_ring_test.dart
git commit -m "feat(design-system): add RadialProgressRing"
```

---

## Task 6: `TrendChart`

**Files:**
- Create: `lib/src/shared/widgets/trend_chart.dart`
- Test: `test/shared/widgets/trend_chart_test.dart`

**Interfaces:**
- Consumes: `fl_chart` (Task 2).
- Produces: `TrendChart({values, labels, color, height})` — consumed by Tasks 8 (Grades) and 11 (Attendance).

- [ ] **Step 1: Write the failing test**

Create `test/shared/widgets/trend_chart_test.dart`:

```dart
import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/shared/widgets/trend_chart.dart';

void main() {
  testWidgets('plots one spot per value', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: TrendChart(
            values: const [1.5, 2.0, 1.8, 2.4],
            labels: const ['W1', 'W2', 'W3', 'W4'],
            color: Colors.blue,
          ),
        ),
      ),
    );

    final chart = tester.widget<LineChart>(find.byType(LineChart));
    expect(chart.data.lineBarsData.single.spots.length, 4);
  });

  testWidgets('shows a placeholder instead of an empty chart when there is no data',
      (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: TrendChart(values: [], labels: [], color: Colors.blue),
        ),
      ),
    );

    expect(find.byType(LineChart), findsNothing);
    expect(find.textContaining('No data'), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/shared/widgets/trend_chart_test.dart`
Expected: FAIL — file does not exist

- [ ] **Step 3: Implement `TrendChart`**

Create `lib/src/shared/widgets/trend_chart.dart`:

```dart
import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import '../../core/theme.dart';

/// A smooth, gradient-filled trend line with a tap tooltip, styled through
/// app tokens. [values] and [labels] must be the same length; each value
/// becomes one point, each label its x-axis tick.
class TrendChart extends StatelessWidget {
  final List<double> values;
  final List<String> labels;
  final Color color;
  final double height;

  const TrendChart({
    super.key,
    required this.values,
    required this.labels,
    required this.color,
    this.height = 180,
  });

  @override
  Widget build(BuildContext context) {
    if (values.isEmpty) {
      return SizedBox(
        height: height,
        child: Center(
          child: Text('No data yet',
              style: AppTextStyles.custom(fontSize: 13, color: AppColors.textSecondary)),
        ),
      );
    }

    final spots = [
      for (var i = 0; i < values.length; i++) FlSpot(i.toDouble(), values[i]),
    ];

    return SizedBox(
      height: height,
      child: LineChart(
        LineChartData(
          minY: 0,
          gridData: FlGridData(
            show: true,
            drawVerticalLine: false,
            getDrawingHorizontalLine: (_) =>
                const FlLine(color: AppColors.borderLight, strokeWidth: 1),
          ),
          borderData: FlBorderData(show: false),
          titlesData: FlTitlesData(
            leftTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
            topTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
            rightTitles: const AxisTitles(sideTitles: SideTitles(showTitles: false)),
            bottomTitles: AxisTitles(
              sideTitles: SideTitles(
                showTitles: true,
                getTitlesWidget: (value, meta) {
                  final i = value.toInt();
                  if (i < 0 || i >= labels.length) return const SizedBox.shrink();
                  return Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Text(labels[i],
                        style: AppTextStyles.custom(fontSize: 10, color: AppColors.textSecondary)),
                  );
                },
              ),
            ),
          ),
          lineTouchData: LineTouchData(
            touchTooltipData: LineTouchTooltipData(
              getTooltipItems: (touchedSpots) => touchedSpots
                  .map((s) => LineTooltipItem(
                        s.y.toStringAsFixed(1),
                        AppTextStyles.custom(
                            fontSize: 12, fontWeight: FontWeight.w700, color: Colors.white),
                      ))
                  .toList(),
            ),
          ),
          lineBarsData: [
            LineChartBarData(
              spots: spots,
              isCurved: true,
              color: color,
              barWidth: 3,
              dotData: const FlDotData(show: false),
              belowBarData: BarAreaData(
                show: true,
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [color.withValues(alpha: 0.25), color.withValues(alpha: 0.0)],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
```

If `flutter analyze` reports a constructor-signature mismatch against the actual installed `fl_chart` version (API has shifted slightly across versions for a few of these — e.g., tooltip background color configuration), fix the specific mismatched call using the installed package's actual signature (check `.dart_tool/package_config.json` or the package source under `~/.pub-cache` for the resolved version) — the surrounding structure and token usage stay as written here.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/shared/widgets/trend_chart_test.dart`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add lib/src/shared/widgets/trend_chart.dart test/shared/widgets/trend_chart_test.dart
git commit -m "feat(design-system): add TrendChart via fl_chart"
```

---

## Task 7: GWA ring on Student Dashboard's grade summary card

**Files:**
- Modify: `lib/src/features/student/student_dashboard_screen.dart`
- Modify: `test/features/student/student_dashboard_screen_test.dart`

**Interfaces:**
- Consumes: `RadialProgressRing` (Task 5).

GWA is on a 1.00 (highest) – 5.00 (failing) scale, so "ring fill fraction" must invert it: `fraction = (5.0 - gwa) / (5.0 - 1.0)`, clamped — a GWA of 1.00 fills the ring completely, 5.00 leaves it empty. `RadialProgressRing` already clamps internally, so passing `value: 5.0 - gwa, max: 4.0` achieves this directly without a separate inversion step.

- [ ] **Step 1: Write the failing test**

Add this test to `test/features/student/student_dashboard_screen_test.dart` (append to `main()`, reusing the existing `_FakeAuthNotifier` and override pattern already in that file):

```dart
  testWidgets('shows a RadialProgressRing for GWA instead of the text badge', (tester) async {
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
          studentGradesProvider.overrideWith((ref) async => const GradesData(grades: [
                GradeEntry(subjectName: 'Math', q1: 1.5, q2: 1.5, q3: 1.5, q4: 1.5, finalGe: 1.5, adjectival: 'Outstanding', isPassed: true),
              ])),
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

    expect(find.byType(RadialProgressRing), findsWidgets);
    expect(find.textContaining('GWA'), findsNothing);
  });
```

Add the import at the top of the test file:

```dart
import 'package:atlasgo/src/shared/widgets/radial_progress_ring.dart';
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_dashboard_screen_test.dart`
Expected: FAIL — no `RadialProgressRing` on screen yet, and the old "GWA 1.50" text badge is still present

- [ ] **Step 3: Wire the ring into `_GradeSummaryCard`**

In `lib/src/features/student/student_dashboard_screen.dart`, add the import:

```dart
import '../../shared/widgets/radial_progress_ring.dart';
```

Replace:

```dart
                  const Spacer(),
                  if (gwa != null)
                    Container(
                      padding: const EdgeInsets.symmetric(
                          horizontal: 10, vertical: 3),
                      decoration: BoxDecoration(
                        color: _gwaColor(gwa).withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text('GWA ${gwa.toStringAsFixed(2)}',
                          style: AppTextStyles.custom(fontSize: 12, fontWeight: FontWeight.w700, color: _gwaColor(gwa))),
                    ),
                  const SizedBox(width: 8),
                  const Icon(Icons.chevron_right_rounded,
                      size: 16, color: AppColors.textDisabled),
```

with:

```dart
                  const Spacer(),
                  if (gwa != null)
                    RadialProgressRing(
                      value: 5.0 - gwa,
                      max: 4.0,
                      size: 40,
                      strokeWidth: 5,
                      colors: [_gwaColor(gwa), _gwaColor(gwa).withValues(alpha: 0.5)],
                      center: Text(gwa.toStringAsFixed(2),
                          style: AppTextStyles.custom(
                              fontSize: 10, fontWeight: FontWeight.w800, color: _gwaColor(gwa))),
                    ),
                  const SizedBox(width: 8),
                  const Icon(Icons.chevron_right_rounded,
                      size: 16, color: AppColors.textDisabled),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_dashboard_screen_test.dart`
Expected: PASS (2 tests)

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/student/student_dashboard_screen.dart test/features/student/student_dashboard_screen_test.dart
git commit -m "feat(grades): show GWA as a RadialProgressRing on the dashboard card"
```

---

## Task 8: GWA trend chart on the Grades screen

**Files:**
- Modify: `lib/src/features/student/student_grades_screen.dart`
- Test: `test/features/student/student_grades_screen_test.dart` (new)

**Interfaces:**
- Consumes: `TrendChart` (Task 6), `GradeEntry.gradeForQuarter(int)` (existing).

- [ ] **Step 1: Write the failing test**

Create `test/features/student/student_grades_screen_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/features/grades/grades_provider.dart';
import 'package:atlasgo/src/features/student/student_grades_screen.dart';
import 'package:atlasgo/src/shared/widgets/trend_chart.dart';

void main() {
  testWidgets('shows a quarter-over-quarter GWA trend chart on the overall tab',
      (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentGradesProvider.overrideWith((ref) async => const GradesData(
                schoolYear: '2026-2027',
                grades: [
                  GradeEntry(
                      subjectName: 'Math',
                      q1: 2.0, q2: 1.75, q3: 1.5, q4: 1.25,
                      finalGe: 1.6, adjectival: 'Very Satisfactory', isPassed: true),
                  GradeEntry(
                      subjectName: 'Science',
                      q1: 1.5, q2: 1.5, q3: 1.5, q4: 1.5,
                      finalGe: 1.5, adjectival: 'Outstanding', isPassed: true),
                ],
              )),
        ],
        child: const MaterialApp(home: StudentGradesScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byType(TrendChart), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_grades_screen_test.dart`
Expected: FAIL — no `TrendChart` on screen yet

- [ ] **Step 3: Add the chart to the overall (`quarterIndex == 0`) tab**

In `lib/src/features/student/student_grades_screen.dart`, add the import:

```dart
import '../../shared/widgets/trend_chart.dart';
```

In `_StudentGradesTab.build()`, replace:

```dart
              const SectionLabel('SUBJECTS'),
              ...d.grades.map((g) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: GradeCard(grade: g, quarterIndex: quarterIndex),
                  )),
```

with:

```dart
              if (quarterIndex == 0 && d.grades.isNotEmpty) ...[
                const SectionLabel('GWA TREND'),
                TrendChart(
                  values: [1, 2, 3, 4]
                      .map((q) => _quarterGwa(d.grades, q))
                      .whereType<double>()
                      .toList(),
                  labels: const ['Q1', 'Q2', 'Q3', 'Q4'],
                  color: AppColors.accent,
                ),
                const SizedBox(height: 20),
              ],
              const SectionLabel('SUBJECTS'),
              ...d.grades.map((g) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: GradeCard(grade: g, quarterIndex: quarterIndex),
                  )),
```

This reuses the existing private `_quarterGwa` method already defined on `_StudentGradesTab` (used by the tab's own GWA badge) — no new averaging logic needed. Because `values` and `labels` are built independently, a quarter with no computable GWA (all subjects null for that quarter) is silently dropped from `values` but not from `labels`, which would misalign the x-axis. Guard against this by filtering both together instead:

Replace the block above with the corrected version that keeps the two lists in lockstep:

```dart
              if (quarterIndex == 0 && d.grades.isNotEmpty) ...[
                const SectionLabel('GWA TREND'),
                Builder(builder: (context) {
                  final quarterLabels = ['Q1', 'Q2', 'Q3', 'Q4'];
                  final points = <double>[];
                  final labels = <String>[];
                  for (var q = 1; q <= 4; q++) {
                    final v = _quarterGwa(d.grades, q);
                    if (v != null) {
                      points.add(v);
                      labels.add(quarterLabels[q - 1]);
                    }
                  }
                  return TrendChart(values: points, labels: labels, color: AppColors.accent);
                }),
                const SizedBox(height: 20),
              ],
              const SectionLabel('SUBJECTS'),
              ...d.grades.map((g) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: GradeCard(grade: g, quarterIndex: quarterIndex),
                  )),
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_grades_screen_test.dart`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/student/student_grades_screen.dart test/features/student/student_grades_screen_test.dart
git commit -m "feat(grades): add GWA quarter-trend chart to the Grades screen"
```

---

## Task 9: Completion ring on Portal-todo (forms + clearance)

**Files:**
- Modify: `lib/src/features/student/student_dashboard_screen.dart`
- Modify: `test/features/student/student_dashboard_screen_test.dart`

**Interfaces:**
- Consumes: `RadialProgressRing` (Task 5).

- [ ] **Step 1: Write the failing test**

Add this test to `test/features/student/student_dashboard_screen_test.dart`:

```dart
  testWidgets('shows a completion RadialProgressRing on the annual-forms todo row',
      (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          authStateProvider.overrideWith(() => _FakeAuthNotifier()),
          studentProfileProvider.overrideWith((ref) async => const StudentProfile(
                id: 2, name: 'Juan Dela Cruz', gradeLevel: 8, section: 'Curie', schoolYear: '2026-2027')),
          studentTodayProvider.overrideWith((ref) async =>
              const StudentTodaySummary(lastStatus: 'in', totalScans: 1)),
          studentGradesProvider.overrideWith((ref) async => const GradesData(grades: [])),
          portalDashboardProvider.overrideWith((ref) async => const PortalDashboard(
                gradeLevel: 8,
                completion: [],
                totalDone: 3,
                total: 10,
                clearance: null,
                intern: null,
              )),
        ],
        child: const MaterialApp(home: StudentDashboardScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Annual forms'), findsOneWidget);
    expect(find.byType(RadialProgressRing), findsWidgets);
  });
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_dashboard_screen_test.dart`
Expected: FAIL — `_todoRow` renders no ring today

- [ ] **Step 3: Add a trailing ring to `_todoRow`, used by the forms and clearance rows**

In `lib/src/features/student/student_dashboard_screen.dart`, replace the `_todoRow` method signature and body:

```dart
  Widget _todoRow(
    BuildContext context, {
    required IconData icon,
    required Color iconColor,
    required Color iconBg,
    required String title,
    required String subtitle,
    required String route,
  }) =>
      InkWell(
        onTap: () => context.push(route),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration:
                    BoxDecoration(color: iconBg, shape: BoxShape.circle),
                child: Icon(icon, size: 18, color: iconColor),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title,
                        style: AppTextStyles.custom(fontSize: 13, fontWeight: FontWeight.w700)),
                    Text(subtitle,
                        style: AppTextStyles.custom(fontSize: 11, color: AppColors.textSecondary)),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right_rounded,
                  size: 18, color: AppColors.textDisabled),
            ],
          ),
        ),
      );
```

with:

```dart
  Widget _todoRow(
    BuildContext context, {
    required IconData icon,
    required Color iconColor,
    required Color iconBg,
    required String title,
    required String subtitle,
    required String route,
    int? completionDone,
    int? completionTotal,
  }) =>
      InkWell(
        onTap: () => context.push(route),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
          child: Row(
            children: [
              Container(
                width: 38,
                height: 38,
                decoration:
                    BoxDecoration(color: iconBg, shape: BoxShape.circle),
                child: Icon(icon, size: 18, color: iconColor),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title,
                        style: AppTextStyles.custom(fontSize: 13, fontWeight: FontWeight.w700)),
                    Text(subtitle,
                        style: AppTextStyles.custom(fontSize: 11, color: AppColors.textSecondary)),
                  ],
                ),
              ),
              if (completionTotal != null && completionTotal > 0) ...[
                RadialProgressRing(
                  value: (completionDone ?? 0).toDouble(),
                  max: completionTotal.toDouble(),
                  size: 32,
                  strokeWidth: 4,
                  colors: const [AppColors.accent, AppColors.accentMid],
                ),
                const SizedBox(width: 8),
              ],
              const Icon(Icons.chevron_right_rounded,
                  size: 18, color: AppColors.textDisabled),
            ],
          ),
        ),
      );
```

Then update the two call sites that have a real completion ratio — replace:

```dart
    if (portal.total > 0 && portal.totalDone < portal.total) {
      rows.add(_todoRow(
        context,
        icon: Icons.assignment_rounded,
        iconColor: AppColors.accent,
        iconBg: AppColors.accentBg,
        title: 'Annual forms',
        subtitle:
            '${portal.totalDone} of ${portal.total} sections completed — tap to continue',
        route: '/student/portal/forms',
      ));
    }

    final c = portal.clearance;
    if (c != null && c.status != 'not_generated') {
      rows.add(_todoRow(
        context,
        icon: Icons.verified_rounded,
        iconColor: AppColors.success,
        iconBg: AppColors.successBg,
        title: c.periodTitle ?? 'Year-End Clearance',
        subtitle: c.holds > 0
            ? '${c.pending} pending · ${c.holds} on hold'
            : '${c.done} of ${c.total} requirements cleared',
        route: '/student/portal/clearance',
      ));
    }
```

with:

```dart
    if (portal.total > 0 && portal.totalDone < portal.total) {
      rows.add(_todoRow(
        context,
        icon: Icons.assignment_rounded,
        iconColor: AppColors.accent,
        iconBg: AppColors.accentBg,
        title: 'Annual forms',
        subtitle: 'Tap to continue',
        route: '/student/portal/forms',
        completionDone: portal.totalDone,
        completionTotal: portal.total,
      ));
    }

    final c = portal.clearance;
    if (c != null && c.status != 'not_generated') {
      rows.add(_todoRow(
        context,
        icon: Icons.verified_rounded,
        iconColor: AppColors.success,
        iconBg: AppColors.successBg,
        title: c.periodTitle ?? 'Year-End Clearance',
        subtitle: c.holds > 0
            ? '${c.pending} pending · ${c.holds} on hold'
            : 'Tap to view details',
        route: '/student/portal/clearance',
        completionDone: c.done,
        completionTotal: c.total,
      ));
    }
```

(The dormer row is unchanged — it has no completion ratio, so `completionDone`/`completionTotal` stay `null` and no ring renders for it.)

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_dashboard_screen_test.dart`
Expected: PASS (4 tests)

- [ ] **Step 5: Run the full analyzer and test suite**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter analyze && flutter test`
Expected: analyze clean, all tests pass

- [ ] **Step 6: Commit**

```bash
git add lib/src/features/student/student_dashboard_screen.dart test/features/student/student_dashboard_screen_test.dart
git commit -m "feat(portal): show a completion RadialProgressRing on forms/clearance todo rows"
```

---

## Task 10: `attendance_summary_provider.dart`

**Files:**
- Create: `lib/src/features/student/attendance_summary_provider.dart`
- Test: `test/features/student/attendance_summary_provider_test.dart`

**Interfaces:**
- Consumes: `apiClientProvider` (existing).
- Produces: `AttendanceSummary` model, `WeeklyAttendance` model, `attendanceSummaryProvider` (`FutureProvider.autoDispose<AttendanceSummary>`) — consumed by Task 11.

- [ ] **Step 1: Write the failing test**

Create `test/features/student/attendance_summary_provider_test.dart`:

```dart
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/core/api_client.dart';
import 'package:atlasgo/src/features/student/attendance_summary_provider.dart';

class _FixedAdapter implements HttpClientAdapter {
  final String body;
  _FixedAdapter(this.body);

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream,
      Future<void>? cancelFuture) async {
    return ResponseBody.fromString(body, 200, headers: {
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

  test('parses the summary response into typed models', () async {
    final apiClient = ApiClient();
    apiClient.dio.httpClientAdapter = _FixedAdapter('''
    {
      "month_present": 12,
      "month_school_days": 15,
      "month_rate": 0.8,
      "weekly": [
        {"week_start": "2026-08-03", "present": 5, "school_days": 5, "rate": 1.0},
        {"week_start": "2026-08-10", "present": 4, "school_days": 5, "rate": 0.8}
      ]
    }
    ''');

    final container = ProviderContainer(overrides: [
      apiClientProvider.overrideWithValue(apiClient),
    ]);
    addTearDown(container.dispose);

    final summary = await container.read(attendanceSummaryProvider.future);

    expect(summary.monthPresent, 12);
    expect(summary.monthSchoolDays, 15);
    expect(summary.monthRate, 0.8);
    expect(summary.weekly, hasLength(2));
    expect(summary.weekly.first.weekStart, '2026-08-03');
    expect(summary.weekly.last.rate, 0.8);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/attendance_summary_provider_test.dart`
Expected: FAIL — file does not exist

- [ ] **Step 3: Implement the provider and models**

Create `lib/src/features/student/attendance_summary_provider.dart`:

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api_client.dart';

class WeeklyAttendance {
  final String weekStart;
  final int present;
  final int schoolDays;
  final double? rate;

  const WeeklyAttendance({
    required this.weekStart,
    required this.present,
    required this.schoolDays,
    this.rate,
  });

  factory WeeklyAttendance.fromJson(Map<String, dynamic> json) => WeeklyAttendance(
        weekStart: json['week_start'] as String,
        present: json['present'] as int,
        schoolDays: json['school_days'] as int,
        rate: (json['rate'] as num?)?.toDouble(),
      );
}

class AttendanceSummary {
  final int monthPresent;
  final int monthSchoolDays;
  final double? monthRate;
  final List<WeeklyAttendance> weekly;

  const AttendanceSummary({
    required this.monthPresent,
    required this.monthSchoolDays,
    this.monthRate,
    required this.weekly,
  });

  factory AttendanceSummary.fromJson(Map<String, dynamic> json) => AttendanceSummary(
        monthPresent: json['month_present'] as int,
        monthSchoolDays: json['month_school_days'] as int,
        monthRate: (json['month_rate'] as num?)?.toDouble(),
        weekly: (json['weekly'] as List)
            .map((e) => WeeklyAttendance.fromJson(e as Map<String, dynamic>))
            .toList(),
      );
}

final attendanceSummaryProvider = FutureProvider.autoDispose<AttendanceSummary>((ref) async {
  final response = await ref.read(apiClientProvider).get('/student/attendance/summary');
  return AttendanceSummary.fromJson(response.data as Map<String, dynamic>);
});
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/attendance_summary_provider_test.dart`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/student/attendance_summary_provider.dart test/features/student/attendance_summary_provider_test.dart
git commit -m "feat(attendance): add attendanceSummaryProvider"
```

---

## Task 11: Ring + trend chart on `StudentAttendanceScreen`

**Files:**
- Modify: `lib/src/features/student/student_attendance_screen.dart`
- Test: `test/features/student/student_attendance_screen_test.dart` (new)

**Interfaces:**
- Consumes: `RadialProgressRing` (Task 5), `TrendChart` (Task 6), `attendanceSummaryProvider`/`AttendanceSummary`/`WeeklyAttendance` (Task 10).

- [ ] **Step 1: Write the failing test**

Create `test/features/student/student_attendance_screen_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/features/student/attendance_summary_provider.dart';
import 'package:atlasgo/src/features/student/student_attendance_screen.dart';
import 'package:atlasgo/src/shared/widgets/radial_progress_ring.dart';
import 'package:atlasgo/src/shared/widgets/trend_chart.dart';

void main() {
  testWidgets('shows an attendance-rate ring and an 8-week trend chart', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          attendanceSummaryProvider.overrideWith((ref) async => const AttendanceSummary(
                monthPresent: 12,
                monthSchoolDays: 15,
                monthRate: 0.8,
                weekly: [
                  WeeklyAttendance(weekStart: '2026-08-03', present: 5, schoolDays: 5, rate: 1.0),
                  WeeklyAttendance(weekStart: '2026-08-10', present: 4, schoolDays: 5, rate: 0.8),
                ],
              )),
        ],
        child: const MaterialApp(home: StudentAttendanceScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byType(RadialProgressRing), findsOneWidget);
    expect(find.byType(TrendChart), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_attendance_screen_test.dart`
Expected: FAIL — neither widget is on screen yet

- [ ] **Step 3: Add the summary header section**

In `lib/src/features/student/student_attendance_screen.dart`, add the imports:

```dart
import '../../shared/widgets/radial_progress_ring.dart';
import '../../shared/widgets/trend_chart.dart';
import 'attendance_summary_provider.dart';
```

Change `_StudentAttendanceScreenState.build()` from `ConsumerStatefulWidget`'s existing `build` (it already has `ref` via `ConsumerState`) — after the `final logs = ref.watch(_studentAttendanceProvider(dateStr));` line, add:

```dart
    final summary = ref.watch(attendanceSummaryProvider);
```

Then, immediately after the closing of the header `Container` (right after the `const Divider(height: 1),` line and before `Expanded(`), insert a new summary section:

```dart
          summary.maybeWhen(
            data: (s) => Padding(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 4),
              child: Row(
                children: [
                  RadialProgressRing(
                    value: s.monthPresent.toDouble(),
                    max: s.monthSchoolDays.toDouble(),
                    size: 72,
                    strokeWidth: 8,
                    colors: const [AppColors.success, Color(0xFF6EE7B7)],
                    center: Text(
                      s.monthRate != null ? '${(s.monthRate! * 100).round()}%' : '—',
                      style: AppTextStyles.custom(
                          fontSize: 15, fontWeight: FontWeight.w800, color: AppColors.success),
                    ),
                  ),
                  const SizedBox(width: 16),
                  Expanded(
                    child: TrendChart(
                      values: s.weekly.map((w) => (w.rate ?? 0) * 100).toList(),
                      labels: s.weekly
                          .map((w) => DateFormat('M/d').format(DateTime.parse(w.weekStart)))
                          .toList(),
                      color: AppColors.success,
                      height: 90,
                    ),
                  ),
                ],
              ),
            ),
            orElse: () => const SizedBox.shrink(),
          ),
```

This uses `maybeWhen`/`orElse` (not `when`) so loading/error states simply omit the section rather than blocking the existing per-day timeline (which has its own loading/error handling already) — the summary is supplementary context, not a hard dependency for the rest of the screen.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_attendance_screen_test.dart`
Expected: PASS

- [ ] **Step 5: Run the full analyzer and test suite**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter analyze && flutter test`
Expected: analyze clean, all tests pass

- [ ] **Step 6: Commit**

```bash
git add lib/src/features/student/student_attendance_screen.dart test/features/student/student_attendance_screen_test.dart
git commit -m "feat(attendance): add attendance-rate ring and weekly trend chart"
```

---

## Post-plan verification (Simulator click-through)

Matching the pattern from the prior redesign phase:

1. Confirm the Student Dashboard's grade card shows a ring (not the old GWA text badge) and that the ring's fill visually matches the GWA (near-full for a strong GWA, near-empty for a weak one — remember the scale is inverted, low GWA = high fill).
2. Open the Grades screen's "Overall" tab and confirm the GWA trend chart renders with the right number of quarter points and a working tap tooltip.
3. Confirm the Portal-todo section's "Annual forms" and clearance rows (when present) show a completion ring alongside the existing text.
4. Open the Attendance screen and confirm the new ring + trend chart render above the existing day-picker/timeline, using the dev test student's real scan history.
5. Confirm `HeroHeader` (Home and Student Dashboard) now renders as a margined, fully-rounded card in the navy→mint gradient, not the old full-bleed edge-to-edge one.
