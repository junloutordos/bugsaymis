# AtlasGo Auth Redesign & Hero Consolidation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix `AppGradients.hero` to genuinely match the reference (blue-to-pastel-green), merge Student Dashboard's two stacked hero cards into one, and redesign Login with a Scholar/Parent chooser step plus the corrected hero gradient banner.

**Architecture:** Four small, tightly-scoped changes to three existing Flutter files, in dependency order: the color token first, then `HeroHeader`'s new `onTap` capability, then Student Dashboard's merge (consumes both), then Login's redesign (consumes the corrected token independently).

**Tech Stack:** Flutter 3 / Riverpod 2.6 / go_router 14.

**Spec:** `docs/superpowers/specs/2026-08-22-atlasgo-auth-and-hero-refresh-design.md`

## Global Constraints

- No new routes, no `router.dart` changes — the Scholar/Parent choice is internal wizard state on the existing `/login` route.
- No change to `_googleSignIn()`'s implementation or the Google sign-in flow itself — only what triggers it.
- Home (parent)'s `HeroHeader` usage is untouched except inheriting the corrected gradient automatically (it reads the same token, no code change needed there).
- No backend changes, no new dependencies.

---

## Task 1: Fix `AppGradients.hero` color values

**Files:**
- Modify: `lib/src/core/theme.dart`
- Modify: `test/core/theme_palette_test.dart`

**Interfaces:**
- Produces: `AppGradients.hero` with corrected colors — consumed automatically by `HeroHeader` (Home, Student Dashboard) and by Task 4 (Login banner). No signature change, just new color values.

- [ ] **Step 1: Update the failing test's expectations**

In `test/core/theme_palette_test.dart`, replace:

```dart
  test('hero gradient goes from brand navy to emerald', () {
    expect(AppGradients.hero.colors.first, const Color(0xFF1A3557));
    expect(AppGradients.hero.colors.last, const Color(0xFF34D399));
  });
```

with:

```dart
  test('hero gradient goes from pastel blue to pastel green', () {
    expect(AppGradients.hero.colors.first, const Color(0xFF4F86E8));
    expect(AppGradients.hero.colors.last, const Color(0xFF8FE3A9));
  });
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/core/theme_palette_test.dart`
Expected: FAIL — `AppGradients.hero` still holds the old navy/emerald values

- [ ] **Step 3: Update the token**

In `lib/src/core/theme.dart`, replace:

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

with:

```dart
  /// Contained-hero gradient — used by HeroHeader and the Login banner.
  /// Medium-saturated blue to soft pastel green, matching the reference
  /// design exactly (blue-dominant, green as the secondary accent). The
  /// blue stop is deliberately not ultra-pale — it needs to stay dark
  /// enough for the white greeting/name text drawn on top of it.
  static const hero = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF4F86E8), Color(0xFF8FE3A9)],
  );
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/core/theme_palette_test.dart`
Expected: PASS

- [ ] **Step 5: Run the full analyzer and test suite (this token is consumed by two already-shipped screens)**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter analyze && flutter test`
Expected: analyze clean, all tests pass — `hero_header_test.dart`'s gradient-equality assertion (`expect(decoration.gradient, AppGradients.hero)`) compares against the token itself, not a hardcoded color, so it stays green.

- [ ] **Step 6: Commit**

```bash
git add lib/src/core/theme.dart test/core/theme_palette_test.dart
git commit -m "fix(design-system): correct AppGradients.hero to match the reference (blue-to-pastel-green)"
```

---

## Task 2: `HeroHeader` gains an optional `onTap`

**Files:**
- Modify: `lib/src/shared/widgets/hero_header.dart`
- Modify: `test/shared/widgets/hero_header_test.dart`

**Interfaces:**
- Produces: `HeroHeader({..., onTap})` (new optional param, default `null` = not tappable, matching today's behavior exactly for existing callers) — consumed by Task 3 (Student Dashboard).

- [ ] **Step 1: Write the failing test**

Add this test to `test/shared/widgets/hero_header_test.dart` (append to `main()`):

```dart
  testWidgets('invokes onTap when the card body is tapped, when provided', (tester) async {
    var tapped = false;
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
            onTap: () => tapped = true,
          ),
        ),
      ),
    );

    await tester.tap(find.text('Maria'));
    await tester.pump();
    expect(tapped, isTrue);
  });

  testWidgets('is not tappable when onTap is not provided', (tester) async {
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

    // No onTap given — there should be no InkWell wrapping the card body
    // (the action button's own internal InkWell from IconButton is fine).
    expect(find.byType(InkWell), findsNothing);
  });
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/shared/widgets/hero_header_test.dart`
Expected: FAIL — `onTap` parameter doesn't exist yet

- [ ] **Step 3: Add `onTap` support**

In `lib/src/shared/widgets/hero_header.dart`, add the field and constructor parameter:

```dart
  final VoidCallback? onTap;
```

(add after `final Widget? trailing;`), and add `this.onTap,` to the constructor's parameter list (after `this.trailing,`).

Then replace the `build()` method:

```dart
  @override
  Widget build(BuildContext context) {
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
```

with:

```dart
  @override
  Widget build(BuildContext context) {
    final content = Padding(
      padding: EdgeInsets.fromLTRB(
          AppSpacing.xl, AppSpacing.lg, AppSpacing.xl, AppSpacing.xl),
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
    );

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
        child: onTap == null
            ? content
            : Material(
                color: Colors.transparent,
                borderRadius: BorderRadius.circular(AppRadius.card),
                child: InkWell(
                  onTap: onTap,
                  borderRadius: BorderRadius.circular(AppRadius.card),
                  child: content,
                ),
              ),
      ),
    );
  }
```

The `_HeroActionButton`'s own `IconButton` still wins tap priority over this outer `InkWell` via Flutter's normal gesture-arena resolution (innermost tappable widget claims the tap) — tapping the sign-out/profile action button will never trigger the card's `onTap`.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/shared/widgets/hero_header_test.dart`
Expected: PASS (6 tests)

- [ ] **Step 5: Run the full analyzer and test suite**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter analyze && flutter test`
Expected: analyze clean, all tests pass (Home and Student Dashboard don't pass `onTap` yet, so they stay non-tappable — no regression)

- [ ] **Step 6: Commit**

```bash
git add lib/src/shared/widgets/hero_header.dart test/shared/widgets/hero_header_test.dart
git commit -m "feat(design-system): add optional onTap to HeroHeader"
```

---

## Task 3: Merge Student Dashboard's two hero cards

**Files:**
- Modify: `lib/src/features/student/student_dashboard_screen.dart`
- Modify: `test/features/student/student_dashboard_screen_test.dart`

**Interfaces:**
- Consumes: `HeroHeader`'s new `onTap` (Task 2).
- Produces: nothing new consumed elsewhere — `_ProfileCard` is deleted.

- [ ] **Step 1: Write the failing test**

Add this test to `test/features/student/student_dashboard_screen_test.dart` (append to `main()`):

```dart
  testWidgets('shows grade/section and school year inside the hero, with no separate identity card',
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

    // Exactly one HeroHeader (already asserted elsewhere), and the
    // grade/section + school year now live inside it instead of a
    // separate card.
    expect(find.textContaining('Grade 8'), findsOneWidget);
    expect(find.textContaining('Curie'), findsOneWidget);
    expect(find.textContaining('2026-2027'), findsOneWidget);
    // "Student" role badge and the separate navy identity card are gone —
    // there is now only one gradient-decorated Container using the hero
    // gradient (the HeroHeader itself).
    expect(find.text('Student'), findsNothing);
  });

  testWidgets('tapping the hero navigates to profile', (tester) async {
    final router = GoRouter(routes: [
      GoRoute(path: '/', builder: (c, s) => const StudentDashboardScreen()),
      GoRoute(path: '/profile', builder: (c, s) => const Scaffold(body: Text('Profile Page'))),
    ]);

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
                totalDone: 0,
                total: 0,
                clearance: null,
                intern: null,
              )),
        ],
        child: MaterialApp.router(routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Juan'));
    await tester.pumpAndSettle();

    expect(find.text('Profile Page'), findsOneWidget);
  });
```

The second test taps `find.text('Juan')` rather than `'Juan Dela Cruz'` — the dashboard's `firstName` (used in `HeroHeader.name`) is `user.name.split(' ').first`, i.e. just `'Juan'`, from the `_FakeAuthNotifier`'s `AuthUser(name: 'Juan Dela Cruz', ...)`.

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_dashboard_screen_test.dart`
Expected: FAIL — grade/section/S.Y. text isn't in the hero yet (it's in the separate `_ProfileCard`, further down the scroll, and the "Student" badge still exists); tapping the hero does nothing yet

- [ ] **Step 3: Merge the content**

In `lib/src/features/student/student_dashboard_screen.dart`, replace the `HeroHeader` call and the `Padding`/`Column`'s first child (the profile-card block):

```dart
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
                  // ── Profile card ────────────────────────────────────
                  profile.when(
                    loading: () => const ShimmerCard(height: 100),
                    error: (_, _) => const SizedBox.shrink(),
                    data: (p) => _ProfileCard(
                      profile: p,
                      onTap: () => context.push('/profile'),
                    ),
                  ),

                  const SizedBox(height: 20),
                  const SectionLabel("TODAY'S GATE STATUS"),
```

with:

```dart
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
              onTap: () => context.push('/profile'),
              trailing: _dashboardHeroTrailing(today, profile),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
              child: Column(
                children: [
                  const SectionLabel("TODAY'S GATE STATUS"),
```

- [ ] **Step 4: Add the `_dashboardHeroTrailing` helper and delete `_ProfileCard`**

In the same file, delete the entire `_ProfileCard` class (it's no longer referenced by anything). Add this top-level private function in its place:

```dart
/// Composes the HeroHeader's trailing content on Student Dashboard: the
/// attendance status badge (if loaded) plus a compact grade/section + S.Y.
/// line (if the profile is loaded) — replaces the old standalone
/// _ProfileCard, whose name/avatar were redundant with the greeting
/// HeroHeader already shows large above this.
Widget? _dashboardHeroTrailing(
  AsyncValue<StudentTodaySummary> today,
  AsyncValue<StudentProfile> profile,
) {
  final statusBadge = today.maybeWhen(
    data: (t) => StatusBadge(status: t.lastStatus),
    orElse: () => null,
  );

  final identityLine = profile.maybeWhen(
    data: (p) {
      final parts = <String>[
        if (p.gradeLevel != null && p.section != null) 'Grade ${p.gradeLevel} — ${p.section}',
        if (p.schoolYear != null) 'S.Y. ${p.schoolYear}',
      ];
      return parts.isEmpty ? null : parts.join(' · ');
    },
    orElse: () => null,
  );

  if (statusBadge == null && identityLine == null) return null;

  return Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      if (statusBadge != null) statusBadge,
      if (identityLine != null) ...[
        if (statusBadge != null) const SizedBox(height: 8),
        Text(identityLine,
            style: AppTextStyles.custom(
                fontSize: 12, fontWeight: FontWeight.w500, color: Colors.white70)),
      ],
    ],
  );
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_dashboard_screen_test.dart`
Expected: PASS (6 tests)

- [ ] **Step 6: Run the full analyzer and test suite**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter analyze && flutter test`
Expected: analyze clean (confirms `_ProfileCard` had no other references), all tests pass

- [ ] **Step 7: Commit**

```bash
git add lib/src/features/student/student_dashboard_screen.dart test/features/student/student_dashboard_screen_test.dart
git commit -m "feat(student-dashboard): merge the separate identity card into the HeroHeader"
```

---

## Task 4: Login redesign — Scholar/Parent chooser + hero gradient banner

**Files:**
- Modify: `lib/src/features/auth/login_screen.dart`
- Create: `test/features/auth/login_screen_test.dart`

**Interfaces:**
- Consumes: `AppGradients.hero` (Task 1, already corrected), existing `_googleSignIn()`/`_submit()` (unchanged), `AppMotion` (existing token).
- Produces: no new public interface — `LoginScreen` itself is still constructed the same way (`const LoginScreen()`), only its internal build changes.

- [ ] **Step 1: Write the failing tests**

Create `test/features/auth/login_screen_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/features/auth/login_screen.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  const secureStorageChannel = MethodChannel('plugins.it_nomads.com/flutter_secure_storage');
  TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
      .setMockMethodCallHandler(secureStorageChannel, (call) async => null);

  testWidgets('opens on the role chooser with no form fields visible', (tester) async {
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: LoginScreen())),
    );
    await tester.pumpAndSettle();

    expect(find.text("I'm a Scholar"), findsOneWidget);
    expect(find.text("I'm a Parent"), findsOneWidget);
    expect(find.byType(TextFormField), findsNothing);
  });

  testWidgets('tapping "I\'m a Parent" reveals the email/password form', (tester) async {
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: LoginScreen())),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text("I'm a Parent"));
    await tester.pumpAndSettle();

    expect(find.byType(TextFormField), findsNWidgets(2));
    expect(find.text("I'm a Scholar"), findsNothing);
  });

  testWidgets('the back arrow from the parent form returns to the chooser', (tester) async {
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: LoginScreen())),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text("I'm a Parent"));
    await tester.pumpAndSettle();
    expect(find.byType(TextFormField), findsNWidgets(2));

    await tester.tap(find.byIcon(Icons.arrow_back_ios_new_rounded));
    await tester.pumpAndSettle();

    expect(find.text("I'm a Scholar"), findsOneWidget);
    expect(find.byType(TextFormField), findsNothing);
  });

  testWidgets('tapping "I\'m a Scholar" invokes the Google sign-in path', (tester) async {
    await tester.pumpWidget(
      const ProviderScope(child: MaterialApp(home: LoginScreen())),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text("I'm a Scholar"));
    await tester.pumpAndSettle();

    // No real Google Sign-In platform channel exists in a widget test, so
    // the call throws and _googleSignIn's own catch block surfaces a
    // SnackBar — that SnackBar appearing is proof the Google path was
    // actually invoked (not just that the button exists).
    expect(find.byType(SnackBar), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/auth/login_screen_test.dart`
Expected: FAIL — the current screen shows the combined form immediately, no role chooser exists

- [ ] **Step 3: Implement the chooser + trimmed form + banner fix**

In `lib/src/features/auth/login_screen.dart`, add the step enum and state field. Replace:

```dart
class _LoginScreenState extends ConsumerState<LoginScreen>
    with SingleTickerProviderStateMixin {
  final _formKey   = GlobalKey<FormState>();
  final _emailCtrl = TextEditingController();
  final _passCtrl  = TextEditingController();
  bool _obscure = true;
  bool _googleLoading = false;
```

with:

```dart
enum _LoginStep { choose, parentForm }

class _LoginScreenState extends ConsumerState<LoginScreen>
    with SingleTickerProviderStateMixin {
  final _formKey   = GlobalKey<FormState>();
  final _emailCtrl = TextEditingController();
  final _passCtrl  = TextEditingController();
  bool _obscure = true;
  bool _googleLoading = false;
  _LoginStep _step = _LoginStep.choose;
```

Replace the top arc decoration's gradient — change:

```dart
              decoration: const BoxDecoration(
                gradient: AppGradients.authDecoration,
                borderRadius: BorderRadius.only(
                  bottomLeft: Radius.circular(40),
                  bottomRight: Radius.circular(40),
                ),
              ),
```

with:

```dart
              decoration: const BoxDecoration(
                gradient: AppGradients.hero,
                borderRadius: BorderRadius.only(
                  bottomLeft: Radius.circular(40),
                  bottomRight: Radius.circular(40),
                ),
              ),
```

Replace the body's second `_Entrance` (the form card) — change:

```dart
                  const SizedBox(height: 36),
                  _Entrance(animation: _cardAnim, child: _formCard(busy, isLoading)),
```

with:

```dart
                  const SizedBox(height: 36),
                  _Entrance(
                    animation: _cardAnim,
                    child: AnimatedSwitcher(
                      duration: AppMotion.base,
                      switchInCurve: AppMotion.standard,
                      switchOutCurve: AppMotion.standard,
                      child: _step == _LoginStep.choose
                          ? _roleChooser(key: const ValueKey('choose'))
                          : _parentFormCard(busy, isLoading, key: const ValueKey('form')),
                    ),
                  ),
```

Replace the entire `_formCard` method (from `Widget _formCard(bool busy, bool isLoading) => Container(` through its closing `);` before `class _Entrance`) with three new members: `_roleChooser`, `_RoleCard` usage, and `_parentFormCard`:

```dart
  Widget _roleChooser({Key? key}) => Container(
        key: key,
        padding: const EdgeInsets.all(20),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.all(Radius.circular(AppRadius.sheet)),
          boxShadow: kFormShadow,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text('Welcome back',
                style: AppTextStyles.screenTitle.copyWith(fontSize: 20)),
            const SizedBox(height: 4),
            Text("Who's signing in?", style: AppTextStyles.cardSubtitle),
            const SizedBox(height: 20),
            if (_googleAvailable) ...[
              _RoleCard(
                icon: Icons.school_rounded,
                title: "I'm a Scholar",
                subtitle: 'Sign in with your school Google account',
                busy: _googleLoading,
                onTap: _googleLoading ? null : _googleSignIn,
              ),
              const SizedBox(height: 12),
            ],
            _RoleCard(
              icon: Icons.family_restroom_rounded,
              title: "I'm a Parent",
              subtitle: 'Sign in with email and password',
              onTap: () => setState(() => _step = _LoginStep.parentForm),
            ),
          ],
        ),
      );

  Widget _parentFormCard(bool busy, bool isLoading, {Key? key}) => Container(
        key: key,
        padding: const EdgeInsets.all(24),
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.all(Radius.circular(AppRadius.sheet)),
          boxShadow: kFormShadow,
        ),
        child: Form(
          key: _formKey,
          child: AutofillGroup(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    IconButton(
                      icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
                      onPressed: () => setState(() => _step = _LoginStep.choose),
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(),
                      color: AppColors.textSecondary,
                    ),
                    const SizedBox(width: 8),
                    Text('Parent Sign In',
                        style: AppTextStyles.screenTitle.copyWith(fontSize: 20)),
                  ],
                ),
                Padding(
                  padding: const EdgeInsets.only(left: 26, top: 4),
                  child: Text('Sign in to your account', style: AppTextStyles.cardSubtitle),
                ),

                const SizedBox(height: 24),

                // ── Email ─────────────────────────────────────────────
                LabelledField(
                  label: 'Email address',
                  child: TextFormField(
                    controller: _emailCtrl,
                    enabled: !busy,
                    keyboardType: TextInputType.emailAddress,
                    textInputAction: TextInputAction.next,
                    autofillHints: const [
                      AutofillHints.username,
                      AutofillHints.email,
                    ],
                    style: AppTextStyles.bodyMedium,
                    decoration: const InputDecoration(
                      hintText: 'you@email.com',
                      prefixIcon: Icon(Icons.email_outlined,
                          color: AppColors.textSecondary, size: 18),
                    ),
                    validator: (v) => v == null || !v.contains('@')
                        ? 'Enter a valid email'
                        : null,
                  ),
                ),

                const SizedBox(height: 16),

                // ── Password ──────────────────────────────────────────
                LabelledField(
                  label: 'Password',
                  child: TextFormField(
                    controller: _passCtrl,
                    enabled: !busy,
                    obscureText: _obscure,
                    textInputAction: TextInputAction.done,
                    autofillHints: const [AutofillHints.password],
                    style: AppTextStyles.bodyMedium,
                    decoration: InputDecoration(
                      hintText: '••••••••',
                      prefixIcon: const Icon(Icons.lock_outline_rounded,
                          color: AppColors.textSecondary, size: 18),
                      suffixIcon: IconButton(
                        icon: Icon(
                          _obscure
                              ? Icons.visibility_off_outlined
                              : Icons.visibility_outlined,
                          color: AppColors.textSecondary,
                          size: 20,
                        ),
                        onPressed: () =>
                            setState(() => _obscure = !_obscure),
                      ),
                    ),
                    validator: (v) =>
                        v == null || v.isEmpty ? 'Enter your password' : null,
                    onFieldSubmitted: (_) => _submit(),
                  ),
                ),

                const SizedBox(height: 28),

                // ── Sign In button ────────────────────────────────────
                SizedBox(
                  width: double.infinity,
                  child: GradientButton(
                    text: 'Sign In',
                    isLoading: isLoading,
                    onPressed: busy ? null : _submit,
                  ),
                ),

                const SizedBox(height: 20),

                // ── Register links ────────────────────────────────────
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text('Parent? ', style: AppTextStyles.cardSubtitle),
                    GestureDetector(
                      onTap: () => context.push('/register'),
                      child: Text('Create account',
                          style: AppTextStyles.bodySemibold.copyWith(
                              color: AppColors.accent, fontSize: 13)),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      );
```

Finally, add the `_RoleCard` widget class right before `_Entrance` at the bottom of the file:

```dart
class _RoleCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback? onTap;
  final bool busy;

  const _RoleCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.busy = false,
  });

  @override
  Widget build(BuildContext context) => Material(
        color: AppColors.background,
        borderRadius: BorderRadius.circular(AppRadius.card),
        child: InkWell(
          borderRadius: BorderRadius.circular(AppRadius.card),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: const BoxDecoration(
                      color: AppColors.accentBg, shape: BoxShape.circle),
                  child: busy
                      ? const Padding(
                          padding: EdgeInsets.all(12),
                          child: CircularProgressIndicator(strokeWidth: 2))
                      : Icon(icon, color: AppColors.accent, size: 22),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(title, style: AppTextStyles.cardTitle),
                      const SizedBox(height: 2),
                      Text(subtitle, style: AppTextStyles.cardSubtitle),
                    ],
                  ),
                ),
                const Icon(Icons.chevron_right_rounded, color: AppColors.textDisabled),
              ],
            ),
          ),
        ),
      );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/auth/login_screen_test.dart`
Expected: PASS (4 tests)

- [ ] **Step 5: Run the full analyzer and test suite**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter analyze && flutter test`
Expected: analyze clean, all tests pass

- [ ] **Step 6: Commit**

```bash
git add lib/src/features/auth/login_screen.dart test/features/auth/login_screen_test.dart
git commit -m "feat(auth): add Scholar/Parent chooser step to Login, restyle banner with the hero gradient"
```

---

## Post-plan verification (Simulator click-through)

Matching the pattern from the prior two redesign phases:

1. Confirm Home and Student Dashboard's hero cards now show the corrected blue-to-pastel-green gradient (not the old navy-to-emerald one).
2. Confirm Student Dashboard shows exactly one gradient hero card (no separate navy identity card below it), with grade/section + S.Y. visible inside the hero's trailing area alongside the attendance status badge.
3. Tap the Student Dashboard hero card (not the sign-out button) and confirm it navigates to `/profile`.
4. Open Login fresh (logged out) — confirm it opens on the Scholar/Parent chooser, not a combined form.
5. Tap "I'm a Parent" — confirm the email/password form appears with a working back arrow, and confirm "I'm a Scholar" is genuinely gone from view (not just visually hidden).
6. Tap "I'm a Scholar" — confirm it opens the real Google sign-in flow (same behavior as the old Google button did).
7. Confirm Login's top banner now uses the corrected blue-to-pastel-green gradient.
