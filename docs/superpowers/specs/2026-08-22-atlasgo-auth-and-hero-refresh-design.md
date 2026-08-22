# AtlasGo Auth Redesign & Hero Consolidation

**Date:** 2026-08-22
**Status:** Approved (design), pending implementation plan

## Problem

Three issues surfaced after shipping the reference-driven visual refresh, all traced back to the same root cause: the hero gradient never actually matched the reference image, and that mismatch cascaded into other visual/UX decisions:

1. **`AppGradients.hero` doesn't match the reference.** It was defined as navy (`#1A3557`) → emerald (`#34D399`) — a dark, green-dominant gradient. The actual reference image (a full-quality copy the user provided, `~/Downloads/inspo.jpg`) shows a medium-saturated **blue**-dominant gradient fading to a **soft pastel green**, with blue remaining the app's primary/interactive color throughout (buttons, active nav state) and green used only as a secondary accent.
2. **Student Dashboard has two stacked gradient "hero" cards** — `HeroHeader` (greeting) and the separate navy `_ProfileCard` (identity: name, grade/section, S.Y., "Student" badge) directly beneath it. This reads as visually redundant and was never intended as two separate identity moments.
3. **Login conflates two different sign-in paths in one screen**, causing real user confusion: the Google sign-in button (for students) and the email/password form (for parents) are both visible at once with only a thin divider ("parents sign in with email") distinguishing them.

## Non-goals

- **Not touching the Register screen, `/verify-email`, or `/student/link` routes.** They stay exactly as they are, reached the same way they are today (from the parent form step).
- **Not adding new routes.** The Scholar/Parent choice is implemented as an internal two-step wizard on the existing `/login` route, the same pattern `sos_trigger_sheet.dart` already uses for its own phases (`enum _Phase`) — not a router-level change.
- **Not changing the Google sign-in flow itself.** Tapping "I'm a Scholar" calls the exact same `_googleSignIn()` method the current Google button already calls — only *when* it's reachable changes, not how it behaves.
- **Not changing Home (parent)'s structure beyond the gradient token fix.** Home has no second identity card to merge — parents don't have a "grade/section" identity block — so this spec's hero-merge (point 2 above) is Student Dashboard-only.
- **Not touching SOS's radar-pulse red/white palette, or the Grades/Attendance ring and chart colors** (green for attendance/success, semantic GWA thresholds) — those are deliberate semantic choices unrelated to the brand hero gradient.
- **Not a full visual audit of every remaining screen.** This spec is scoped to the hero gradient token, Student Dashboard's card structure, and the Login screen specifically.

## Scope

1. **Fix `AppGradients.hero`** to a genuinely blue-to-green pastel pair.
2. **Merge Student Dashboard's two hero cards** into one.
3. **Add a Scholar/Parent chooser step** to `LoginScreen`, before either sign-in path is shown.
4. **Restyle `LoginScreen`'s banner** to reuse `AppGradients.hero` instead of the old `AppGradients.authDecoration`.

## Key architectural decisions

### 1. `AppGradients.hero` color values

Replace:
```dart
static const hero = LinearGradient(
  begin: Alignment.topLeft, end: Alignment.bottomRight,
  colors: [Color(0xFF1A3557), Color(0xFF34D399)],
);
```
with:
```dart
static const hero = LinearGradient(
  begin: Alignment.topLeft, end: Alignment.bottomRight,
  colors: [Color(0xFF4F86E8), Color(0xFF8FE3A9)],
);
```
`#4F86E8` is a medium-saturated blue (dark enough to keep the existing white greeting/name text legible — this is *not* an ultra-pale pastel, which would fail contrast with white text); `#8FE3A9` is a soft pastel green matching the reference's ending tone. Because `HeroHeader` already reads this token (not a hardcoded color), Home and Student Dashboard both pick up the fix automatically — no changes needed in either screen for this part.

### 2. Student Dashboard hero merge

`_ProfileCard` (the separate navy `LinearGradient(colors: [Color(0xFF1A3557), Color(0xFF2563EB)])` card showing avatar-initial, name, "Student" badge, grade/section, S.Y., tappable to `/profile`) is deleted. Its useful content (grade/section + S.Y., since the name is already shown large in `HeroHeader`'s own greeting) moves into `HeroHeader`'s `trailing` slot, alongside the existing attendance `StatusBadge` — both rendered in the same trailing area, stacked vertically. `HeroHeader` gains a new optional `onTap` parameter (the whole card becomes tappable to `/profile` on Student Dashboard, matching `_ProfileCard`'s original whole-row tap behavior); Home (parent) doesn't pass this parameter and its `HeroHeader` stays non-tappable, matching current behavior since parents have no analogous "student record" to view.

### 3. Login: Scholar/Parent chooser

`LoginScreen` gains an internal `enum _LoginStep { choose, parentForm }` state (defaulting to `choose`), following the existing `_Phase`-style wizard pattern already used in `sos_trigger_sheet.dart`. No new routes, no changes to `router.dart`.

- **`choose` step**: the brand block (logo, "AtlasGo", tagline — unchanged) followed by two large tappable role cards:
  - **"I'm a Scholar"** — subtitle "Sign in with your school Google account", tapping it calls the existing `_googleSignIn()` directly (identical behavior to today's Google button; no intermediate screen since Google's own OAuth sheet *is* the form).
  - **"I'm a Parent"** — subtitle "Sign in with email and password", tapping it sets `_step = _LoginStep.parentForm`.
- **`parentForm` step**: today's form card, minus the Google button and the "parents sign in with email" divider (both now redundant — reaching this step already means the user self-identified as a parent). A back arrow at the top of the card returns to `choose`. The email/password fields, validation, "Sign In" button, and "Parent? Create account" link are all unchanged from today's implementation.

This directly resolves the confusion: a scholar never sees an email/password form to be tempted by, and a parent never sees (and can't accidentally tap) the Google button.

### 4. Login visual restyle

The top arc `Container` currently uses `AppGradients.authDecoration` with `BorderRadius.only(bottomLeft/bottomRight)` (full-bleed, only bottom corners rounded, matching the *old* pre-refresh `HeroHeader` style). Replace it with `AppGradients.hero` for brand consistency with Home/Dashboard. The banner keeps its own existing shape (full-bleed arc, not a margined card like `HeroHeader`) since Login's brand block (logo + wordmark, centered, no per-user greeting) is a different compositional need than a dashboard hero — reusing the *gradient token* for consistency, not the `HeroHeader` *widget* itself, which is the correct level of reuse here (`HeroHeader`'s greeting/name/trailing-stat structure doesn't apply to a pre-login screen with no user yet).

## Data model

None — this is a Flutter-only, presentation-layer change. No backend routes, no new dependencies.

## UI/UX

- **Home & Student Dashboard**: gradient shifts from navy-to-emerald to blue-to-pastel-green. Student Dashboard's hero card additionally gains a grade/section + S.Y. line in its trailing area and becomes tappable to `/profile`; its separate navy identity card is gone.
- **Login**: opens on a Scholar/Parent chooser instead of a combined form; the parent path is a single back-and-forth step away; the banner uses the new blue-green gradient instead of the old navy-blue-skyblue one.

## Testing

- **Flutter (widget tests)**: `theme_palette_test.dart` updated to assert the new `AppGradients.hero` color values. `student_dashboard_screen_test.dart` updated to assert `_ProfileCard`'s old content (grade/section, S.Y.) now renders inside the `HeroHeader` area and that no separate navy identity card exists; a tap-through-to-`/profile` test on the hero card itself. `login_screen_test.dart` (new — no test file exists for this screen today) covering: chooser step renders both role cards and no form fields; tapping "I'm a Parent" reveals the email/password form (and hides the Google button); tapping "I'm a Scholar" invokes the Google sign-in path; the back arrow from the parent form returns to the chooser.

## Rollout

Single implementation plan — all four points are small, tightly-related changes to two existing files (`theme.dart`, `student_dashboard_screen.dart`) plus one screen redesign (`login_screen.dart`), no backend, no new dependencies. Simulator click-through afterward, matching the established verification pattern for this redesign effort.
