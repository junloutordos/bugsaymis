# Dyna macOS Expansion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Google Sign-In to Dyna.app (primary, alongside the existing email/password flow)
and ship the interim Atlas-mark app icon.

**Architecture:** `GoogleSignIn-iOS` (SPM) handles the native OAuth flow and hands back an ID
token; a thin protocol wraps the SDK call so `LoginViewModel` stays unit-testable without a
real Google account. `DynaAPIClient` gets a `loginWithGoogle` method mirroring the existing
`login` method exactly, calling the backend endpoint built in
`docs/superpowers/plans/2026-08-02-dyna-expansion-backend.md` (Task 9). The icon is generated
from the Atlas mark via `sips` (no external tools) into a standard `AppIcon.appiconset`.

**Tech Stack:** Swift 6, SwiftUI, `GoogleSignIn-iOS` SPM package (new dependency — confirmed to
support native macOS via the system browser, not iOS/Catalyst-only).

## Global Constraints

- **Swift 6 strict concurrency, established in Phase 1 — apply proactively, don't rediscover:**
  every view model driven by a SwiftUI `Task { await ... }` needs `@MainActor`; every
  networking class called from a `@MainActor` view model needs `Sendable`; static mutable test
  mock state needs `nonisolated(unsafe)`; multi-line string literals need their content on its
  own line (`"""\ncontent\n"""`, never `"""content"""` on one line).
  See `docs/superpowers/plans/2026-08-02-dyna-macos-app.md` Tasks 3, 4, 6 for the exact errors
  these produce if skipped.
- Regenerate the Xcode project after any `project.yml` change: `cd ~/bugsaymis-dyna && xcodegen generate`.
- Google Sign-In is **primary**, shown above the email/password form (per the approved
  expansion design — both stay, Google first).
- The icon work is explicitly interim ("for the meantime" per the original request) — a real
  icon design pass is a separate future task, not part of this plan's scope.
- **Two things in this plan are genuinely blocked on external, credential-holding setup you
  must do yourself** (same category as Phase 1's Developer ID certificate blocker):
  1. The real Google OAuth client ID/reversed-client-ID values (from the one-time Google Cloud
     Console setup in the expansion design spec) — the code below uses placeholder config
     values that must be filled in with the real ones once that setup is done.
  2. A live end-to-end click-through of the Google Sign-In flow, which needs a real Google
     account and the real OAuth client above.
  Everything else (SDK integration, networking method, view model logic, icon) is fully
  buildable and testable now.

---

## File structure

```
Dyna/Auth/
  DynaGoogleSignInService.swift        (protocol + concrete GIDSignIn-backed implementation)
  LoginViewModel.swift                 (modify — add signInWithGoogle())
  LoginView.swift                      (modify — add Google Sign-In button)

Dyna/Networking/
  DynaAPIClient.swift                  (modify — add loginWithGoogle)

Dyna/
  DynaApp.swift                        (modify — configure GIDSignIn on launch, handle callback URL)
  Info.plist                           (modify — GIDClientID, CFBundleURLTypes for the OAuth redirect)

Dyna/Assets.xcassets/AppIcon.appiconset/
  Contents.json
  icon_16x16.png, icon_16x16@2x.png, icon_32x32.png, icon_32x32@2x.png,
  icon_128x128.png, icon_128x128@2x.png, icon_256x256.png, icon_256x256@2x.png,
  icon_512x512.png, icon_512x512@2x.png

scripts/
  generate-app-icon.sh

project.yml                            (modify — GoogleSignIn SPM dependency, AppIcon setting)

DynaTests/
  DynaAPIClientGoogleLoginTests.swift
  LoginViewModelGoogleSignInTests.swift
```

---

### Task 1: Add `GoogleSignIn-iOS` SPM dependency + Info.plist config

**Files:**
- Modify: `project.yml`

**Interfaces:** none (dependency + config only — build-verified, not unit-testable).

- [ ] **Step 1: Add the SPM package to `project.yml`**

```yaml
packages:
  GoogleSignIn:
    url: https://github.com/google/GoogleSignIn-iOS
    from: 7.0.0
```

Add `GoogleSignIn` as a dependency on the `Dyna` target (not `DynaTests` — the test target
doesn't call the SDK directly, only through the protocol wrapper built in Task 2):

```yaml
targets:
  Dyna:
    dependencies:
      - package: GoogleSignIn
        product: GoogleSignIn
```

- [ ] **Step 2: Add the OAuth client config to the `info.properties` block**

```yaml
    info:
      properties:
        GIDClientID: "PLACEHOLDER_GOOGLE_IOS_CLIENT_ID"
        CFBundleURLTypes:
          - CFBundleURLSchemes:
              - "PLACEHOLDER_GOOGLE_REVERSED_CLIENT_ID"
```

Both placeholders get their real values once the one-time Google Cloud Console setup (new
iOS-type OAuth client bound to `ph.edu.pshs.crc.atlas.dyna`, per the expansion design spec) is
done — Google's console gives you both the client ID and its reversed form directly on the
client's detail page. Until then, the project builds fine (these are just string values, not
compiled against), but real sign-in attempts will fail with an invalid-client error, which is
expected and not a bug.

- [ ] **Step 3: Regenerate and build**

Run: `cd ~/bugsaymis-dyna && xcodegen generate`
Run: `xcodebuild build -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: `** BUILD SUCCEEDED **` — confirms the SPM package resolves and links correctly, even
though the placeholder client IDs mean real sign-in isn't functional yet.

- [ ] **Step 4: Commit**

```bash
git add project.yml Dyna.xcodeproj Dyna/Info.plist
git commit -m "chore(dyna-app): add GoogleSignIn-iOS dependency and OAuth config placeholders"
```

---

### Task 2: `DynaGoogleSignInService` — protocol wrapper over `GIDSignIn`

**Files:**
- Create: `Dyna/Auth/DynaGoogleSignInService.swift`

**Interfaces:**
- Produces: `protocol DynaGoogleSignInService { func signIn() async throws -> String }`
  (returns the ID token string) and `final class LiveDynaGoogleSignInService: DynaGoogleSignInService`
  wrapping `GIDSignIn.sharedInstance.signIn(withPresenting:)`. **Corrected during execution:**
  the label is `withPresenting:` on macOS too, not a separate `withPresentingWindow:` as
  originally planned from a web search — the compiler caught this immediately (`incorrect
  argument label`), the label just accepts an `NSWindow` on macOS vs a `UIViewController` on
  iOS via platform-conditional overloads. Task 4 (`LoginViewModel`) depends on the protocol,
  not the concrete class, so tests can substitute a fake without touching the real SDK.

This task has no automated test — it's a thin bridge over a UI-driven, credential-requiring SDK
call that can't be exercised without a real Google account and the real OAuth client from Task
1's placeholders. Task 4's `LoginViewModelGoogleSignInTests` covers all the logic that *can* be
tested, by mocking this protocol.

- [ ] **Step 1: Write the protocol and live implementation**

```swift
import AppKit
import GoogleSignIn

protocol DynaGoogleSignInService: Sendable {
    @MainActor func signIn() async throws -> String
}

enum DynaGoogleSignInError: Error {
    case noPresentableWindow
    case noIdToken
}

final class LiveDynaGoogleSignInService: DynaGoogleSignInService {
    @MainActor
    func signIn() async throws -> String {
        guard let window = NSApplication.shared.windows.first(where: \.isKeyWindow) ?? NSApplication.shared.windows.first else {
            throw DynaGoogleSignInError.noPresentableWindow
        }

        let result = try await GIDSignIn.sharedInstance.signIn(withPresenting: window)

        guard let idToken = result.user.idToken?.tokenString else {
            throw DynaGoogleSignInError.noIdToken
        }

        return idToken
    }
}
```

- [ ] **Step 2: Build to verify it compiles**

Run: `cd ~/bugsaymis-dyna && xcodegen generate && xcodebuild build -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: `** BUILD SUCCEEDED **`

- [ ] **Step 3: Commit**

```bash
git add Dyna/Auth/DynaGoogleSignInService.swift Dyna.xcodeproj
git commit -m "feat(dyna-app): add DynaGoogleSignInService wrapping GIDSignIn"
```

---

### Task 3: `DynaAPIClient.loginWithGoogle`

**Files:**
- Modify: `Dyna/Networking/DynaAPIClient.swift`
- Test: `DynaTests/DynaAPIClientGoogleLoginTests.swift`

**Interfaces:**
- Consumes: `AppConfig.apiBaseURL`, the injectable-`URLSession` pattern already established.
- Produces: `DynaAPIClient.loginWithGoogle(idToken: String, deviceName: String) async throws -> LoginResponse`
  — same `LoginResponse` type the password `login()` already returns, hitting
  `POST /api/dyna/login/google` (backend Task 9). Reuses the existing `DynaAPIError` cases
  (`.invalidCredentials` doesn't apply here — a failed Google login is `.serverError` with the
  backend's message, since the backend returns 404/403/401 with a `message` body, not 422).

- [ ] **Step 1: Write the failing test**

```swift
import XCTest
@testable import Dyna

final class DynaAPIClientGoogleLoginTests: XCTestCase {
    func test_loginWithGoogle_decodes_a_successful_response() async throws {
        let session = URLSession.mockSession(status: 200, json: """
        {"token":"abc123","user":{"name":"Junlou Tordos","email":"junlou@example.com"}}
        """)
        let client = DynaAPIClient(session: session)

        let response = try await client.loginWithGoogle(idToken: "fake-id-token", deviceName: "Test Mac")

        XCTAssertEqual(response.token, "abc123")
    }

    func test_loginWithGoogle_surfaces_the_backend_message_on_404() async throws {
        let session = URLSession.mockSession(status: 404, json: """
        {"message":"No Atlas Account found for this Google account."}
        """)
        let client = DynaAPIClient(session: session)

        do {
            _ = try await client.loginWithGoogle(idToken: "fake-id-token", deviceName: "Test Mac")
            XCTFail("Expected an error")
        } catch DynaAPIError.serverError(let message) {
            XCTAssertEqual(message, "No Atlas Account found for this Google account.")
        }
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: FAIL — `loginWithGoogle` doesn't exist.

- [ ] **Step 3: Add `loginWithGoogle` to `DynaAPIClient`**

Add to the class body, next to the existing `login` method (unauthenticated request — no
Bearer token yet, matching how `login` itself works):

```swift
    func loginWithGoogle(idToken: String, deviceName: String) async throws -> LoginResponse {
        var request = URLRequest(url: baseURL.appending(path: "/api/dyna/login/google"))
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.httpBody = try JSONSerialization.data(withJSONObject: [
            "id_token": idToken, "device_name": deviceName,
        ])

        let (data, response): (Data, URLResponse)
        do {
            (data, response) = try await session.data(for: request)
        } catch {
            throw DynaAPIError.network(error.localizedDescription)
        }

        guard let http = response as? HTTPURLResponse else { throw DynaAPIError.network("No HTTP response") }

        guard http.statusCode == 200 else {
            let message = (try? JSONSerialization.jsonObject(with: data) as? [String: Any])?["message"] as? String
            throw DynaAPIError.serverError(message ?? "Unexpected server error (\(http.statusCode)).")
        }

        guard let decoded = try? JSONDecoder().decode(LoginResponse.self, from: data) else {
            throw DynaAPIError.decodingFailed
        }
        return decoded
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Dyna/Networking/DynaAPIClient.swift DynaTests/DynaAPIClientGoogleLoginTests.swift
git commit -m "feat(dyna-app): add DynaAPIClient.loginWithGoogle"
```

---

### Task 4: `LoginViewModel.signInWithGoogle()` + Google button in `LoginView`

**Files:**
- Modify: `Dyna/Auth/LoginViewModel.swift`
- Modify: `Dyna/Auth/LoginView.swift`
- Test: `DynaTests/LoginViewModelGoogleSignInTests.swift`

**Interfaces:**
- Consumes: `DynaGoogleSignInService` protocol (Task 2), `DynaAPIClient.loginWithGoogle` (Task
  3), `KeychainStore.save(token:)` (Phase 1).
- Produces: `LoginViewModel.signInWithGoogle() async` — same success/error state machine as
  `signIn()` (`isLoading`, `errorMessage`, `isAuthenticated`, token saved to Keychain).
  `LoginViewModel`'s initializer gains an injectable `googleSignInService` parameter,
  defaulting to `LiveDynaGoogleSignInService()`, so tests can substitute a fake — this follows
  the exact same injectable-dependency pattern already used for `client`.

- [ ] **Step 1: Write the failing test**

```swift
import XCTest
@testable import Dyna

private final class FakeGoogleSignInService: DynaGoogleSignInService {
    // Test-only mock state, set synchronously before signIn() is awaited — never mutated
    // concurrently. DynaGoogleSignInService requires Sendable, and Swift 6 flags any mutable
    // stored property on a Sendable class as unsafe by default; nonisolated(unsafe) opts out
    // for this deliberately single-context double (found during execution — same pattern as
    // MockURLProtocol's statics in the Phase 1 plan).
    nonisolated(unsafe) var stubbedIdToken: String?
    nonisolated(unsafe) var stubbedError: Error?

    func signIn() async throws -> String {
        if let stubbedError { throw stubbedError }
        return stubbedIdToken ?? "fake-id-token"
    }
}

@MainActor
final class LoginViewModelGoogleSignInTests: XCTestCase {
    override func tearDown() {
        KeychainStore.deleteToken()
        super.tearDown()
    }

    func test_signInWithGoogle_stores_the_token_and_sets_isAuthenticated_on_success() async {
        let session = URLSession.mockSession(status: 200, json: """
        {"token":"abc123","user":{"name":"Junlou Tordos","email":"junlou@example.com"}}
        """)
        let viewModel = LoginViewModel(client: DynaAPIClient(session: session), googleSignInService: FakeGoogleSignInService())

        await viewModel.signInWithGoogle()

        XCTAssertTrue(viewModel.isAuthenticated)
        XCTAssertNil(viewModel.errorMessage)
        XCTAssertEqual(KeychainStore.loadToken(), "abc123")
    }

    func test_signInWithGoogle_sets_a_readable_error_when_no_atlas_account_matches() async {
        let session = URLSession.mockSession(status: 404, json: """
        {"message":"No Atlas Account found for this Google account."}
        """)
        let viewModel = LoginViewModel(client: DynaAPIClient(session: session), googleSignInService: FakeGoogleSignInService())

        await viewModel.signInWithGoogle()

        XCTAssertFalse(viewModel.isAuthenticated)
        XCTAssertEqual(viewModel.errorMessage, "No Atlas Account found for this Google account.")
    }

    func test_signInWithGoogle_sets_a_readable_error_when_the_sdk_call_itself_fails() async {
        let googleService = FakeGoogleSignInService()
        googleService.stubbedError = DynaGoogleSignInError.noPresentableWindow
        let viewModel = LoginViewModel(client: DynaAPIClient(session: .shared), googleSignInService: googleService)

        await viewModel.signInWithGoogle()

        XCTAssertFalse(viewModel.isAuthenticated)
        XCTAssertNotNil(viewModel.errorMessage)
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: FAIL — `signInWithGoogle` doesn't exist.

- [ ] **Step 3: Add `signInWithGoogle` to `LoginViewModel`, wire the constructor**

```swift
@MainActor
@Observable
final class LoginViewModel {
    var email = ""
    var password = ""
    var isLoading = false
    var errorMessage: String?
    var isAuthenticated = false

    private let client: DynaAPIClient
    private let googleSignInService: DynaGoogleSignInService

    init(client: DynaAPIClient = DynaAPIClient(), googleSignInService: DynaGoogleSignInService = LiveDynaGoogleSignInService()) {
        self.client = client
        self.googleSignInService = googleSignInService
    }

    func signIn() async {
        errorMessage = nil
        isLoading = true
        defer { isLoading = false }

        do {
            let response = try await client.login(email: email, password: password, deviceName: Host.current().localizedName ?? "Mac")
            try KeychainStore.save(token: response.token)
            isAuthenticated = true
        } catch DynaAPIError.invalidCredentials {
            errorMessage = "Incorrect email or password."
        } catch DynaAPIError.noAccess {
            errorMessage = "This Atlas Account does not have Dyna access. Contact MIS if you believe this is a mistake."
        } catch {
            errorMessage = "Couldn't sign in — check your connection and try again."
        }
    }

    func signInWithGoogle() async {
        errorMessage = nil
        isLoading = true
        defer { isLoading = false }

        do {
            let idToken = try await googleSignInService.signIn()
            let response = try await client.loginWithGoogle(idToken: idToken, deviceName: Host.current().localizedName ?? "Mac")
            try KeychainStore.save(token: response.token)
            isAuthenticated = true
        } catch let error as DynaAPIError {
            if case .serverError(let message) = error {
                errorMessage = message
            } else {
                errorMessage = "Couldn't sign in with Google — check your connection and try again."
            }
        } catch {
            errorMessage = "Google sign-in didn't complete. Please try again."
        }
    }
}
```

Update `LoginView` to add the Google button above the email/password form:

```swift
struct LoginView: View {
    @State private var viewModel = LoginViewModel()
    var onAuthenticated: () -> Void

    var body: some View {
        VStack(spacing: 16) {
            Text("Sign in to Dyna").font(.title2).bold()
            Text("Use your Atlas Account to continue.").foregroundStyle(.secondary)

            Button {
                Task {
                    await viewModel.signInWithGoogle()
                    if viewModel.isAuthenticated { onAuthenticated() }
                }
            } label: {
                Label("Sign in with Google", systemImage: "globe")
                    .frame(maxWidth: .infinity)
            }
            .disabled(viewModel.isLoading)

            Divider().overlay(Text("or").font(.caption).foregroundStyle(.secondary))

            TextField("Email", text: $viewModel.email)
                .textContentType(.username)
            SecureField("Password", text: $viewModel.password)
                .textContentType(.password)

            if let error = viewModel.errorMessage {
                Text(error).foregroundStyle(.red).font(.callout)
            }

            Button {
                Task {
                    await viewModel.signIn()
                    if viewModel.isAuthenticated { onAuthenticated() }
                }
            } label: {
                if viewModel.isLoading {
                    ProgressView()
                } else {
                    Text("Sign In")
                }
            }
            .disabled(viewModel.isLoading || viewModel.email.isEmpty || viewModel.password.isEmpty)
        }
        .padding(32)
        .frame(width: 360)
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Dyna/Auth/LoginViewModel.swift Dyna/Auth/LoginView.swift DynaTests/LoginViewModelGoogleSignInTests.swift
git commit -m "feat(dyna-app): add Google Sign-In to LoginViewModel and LoginView"
```

---

### Task 5: Handle the OAuth redirect URL in `DynaApp`

**Files:**
- Modify: `Dyna/DynaApp.swift`

**Interfaces:** none (app-lifecycle wiring, not independently testable — verified by Task 6's
manual check once real credentials exist).

`GIDSignIn`'s macOS flow completes via a redirect back into the app through the URL scheme
registered in Task 1 (`CFBundleURLTypes`) — the app must hand that URL to
`GIDSignIn.sharedInstance.handle(_:)` or the sign-in flow never completes.

- [ ] **Step 1: Add `.onOpenURL` to the root scene**

```swift
import SwiftUI
import GoogleSignIn

@main
struct DynaApp: App {
    @State private var isAuthenticated = KeychainStore.loadToken() != nil
    @State private var chatViewModel = ChatViewModel()
    @State private var conversations: [ConversationSummary] = []

    var body: some Scene {
        WindowGroup {
            Group {
                if isAuthenticated {
                    NavigationSplitView {
                        ConversationListView(
                            conversations: conversations,
                            onSelect: { conversation in Task { await chatViewModel.openConversation(conversation.id) } },
                            onNewConversation: { chatViewModel.startNewConversation() }
                        )
                        .task { conversations = await chatViewModel.loadConversations() }
                    } detail: {
                        ChatView(viewModel: chatViewModel)
                    }
                } else {
                    LoginView(onAuthenticated: { isAuthenticated = true })
                }
            }
            .onOpenURL { url in
                GIDSignIn.sharedInstance.handle(url)
            }
        }

        MenuBarExtra("Dyna", systemImage: "sparkles") {
            Button("Open Dyna") { NSApp.activate(ignoringOtherApps: true) }
            Divider()
            Button("Sign Out") {
                KeychainStore.deleteToken()
                isAuthenticated = false
            }
            Button("Quit") { NSApp.terminate(nil) }
        }
    }
}
```

- [ ] **Step 2: Build to verify it compiles**

Run: `cd ~/bugsaymis-dyna && xcodegen generate && xcodebuild build -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: `** BUILD SUCCEEDED **`

- [ ] **Step 3: Commit**

```bash
git add Dyna/DynaApp.swift
git commit -m "feat(dyna-app): handle the Google Sign-In OAuth redirect URL"
```

---

### Task 6: Interim app icon from the Atlas mark

**Files:**
- Create: `scripts/generate-app-icon.sh`
- Create: `Dyna/Assets.xcassets/AppIcon.appiconset/Contents.json`
- Create: `Dyna/Assets.xcassets/AppIcon.appiconset/icon_*.png` (10 files, generated by the script)
- Modify: `project.yml`

**Interfaces:** none (asset pipeline, verified by inspecting the generated files' actual pixel
dimensions — a real, executable check, not a subjective one).

**Confirmed inputs:** `~/Downloads/Atlas_Mark_Only.png` is 918×1178 RGBA (verified directly —
`sips -g pixelWidth -g pixelHeight`). AtlasGo's own shipped icon
(`~/bugsaymis-mobile/assets/images/atlasgo_mark.png`) is 960×960 RGBA — confirming the "pad to
square" treatment to replicate.

- [ ] **Step 1: Write `Contents.json`**

```json
{
  "images": [
    {"filename": "icon_16x16.png", "idiom": "mac", "scale": "1x", "size": "16x16"},
    {"filename": "icon_16x16@2x.png", "idiom": "mac", "scale": "2x", "size": "16x16"},
    {"filename": "icon_32x32.png", "idiom": "mac", "scale": "1x", "size": "32x32"},
    {"filename": "icon_32x32@2x.png", "idiom": "mac", "scale": "2x", "size": "32x32"},
    {"filename": "icon_128x128.png", "idiom": "mac", "scale": "1x", "size": "128x128"},
    {"filename": "icon_128x128@2x.png", "idiom": "mac", "scale": "2x", "size": "128x128"},
    {"filename": "icon_256x256.png", "idiom": "mac", "scale": "1x", "size": "256x256"},
    {"filename": "icon_256x256@2x.png", "idiom": "mac", "scale": "2x", "size": "256x256"},
    {"filename": "icon_512x512.png", "idiom": "mac", "scale": "1x", "size": "512x512"},
    {"filename": "icon_512x512@2x.png", "idiom": "mac", "scale": "2x", "size": "512x512"}
  ],
  "info": {"author": "xcode", "version": 1}
}
```

- [ ] **Step 2: Write the generation script**

```bash
#!/usr/bin/env bash
# Generates Dyna's interim AppIcon.appiconset from the Atlas mark, replicating
# AtlasGo's own shipped-icon treatment: glyph padded onto a white square canvas.
set -euo pipefail

SOURCE="$HOME/Downloads/Atlas_Mark_Only.png"
ICONSET_DIR="Dyna/Assets.xcassets/AppIcon.appiconset"
PADDED="/tmp/atlas-mark-padded-square.png"
BASE_1024="/tmp/atlas-mark-1024.png"

if [ ! -f "$SOURCE" ]; then
  echo "Source not found: $SOURCE" >&2
  exit 1
fi

mkdir -p "$ICONSET_DIR"

# Source is 918x1178 (portrait) -- pad to a 1178x1178 square, white background,
# content centered, then downscale to the 1024 base every other size derives from.
sips -p 1178 1178 --padColor FFFFFF "$SOURCE" --out "$PADDED"
sips -z 1024 1024 "$PADDED" --out "$BASE_1024"

declare -A SIZES=(
  ["icon_16x16.png"]=16
  ["icon_16x16@2x.png"]=32
  ["icon_32x32.png"]=32
  ["icon_32x32@2x.png"]=64
  ["icon_128x128.png"]=128
  ["icon_128x128@2x.png"]=256
  ["icon_256x256.png"]=256
  ["icon_256x256@2x.png"]=512
  ["icon_512x512.png"]=512
  ["icon_512x512@2x.png"]=1024
)

for filename in "${!SIZES[@]}"; do
  size="${SIZES[$filename]}"
  sips -z "$size" "$size" "$BASE_1024" --out "$ICONSET_DIR/$filename" > /dev/null
done

echo "Generated 10 icon files at $ICONSET_DIR"
```

- [ ] **Step 3: Run the script and verify the output**

Run: `chmod +x scripts/generate-app-icon.sh && cd ~/bugsaymis-dyna && ./scripts/generate-app-icon.sh`
Then verify actual dimensions, not just that files exist:
Run: `sips -g pixelWidth -g pixelHeight Dyna/Assets.xcassets/AppIcon.appiconset/icon_512x512@2x.png`
Expected: `pixelWidth: 1024` / `pixelHeight: 1024`. Spot-check `icon_16x16.png` the same way,
expecting 16×16.

- [ ] **Step 4: Wire the icon set into the Xcode project**

Add to `project.yml`, `Dyna` target's `settings.base`:

```yaml
        ASSETCATALOG_COMPILER_APPICON_NAME: AppIcon
```

Add `Dyna/Assets.xcassets` to the target's `sources` if not already covered by the existing
`Dyna` folder source entry (it should be, since XcodeGen picks up asset catalogs within a
sourced folder automatically — confirm by regenerating and checking the asset catalog appears
in the generated project, not by assuming).

- [ ] **Step 5: Regenerate, build, and visually confirm**

Run: `cd ~/bugsaymis-dyna && xcodegen generate && xcodebuild build -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: `** BUILD SUCCEEDED **`. Then run the app (⌘R from Xcode, or `open` the built `.app`
per Phase 1's pattern) and confirm the Dock icon and menu-bar icon show the Atlas mark, not the
default Swift/blank icon.

- [ ] **Step 6: Commit**

```bash
git add scripts/generate-app-icon.sh Dyna/Assets.xcassets project.yml Dyna.xcodeproj
git commit -m "feat(dyna-app): add interim app icon from the Atlas mark"
```

---

### Task 7: Full suite verification

**Files:** none (verification-only task).

- [ ] **Step 1: Run the full Dyna.app test suite**

Run: `cd ~/bugsaymis-dyna && xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: `** TEST SUCCEEDED **` — every test from Phase 1 plus this plan.

- [ ] **Step 2: Confirm the Google Sign-In button and icon are both visible**

Launch the built app and screenshot: confirm the "Sign in with Google" button appears above
the email/password form on the login screen, and the app icon (Dock/menu bar) shows the Atlas
mark.

- [ ] **Step 3: Note the two items still blocked on external setup**

Real end-to-end Google Sign-In (needs the actual OAuth client ID from Google Cloud Console,
replacing Task 1's placeholders) and the signed/notarized `.dmg` build (Phase 1 Task 8, needs
the Developer ID Application certificate) are both genuinely pending your action outside this
codebase — not something to "fix" by continuing to iterate on code.

- [ ] **Step 4: Commit (if Steps 1-2 required fixes)**

```bash
git add -u
git commit -m "fix(dyna-app): address issues found in expansion full-suite verification"
```
