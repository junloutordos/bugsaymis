# AtlasGo CI/CD Pipeline (Phase 1) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up a repeatable CI/CD pipeline for AtlasGo (`~/bugsaymis-mobile`) — git hosting, test automation on every push, and tag-triggered build/sign/publish to the Play Store, TestFlight, the self-hosted APK, and Shorebird.

**Architecture:** GitHub Actions orchestrates; fastlane owns the actual build/sign/publish logic per platform (so lanes are runnable locally too). Every push/PR to `main` runs `flutter analyze`+`flutter test` on Linux. Pushing a `vX.Y.Z` tag runs two parallel jobs (`release-android` on Linux, `release-ios` on macOS) that each build via `shorebird release` (making every store release Shorebird-patchable), sign, and upload. A third workflow, manual-dispatch only, runs `shorebird patch` against an already-released version.

**Tech Stack:** Flutter 3.41.6 (pinned), fastlane + Bundler/Ruby, fastlane match (iOS signing), Shorebird CLI, GitHub Actions, AWS CLI (S3 upload), Google Play Console / App Store Connect.

**Spec:** `docs/superpowers/specs/2026-08-23-atlasgo-cicd-pipeline-design.md`

## Global Constraints

- Package identity — the two platforms use DIFFERENT identifiers, confirmed during Task 6 execution (fastlane match failed against the assumed-shared value): Android `applicationId` is `ph.edu.pshs.crc.bugsaymis_mobile` (with underscore, `android/app/build.gradle`); iOS bundle id is `ph.edu.pshs.crc.bugsaymisMobile` (camelCase, no underscore, `ios/Runner.xcodeproj/project.pbxproj`). Both unchanged, do not touch — use the correct one per platform's Appfile.
- Flutter version pinned in every workflow: exactly `3.41.6`, `channel: stable` — never `flutter-version: stable` (floating).
- Release trigger: push of a tag matching `v*.*.*` only. No release on plain pushes to `main` — `main` pushes only run analyze+test.
- Android: reuse the existing `atlasgo-release.jks` keystore as-is (no key rotation). Play Store track is **production**. No Play App Signing enrollment decision is made in this plan — that's a one-time Play Console action, out of scope.
- iOS: fastlane match, `readonly: true` in CI (CI never generates or revokes certs). Cert/profile bundle lives in a new private repo `junloutordos/bugsaymis-mobile-certificates`. CI **never** calls `deliver`/submit-for-review — uploads stop at TestFlight/App Store Connect.
- Self-hosted APK upload target: `s3://crcmis-mis-storage/atlas-app-releases/atlasgo-latest.apk`, region `ap-southeast-1`, via a **new, narrowly-scoped IAM user** (`s3:PutObject` on that one prefix only) — never the backend deploy pipeline's `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY` secrets.
- Build number = `${{ github.run_number }}` (monotonic across the whole repo). Build name = the pushed tag with its leading `v` stripped (`v1.3.0` → `1.3.0`). `pubspec.yaml`'s checked-in version stays a rough local-dev indicator only — release builds never read it.
- Shorebird app id: `atlasgo`. Both platforms' release lanes use `shorebird release`, not plain `flutter build`. Patches only ever run via manual `workflow_dispatch` — never automatically.
- No changes to the `bugsaymis` (backend) repo in this plan. No automation of `config('atlasgo.mobile_version')`.
- No changes to `~/bugsaymis-mobile`'s application code (`lib/`) in this plan — infra files only.

---

## Task 1: Confirm Play App Signing status (human checkpoint)

This doesn't touch code, but the spec asked for it up front because it changes what to tell existing sideloaded APK users once Play Store publishing goes live.

**Files:** none.

- [ ] **Step 1: Ask the user to check Play Console**

Ask the user to open Play Console → select AtlasGo → **Setup → App integrity**, and report back whether **Play App Signing** shows as **Enabled** or **Not enabled** for this app.

- [ ] **Step 2: Record the answer inline in this plan**

Once the user answers, replace this line with the actual finding before moving on:

> **Play App Signing status: Enabled** (confirmed by user, 2026-08-23). Google re-signs Play Store downloads with its own key — existing sideloaded self-hosted-APK users will hit a one-time "app not installed" signature conflict if they later install from Play Store, requiring one manual uninstall of the sideloaded copy. Flag this to the user again in Task 11 once the first production Play Store rollout actually goes live.

If **enabled**: Google re-signs Play Store downloads with its own key, so a user who currently has the sideloaded, self-signed APK installed will see a one-time "app not installed" signature conflict the first time they try to get the app from Play Store — they'd need to uninstall the sideloaded copy once. This doesn't block anything in this plan; it's a fact to tell the user before the first production Play Store rollout (Task 11) and to mention in any release-announcement copy later. If **not enabled**, Play Store distributes the exact APK fastlane uploads (signed with `atlasgo-release.jks`, same key as the sideloaded APK) — existing sideloaded users get a seamless in-place update with no action needed.

No commit for this task (no files changed).

---

## Task 2: Create GitHub repos and push AtlasGo's existing history

**Files:** none created locally yet (this is repo/remote setup in `~/bugsaymis-mobile`).

- [ ] **Step 1: Create the main repo and push**

```bash
cd ~/bugsaymis-mobile
gh repo create junloutordos/bugsaymis-mobile --private --source=. --remote=origin
git push -u origin main
```

- [ ] **Step 2: Verify the push**

```bash
gh repo view junloutordos/bugsaymis-mobile --json defaultBranchRef,pushedAt
```

Expected: `defaultBranchRef.name` is `main`, `pushedAt` is a timestamp from just now.

- [ ] **Step 3: Create the empty certificates repo**

```bash
gh repo create junloutordos/bugsaymis-mobile-certificates --private -d "fastlane match encrypted cert/profile store for AtlasGo"
```

No local clone needed yet — `fastlane match init` (Task 6) populates it directly.

No code commit for this task (remote/repo creation only).

---

## Task 3: Add the CI workflow (analyze + test on every push/PR)

**Files:**
- Create: `~/bugsaymis-mobile/.github/workflows/ci.yml`

- [ ] **Step 1: Write the workflow**

```yaml
name: CI

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  analyze-test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: subosito/flutter-action@v2
        with:
          flutter-version: '3.41.6'
          channel: 'stable'

      - run: flutter pub get
      - run: flutter analyze
      - run: flutter test
```

- [ ] **Step 2: Confirm the baseline passes locally first**

```bash
cd ~/bugsaymis-mobile
flutter analyze
flutter test
```

Expected: `No issues found!` and `All tests passed!` (already confirmed clean as of this plan's writing — 0 analyzer issues, 86 tests passing; re-confirm since time has passed).

- [ ] **Step 3: Commit and push**

```bash
git add .github/workflows/ci.yml
git commit -m "ci: add analyze+test workflow on push/PR to main"
git push origin main
```

- [ ] **Step 4: Verify the workflow actually ran on GitHub**

```bash
gh run list --workflow=ci.yml --limit=1
gh run watch --exit-status
```

Expected: the run's conclusion is `success`.

---

## Task 4: Add the Android fastlane skeleton

**Files:**
- Create: `~/bugsaymis-mobile/android/Gemfile`
- Create: `~/bugsaymis-mobile/android/fastlane/Appfile`
- Create: `~/bugsaymis-mobile/android/fastlane/Fastfile`
- Modify: `~/bugsaymis-mobile/.gitignore` (add fastlane's own generated report files)

- [ ] **Step 1: Write the Gemfile**

`android/Gemfile`:
```ruby
source "https://rubygems.org"

gem "fastlane"
```

- [ ] **Step 2: Write the Appfile**

`android/fastlane/Appfile`:
```ruby
json_key_file(ENV["PLAY_SERVICE_ACCOUNT_JSON_PATH"])
package_name("ph.edu.pshs.crc.bugsaymis_mobile")
```

- [ ] **Step 3: Write the Fastfile**

`android/fastlane/Fastfile`:
```ruby
default_platform(:android)

platform :android do
  desc "Build, sign, and publish AtlasGo: Play Store production track + self-hosted APK to S3"
  lane :release do
    build_name = ENV.fetch("BUILD_NAME") { UI.user_error!("BUILD_NAME env var is required") }
    build_number = ENV.fetch("BUILD_NUMBER") { UI.user_error!("BUILD_NUMBER env var is required") }

    # App bundle for the Play Store, via Shorebird so this release is patchable.
    sh(
      "shorebird", "release", "android",
      "--artifact=aab",
      "--build-name=#{build_name}",
      "--build-number=#{build_number}",
      chdir: ".."
    )

    upload_to_play_store(
      track: "production",
      aab: "../build/app/outputs/bundle/release/app-release.aab",
      release_status: "completed",
      skip_upload_metadata: true,
      skip_upload_images: true,
      skip_upload_screenshots: true
    )

    # Plain installable APK for the self-hosted direct-download path.
    sh(
      "shorebird", "release", "android",
      "--artifact=apk",
      "--build-name=#{build_name}",
      "--build-number=#{build_number}",
      chdir: ".."
    )

    sh(
      "aws", "s3", "cp",
      "../build/app/outputs/flutter-apk/app-release.apk",
      "s3://crcmis-mis-storage/atlas-app-releases/atlasgo-latest.apk"
    )
  end
end
```

- [ ] **Step 4: Ignore fastlane's local report noise**

Add to `~/bugsaymis-mobile/.gitignore`:
```
# fastlane
**/fastlane/report.xml
**/fastlane/Preview.html
**/fastlane/screenshots/**/*.png
**/fastlane/test_output
```

- [ ] **Step 5: Verify the Fastfile parses and the lane is registered**

```bash
cd ~/bugsaymis-mobile/android
bundle install
bundle exec fastlane lanes
```

Expected: output lists `android release` — this confirms the Ruby is syntactically valid and fastlane recognizes the lane. It will NOT run successfully yet (no `PLAY_SERVICE_ACCOUNT_JSON_PATH`, no Shorebird login) — that's expected until Tasks 6–8.

- [ ] **Step 6: Commit**

```bash
cd ~/bugsaymis-mobile
git add android/Gemfile android/fastlane/Appfile android/fastlane/Fastfile .gitignore
git commit -m "ci(android): add fastlane skeleton for the release lane"
git push origin main
```

---

## Task 5: Add the iOS fastlane + match skeleton

**Files:**
- Create: `~/bugsaymis-mobile/ios/Gemfile`
- Create: `~/bugsaymis-mobile/ios/fastlane/Appfile`
- Create: `~/bugsaymis-mobile/ios/fastlane/Fastfile`

- [ ] **Step 1: Write the Gemfile**

`ios/Gemfile`:
```ruby
source "https://rubygems.org"

gem "fastlane"
```

- [ ] **Step 2: Write the Appfile**

`ios/fastlane/Appfile`:
```ruby
app_identifier("ph.edu.pshs.crc.bugsaymisMobile")
team_id(ENV["APPLE_TEAM_ID"])
```

- [ ] **Step 3: Write the Fastfile**

`ios/fastlane/Fastfile`:
```ruby
default_platform(:ios)

platform :ios do
  desc "Build, sign via match, and upload AtlasGo to TestFlight"
  lane :release do
    build_name = ENV.fetch("BUILD_NAME") { UI.user_error!("BUILD_NAME env var is required") }
    build_number = ENV.fetch("BUILD_NUMBER") { UI.user_error!("BUILD_NUMBER env var is required") }

    api_key = app_store_connect_api_key(
      key_id: ENV["APP_STORE_CONNECT_API_KEY_ID"],
      issuer_id: ENV["APP_STORE_CONNECT_API_ISSUER_ID"],
      key_content: ENV["APP_STORE_CONNECT_API_KEY_CONTENT"],
      is_key_content_base64: true
    )

    match(
      type: "appstore",
      readonly: true,
      api_key: api_key,
      git_url: "git@github.com:junloutordos/bugsaymis-mobile-certificates.git"
    )

    sh(
      "shorebird", "release", "ios",
      "--build-name=#{build_name}",
      "--build-number=#{build_number}",
      chdir: ".."
    )

    ipa_path = Dir.glob("../build/ios/ipa/*.ipa").first
    UI.user_error!("No .ipa found under build/ios/ipa") unless ipa_path

    upload_to_testflight(
      ipa: ipa_path,
      api_key: api_key,
      skip_waiting_for_build_processing: true
    )
  end
end
```

- [ ] **Step 4: Verify the Fastfile parses and the lane is registered**

```bash
cd ~/bugsaymis-mobile/ios
bundle install
bundle exec fastlane lanes
```

Expected: output lists `ios release`. It will NOT run successfully yet (no App Store Connect API key, no match repo populated, no Shorebird login) — expected until Tasks 6–8.

- [ ] **Step 5: Commit**

```bash
cd ~/bugsaymis-mobile
git add ios/Gemfile ios/fastlane/Appfile ios/fastlane/Fastfile
git commit -m "ci(ios): add fastlane + match skeleton for the release lane"
git push origin main
```

---

## Task 6: Human checkpoint — Apple + Play credentials, match init

None of this is scriptable by an agent: it requires interactive Apple ID / Google account logins in a browser. Ask the user to complete each step, then collect the resulting values.

**Files:** none (external account setup only).

- [ ] **Step 1: Ask the user to generate an App Store Connect API key**

Instructions to relay: App Store Connect → **Users and Access → Integrations → App Store Connect API** → generate a new key with **App Manager** role. Download the `.p8` file (only downloadable once), and note the **Key ID** and **Issuer ID** shown on that page.

- [ ] **Step 2: Ask the user to find their Apple Team ID**

Instructions to relay: Apple Developer account → **Membership** page → **Team ID** (a 10-character alphanumeric string).

- [ ] **Step 3: Ask the user to run `fastlane match init` and `fastlane match appstore` locally**

Instructions to relay (run from `~/bugsaymis-mobile/ios`, on their own Mac, with Xcode installed):
```bash
cd ~/bugsaymis-mobile/ios
bundle exec fastlane match init
# When prompted for storage type, choose "git"
# When prompted for the git URL, enter: git@github.com:junloutordos/bugsaymis-mobile-certificates.git

bundle exec fastlane match appstore
# This logs into their Apple ID interactively (2FA), generates the distribution
# cert + provisioning profile, and pushes them encrypted to the certificates repo.
# It will ask for a new passphrase to encrypt the repo with — that passphrase
# becomes the MATCH_PASSWORD secret in Task 7.
```

- [ ] **Step 4: Ask the user to create the Play Console service account**

Instructions to relay: Google Cloud Console (the project linked to Play Console) → **IAM & Admin → Service Accounts** → create one (e.g. `atlasgo-ci@<project>.iam.gserviceaccount.com`) → create a JSON key for it → download the JSON. Then in **Play Console → Users and permissions → Invite new users**, paste that service account's email, grant it **Release manager** access scoped to AtlasGo only (not all apps on the account).

- [ ] **Step 5: Collect the values**

Confirm the user has, ready to hand over in Task 7:
- App Store Connect API Key ID, Issuer ID, and the `.p8` file contents
- Apple Team ID
- The `match` passphrase they set in Step 3
- The Play Console service account JSON file contents

No commit for this task (no files in the repo change).

---

## Task 7: Create the S3-upload IAM user and add all GitHub secrets

**Files:** none (AWS + GitHub secret configuration only).

- [ ] **Step 1: Create the narrowly-scoped IAM user for APK uploads**

This touches the production AWS account — confirm with the user before running:

```bash
aws iam create-user --user-name atlasgo-apk-uploader
```

- [ ] **Step 2: Attach an inline policy scoped to exactly one S3 prefix**

```bash
cat > /tmp/atlasgo-apk-uploader-policy.json <<'EOF'
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": "s3:PutObject",
      "Resource": "arn:aws:s3:::crcmis-mis-storage/atlas-app-releases/*"
    }
  ]
}
EOF

aws iam put-user-policy \
  --user-name atlasgo-apk-uploader \
  --policy-name atlasgo-apk-upload-only \
  --policy-document file:///tmp/atlasgo-apk-uploader-policy.json

rm /tmp/atlasgo-apk-uploader-policy.json
```

- [ ] **Step 3: Generate its access key**

```bash
aws iam create-access-key --user-name atlasgo-apk-uploader
```

Capture `AccessKeyId` and `SecretAccessKey` from the output immediately — AWS shows the secret exactly once.

- [ ] **Step 4: Add every secret to the `bugsaymis-mobile` GitHub repo**

```bash
cd ~/bugsaymis-mobile

gh secret set ANDROID_KEYSTORE_BASE64 --body "$(base64 -i android/keystore/atlasgo-release.jks)"
gh secret set ANDROID_KEYSTORE_PASSWORD   # paste value from android/key.properties
gh secret set ANDROID_KEY_ALIAS           # paste value from android/key.properties
gh secret set ANDROID_KEY_PASSWORD        # paste value from android/key.properties

gh secret set PLAY_SERVICE_ACCOUNT_JSON < /path/to/downloaded-service-account.json

gh secret set APK_S3_UPLOAD_ACCESS_KEY_ID       # paste from Step 3
gh secret set APK_S3_UPLOAD_SECRET_ACCESS_KEY   # paste from Step 3

gh secret set MATCH_PASSWORD              # the passphrase from Task 6 Step 3
gh secret set MATCH_GIT_DEPLOY_KEY < /path/to/deploy-key   # see sub-step below

gh secret set APPLE_TEAM_ID                # from Task 6 Step 2
gh secret set APP_STORE_CONNECT_API_KEY_ID
gh secret set APP_STORE_CONNECT_API_ISSUER_ID
gh secret set APP_STORE_CONNECT_API_KEY_CONTENT --body "$(base64 -i /path/to/AuthKey_XXXX.p8)"
```

For `MATCH_GIT_DEPLOY_KEY`: generate a dedicated SSH key pair (`ssh-keygen -t ed25519 -f /tmp/atlasgo-match-deploy -N ""`), add the **public** key as a deploy key (write access, since match pushes updates) on `junloutordos/bugsaymis-mobile-certificates` via `gh repo deploy-key add /tmp/atlasgo-match-deploy.pub --title "CI (match)" --allow-write --repo junloutordos/bugsaymis-mobile-certificates`, then store the **private** key contents as the `MATCH_GIT_DEPLOY_KEY` secret. Delete the local key files afterward.

- [ ] **Step 5: Verify all secrets are present**

```bash
gh secret list --repo junloutordos/bugsaymis-mobile
```

Expected: `ANDROID_KEYSTORE_BASE64`, `ANDROID_KEYSTORE_PASSWORD`, `ANDROID_KEY_ALIAS`, `ANDROID_KEY_PASSWORD`, `PLAY_SERVICE_ACCOUNT_JSON`, `APK_S3_UPLOAD_ACCESS_KEY_ID`, `APK_S3_UPLOAD_SECRET_ACCESS_KEY`, `MATCH_PASSWORD`, `MATCH_GIT_DEPLOY_KEY`, `APPLE_TEAM_ID`, `APP_STORE_CONNECT_API_KEY_ID`, `APP_STORE_CONNECT_API_ISSUER_ID`, `APP_STORE_CONNECT_API_KEY_CONTENT` all listed. (`SHOREBIRD_TOKEN` is added in Task 8, not here.)

No commit for this task (no files in the repo change — secrets and IAM only).

---

## Task 8: Shorebird account setup and wiring

**Files:**
- Create: `~/bugsaymis-mobile/shorebird.yaml` (generated, then committed)

- [ ] **Step 1: Install the Shorebird CLI locally (if not already)**

```bash
curl --proto '=https' --tlsv1.2 -sSf https://raw.githubusercontent.com/shorebirdtech/install/main/install.sh | bash
```

- [ ] **Step 2: Register the app**

```bash
cd ~/bugsaymis-mobile
shorebird apps create atlasgo
```

This writes `shorebird.yaml` (a non-secret app id) into the repo root.

- [ ] **Step 3: Mint the CI token (human checkpoint — semi-interactive OAuth)**

Ask the user to run:
```bash
shorebird login:ci
```
This opens a browser for login, then prints a CI token to the terminal. Add it as a secret:
```bash
gh secret set SHOREBIRD_TOKEN --repo junloutordos/bugsaymis-mobile
```

- [ ] **Step 4: Commit `shorebird.yaml`**

```bash
git add shorebird.yaml
git commit -m "chore: register AtlasGo with Shorebird"
git push origin main
```

(No lane changes needed in this task — Tasks 4 and 5 already wrote the Fastfiles using `shorebird release`, anticipating this setup.)

---

## Task 9: Add the tag-triggered release workflow

**Files:**
- Create: `~/bugsaymis-mobile/.github/workflows/release.yml`

- [ ] **Step 1: Write the workflow**

```yaml
name: Release

on:
  push:
    tags:
      - 'v*.*.*'

jobs:
  release-android:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: subosito/flutter-action@v2
        with:
          flutter-version: '3.41.6'
          channel: 'stable'

      - uses: actions/setup-java@v4
        with:
          distribution: 'zulu'
          java-version: '17'

      - name: Derive build name/number
        run: |
          echo "BUILD_NAME=${GITHUB_REF_NAME#v}" >> "$GITHUB_ENV"
          echo "BUILD_NUMBER=${{ github.run_number }}" >> "$GITHUB_ENV"

      - name: Install Shorebird
        run: |
          curl --proto '=https' --tlsv1.2 -sSf https://raw.githubusercontent.com/shorebirdtech/install/main/install.sh | bash
          echo "$HOME/.shorebird/bin" >> "$GITHUB_PATH"

      - name: Decode Android keystore
        run: |
          echo "${{ secrets.ANDROID_KEYSTORE_BASE64 }}" | base64 -d > android/keystore/atlasgo-release.jks
          cat > android/key.properties <<EOF
          storeFile=keystore/atlasgo-release.jks
          storePassword=${{ secrets.ANDROID_KEYSTORE_PASSWORD }}
          keyAlias=${{ secrets.ANDROID_KEY_ALIAS }}
          keyPassword=${{ secrets.ANDROID_KEY_PASSWORD }}
          EOF

      - name: Write Play service account key
        run: echo '${{ secrets.PLAY_SERVICE_ACCOUNT_JSON }}' > android/play-service-account.json

      - uses: aws-actions/configure-aws-credentials@v4.0.2
        with:
          aws-access-key-id: ${{ secrets.APK_S3_UPLOAD_ACCESS_KEY_ID }}
          aws-secret-access-key: ${{ secrets.APK_S3_UPLOAD_SECRET_ACCESS_KEY }}
          aws-region: ap-southeast-1

      - name: bundle install
        working-directory: android
        run: bundle install

      - name: fastlane release
        working-directory: android
        env:
          SHOREBIRD_TOKEN: ${{ secrets.SHOREBIRD_TOKEN }}
          PLAY_SERVICE_ACCOUNT_JSON_PATH: play-service-account.json
        run: bundle exec fastlane release

  release-ios:
    runs-on: macos-14
    steps:
      - uses: actions/checkout@v4

      - uses: subosito/flutter-action@v2
        with:
          flutter-version: '3.41.6'
          channel: 'stable'

      - name: Derive build name/number
        run: |
          echo "BUILD_NAME=${GITHUB_REF_NAME#v}" >> "$GITHUB_ENV"
          echo "BUILD_NUMBER=${{ github.run_number }}" >> "$GITHUB_ENV"

      - name: Install Shorebird
        run: |
          curl --proto '=https' --tlsv1.2 -sSf https://raw.githubusercontent.com/shorebirdtech/install/main/install.sh | bash
          echo "$HOME/.shorebird/bin" >> "$GITHUB_PATH"

      - uses: webfactory/ssh-agent@v0.9.0
        with:
          ssh-private-key: ${{ secrets.MATCH_GIT_DEPLOY_KEY }}

      - name: bundle install
        working-directory: ios
        run: bundle install

      - name: fastlane release
        working-directory: ios
        env:
          SHOREBIRD_TOKEN: ${{ secrets.SHOREBIRD_TOKEN }}
          MATCH_PASSWORD: ${{ secrets.MATCH_PASSWORD }}
          APPLE_TEAM_ID: ${{ secrets.APPLE_TEAM_ID }}
          APP_STORE_CONNECT_API_KEY_ID: ${{ secrets.APP_STORE_CONNECT_API_KEY_ID }}
          APP_STORE_CONNECT_API_ISSUER_ID: ${{ secrets.APP_STORE_CONNECT_API_ISSUER_ID }}
          APP_STORE_CONNECT_API_KEY_CONTENT: ${{ secrets.APP_STORE_CONNECT_API_KEY_CONTENT }}
        run: bundle exec fastlane release
```

- [ ] **Step 2: Commit**

```bash
cd ~/bugsaymis-mobile
git add .github/workflows/release.yml
git commit -m "ci: add tag-triggered release workflow for Android + iOS"
git push origin main
```

No run yet — the first real trigger is Task 11, once every secret from Tasks 6–8 exists.

---

## Task 10: Add the manual Shorebird patch workflow

**Files:**
- Create: `~/bugsaymis-mobile/.github/workflows/shorebird-patch.yml`

- [ ] **Step 1: Write the workflow**

```yaml
name: Shorebird Patch

on:
  workflow_dispatch:
    inputs:
      platform:
        description: 'Platform to patch'
        required: true
        type: choice
        options: [android, ios]
      release-version:
        description: 'Exact released version to patch (e.g. 1.3.0)'
        required: true
        type: string

jobs:
  patch:
    runs-on: ${{ inputs.platform == 'ios' && 'macos-14' || 'ubuntu-latest' }}
    steps:
      - uses: actions/checkout@v4

      - uses: subosito/flutter-action@v2
        with:
          flutter-version: '3.41.6'
          channel: 'stable'

      - name: Install Shorebird
        run: |
          curl --proto '=https' --tlsv1.2 -sSf https://raw.githubusercontent.com/shorebirdtech/install/main/install.sh | bash
          echo "$HOME/.shorebird/bin" >> "$GITHUB_PATH"

      - name: shorebird patch
        env:
          SHOREBIRD_TOKEN: ${{ secrets.SHOREBIRD_TOKEN }}
        run: |
          shorebird patch ${{ inputs.platform }} \
            --release-version=${{ inputs.release-version }}
```

- [ ] **Step 2: Commit**

```bash
cd ~/bugsaymis-mobile
git add .github/workflows/shorebird-patch.yml
git commit -m "ci: add manual-dispatch Shorebird patch workflow"
git push origin main
```

---

## Task 11: End-to-end verification with a real tagged release

**Files:** none (verification only — fixes to earlier tasks' files happen inline if this surfaces real issues, per the spec's acknowledgment that store-publishing can't be meaningfully unit-tested beforehand).

- [ ] **Step 1: Confirm every prerequisite secret exists**

```bash
gh secret list --repo junloutordos/bugsaymis-mobile
```

Expected: all 13 secrets from Tasks 7 and 8 present (the 12 from Task 7 plus `SHOREBIRD_TOKEN`).

- [ ] **Step 2: Push a real release tag**

```bash
cd ~/bugsaymis-mobile
git tag v1.3.0
git push origin v1.3.0
```

(Use whatever version number is actually next — check `pubspec.yaml` and the current Play Console / TestFlight listings first so this isn't a duplicate/lower version.)

- [ ] **Step 3: Watch both release jobs**

```bash
gh run list --workflow=release.yml --limit=1
gh run watch --exit-status
```

- [ ] **Step 4: Verify each destination actually received the artifact**

- Play Console: AtlasGo → Production track shows the new version, status "In review" or "Live".
- App Store Connect: TestFlight tab shows the new build, "Processing" or ready.
- S3: `aws s3 ls s3://crcmis-mis-storage/atlas-app-releases/atlasgo-latest.apk` shows a `LastModified` timestamp from just now.

- [ ] **Step 5: Fix any real issues surfaced by this run**

If a fastlane action, Shorebird flag, or workflow step fails against the real services (expected to be where any remaining inaccuracy in this plan's authored YAML/Ruby shows up — the spec explicitly calls this out as untestable ahead of time), fix it in the relevant task's file, commit the fix, and re-tag/re-push to retry (delete and recreate the tag, or bump to the next patch version) until all three destinations confirm success.

- [ ] **Step 6: Tell the user the outcome of the Play App Signing question from Task 1**

If Task 1 found Play App Signing **enabled**, remind the user now (with the app actually live on Play Store) that existing sideloaded users will hit a one-time reinstall prompt if they get the app from Play Store instead of continuing to use the self-hosted APK path.

---

## Execution Notes (added during real run)

- **Task 5/6 correction:** `ios/fastlane/Appfile`'s `app_identifier` was originally written as `ph.edu.pshs.crc.bugsaymis_mobile` (copied from the Android value, wrongly assumed shared). Running `fastlane match appstore` for real in Task 6 failed with "Could not find App ID with bundle identifier 'ph.edu.pshs.crc.bugsaymis_mobile'" — App Store Connect's actual registered app is `ph.edu.pshs.crc.bugsaymisMobile` (confirmed against `ios/Runner.xcodeproj/project.pbxproj`'s `PRODUCT_BUNDLE_IDENTIFIER`). Fixed in the Appfile and in this plan's Global Constraints. Android's Appfile (`android/fastlane/Appfile`, `package_name`) was already correct and untouched.
- **Task 6 App Store Connect API key:** reused an existing key rather than creating a new one — Key ID `9MZ8Y4F7ZV`, Issuer ID `68721f3c-4990-4f4a-9e02-b5b87690a688`, `.p8` at `~/Downloads/AuthKey_9MZ8Y4F7ZV.p8`. Its access role is "Developer", not "App Manager" — sufficient for a plain TestFlight upload (this lane never calls `deliver`/submit-for-review), revisit only if Task 11's real run hits a permission error.
- **Task 6 Play Console service account:** the classic "API access" page is gone from the current Play Console UI. Actual path used: created a new GCP project (`atlasgo-ci`, personal/no-org, under `junloutordos@gmail.com` — the same account that owns the Play Console developer account "PSHS-CRC MIS") → IAM & Admin → Service Accounts → created `atlasgo-ci@atlasgo-ci.iam.gserviceaccount.com` → JSON key downloaded to `~/Downloads/atlasgo-ci-c3bfeec4688f.json` → invited into Play Console (Users and permissions → Invite user) scoped to the AtlasGo app only, granted "Release to production, exclude devices and use Play app signing" + "Release apps to testing tracks" (the closest current equivalent to the old "Release manager" role — Play Console now uses granular per-app checkboxes instead of named roles).
- **Task 9 keystore path correction:** the real `android/key.properties` (confirmed by reading it) uses `storeFile=../keystore/atlasgo-release.jks`, not `keystore/atlasgo-release.jks` as this plan's draft Task 9 sample showed — the signing config that calls `file(storeFile)` lives in `android/app/build.gradle.kts`, so the path is resolved relative to `android/app/`, not `android/`. The actual `release.yml` committed to the repo uses the correct `../`-prefixed path; only this plan's illustrative sample was off.
- **Task 8 Shorebird API key scope:** the console's "Release and patch only" scoped-key option is gated behind a paid plan ("PRO" badge) — this account is on the free Hobby tier, so only "Full access" keys are available. `SHOREBIRD_TOKEN` is therefore a full-access key (1-year expiration), not the least-privilege scope originally intended. Revisit if/when the account upgrades to a paid Shorebird plan.
- **Task 8 Shorebird CLI changes (post-write-time):** `shorebird apps create` no longer exists — app registration is now `shorebird init` (writes `shorebird.yaml`). `shorebird login:ci` was removed entirely; CI auth is now an **API key** generated in the web console (console.shorebird.dev → Account → API Keys → Create API Key, choose "Release & Patch only" scope, any expiration), not a CLI command. The env var the CLI reads is still `SHOREBIRD_TOKEN` — no workflow changes needed, only the generation method changed.
- **Task 6 `match appstore` crash:** first real run generated the distribution cert (`RJQ7NDZ9HV`) and provisioning profile successfully on the Apple Developer Portal, but crashed on the final "encrypt and push to the certificates repo" step with `FastlaneCrash: [!] Error encrypting ... couldn't set additional authenticated data` — a known fastlane/match issue where this Mac's system Ruby (2.6.10) OpenSSL binding can't do AES-256-GCM's authenticated-data step. Fixed by adding `force_legacy_encryption(true)` to `ios/fastlane/Matchfile` (governs the standalone `fastlane match` CLI) and `force_legacy_encryption: true` to the `match(...)` call in `ios/fastlane/Fastfile` (governs CI's readonly fetch) — both must agree, since CI decrypts with whatever scheme actually encrypted the repo.
- **Play Console account gotcha:** `jtordos@crc.pshs.edu.ph` (the Workspace account active in Chrome by default) is NOT the Play Console account — the real one is `junloutordos@gmail.com` ("PSHS-CRC MIS" developer account). Browser account-switching via the profile-chooser dialog only affects the current tab/origin; a fresh navigation to a different Google product can silently fall back to `authuser=0`. Confirm the account chip in the top-left of any Play Console/Cloud Console page before acting.

## Self-Review Notes

- **Spec coverage:** every "Key architectural decision" in the spec (repo hosting, two-workflow split, fastlane-owns-signing, derived build numbers, match readonly, Shorebird release+patch split) maps to a task above. The spec's "Secrets inventory" table is fully covered by Task 7 + Task 8 Step 3. The spec's "One-time setup checklist" maps to Tasks 2, 6, 7, 8.
- **Non-goals respected:** no in-app update UX code, no backend repo changes, no `mobile_version` automation, no Play App Signing enrollment action, no signing-key rotation, no automatic Apple review submission — none of the tasks above touch any of these.
- **Added beyond the spec's literal secrets table**, discovered while writing real fastlane config: `APPLE_TEAM_ID` (match/Appfile requires it) and the SSH deploy-key mechanics for `MATCH_GIT_DEPLOY_KEY` (the spec named the secret but not the exact delivery mechanism). Both are called out explicitly in Task 7 rather than left implicit.
