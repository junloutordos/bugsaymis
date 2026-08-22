# AtlasGo Premium Redesign — Phase 2+3 (Combined) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Bring the ~14 AtlasGo screens not yet touched by the premium redesign (Attendance, Grades, Schedule, Student Schedule, Children, Link Child, Profile, Notification Preferences, Student ID, Services, Clearance, Forms Overview, RH Application, Leave Passes, Lost & Found, Profile/Medical Section Forms, Verify Email, Student Link) up to the same visual/motion bar as Home and Student Dashboard, plus fix two Phase-1 loose ends (Register's gradient, Login's missing footer).

**Architecture:** Reuse existing shared primitives (`HeroHeader`, `Pressable`, `StaggeredList`, `AppCard`, `PortalSubScreen`) rather than inventing new ones. Two shared components get small, additive extensions first (`HeroHeader` gains optional `leading`/action params; `PortalSubScreen` gains a restyled header + `floatingActionButton`/`actions` support) because that one change cascades correctly to 7+ screens. Per-screen tasks then apply these primitives to each screen's existing widget tree — this is a visual/motion pass, not a rewrite: providers, routes, and business logic are untouched everywhere.

**Tech Stack:** Flutter (Riverpod, go_router), existing `theme.dart` design tokens (`AppColors`, `AppGradients`, `AppSpacing`, `AppMotion`, `AppElevation`), `fl_chart` (already a dependency, not newly added). No new packages.

**Spec:** `docs/superpowers/specs/2026-08-22-atlasgo-premium-redesign-phase-b2-design.md` (Phase 2 + Phase 3 sections)

## Global Constraints

- No new Flutter dependencies — motion uses native `Animated*`/`AnimationController` via the existing `AppMotion` tokens, same as `Pressable`/`StaggeredList` already do.
- No dark mode work — light theme only, per spec non-goals.
- No backend changes — this phase is Flutter-only, pure presentation layer.
- Distribution (APK/IPA rebuild) is out of scope — changes land on `bugsaymis-mobile` `main` only.
- Verification is `flutter analyze` (must stay clean, zero new warnings) per task, plus one batched iOS Simulator click-through per archetype group at the end (4 checkpoints: subject-dashboards, settings, portal-forms, auth-utility) — not a full widget-test suite, since this is a visual/motion pass with no new business logic. Where a task changes real branching logic (e.g. the Attendance back-button visibility), add a widget test for that specific behavior.
- Every screen keeps `AppColors.background` as its `Scaffold.backgroundColor` — no new page backgrounds invented.
- `AppGradients.authDecoration` is deleted once its last call site is removed (Task 4) — confirmed zero other usages via repo-wide grep before deleting.

---

### Task 1: Extend `HeroHeader` — optional `leading`, optional action button

**Files:**
- Modify: `lib/src/shared/widgets/hero_header.dart`

**Interfaces:**
- Consumes: nothing new.
- Produces: `HeroHeader` constructor gains `leading` (`Widget?`, default `null`) and widens `actionIcon`/`actionTooltip`/`onActionTap` from required to optional (`IconData?`, `String?`, `VoidCallback?`, all default `null`). Existing call sites (`home_screen.dart`, `student_dashboard_screen.dart`) already pass all three non-null, so this is source-compatible — no other file changes in this task.

- [ ] **Step 1: Widen the constructor and only render the action button when present**

Replace the class body in `lib/src/shared/widgets/hero_header.dart`:

```dart
class HeroHeader extends StatelessWidget {
  final Widget? leading;
  final String greeting;
  final String name;
  final String subtitle;
  final IconData? actionIcon;
  final String? actionTooltip;
  final VoidCallback? onActionTap;
  final Widget? trailing;
  final VoidCallback? onTap;

  const HeroHeader({
    super.key,
    this.leading,
    required this.greeting,
    required this.name,
    required this.subtitle,
    this.actionIcon,
    this.actionTooltip,
    this.onActionTap,
    this.trailing,
    this.onTap,
  });

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
              if (leading != null) ...[
                leading!,
                SizedBox(width: AppSpacing.sm),
              ],
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
              if (actionIcon != null && onActionTap != null) ...[
                SizedBox(width: AppSpacing.md),
                _HeroActionButton(
                  icon: actionIcon!,
                  tooltip: actionTooltip ?? '',
                  onTap: onActionTap!,
                ),
              ],
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
}
```

(`_HeroActionButton` at the bottom of the file is unchanged — leave it as-is.)

- [ ] **Step 2: Add a reusable "translucent white on gradient" back button**

Below `_HeroActionButton` in the same file, add a small helper widget every later task will import — a circular translucent-white icon button matching the visual language `_HeroActionButton` already established, sized for use as `HeroHeader.leading`:

```dart
/// Translucent-white circular icon button for use as [HeroHeader.leading]
/// (a back button on the gradient) — visually matches [_HeroActionButton]
/// but is exposed for the leading slot instead of the trailing action slot.
class HeroBackButton extends StatelessWidget {
  final VoidCallback onTap;
  const HeroBackButton({super.key, required this.onTap});

  @override
  Widget build(BuildContext context) => Container(
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.16),
          shape: BoxShape.circle,
        ),
        child: IconButton(
          icon: const Icon(Icons.arrow_back_ios_new_rounded, size: 18),
          tooltip: 'Back',
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
      );
}
```

- [ ] **Step 3: Analyze**

Run: `cd ~/bugsaymis-mobile && flutter analyze lib/src/shared/widgets/hero_header.dart`
Expected: `No issues found!`

- [ ] **Step 4: Commit**

```bash
cd ~/bugsaymis-mobile
git add lib/src/shared/widgets/hero_header.dart
git commit -m "feat(atlasgo): widen HeroHeader with optional leading + action, add HeroBackButton"
```

---

### Task 2: Redesign `PortalSubScreen` — soft-shadow header, FAB + actions support

**Files:**
- Modify: `lib/src/features/portal/portal_widgets.dart`

**Interfaces:**
- Consumes: `AppElevation.resting`, `AppTextStyles`, `AppColors` (all existing, from `theme.dart`).
- Produces: `PortalSubScreen` constructor gains `subtitle` (`String?`, default `null`), `actions` (`List<Widget>`, default `const []`), `floatingActionButton` (`Widget?`, default `null`). `title`/`body`/`bottomBar` keep their existing required/optional shape — every current call site (`ClearanceScreen`, `FormsOverviewScreen`, `RhApplicationScreen`, `ProfileSectionFormScreen`, `MedicalSectionFormScreen`) keeps compiling unchanged.

- [ ] **Step 1: Replace the default `AppBar` with a custom soft-shadow header**

In `lib/src/features/portal/portal_widgets.dart`, replace the `PortalSubScreen` class:

```dart
/// Standard sub-screen scaffold for portal pages: soft-shadow white header
/// with back button + title (matching the app's other pushed-screen
/// headers), slate background body.
class PortalSubScreen extends StatelessWidget {
  final String title;
  final String? subtitle;
  final Widget body;
  final Widget? bottomBar;
  final Widget? floatingActionButton;
  final List<Widget> actions;

  const PortalSubScreen({
    super.key,
    required this.title,
    this.subtitle,
    required this.body,
    this.bottomBar,
    this.floatingActionButton,
    this.actions = const [],
  });

  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: AppColors.background,
        body: Column(
          children: [
            Container(
              decoration: const BoxDecoration(
                color: Colors.white,
                boxShadow: AppElevation.resting,
              ),
              child: SafeArea(
                bottom: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(4, 8, 12, 12),
                  child: Row(
                    children: [
                      IconButton(
                        icon: const Icon(Icons.arrow_back_ios_new_rounded,
                            color: AppColors.textPrimary, size: 20),
                        onPressed: () => Navigator.of(context).maybePop(),
                      ),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(title, style: AppTextStyles.title),
                            if (subtitle != null)
                              Text(subtitle!,
                                  style: AppTextStyles.custom(
                                      fontSize: 12,
                                      color: AppColors.textSecondary)),
                          ],
                        ),
                      ),
                      ...actions,
                    ],
                  ),
                ),
              ),
            ),
            Expanded(child: body),
          ],
        ),
        bottomNavigationBar: bottomBar,
        floatingActionButton: floatingActionButton,
      );
}
```

`Navigator.of(context).maybePop()` matches what the default `AppBar`'s auto back-button already did (pop if possible, no-op otherwise) — every current call site is only ever reached via `context.push(...)`, so behavior is unchanged.

- [ ] **Step 2: Analyze**

Run: `cd ~/bugsaymis-mobile && flutter analyze lib/src/features/portal/`
Expected: `No issues found!` — this touches every portal screen's header implicitly; analyze the whole folder to catch any call-site break.

- [ ] **Step 3: Commit**

```bash
cd ~/bugsaymis-mobile
git add lib/src/features/portal/portal_widgets.dart
git commit -m "feat(atlasgo): redesign PortalSubScreen header, add FAB/actions/subtitle support"
```

---

### Task 3: Migrate Leave Passes + Lost & Found onto `PortalSubScreen`

**Files:**
- Modify: `lib/src/features/portal/leave_passes_screen.dart:1-42`
- Modify: `lib/src/features/portal/lost_found_screen.dart:1-42`

**Interfaces:**
- Consumes: `PortalSubScreen` from Task 2 (`title`, `body`, `floatingActionButton`).
- Produces: nothing new — both screens' public API (constructor, route usage) is unchanged.

- [ ] **Step 1: `leave_passes_screen.dart` — drop the bespoke `Scaffold`+`AppBar`**

Replace lines 27–39 (the `Scaffold(... appBar: AppBar(...), floatingActionButton: ...)` opening) through the `body:` param's outer `Scaffold(` wrapper — i.e. replace this whole block:

```dart
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Leave Passes')),
      floatingActionButton: isDormer
          ? FloatingActionButton.extended(
              backgroundColor: AppColors.accent,
              foregroundColor: Colors.white,
              icon: const Icon(Icons.add_rounded),
              label: Text('File Leave Pass',
                  style: AppTextStyles.custom(fontSize: 13, fontWeight: FontWeight.w600)),
              onPressed: () => _openFileSheet(context, ref),
            )
          : null,
      body: RefreshIndicator(
```

with:

```dart
    return PortalSubScreen(
      title: 'Leave Passes',
      floatingActionButton: isDormer
          ? FloatingActionButton.extended(
              backgroundColor: AppColors.accent,
              foregroundColor: Colors.white,
              icon: const Icon(Icons.add_rounded),
              label: Text('File Leave Pass',
                  style: AppTextStyles.custom(fontSize: 13, fontWeight: FontWeight.w600)),
              onPressed: () => _openFileSheet(context, ref),
            )
          : null,
      body: RefreshIndicator(
```

And change the final closing `);` of `build()` from `Scaffold`'s close to match (the existing trailing `),\n    );` at the end of the `data.when(...)` / `RefreshIndicator` closing already matches — `PortalSubScreen` takes the same trailing-comma shape, no further change needed there).

- [ ] **Step 2: `lost_found_screen.dart` — same migration**

Replace lines 31–41:

```dart
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Lost & Found')),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: AppColors.accent,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add_rounded),
        label: Text('Report Item',
            style: AppTextStyles.custom(fontSize: 13, fontWeight: FontWeight.w600)),
        onPressed: () => _openReportSheet(context),
      ),
      body: RefreshIndicator(
```

with:

```dart
    return PortalSubScreen(
      title: 'Lost & Found',
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: AppColors.accent,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.add_rounded),
        label: Text('Report Item',
            style: AppTextStyles.custom(fontSize: 13, fontWeight: FontWeight.w600)),
        onPressed: () => _openReportSheet(context),
      ),
      body: RefreshIndicator(
```

- [ ] **Step 3: Add the `portal_widgets.dart` import if missing**

Both files already `import 'portal_widgets.dart';` (confirmed in the existing import lists) — no import change needed.

- [ ] **Step 4: Analyze**

Run: `cd ~/bugsaymis-mobile && flutter analyze lib/src/features/portal/leave_passes_screen.dart lib/src/features/portal/lost_found_screen.dart`
Expected: `No issues found!`

- [ ] **Step 5: Commit**

```bash
cd ~/bugsaymis-mobile
git add lib/src/features/portal/leave_passes_screen.dart lib/src/features/portal/lost_found_screen.dart
git commit -m "refactor(atlasgo): migrate Leave Passes + Lost & Found onto PortalSubScreen"
```

---

### Task 4: Auth family — unify gradient, Login footer, Student Link arc redesign

**Files:**
- Modify: `lib/src/features/auth/register_screen.dart:82`
- Modify: `lib/src/features/auth/verify_email_screen.dart:134`
- Modify: `lib/src/features/auth/login_screen.dart:143-170`
- Modify: `lib/src/features/auth/student_link_screen.dart:52-124`
- Modify: `lib/src/core/theme.dart:93-98` (delete `authDecoration` once unused)

**Interfaces:**
- Consumes: `AppGradients.hero`, `kFormShadow`, `AppRadius.sheet` (all existing).
- Produces: nothing new — this task only swaps gradient constants and adds static content.

- [ ] **Step 1: `register_screen.dart` — swap gradient**

Change line 82 from:

```dart
                  gradient: AppGradients.authDecoration,
```

to:

```dart
                  gradient: AppGradients.hero,
```

- [ ] **Step 2: `verify_email_screen.dart` — swap gradient**

Change line 134 from the same `gradient: AppGradients.authDecoration,` to `gradient: AppGradients.hero,`.

- [ ] **Step 3: `login_screen.dart` — add footer**

In `_LoginScreenState.build()`, the scrollable `Column`'s `children` currently ends with the `_Entrance(animation: _cardAnim, ...)` block (around line 163) followed by the closing `],`. Add a footer directly after that entry, before the closing `],`:

```dart
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
                  const SizedBox(height: 24),
                  Text(
                    'AtlasGo is the Mobile app of Philippine Science High '
                    'School – Caraga Region Campus in Butuan City',
                    textAlign: TextAlign.center,
                    style: AppTextStyles.caption,
                  ),
                ],
```

(This replaces the existing bare `],` that closes the `children` list — the new `Text` + `SizedBox` are inserted immediately before it.)

- [ ] **Step 4: `student_link_screen.dart` — convert to the shared auth-arc pattern**

Replace the entire `build()` method (lines 52–124) with the arc-decoration pattern used by the other four auth screens, keeping every existing field/behavior (`_pisayCtrl`, `_loading`, `_submit`):

```dart
  @override
  Widget build(BuildContext context) => Scaffold(
        backgroundColor: AppColors.authBg,
        body: Stack(
          children: [
            Positioned(
              top: 0, left: 0, right: 0,
              child: Container(
                height: 220,
                decoration: const BoxDecoration(
                  gradient: AppGradients.hero,
                  borderRadius: BorderRadius.only(
                    bottomLeft: Radius.circular(40),
                    bottomRight: Radius.circular(40),
                  ),
                ),
              ),
            ),
            SafeArea(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(24, 0, 24, 32),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: IconButton(
                          icon: const Icon(Icons.arrow_back_ios_new_rounded,
                              color: Colors.white, size: 20),
                          style: IconButton.styleFrom(
                            backgroundColor: Colors.white.withValues(alpha: 0.15),
                            shape: const CircleBorder(),
                          ),
                          onPressed: () => context.pop(),
                        ),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Center(
                      child: Column(
                        children: [
                          Container(
                            width: 72,
                            height: 72,
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.15),
                              shape: BoxShape.circle,
                              border: Border.all(color: Colors.white.withValues(alpha: 0.25)),
                            ),
                            child: const Icon(Icons.badge_rounded, color: Colors.white, size: 36),
                          ),
                          const SizedBox(height: 16),
                          Text('One last step',
                              style: AppTextStyles.screenTitle.copyWith(color: Colors.white)),
                        ],
                      ),
                    ),
                    const SizedBox(height: 28),
                    Container(
                      padding: const EdgeInsets.all(24),
                      decoration: const BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.all(Radius.circular(AppRadius.sheet)),
                        boxShadow: kFormShadow,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Link your student record',
                              style: AppTextStyles.sectionHeader),
                          const SizedBox(height: 8),
                          RichText(
                            text: TextSpan(
                              style: AppTextStyles.cardSubtitle.copyWith(height: 1.5),
                              children: [
                                const TextSpan(text: 'Signed in as '),
                                TextSpan(text: widget.email, style: AppTextStyles.fieldLabel),
                                const TextSpan(text: '. Enter your PISAY ID once to link it.'),
                              ],
                            ),
                          ),
                          const SizedBox(height: 20),
                          TextField(
                            controller: _pisayCtrl,
                            textCapitalization: TextCapitalization.characters,
                            style: AppTextStyles.bodyMedium,
                            decoration: const InputDecoration(
                              labelText: 'PISAY ID',
                              hintText: 'e.g. 25-12345',
                            ),
                            onSubmitted: (_) => _submit(),
                          ),
                          const SizedBox(height: 20),
                          SizedBox(
                            width: double.infinity,
                            child: GradientButton(
                              text: 'Link & Continue',
                              isLoading: _loading,
                              onPressed: _loading ? null : _submit,
                            ),
                          ),
                          const SizedBox(height: 12),
                          Center(
                            child: Text(
                              'Only enrolled students can link. Contact the Guidance Office if you need help.',
                              textAlign: TextAlign.center,
                              style: AppTextStyles.caption,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      );
```

Remove the now-unused `import '../portal/portal_widgets.dart';` line only if `showErrorSnack`/`friendlyError` (used in `_submit()`, unchanged) are the only symbols from it — they still are, so **keep** that import; only the `appBar: AppBar(...)` construct is gone, no import changes needed here.

- [ ] **Step 5: Delete the now-dead `authDecoration` gradient**

Confirm zero remaining references:

Run: `cd ~/bugsaymis-mobile && grep -rn "authDecoration" lib/`
Expected: only the definition line in `theme.dart` (no call sites left).

Then remove it from `lib/src/core/theme.dart`, deleting lines 93–98:

```dart
  static const authDecoration = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFF1A3557), Color(0xFF2563EB), Color(0xFF38BDF8)],
    stops: [0.0, 0.6, 1.0],
  );

```

- [ ] **Step 6: Analyze**

Run: `cd ~/bugsaymis-mobile && flutter analyze lib/src/features/auth/ lib/src/core/theme.dart`
Expected: `No issues found!`

- [ ] **Step 7: Commit**

```bash
cd ~/bugsaymis-mobile
git add lib/src/features/auth/register_screen.dart lib/src/features/auth/verify_email_screen.dart \
        lib/src/features/auth/login_screen.dart lib/src/features/auth/student_link_screen.dart \
        lib/src/core/theme.dart
git commit -m "feat(atlasgo): unify auth-family gradient, add Login footer, redesign Student Link"
```

---

### Task 5: Portal action/form screens — `Pressable` + `StaggeredList` on rows

**Files:**
- Modify: `lib/src/features/portal/services_screen.dart`
- Modify: `lib/src/features/portal/clearance_screen.dart`
- Modify: `lib/src/features/portal/forms_overview_screen.dart`
- Modify: `lib/src/features/portal/rh_application_screen.dart`
- Modify: `lib/src/features/portal/leave_passes_screen.dart`
- Modify: `lib/src/features/portal/lost_found_screen.dart`
- Modify: `lib/src/features/portal/profile_section_form_screen.dart`
- Modify: `lib/src/features/portal/medical_section_form_screen.dart`

**Interfaces:**
- Consumes: `Pressable` (`lib/src/shared/widgets/pressable.dart`), `StaggeredList` (`lib/src/shared/widgets/staggered_list.dart`) — both already exist, zero call sites before this task.
- Produces: nothing new.

This task is mechanical and near-identical per file: (a) add the two imports, (b) wrap each `ListView`'s item-generating `children:` list in `StaggeredList(children: [...])` where the items are built via `.map()`/`for`-loop into a fixed list (not `ListView.builder`, which stays as-is — `StaggeredList` is not a lazy list and must not replace a builder over a potentially-long list), (c) wrap each individually-tappable row/tile that isn't already `AppCard(onTap: ...)` or `InkWell` in `Pressable`.

- [ ] **Step 1: `services_screen.dart`**

Add imports after the existing `import '../../core/theme.dart';` line:
```dart
import '../../shared/widgets/pressable.dart';
import '../../shared/widgets/staggered_list.dart';
```

Wrap the `data: (d) => ListView(... children: [ ...` body content in `StaggeredList` — replace the `children: [` opening of that `ListView` (the one starting `const SectionLabel('ANNUAL FORMS'),`) so the list becomes:
```dart
                data: (d) => ListView(
                  padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
                  children: [
                    StaggeredList(children: [
                      const SectionLabel('ANNUAL FORMS'),
                      _FormsCard(dashboard: d),
                      const SizedBox(height: 20),
                      const SectionLabel('RESIDENCE HALL'),
                      _ServiceTile(
                        icon: Icons.night_shelter_rounded,
                        iconColor: const Color(0xFF7C3AED),
                        iconBg: const Color(0xFFF3E8FF),
                        title: 'Accommodation Application',
                        subtitle: _rhSubtitle(d),
                        trailing: d.rhApplication != null
                            ? PortalStatusChip.forStatus(
                                d.isDormer
                                    ? 'active'
                                    : (d.rhApplication!['status'] as String? ??
                                        'pending'))
                            : null,
                        onTap: () => context.push('/student/portal/rh-application'),
                      ),
                      const SizedBox(height: 12),
                      _ServiceTile(
                        icon: Icons.assignment_return_rounded,
                        iconColor: const Color(0xFF0D9488),
                        iconBg: const Color(0xFFCCFBF1),
                        title: 'Leave Passes',
                        subtitle: d.isDormer
                            ? 'File and track your dorm leave passes'
                            : 'Available to active dormers',
                        onTap: () => context.push('/student/portal/leave-passes'),
                      ),
                      const SizedBox(height: 20),
                      const SectionLabel('CAMPUS'),
                      _ServiceTile(
                        icon: Icons.grid_view_rounded,
                        iconColor: AppColors.accentMid,
                        iconBg: AppColors.accentBg,
                        title: 'Class Schedule',
                        subtitle: 'Your weekly timetable',
                        onTap: () => context.push('/student/schedule'),
                      ),
                      const SizedBox(height: 12),
                      _ServiceTile(
                        icon: Icons.travel_explore_rounded,
                        iconColor: const Color(0xFFD97706),
                        iconBg: const Color(0xFFFEF3C7),
                        title: 'Lost & Found',
                        subtitle:
                            'Report items, browse GSU custody, earn honesty points',
                        onTap: () => context.push('/student/portal/lost-found'),
                      ),
                      const SizedBox(height: 20),
                      const SectionLabel('CLEARANCE'),
                      _ServiceTile(
                        icon: Icons.verified_rounded,
                        iconColor: AppColors.success,
                        iconBg: AppColors.successBg,
                        title: 'Year-End Clearance',
                        subtitle: d.clearance == null
                            ? 'No clearance period yet'
                            : d.clearance!.status == 'not_generated'
                                ? '${d.clearance!.periodTitle ?? 'Clearance'} — not yet generated'
                                : '${d.clearance!.done} of ${d.clearance!.total} requirements cleared',
                        trailing: d.clearance != null &&
                                d.clearance!.status != 'not_generated'
                            ? PortalStatusChip.forStatus(d.clearance!.status)
                            : null,
                        onTap: () => context.push('/student/portal/clearance'),
                      ),
                    ]),
                    const SizedBox(height: 20),
                    const SectionLabel('ACCOUNT'),
                    AppCard(
                      padding: EdgeInsets.zero,
                      child: Column(
                        children: [
                          ListTile(
                            leading: const Icon(Icons.person_rounded,
                                color: AppColors.textSecondary, size: 20),
                            title: Text(user?.name ?? 'Student',
                                style: AppTextStyles.custom(fontSize: 14, fontWeight: FontWeight.w600)),
                            subtitle: Text(user?.email ?? '',
                                style: AppTextStyles.custom(fontSize: 12, color: AppColors.textSecondary)),
                          ),
                          const Divider(height: 1),
                          ListTile(
                            leading: const Icon(Icons.logout_rounded,
                                color: Colors.redAccent, size: 20),
                            title: Text('Sign out',
                                style: AppTextStyles.bodySemibold.copyWith(color: Colors.redAccent)),
                            onTap: () async {
                              await ref
                                  .read(authStateProvider.notifier)
                                  .logout();
                              if (context.mounted) context.go('/login');
                            },
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
```

(`_ServiceTile` and `_FormsCard` already wrap their content in `AppCard`/`InkWell`, which already carries `Pressable`-equivalent press-scale via `AppCard`'s own `_pressed` state — no separate `Pressable` wrap needed for those two; the account `ListTile`s stay plain since a bottom-sheet-free tap on a two-row account card doesn't need press-scale.)

- [ ] **Step 2: `clearance_screen.dart`**

Add the two imports. In `_SummaryCard`/`_ItemTile`'s parent `ListView` (the `data: (d) { ... return ListView(padding:..., children: [_SummaryCard(...), const SizedBox(height:20), const SectionLabel('REQUIREMENTS'), AppCard(...) ]); }`), the per-item `_ItemTile` rows inside the requirements `AppCard` stay unwrapped (they're informational, not tappable). Instead, wrap the outer `ListView`'s `children` list in a single `StaggeredList` — `StaggeredList` takes the list of top-level children as its own single `ListView` child (not spread in place), so replace the whole `children: [...]` list with a one-item list containing the `StaggeredList`:

```dart
            return ListView(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
              children: [
                StaggeredList(children: [
                  _SummaryCard(
                      period: period,
                      clearance: clearance,
                      done: done,
                      total: items.length),
                  const SizedBox(height: 20),
                  const SectionLabel('REQUIREMENTS'),
                  AppCard(
                    padding: EdgeInsets.zero,
                    child: Column(
                      children: [
                        for (var i = 0; i < items.length; i++) ...[
                          if (i > 0) const Divider(height: 1, indent: 52),
                          _ItemTile(item: items[i] as Map<String, dynamic>),
                        ],
                      ],
                    ),
                  ),
                ]),
              ],
            );
```

- [ ] **Step 3: `forms_overview_screen.dart`**

Add the two imports. Wrap the outer `ListView`'s `children` (the one holding the intro `Text`, both `SectionLabel`s, and both `AppCard`s) in a single `StaggeredList`, same pattern as Step 2 — replace:

```dart
            return ListView(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
              children: [
                if (d.schoolYear != null)
```

with:

```dart
            return ListView(
              padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
              children: [
                StaggeredList(children: [
                  if (d.schoolYear != null)
```

and change the list's closing `],` (right before the `);` that ends this `ListView(`) to `]),\n              ],` — i.e. the final two lines of that `ListView(...)` become:

```dart
                ]),
              ],
            );
```

- [ ] **Step 4: `rh_application_screen.dart`**

Add the two imports. The `_statusView(...)` helper already returns a single-item `ListView` (no benefit from staggering one card) — leave it. In `_ApplicationForm.build()`, wrap its `ListView`'s `children` the same way: replace the opening `children: [` (right after `padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),`) with `children: [\n          StaggeredList(children: [`, and change the closing `],\n      );` at the very end of that widget to `]),\n        ],\n      );`.

- [ ] **Step 5: `leave_passes_screen.dart`**

Add the two imports. The passes list currently uses `ListView.builder` (lazy, potentially long) — per the rule above, **do not** wrap it in `StaggeredList` (that would defeat lazy building). Instead, give each `_PassCard` a `Pressable` wrap so the whole card has press feedback (it's informational-only today, no `onTap`, but the visual press-scale still reads as "this app pays attention to touch" consistent with every other card in the redesigned screens). Change `_PassCard.build()`:

```dart
  @override
  Widget build(BuildContext context) {
    final purpose = pass['purpose']?.toString() ?? 'other';

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Pressable(
        onTap: () {},
        borderRadius: BorderRadius.circular(AppRadius.card),
        child: AppCard(
          child: Column(
```

and close the added `Pressable(` wrapper by adding one more `)` right before the existing final `);` of the method (after `AppCard`'s closing `),`).

- [ ] **Step 6: `lost_found_screen.dart`**

Add the two imports. Its board/reports lists are built via `.map()` into a fixed `ListView`'s `children` (not `.builder`), so `StaggeredList` applies cleanly. Wrap just the two mapped lists (not the whole screen, since the points banner/tab bar shouldn't stagger every rebuild when switching tabs) — change:

```dart
                if (_tab == 0) ...[
                  if (board.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(top: 40),
                      child: EmptyState(
                        icon: Icons.inventory_2_rounded,
                        headline: 'No items in GSU custody',
                        subtext:
                            'Found items turned over to the GSU office will appear here so owners can claim them.',
                      ),
                    )
                  else
                    ...board.map((item) => _BoardCard(
                        item: item as Map<String, dynamic>)),
                ] else ...[
                  if (myReports.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(top: 40),
                      child: EmptyState(
                        icon: Icons.assignment_rounded,
                        headline: 'No reports yet',
                        subtext:
                            'Lost something? Found something? File a report with the button below.',
                      ),
                    )
                  else
                    ...myReports.map((item) => _ReportCard(
                        item: item as Map<String, dynamic>)),
                ],
```

to:

```dart
                if (_tab == 0) ...[
                  if (board.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(top: 40),
                      child: EmptyState(
                        icon: Icons.inventory_2_rounded,
                        headline: 'No items in GSU custody',
                        subtext:
                            'Found items turned over to the GSU office will appear here so owners can claim them.',
                      ),
                    )
                  else
                    StaggeredList(children: [
                      for (final item in board)
                        _BoardCard(item: item as Map<String, dynamic>),
                    ]),
                ] else ...[
                  if (myReports.isEmpty)
                    const Padding(
                      padding: EdgeInsets.only(top: 40),
                      child: EmptyState(
                        icon: Icons.assignment_rounded,
                        headline: 'No reports yet',
                        subtext:
                            'Lost something? Found something? File a report with the button below.',
                      ),
                    )
                  else
                    StaggeredList(children: [
                      for (final item in myReports)
                        _ReportCard(item: item as Map<String, dynamic>),
                    ]),
                ],
```

- [ ] **Step 7: `profile_section_form_screen.dart` + `medical_section_form_screen.dart`**

Add the two imports to both files. These are single-form screens (no repeated list of tappable rows at the top level — `RepeaterCard` entries inside are already individually removable via their own `IconButton`, and stagger-animating a growing/shrinking repeater list on every add/remove would fight the user's own edit — **do not** wrap the repeater rows). Skip `StaggeredList` for both files; the header restyle from Task 2 already covers their visual upgrade. No body changes needed in this task for these two files — remove them from the "Files" list above if a reviewer prefers a smaller diff; they're listed here only because the original screen inventory named them, and this step documents that decision (no-op) rather than silently skipping.

- [ ] **Step 8: Analyze**

Run: `cd ~/bugsaymis-mobile && flutter analyze lib/src/features/portal/`
Expected: `No issues found!`

- [ ] **Step 9: Commit**

```bash
cd ~/bugsaymis-mobile
git add lib/src/features/portal/services_screen.dart lib/src/features/portal/clearance_screen.dart \
        lib/src/features/portal/forms_overview_screen.dart lib/src/features/portal/rh_application_screen.dart \
        lib/src/features/portal/leave_passes_screen.dart lib/src/features/portal/lost_found_screen.dart
git commit -m "feat(atlasgo): apply StaggeredList/Pressable motion to portal action screens"
```

---

### Task 6: Attendance (parent) — `HeroHeader` + `Pressable` timeline

**Files:**
- Modify: `lib/src/features/attendance/attendance_screen.dart`

**Interfaces:**
- Consumes: `HeroHeader`, `HeroBackButton` (Task 1), `Pressable`.
- Produces: nothing new.

- [ ] **Step 1: Add imports**

```dart
import '../../shared/widgets/hero_header.dart';
import '../../shared/widgets/pressable.dart';
```

- [ ] **Step 2: Replace the title row with `HeroHeader`, keep the week-strip as its own white section**

Replace the outer `Container(color: Colors.white, child: SafeArea(bottom: false, child: Column(crossAxisAlignment: ..., children: [ /* title row */ Padding(...), /* week strip */ SizedBox(...) ])))` block (lines 59–170) with:

```dart
          HeroHeader(
            leading: context.canPop() ? HeroBackButton(onTap: () => context.pop()) : null,
            greeting: 'Attendance',
            name: widget.studentName ?? 'History',
            subtitle: DateFormat('EEEE, MMMM d').format(_selectedDate),
            actionIcon: Icons.calendar_today_outlined,
            actionTooltip: 'Pick date',
            onActionTap: () async {
              final picked = await showDatePicker(
                context: context,
                initialDate: _selectedDate,
                firstDate: DateTime(2024),
                lastDate: DateTime.now(),
              );
              if (picked != null) {
                setState(() => _selectedDate = picked);
              }
            },
          ),
          Container(
            color: Colors.white,
            child: SizedBox(
              height: 76,
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
                itemCount: _weekDays.length,
                itemBuilder: (_, i) {
                  final day = _weekDays[i];
                  final isSelected = DateFormat('yyyy-MM-dd').format(day) ==
                      DateFormat('yyyy-MM-dd').format(_selectedDate);
                  final isToday = DateFormat('yyyy-MM-dd').format(day) ==
                      DateFormat('yyyy-MM-dd').format(DateTime.now());

                  return GestureDetector(
                    onTap: () => setState(() => _selectedDate = day),
                    child: AnimatedContainer(
                      duration: const Duration(milliseconds: 180),
                      width: 48,
                      margin: const EdgeInsets.only(right: 8),
                      decoration: BoxDecoration(
                        color: isSelected
                            ? AppColors.accent
                            : AppColors.neutralBg,
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            DateFormat('E').format(day)[0],
                            style: AppTextStyles.custom(fontSize: 11, fontWeight: FontWeight.w500, color: isSelected
                                    ? Colors.white70
                                    : AppColors.textSecondary),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            DateFormat('d').format(day),
                            style: AppTextStyles.custom(fontSize: 15, fontWeight: FontWeight.w800, color: isSelected
                                    ? Colors.white
                                    : AppColors.textPrimary),
                          ),
                          if (isToday)
                            Container(
                              margin: const EdgeInsets.only(top: 3),
                              width: 4,
                              height: 4,
                              decoration: BoxDecoration(
                                color: isSelected
                                    ? Colors.white54
                                    : AppColors.accent,
                                shape: BoxShape.circle,
                              ),
                            ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
          ),
```

(The week-strip content is byte-for-byte the same as before — only its outer `SafeArea` was dropped, since `HeroHeader` above it already consumes the top safe area, and its title row was deleted since `HeroHeader` now carries the title.)

- [ ] **Step 3: Wrap each `TimelineItem`'s content card in `Pressable`**

In `TimelineItem.build()`, the "Content card" `Expanded(child: Padding(..., child: Container(...)))` becomes tappable-feeling even though it has no `onTap` today — skip wrapping it (informational log row, matches Task 5 Step 5's reasoning for `_PassCard` only where wrap-with-no-op was chosen for visual consistency; here, leave `TimelineItem` unwrapped since gate-scan log rows are not analogous to a filed request the user might revisit). No change needed to `TimelineItem`.

- [ ] **Step 4: Analyze**

Run: `cd ~/bugsaymis-mobile && flutter analyze lib/src/features/attendance/attendance_screen.dart`
Expected: `No issues found!`

- [ ] **Step 5: Commit**

```bash
cd ~/bugsaymis-mobile
git add lib/src/features/attendance/attendance_screen.dart
git commit -m "feat(atlasgo): HeroHeader on parent Attendance screen"
```

---

### Task 7: Grades (parent) — `HeroHeader` header

**Files:**
- Modify: `lib/src/features/grades/grades_screen.dart`

**Interfaces:**
- Consumes: `HeroHeader` (Task 1).
- Produces: nothing new.

- [ ] **Step 1: Add import**

```dart
import '../../shared/widgets/hero_header.dart';
```

- [ ] **Step 2: Replace the title `Padding` with `HeroHeader`, keep chips/tabs as their own white section**

Replace the outer header block (lines 44–88, the `Container(color: white, child: SafeArea(..., child: Column(..., children: [ Padding(...'Grades'...), _ChildChips/SizedBox, TabBar, Divider ])))`) with:

```dart
          const HeroHeader(
            greeting: 'Track progress',
            name: 'Grades',
            subtitle: 'Quarterly and final marks',
          ),
          Container(
            color: Colors.white,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                students.maybeWhen(
                  data: (list) => list.length > 1
                      ? _ChildChips(
                          students: list,
                          selected: _selectedStudentIndex,
                          onSelect: (i) =>
                              setState(() => _selectedStudentIndex = i),
                        )
                      : const SizedBox(height: 12),
                  orElse: () => const SizedBox(height: 12),
                ),
                TabBar(
                  controller: _tabCtrl,
                  isScrollable: false,
                  labelColor: AppColors.accent,
                  unselectedLabelColor: AppColors.textSecondary,
                  indicatorColor: AppColors.accent,
                  indicatorSize: TabBarIndicatorSize.label,
                  labelStyle: AppTextStyles.custom(fontSize: 13, fontWeight: FontWeight.w700),
                  unselectedLabelStyle: AppTextStyles.custom(fontSize: 13, fontWeight: FontWeight.w500),
                  tabs: _quarters.map((q) => Tab(text: q)).toList(),
                ),
                const Divider(height: 1),
              ],
            ),
          ),
```

- [ ] **Step 3: Analyze**

Run: `cd ~/bugsaymis-mobile && flutter analyze lib/src/features/grades/grades_screen.dart`
Expected: `No issues found!`

- [ ] **Step 4: Commit**

```bash
cd ~/bugsaymis-mobile
git add lib/src/features/grades/grades_screen.dart
git commit -m "feat(atlasgo): HeroHeader on parent Grades screen"
```

---

### Task 8: Schedule (parent) + Student Schedule — `HeroHeader` header

**Files:**
- Modify: `lib/src/features/schedule/schedule_screen.dart`
- Modify: `lib/src/features/student/student_schedule_screen.dart`

**Interfaces:**
- Consumes: `HeroHeader`, `HeroBackButton` (Task 1).
- Produces: nothing new.

- [ ] **Step 1: `schedule_screen.dart` — add import, replace header**

Add `import '../../shared/widgets/hero_header.dart';`.

Replace the header block (lines 57–109, same shape as Task 7's Grades block) with:

```dart
          const HeroHeader(
            greeting: 'This week',
            name: 'Class Schedule',
            subtitle: 'Weekly timetable',
          ),
          Container(
            color: Colors.white,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                students.maybeWhen(
                  data: (list) => list.length > 1
                      ? _ChildChips(
                          students: list,
                          selected: _selectedStudentIndex,
                          onSelect: (i) =>
                              setState(() => _selectedStudentIndex = i),
                        )
                      : const SizedBox(height: 12),
                  orElse: () => const SizedBox(height: 12),
                ),
                TabBar(
                  controller: _tabCtrl,
                  isScrollable: false,
                  labelColor: AppColors.accent,
                  unselectedLabelColor: AppColors.textSecondary,
                  indicatorColor: AppColors.accent,
                  indicatorSize: TabBarIndicatorSize.label,
                  labelStyle: AppTextStyles.custom(fontSize: 13, fontWeight: FontWeight.w700),
                  unselectedLabelStyle: AppTextStyles.custom(fontSize: 13, fontWeight: FontWeight.w500),
                  tabs: List.generate(
                    _days.length,
                    (i) => Tab(
                      child: _DayTab(
                        label: _dayLabels[i],
                        isToday: i == _todayIndex(),
                      ),
                    ),
                  ),
                ),
                const Divider(height: 1),
              ],
            ),
          ),
```

- [ ] **Step 2: `student_schedule_screen.dart` — add import, replace header**

Add `import '../../shared/widgets/hero_header.dart';`.

Replace the header block (lines 48–112) with:

```dart
          HeroHeader(
            leading: HeroBackButton(
              onTap: () =>
                  context.canPop() ? context.pop() : context.go('/student/home'),
            ),
            greeting: 'This week',
            name: 'My Schedule',
            subtitle: 'Weekly timetable',
          ),
          Container(
            color: Colors.white,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                TabBar(
                  controller: _tabCtrl,
                  isScrollable: false,
                  labelColor: AppColors.accent,
                  unselectedLabelColor: AppColors.textSecondary,
                  indicatorColor: AppColors.accent,
                  indicatorSize: TabBarIndicatorSize.label,
                  labelStyle: AppTextStyles.custom(fontSize: 13, fontWeight: FontWeight.w700),
                  unselectedLabelStyle: AppTextStyles.custom(fontSize: 13, fontWeight: FontWeight.w500),
                  tabs: List.generate(
                    _days.length,
                    (i) => Tab(
                      child: Padding(
                        padding: const EdgeInsets.symmetric(vertical: 8),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(_dayLabels[i]),
                            if (i == todayIdx)
                              Container(
                                margin: const EdgeInsets.only(top: 3),
                                width: 4,
                                height: 4,
                                decoration: const BoxDecoration(
                                  color: AppColors.accent,
                                  shape: BoxShape.circle,
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
                const Divider(height: 1),
              ],
            ),
          ),
```

- [ ] **Step 3: Analyze**

Run: `cd ~/bugsaymis-mobile && flutter analyze lib/src/features/schedule/schedule_screen.dart lib/src/features/student/student_schedule_screen.dart`
Expected: `No issues found!`

- [ ] **Step 4: Commit**

```bash
cd ~/bugsaymis-mobile
git add lib/src/features/schedule/schedule_screen.dart lib/src/features/student/student_schedule_screen.dart
git commit -m "feat(atlasgo): HeroHeader on Schedule + Student Schedule screens"
```

---

### Task 9: Children + Link Child — `HeroHeader`, `Pressable` cards, gradient avatars

**Files:**
- Modify: `lib/src/features/children/children_screen.dart`
- Modify: `lib/src/features/children/link_child_screen.dart`

**Interfaces:**
- Consumes: `HeroHeader`, `HeroBackButton` (Task 1), `Pressable`, `StaggeredList`, `FeatureIconChip`.
- Produces: nothing new.

- [ ] **Step 1: `children_screen.dart` — swap `AppHeader` for `HeroHeader` with a linked-count subtitle**

Add imports:
```dart
import '../../shared/widgets/hero_header.dart';
import '../../shared/widgets/pressable.dart';
import '../../shared/widgets/staggered_list.dart';
```

Replace the `AppHeader(...)` call (lines 19–50) with (`ChildrenScreen` is already a `ConsumerWidget` receiving `ref` directly in `build(context, ref)`, so the header can read `linkedStudentsProvider` straight from the outer `ref` — no extra `Consumer` wrapper needed):

```dart
          HeroHeader(
            leading: HeroBackButton(
              onTap: () => context.canPop() ? context.pop() : context.go('/home'),
            ),
            greeting: 'Manage',
            name: 'My Children',
            subtitle: () {
              final count = ref.watch(linkedStudentsProvider).value?.length ?? 0;
              return count == 0
                  ? 'No children linked yet'
                  : '$count linked ${count == 1 ? 'child' : 'children'}';
            }(),
            actionIcon: Icons.add_rounded,
            actionTooltip: 'Link a child',
            onActionTap: () async {
              await context.push('/children/link');
              ref.invalidate(linkedStudentsProvider);
            },
          ),
```

- [ ] **Step 2: Give `_ChildCard`'s avatar a gradient chip and wrap the card in `Pressable`**

`_ChildCard` today is an `AnimatedContainer` with no `onTap` (unlink is its own `IconButton`). Replace its avatar `Container` (the 48×48 gradient circle with the initial letter) with `FeatureIconChip`-style treatment — since `FeatureIconChip` takes an `IconData`, not initials text, keep the existing initials-in-a-gradient-circle as-is (it already IS the bold/vibrant treatment the spec calls for) — no change needed there. Instead, wrap the whole card in `Pressable` so the row reads as touchable even though only the unlink icon acts:

In `_ChildrenBody`'s student list, change:

```dart
                  ...list.map((s) => Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _ChildCard(
                            student: s, onUnlink: () => onUnlink(s)),
                      )),
```

to (wrap the mapped list in `StaggeredList`, keep each card as-is — no per-card `Pressable`, since the card itself has an internal actionable icon and stacking `Pressable`'s ripple under `AnimatedContainer` would double up visual feedback):

```dart
                  StaggeredList(children: [
                    for (final s in list)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: _ChildCard(
                            student: s, onUnlink: () => onUnlink(s)),
                      ),
                  ]),
```

- [ ] **Step 3: `link_child_screen.dart` — wrap relationship chips in `Pressable`**

Add `import '../../shared/widgets/pressable.dart';`.

In `_Chip.build()`, wrap the `GestureDetector` in `Pressable` isn't quite right since `_Chip` already handles its own tap — instead, replace the plain `GestureDetector` with `Pressable` directly (it supersedes `GestureDetector` + adds the press-scale):

```dart
  @override
  Widget build(BuildContext context) {
    final isSelected = selected == value;
    return Pressable(
      onTap: () => onTap(value),
      borderRadius: BorderRadius.circular(10),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
        decoration: BoxDecoration(
          color: isSelected ? AppColors.accent : AppColors.neutralBg,
          borderRadius: BorderRadius.circular(10),
          border: Border.all(
              color: isSelected ? AppColors.accent : AppColors.border),
        ),
        child: Text(label,
            style: AppTextStyles.custom(fontSize: 13, fontWeight: FontWeight.w500, color: isSelected
                    ? Colors.white
                    : AppColors.textSecondary)),
      ),
    );
  }
```

- [ ] **Step 4: Analyze**

Run: `cd ~/bugsaymis-mobile && flutter analyze lib/src/features/children/`
Expected: `No issues found!`

- [ ] **Step 5: Commit**

```bash
cd ~/bugsaymis-mobile
git add lib/src/features/children/children_screen.dart lib/src/features/children/link_child_screen.dart
git commit -m "feat(atlasgo): HeroHeader on Children, Pressable chips on Link Child"
```

---

### Task 10: Profile — `HeroHeader`, remove redundant avatar card, `Pressable` menu

**Files:**
- Modify: `lib/src/features/profile/profile_screen.dart`

**Interfaces:**
- Consumes: `HeroHeader`, `HeroBackButton` (Task 1), `Pressable`, `StaggeredList`.
- Produces: nothing new.

- [ ] **Step 1: Add imports**

```dart
import '../../shared/widgets/hero_header.dart';
import '../../shared/widgets/pressable.dart';
import '../../shared/widgets/staggered_list.dart';
```

- [ ] **Step 2: Replace the header + drop the now-redundant avatar/name card**

Replace the outer header block (lines 19–49) with:

```dart
          HeroHeader(
            leading: HeroBackButton(
              onTap: () => context.canPop() ? context.pop() : context.go('/home'),
            ),
            greeting: 'My Profile',
            name: user?.name ?? '—',
            subtitle: user?.email ?? '—',
            trailing: Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.16),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Text(
                user?.isStudent == true ? 'Student' : 'Parent',
                style: AppTextStyles.custom(fontSize: 11, fontWeight: FontWeight.w600, color: Colors.white),
              ),
            ),
          ),
```

Then, in the body's `SingleChildScrollView`, **delete** the entire redundant "Avatar + name card" block (the first child of the body `Column`, lines 58–123 — the `Container` with the 60×60 gradient circle, name, email, role pill) and the `SizedBox(height: 24)` immediately after it. The body now starts directly with `const SectionLabel('ACCOUNT')`.

- [ ] **Step 3: Wrap `_MenuItem`s in `StaggeredList`**

The body's `Column` currently lists `_MenuItem` widgets inline (not via `.map()`). Group each section's `_MenuItem`s into a `StaggeredList` — e.g. for the student branch:

```dart
                  if (user?.isStudent == true) ...[
                    StaggeredList(children: [
                      _MenuItem(
                        icon: Icons.badge_outlined,
                        label: 'Digital Student ID',
                        onTap: () => context.push('/student/id'),
                      ),
                      _MenuItem(
                        icon: Icons.edit_note_rounded,
                        label: 'Update My Information',
                        onTap: () => context.push('/student/profile-update'),
                      ),
                    ]),
                  ] else ...[
                    StaggeredList(children: [
                      _MenuItem(
                        icon: Icons.people_alt_outlined,
                        label: 'Manage Children',
                        onTap: () => context.push('/children'),
                      ),
                    ]),
                  ],
```

`_MenuItem` already wraps itself in `Material`+`InkWell` with its own tap target — no separate `Pressable` needed (would double the ripple), matching the reasoning already applied to `AppCard`-based tiles elsewhere in this plan.

- [ ] **Step 4: Analyze**

Run: `cd ~/bugsaymis-mobile && flutter analyze lib/src/features/profile/profile_screen.dart`
Expected: `No issues found!`

- [ ] **Step 5: Commit**

```bash
cd ~/bugsaymis-mobile
git add lib/src/features/profile/profile_screen.dart
git commit -m "feat(atlasgo): HeroHeader on Profile, drop redundant avatar card"
```

---

### Task 11: Notification Preferences — back button, `Pressable`/`StaggeredList`

**Files:**
- Modify: `lib/src/features/notifications/notification_preferences_screen.dart`

**Interfaces:**
- Consumes: `AppHeader` (existing, unchanged shape — just now passing `leading`), `StaggeredList`.
- Produces: nothing new. (No `HeroHeader` here — settings-tier per spec, light touch only.)

- [ ] **Step 1: Add import and `go_router`**

This file currently has no `go_router` import (no back-button today). Add:
```dart
import 'package:go_router/go_router.dart';
import '../../shared/widgets/staggered_list.dart';
```

- [ ] **Step 2: Give the existing `AppHeader` call a back button**

Change:
```dart
          AppHeader(
            greeting: 'Configure',
            name: 'Notifications',
            subtitle: 'Control how you receive alerts',
          ),
```
to (the outer `build(BuildContext context)` already has `context` with router access — no extra wrapper needed):

```dart
          AppHeader(
            greeting: 'Configure',
            name: 'Notifications',
            subtitle: 'Control how you receive alerts',
            leading: IconButton(
              icon: const Icon(Icons.arrow_back_ios_new_rounded,
                  color: AppColors.textPrimary, size: 20),
              onPressed: () =>
                  context.canPop() ? context.pop() : context.go('/profile'),
            ),
          ),
```

- [ ] **Step 3: Wrap the two `_PrefTile`s in `StaggeredList`**

Change:
```dart
                    _PrefTile(
                      icon: Icons.notifications_active_outlined,
                      title: 'Push Notifications',
                      subtitle: 'Instant alert on this device',
                      value: _notifyPush ?? prefs.notifyPush,
                      onChanged: (v) => setState(() => _notifyPush = v),
                    ),
                    const SizedBox(height: 10),
                    _PrefTile(
                      icon: Icons.email_outlined,
                      title: 'Email Notifications',
                      subtitle: 'Summary sent to your email',
                      value: _notifyEmail ?? prefs.notifyEmail,
                      onChanged: (v) => setState(() => _notifyEmail = v),
                    ),
```
to:
```dart
                    StaggeredList(children: [
                      _PrefTile(
                        icon: Icons.notifications_active_outlined,
                        title: 'Push Notifications',
                        subtitle: 'Instant alert on this device',
                        value: _notifyPush ?? prefs.notifyPush,
                        onChanged: (v) => setState(() => _notifyPush = v),
                      ),
                      const SizedBox(height: 10),
                      _PrefTile(
                        icon: Icons.email_outlined,
                        title: 'Email Notifications',
                        subtitle: 'Summary sent to your email',
                        value: _notifyEmail ?? prefs.notifyEmail,
                        onChanged: (v) => setState(() => _notifyEmail = v),
                      ),
                    ]),
```

- [ ] **Step 4: Analyze**

Run: `cd ~/bugsaymis-mobile && flutter analyze lib/src/features/notifications/notification_preferences_screen.dart`
Expected: `No issues found!`

- [ ] **Step 5: Commit**

```bash
cd ~/bugsaymis-mobile
git add lib/src/features/notifications/notification_preferences_screen.dart
git commit -m "fix(atlasgo): add back button to Notification Preferences, add stagger motion"
```

---

### Task 12: Student ID — `Pressable` close button (light touch)

**Files:**
- Modify: `lib/src/features/student/student_id_screen.dart`

**Interfaces:**
- Consumes: `Pressable` (Task 1's sibling primitive, already existing).
- Produces: nothing new.

This screen already has route-level motion (`appPageTransition`, fullscreen dialog) and an already-polished dark/navy ID-card design — per the spec's settings-tier ("light touch... no invented visualization"), the only change is press feedback on the close button.

- [ ] **Step 1: Add import**

```dart
import '../../shared/widgets/pressable.dart';
```

- [ ] **Step 2: Wrap the close button**

Change:
```dart
            Align(
              alignment: Alignment.centerRight,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(8, 8, 12, 0),
                child: IconButton(
                  icon: const Icon(Icons.close_rounded,
                      color: Colors.white, size: 26),
                  tooltip: 'Close',
                  onPressed: () => context.pop(),
                ),
              ),
            ),
```
to:
```dart
            Align(
              alignment: Alignment.centerRight,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(8, 8, 12, 0),
                child: Pressable(
                  onTap: () => context.pop(),
                  borderRadius: BorderRadius.circular(24),
                  child: const Padding(
                    padding: EdgeInsets.all(8),
                    child: Icon(Icons.close_rounded,
                        color: Colors.white, size: 26),
                  ),
                ),
              ),
            ),
```

(Dropping `IconButton` for `Pressable` loses the built-in tooltip — acceptable here since it's a single obvious close affordance in the corner of a full-screen modal, matching how `_Chip` in Task 9 made the same trade.)

- [ ] **Step 3: Analyze**

Run: `cd ~/bugsaymis-mobile && flutter analyze lib/src/features/student/student_id_screen.dart`
Expected: `No issues found!`

- [ ] **Step 4: Commit**

```bash
cd ~/bugsaymis-mobile
git add lib/src/features/student/student_id_screen.dart
git commit -m "feat(atlasgo): Pressable close button on Student ID screen"
```

---

## Verification (after all 12 tasks)

- [ ] **Full-repo analyze**

Run: `cd ~/bugsaymis-mobile && flutter analyze`
Expected: `No issues found!` (zero regressions across the whole app, not just touched files).

- [ ] **iOS Simulator click-through — 4 batched checkpoints**

Per the established verification pattern for this codebase (real Simulator click-through catches bugs no widget test does — see `project_atlasgo_premium_redesign.md`), boot the existing `AtlasGoTest` simulator (or create one per `project_atlasgo_mobile.md`'s documented steps) and walk each archetype group once, logged in as the existing dev test fixtures (parent: `claude-parent-test@crc.pshs.edu.ph`, student: `claude-emu-test@crc.pshs.edu.ph`, both `claude-test-1234`):

1. **Subject-dashboards**: parent Attendance (back button + date picker still work, hero renders), parent Grades (quarter tabs still switch), Schedule + Student Schedule (day tabs still work), Children (link/unlink still work, count updates), Profile (menu items still navigate, no duplicate name/avatar).
2. **Settings**: Notification Preferences (back button now present, toggle+save still works), Student ID (close button still closes, brightness boost still applies).
3. **Portal-forms**: Services, Clearance, Forms Overview, RH Application, Leave Passes (FAB still files a pass), Lost & Found (FAB still opens report sheet, tab switch still works).
4. **Auth-utility**: Login (footer renders, doesn't clip on small screens), Register (gradient matches Login's), Verify Email (gradient matches), Student Link (new arc layout reachable via a fresh Google sign-in with an unlinked test account, or by temporarily forcing the route in dev).

- [ ] **Update memory**

After Simulator verification passes, update `project_atlasgo_premium_redesign.md` (or a new dated memory) with: what shipped, any bugs found+fixed during click-through (matching the standing pattern — two real bugs were found this way in every prior phase of this rollout), and that Phase 2+3 is now complete alongside Phase 1, closing out the `docs/superpowers/specs/2026-08-22-atlasgo-premium-redesign-phase-b2-design.md` spec.
