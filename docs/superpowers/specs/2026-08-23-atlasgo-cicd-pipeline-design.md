# AtlasGo CI/CD Pipeline — Build, Sign, Test, Publish (Phase 1)

**Date:** 2026-08-23
**Status:** Approved (design), pending implementation plan

## Problem

AtlasGo (`~/bugsaymis-mobile`) has no CI/CD at all. The repo has never had a git remote — it's local-only. There is no `.github/`, no `fastlane/`, no test automation on push, and no build/sign/publish automation for either platform. Every release to date has meant a manual `flutter build`, manual signing, manual upload — and per the redesign memories, several completed feature phases (Foundation, Premium, Hero/Digital ID) are sitting on local `main` unpublished simply because there's no pipeline to push them through. Android distribution is split between a self-hosted APK and a Play Store closed test stuck at 2/12 opted-in testers; iOS is live on the App Store but every future update still goes out by hand.

This spec covers standing up the pipeline itself: git hosting, automated test/lint, and automated build+sign+publish to both stores (plus Shorebird OTA release wiring) triggered by version tags. It does **not** cover the in-app "update available" UX (version-check endpoint, update banner, Play in-app-update API call, Shorebird patch UX) — that's Phase 2, a separate spec once this ships.

## Non-goals

- **Not the in-app update UX.** No new Laravel mobile-API endpoint, no Flutter update banner/dialog, no Play in-app-update-API integration, no Shorebird patch-apply UI. Phase 2.
- **Not fixing the Play Store closed-testing tester shortfall.** Google's requirement that new developer accounts clear a closed-testing threshold before production access unlocks is a policy gate, not something CI can route around. The pipeline publishes to the production track the moment the account is eligible; getting the account eligible (recruiting testers, having them opt in via the Play Store app rather than the browser link) is a separate, non-CI task.
- **Not rotating the Android signing key.** The pipeline reuses the existing `atlasgo-release.jks` keystore as-is. Enrolling in Play App Signing (letting Google hold the actual signing key, with the existing keystore becoming just the "upload key") is a one-time Play Console action taken during the first production upload, not a pipeline design decision.
- **Not continuous/every-push releases.** Releases are tag-triggered only, per the approved answer — a bad merge to `main` never auto-ships to either store.
- **Not automating the final "Submit for Review" click in App Store Connect.** CI uploads the build; a human decides when Apple sees it.
- **Not migrating other repos' CI.** This is scoped to `bugsaymis-mobile` only.
- **Not Firebase App Distribution.** Not selected as a beta channel; Play's internal/closed testing tracks remain the pre-production channel for Android, TestFlight for iOS.
- **Not bumping `config('atlasgo.mobile_version')` in the `bugsaymis` (backend) repo.** That value drives the *current* manual "you're on an old APK" awareness and lives in a different repo with its own deploy pipeline — this spec's automation stops at uploading the APK to S3. Reading it and turning it into an in-app prompt is exactly the Phase 2 problem (the version-check endpoint), so this stays manual for now rather than half-solving Phase 2 as a side effect here.

## Scope

1. **Git hosting** — new private GitHub repo, existing local history pushed as-is.
2. **CI (every push/PR to `main`)** — `flutter analyze` + `flutter test` on a Linux runner.
3. **CD (on `vX.Y.Z` tag push)** — build, sign, and publish both platforms via fastlane.
4. **Signing infrastructure** — Android keystore secret; iOS fastlane match + App Store Connect API key.
5. **Shorebird wiring** — release job uses `shorebird release`; a separate manual-dispatch job runs `shorebird patch` for Dart-only hotfixes to an already-shipped version.

## Key architectural decisions

### 1. Git hosting: new private repo, push local history unmodified

`gh repo create junloutordos/bugsaymis-mobile --private`, add as `origin`, push `main`. Verified before deciding this: `android/key.properties` and `android/keystore/` are already gitignored, so no signing secret is in the 282 tracked files or their history. The four tracked Firebase files (`google-services.json`, `GoogleService-Info.plist`, `firebase_options.dart`, `firebase.json`) are client config, not secrets — standard to commit, no scrubbing needed.

### 2. Two workflows, split by trigger, not one do-everything workflow

`.github/workflows/ci.yml` (push + pull_request to `main`): checkout, pinned Flutter SDK (matching `pubspec.yaml`'s `environment.sdk` constraint via the `subosito/flutter-action` GitHub Action with a pinned version, not `stable`, so a Flutter upstream release can't silently change CI behavior), `flutter pub get`, `flutter analyze`, `flutter test`. Runs on Linux (`ubuntu-latest`) — cheap, fast, no signing needed for analyze/test.

`.github/workflows/release.yml` (push of tag matching `v*.*.*`): two jobs, `release-android` (`ubuntu-latest`) and `release-ios` (`macos-14`), run in parallel since they're independent. Each job: checkout, decode its platform's signing secrets, compute a shared build number (see decision 4), run its fastlane lane. Splitting into two workflows means a `flutter test` failure blocks nothing about the ability to see analyze/test results quickly on every PR, while release-day macOS runner minutes are only spent when a tag is actually pushed — matching the approved tag-triggered, cost-conscious design.

### 3. fastlane owns build/sign/publish; GitHub Actions only orchestrates

Both `android/fastlane/Fastfile` and `ios/fastlane/Fastfile` are added. Each defines one `release` lane:

- **Android `release` lane**: reconstructs `key.properties` from secrets, `flutter build appbundle`, `upload_to_play_store` (fastlane `supply`) targeting the **production** track per the approved "both, Play primary" answer, then also runs `flutter build apk` and uploads it via `aws s3 cp` to the exact key the backend already serves — `s3://crcmis-mis-storage/atlas-app-releases/atlasgo-latest.apk` (confirmed by reading `config/atlasgo.php` in the `bugsaymis` repo, `apk_s3_key`). This automates what's a manual upload today; the AWS credential used is a **new, narrowly-scoped IAM user** (`s3:PutObject` on that one prefix only), not a reuse of the backend deploy pipeline's broader `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY` secrets — a mobile-repo credential leak shouldn't carry ECR/ECS-deploy blast radius.
- **iOS `release` lane**: `match(type: "appstore", readonly: true)` fetches the cert/profile prepared in decision 5, `flutter build ipa`, `upload_to_testflight` (fastlane `pilot`) using the App Store Connect API key. Stops here — no `deliver` submit-for-review call, per the non-goal above.

Keeping the actual build/sign/publish logic in fastlane (not inline GitHub Actions YAML) means the same lanes are runnable locally (`fastlane android release`) for a manual escape hatch if GitHub Actions is ever down, and match the tooling every Flutter CI guide assumes — lowest-surprise choice for future maintenance.

### 4. Build number: derived, not hand-maintained

`pubspec.yaml` today is `1.2.0+3` (name+build number both hand-edited). Going forward: the **version name** comes from the pushed tag (`v1.3.0` → `1.3.0`), and the **build number** is computed by each release job as the GitHub Actions run number (`${{ github.run_number }}`), which is monotonically increasing across the whole repo regardless of platform. Both fastlane lanes pass `--build-number=$RUN_NUMBER --build-name=$TAG_VERSION` to `flutter build`, so `pubspec.yaml`'s checked-in version string is no longer the source of truth for release builds (it stays as a rough local-dev indicator). This avoids the two real failure modes of hand-maintained build numbers: forgetting to bump it (store upload rejected as a duplicate version) and the two platforms drifting to different numbers for the same release.

### 5. iOS signing: fastlane match, one-time setup outside this pipeline's automation

A new private repo `junloutordos/bugsaymis-mobile-certificates` stores match's encrypted cert/profile bundle. One-time, run locally by the user (not automatable — it's an interactive Apple ID login the first time): `fastlane match init`, `fastlane match appstore`. From then on, CI only ever runs `match(..., readonly: true)` — it fetches, it never generates or revokes, so a compromised CI credential can't invalidate the production certificate. The match passphrase and the certificates-repo's deploy key are the two secrets CI needs for this.

### 6. Shorebird: release-time wiring now, patch workflow now, patch *UX* is Phase 2 — Android only, iOS deferred by a confirmed upstream bug

Two things ship in this spec because they're pipeline concerns (an app can't receive Shorebird patches unless the release that shipped it went out *through* Shorebird):
- One-time `shorebird apps create atlasgo` (in practice `shorebird init`, per the CLI's current naming — see execution notes), committing the generated `shorebird.yaml` (non-secret app id) to the repo.
- The Android `release` fastlane lane uses `shorebird release android` in place of plain `flutter build`, so every tagged Android release is patchable afterward.
- A third workflow, `.github/workflows/shorebird-patch.yml`, triggered by `workflow_dispatch` only (never automatic — a patch targets one specific already-released version and picking which one is a human decision), runs `shorebird patch android`/`shorebird patch ios` against the currently-live release track.

**iOS exception, discovered during Task 11's real tagged-release verification, not foreseeable at design time:** `shorebird release ios` has a reproducible upstream bug — it falsely reports CocoaPods as broken and aborts, in an environment where plain `flutter build ipa` (identical CocoaPods install, identical everything) builds and archives successfully every time. Confirmed not caused by CocoaPods version, install method, or Xcode/Swift toolchain version (all independently tried and ruled out). Per user decision, the iOS lane builds via plain `flutter build ios` + fastlane's `build_app` instead of Shorebird, and does not get OTA patch capability until Shorebird fixes this upstream. Android is unaffected and keeps full Shorebird release+patch support as designed.

What's explicitly **not** in this spec: any code in the Flutter app that surfaces "a patch is available" or controls when it's applied — Shorebird's default runtime behavior (check-and-apply-on-next-launch) is left as-is, and any custom UX around it is Phase 2 territory alongside the store-update banner. (For iOS, this is currently moot until Shorebird support is re-added there.)

## Secrets inventory

All stored as GitHub Actions repository secrets on `bugsaymis-mobile` (encrypted at rest by GitHub, injected only into the workflow run, never logged):

| Secret | Used by | Source |
|---|---|---|
| `ANDROID_KEYSTORE_BASE64` | Android release job | `base64 android/keystore/atlasgo-release.jks` |
| `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD` | Android release job | existing `android/key.properties` values |
| `PLAY_SERVICE_ACCOUNT_JSON` | Android release job (`supply`) | new Play Console service account, release-manager role scoped to this one app |
| `APK_S3_UPLOAD_ACCESS_KEY_ID`, `APK_S3_UPLOAD_SECRET_ACCESS_KEY` | Android release job (S3 upload) | new IAM user, `s3:PutObject` on `crcmis-mis-storage/atlas-app-releases/*` only |
| `MATCH_PASSWORD` | iOS release job | set during one-time `match init` |
| `MATCH_GIT_DEPLOY_KEY` | iOS release job | deploy key for `bugsaymis-mobile-certificates`, read-only |
| `APP_STORE_CONNECT_API_KEY_ID`, `APP_STORE_CONNECT_API_ISSUER_ID`, `APP_STORE_CONNECT_API_KEY_CONTENT` | iOS release job (`pilot`) | generated once in App Store Connect (Apple Developer membership confirmed active) |
| `SHOREBIRD_TOKEN` | both release jobs + patch workflow | `shorebird login:ci` |

## One-time setup checklist (not automatable, done by the user before the pipeline's first run)

1. `gh repo create junloutordos/bugsaymis-mobile --private` + push.
2. `gh repo create junloutordos/bugsaymis-mobile-certificates --private` (empty, match populates it).
3. `fastlane match init` + `fastlane match appstore` (interactive Apple ID login, one time).
4. Generate the App Store Connect API key (App Store Connect → Users and Access → Integrations).
5. Create the Play Console service account + grant it release-manager permission on AtlasGo specifically.
6. Create the narrowly-scoped IAM user for APK uploads (`s3:PutObject` on `crcmis-mis-storage/atlas-app-releases/*`).
7. `shorebird login:ci` to mint the CI token; `shorebird apps create atlasgo`.
8. Add all secrets from the inventory above to the new GitHub repo.

The implementation plan should surface these as explicit checkpoints rather than silently assuming they're done, since several are genuinely blocking (the release workflow can't run end-to-end without all of them present).

## Testing

- **CI workflow itself**: verified by observing a real run — push a trivial commit, confirm `flutter analyze`/`flutter test` execute and report status on the PR/commit.
- **Release workflow**: verified by pushing a real tag once all one-time setup steps are complete, confirming both fastlane lanes complete and artifacts land in Play Console (internal or production track, whichever is reachable at the time) and TestFlight. This is inherently an integration test against real external services — no meaningful way to unit-test "did fastlane upload to Play Console."
- **Shorebird patch workflow**: verified by a manual `workflow_dispatch` run against a real released version, confirming `shorebird patch` completes and the patch appears in the Shorebird console.

## Rollout

Ships as **Phase 1** of the AtlasGo CI/CD initiative. Once merged and verified with a real tagged release, immediately brainstorm **Phase 2** (in-app update UX: version-check endpoint on the Laravel backend, Flutter update banner, Play in-app-update API integration, Shorebird patch-apply UX) as its own spec — deferred by scope, not by priority.
