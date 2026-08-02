# Dyna.app (macOS) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the native SwiftUI macOS client for Dyna — sign in with an Atlas Account,
chat with Dyna, see conversation history — as a signed, notarized, direct-download app.

**Architecture:** A single-window + menu-bar-extra SwiftUI app. A `DynaAPIClient` talks to the
backend built in `2026-08-02-dyna-backend.md` (`POST /api/dyna/login`, `POST /api/dyna/chat`,
`GET /api/dyna/conversations`) over plain synchronous JSON (no token streaming in v1 — see that
plan's "Streaming is deliberately out of scope" note). The Sanctum token lives in the macOS
Keychain via a small `KeychainStore` wrapper. State is held in `@Observable` view models per
screen (`LoginViewModel`, `ChatViewModel`), following SwiftUI's standard MV pattern — no
third-party architecture framework.

**Tech Stack:** Swift 6, SwiftUI, macOS 14 (Sonoma) minimum deployment target, `URLSession`
(async/await) for networking, `Security.framework` (Keychain Services) for token storage,
XCTest for tests. No third-party dependencies in v1 — the surface area (login, chat, history)
doesn't need one, and it keeps notarization/signing simple.

## Global Constraints

- Product name in all UI copy: **"Atlas Account"**, never "BugSayMis Account" — this is a
  hard, explicit correction from the design review, not a style preference.
- No file uploads, no write actions, no general-purpose chat — Dyna.app only ever calls the
  three v1 endpoints above.
- Sanctum token stored in Keychain only — never `UserDefaults`, never a plaintext file.
- Distribution: signed with the **existing** Apple Developer ID certificate already used for
  AtlasGo (no new Apple Developer Program enrollment). Direct-download `.dmg`, not the Mac App
  Store.
- Backend base URL must be configurable at build time (dev vs. prod), not hardcoded to one
  environment — mirrors the backend having both a local Docker dev instance
  (`http://localhost:8080`) and production (`https://mis.crc.pshs.edu.ph`).

---

## File structure

```
Dyna/
  Dyna.xcodeproj
  Dyna/
    DynaApp.swift                          (App entry point, menu bar + window scene)
    Networking/
      DynaAPIClient.swift
      DynaAPIError.swift
    Auth/
      KeychainStore.swift
      LoginViewModel.swift
      LoginView.swift
    Chat/
      ChatViewModel.swift
      ChatView.swift
      ConversationListView.swift
      Message.swift                        (local model, distinct from API DTOs)
    Config/
      AppConfig.swift                      (backend base URL, build-time switch)
  DynaTests/
    KeychainStoreTests.swift
    DynaAPIClientTests.swift
    LoginViewModelTests.swift
    ChatViewModelTests.swift
```

**Not in this plan (explicit fast-follow):** menu-bar quick-summon global keyboard shortcut,
markdown table rendering polish, first-launch suggested-prompt seeding, dark-mode-specific
asset tuning, Sparkle-based auto-update. Task 8 gets the app to a signed, notarized, working
`.dmg` — visual polish beyond functional chat/history/login is deliberately deferred so this
plan's tasks stay independently testable and shippable. (System dark mode itself needs no
extra work — SwiftUI follows the OS appearance by default with zero of this plan's code
overriding it.)

---

### Task 1: Xcode project scaffold + `AppConfig`

**Files:**
- Create: `Dyna.xcodeproj` (via Xcode: New Project → macOS → App, SwiftUI interface, Swift
  language, "Dyna" product name, bundle ID `ph.edu.pshs.crc.atlas.dyna`, deployment target
  macOS 14.0)
- Create: `Dyna/Config/AppConfig.swift`
- Test: `DynaTests/AppConfigTests.swift`

**Interfaces:**
- Produces: `AppConfig.apiBaseURL: URL` — resolved from an Xcode build-configuration-specific
  `Info.plist` key `DYNA_API_BASE_URL` (Debug → `http://localhost:8080`, Release →
  `https://mis.crc.pshs.edu.ph`), so later networking code never hardcodes an environment.

- [ ] **Step 1: Create the Xcode project**

In Xcode: File → New → Project → macOS → App. Product Name `Dyna`, Interface: SwiftUI,
Language: Swift, uncheck "Use Core Data" and "Include Tests" prompts default (a test target is
added in this step regardless — confirm `DynaTests` target exists). Set the deployment target
to macOS 14.0 in the project's Build Settings.

Add two build configuration entries to `Info.plist` (or an `.xcconfig` included by both Debug
and Release configs) named `DYNA_API_BASE_URL`, set to `http://localhost:8080` for Debug and
`https://mis.crc.pshs.edu.ph` for Release.

- [ ] **Step 2: Write the failing test**

```swift
import XCTest
@testable import Dyna

final class AppConfigTests: XCTestCase {
    func test_apiBaseURL_is_a_valid_https_or_http_url() throws {
        let url = try XCTUnwrap(AppConfig.apiBaseURL)
        XCTAssertTrue(url.scheme == "http" || url.scheme == "https")
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: FAIL — `AppConfig` doesn't exist.

- [ ] **Step 4: Write `AppConfig`**

```swift
import Foundation

enum AppConfig {
    static var apiBaseURL: URL {
        guard
            let raw = Bundle.main.object(forInfoDictionaryKey: "DYNA_API_BASE_URL") as? String,
            let url = URL(string: raw)
        else {
            fatalError("DYNA_API_BASE_URL is missing or invalid in Info.plist for this build configuration.")
        }
        return url
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add Dyna.xcodeproj Dyna/Config/AppConfig.swift DynaTests/AppConfigTests.swift
git commit -m "chore(dyna-app): scaffold Xcode project with environment-aware AppConfig"
```

---

### Task 2: `KeychainStore` — token persistence

**Files:**
- Create: `Dyna/Auth/KeychainStore.swift`
- Test: `DynaTests/KeychainStoreTests.swift`

**Interfaces:**
- Produces: `KeychainStore.save(token: String) throws`, `KeychainStore.loadToken() -> String?`,
  `KeychainStore.deleteToken()`. Task 3 (`LoginViewModel`) and Task 5 (`DynaAPIClient`)
  depend on exactly these three members.

- [ ] **Step 1: Write the failing test**

```swift
import XCTest
@testable import Dyna

final class KeychainStoreTests: XCTestCase {
    override func tearDown() {
        KeychainStore.deleteToken()
        super.tearDown()
    }

    func test_save_then_load_round_trips_the_token() throws {
        try KeychainStore.save(token: "test-token-123")

        XCTAssertEqual(KeychainStore.loadToken(), "test-token-123")
    }

    func test_loadToken_returns_nil_when_nothing_is_stored() {
        KeychainStore.deleteToken()

        XCTAssertNil(KeychainStore.loadToken())
    }

    func test_delete_removes_a_previously_saved_token() throws {
        try KeychainStore.save(token: "to-be-deleted")

        KeychainStore.deleteToken()

        XCTAssertNil(KeychainStore.loadToken())
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: FAIL — `KeychainStore` doesn't exist.

- [ ] **Step 3: Write `KeychainStore`**

```swift
import Foundation
import Security

enum KeychainStore {
    private static let service = "ph.edu.pshs.crc.atlas.dyna"
    private static let account = "dyna-sanctum-token"

    static func save(token: String) throws {
        deleteToken() // keychain add fails on duplicate; always start clean

        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: account,
            kSecValueData as String: Data(token.utf8),
            kSecAttrAccessible as String: kSecAttrAccessibleAfterFirstUnlock,
        ]

        let status = SecItemAdd(query as CFDictionary, nil)
        guard status == errSecSuccess else {
            throw NSError(domain: "KeychainStore", code: Int(status), userInfo: [
                NSLocalizedDescriptionKey: "Failed to save token to Keychain (status \(status)).",
            ])
        }
    }

    static func loadToken() -> String? {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: account,
            kSecReturnData as String: true,
            kSecMatchLimit as String: kSecMatchLimitOne,
        ]

        var result: AnyObject?
        let status = SecItemCopyMatching(query as CFDictionary, &result)

        guard status == errSecSuccess, let data = result as? Data else { return nil }
        return String(data: data, encoding: .utf8)
    }

    static func deleteToken() {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrService as String: service,
            kSecAttrAccount as String: account,
        ]
        SecItemDelete(query as CFDictionary)
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Dyna/Auth/KeychainStore.swift DynaTests/KeychainStoreTests.swift
git commit -m "feat(dyna-app): add KeychainStore for Sanctum token persistence"
```

---

### Task 3: `DynaAPIClient` — login

**Files:**
- Create: `Dyna/Networking/DynaAPIError.swift`
- Create: `Dyna/Networking/DynaAPIClient.swift`
- Test: `DynaTests/DynaAPIClientTests.swift`

**Interfaces:**
- Consumes: `AppConfig.apiBaseURL` (Task 1).
- Produces: `DynaAPIClient.login(email: String, password: String, deviceName: String) async throws -> LoginResponse`
  where `LoginResponse` is `{ token: String, user: { name: String, email: String } }`, matching
  the backend's `POST /api/dyna/login` response shape exactly (`docs/superpowers/plans/2026-08-02-dyna-backend.md`,
  Task 9). `DynaAPIError` cases: `.invalidCredentials`, `.noAccess`, `.serverError(String)`,
  `.decodingFailed`, `.network(Error)`. `DynaAPIClient` is initialized with an injectable
  `URLSession` (defaults to `.shared`) so tests can substitute a mock protocol — Task 4/6
  depend on this same injected-session pattern.

**Swift 6 note (found during execution):** `DynaAPIClient` must conform to `Sendable` —
`@MainActor` view models (Task 4's `LoginViewModel`, Task 6's `ChatViewModel`) call its async
methods, and Swift 6's strict concurrency checking rejects passing a non-`Sendable` class
across that boundary. It's safe here: both stored properties are `let` and themselves
`Sendable` (`URLSession`, `URL`). Declare `final class DynaAPIClient: Sendable` from the start
rather than discovering this error later in Task 4.

- [ ] **Step 1: Write the failing test**

```swift
import XCTest
@testable import Dyna

final class DynaAPIClientTests: XCTestCase {
    func test_login_decodes_a_successful_response() async throws {
        let session = URLSession.mockSession(status: 200, json: """
        {"token":"abc123","user":{"name":"Junlou Tordos","email":"junlou@example.com"}}
        """)
        let client = DynaAPIClient(session: session)

        let response = try await client.login(email: "junlou@example.com", password: "pw", deviceName: "Test Mac")

        XCTAssertEqual(response.token, "abc123")
        XCTAssertEqual(response.user.name, "Junlou Tordos")
    }

    func test_login_throws_invalidCredentials_on_422() async throws {
        let session = URLSession.mockSession(status: 422, json: """
        {"message":"The provided credentials are incorrect."}
        """)
        let client = DynaAPIClient(session: session)

        do {
            _ = try await client.login(email: "wrong@example.com", password: "bad", deviceName: "Test Mac")
            XCTFail("Expected DynaAPIError.invalidCredentials")
        } catch DynaAPIError.invalidCredentials {
            // expected
        }
    }

    func test_login_throws_noAccess_on_403() async throws {
        let session = URLSession.mockSession(status: 403, json: """
        {"message":"This account does not have Dyna access."}
        """)
        let client = DynaAPIClient(session: session)

        do {
            _ = try await client.login(email: "no-access@example.com", password: "pw", deviceName: "Test Mac")
            XCTFail("Expected DynaAPIError.noAccess")
        } catch DynaAPIError.noAccess {
            // expected
        }
    }
}
```

Also add a small test helper (same file or a `TestSupport/URLSessionMock.swift`):

```swift
import Foundation

extension URLSession {
    static func mockSession(status: Int, json: String) -> URLSession {
        let config = URLSessionConfiguration.ephemeral
        config.protocolClasses = [MockURLProtocol.self]
        MockURLProtocol.stubStatus = status
        MockURLProtocol.stubBody = json.data(using: .utf8)!
        return URLSession(configuration: config)
    }
}

final class MockURLProtocol: URLProtocol {
    // Test-only mock state, set synchronously before the session request that reads it —
    // never mutated concurrently. nonisolated(unsafe) opts out of Swift 6's strict
    // concurrency checking for this deliberately shared, single-threaded-per-test static.
    // (Found during execution: Swift 6 rejects plain `static var` here as
    // "not concurrency-safe" — the project targets Swift 6, per the plan's Tech Stack.)
    nonisolated(unsafe) static var stubStatus = 200
    nonisolated(unsafe) static var stubBody = Data()

    override class func canInit(with request: URLRequest) -> Bool { true }
    override class func canonicalRequest(for request: URLRequest) -> URLRequest { request }

    override func startLoading() {
        let response = HTTPURLResponse(url: request.url!, statusCode: Self.stubStatus, httpVersion: nil, headerFields: nil)!
        client?.urlProtocol(self, didReceive: response, cacheStoragePolicy: .notAllowed)
        client?.urlProtocol(self, didLoad: Self.stubBody)
        client?.urlProtocolDidFinishLoading(self)
    }

    override func stopLoading() {}
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: FAIL — `DynaAPIClient` doesn't exist.

- [ ] **Step 3: Write `DynaAPIError` and `DynaAPIClient`**

```swift
import Foundation

enum DynaAPIError: Error, Equatable {
    case invalidCredentials
    case noAccess
    case serverError(String)
    case decodingFailed
    case network(String)

    static func == (lhs: DynaAPIError, rhs: DynaAPIError) -> Bool {
        switch (lhs, rhs) {
        case (.invalidCredentials, .invalidCredentials), (.noAccess, .noAccess), (.decodingFailed, .decodingFailed):
            return true
        case let (.serverError(a), .serverError(b)): return a == b
        case let (.network(a), .network(b)): return a == b
        default: return false
        }
    }
}
```

```swift
import Foundation

struct LoginResponse: Decodable {
    struct User: Decodable { let name: String; let email: String }
    let token: String
    let user: User
}

final class DynaAPIClient: Sendable {
    private let session: URLSession
    private let baseURL: URL

    init(session: URLSession = .shared, baseURL: URL = AppConfig.apiBaseURL) {
        self.session = session
        self.baseURL = baseURL
    }

    func login(email: String, password: String, deviceName: String) async throws -> LoginResponse {
        var request = URLRequest(url: baseURL.appending(path: "/api/dyna/login"))
        request.httpMethod = "POST"
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.httpBody = try JSONSerialization.data(withJSONObject: [
            "email": email, "password": password, "device_name": deviceName,
        ])

        let (data, response): (Data, URLResponse)
        do {
            (data, response) = try await session.data(for: request)
        } catch {
            throw DynaAPIError.network(error.localizedDescription)
        }

        guard let http = response as? HTTPURLResponse else { throw DynaAPIError.network("No HTTP response") }

        switch http.statusCode {
        case 200:
            guard let decoded = try? JSONDecoder().decode(LoginResponse.self, from: data) else {
                throw DynaAPIError.decodingFailed
            }
            return decoded
        case 422:
            throw DynaAPIError.invalidCredentials
        case 403:
            throw DynaAPIError.noAccess
        default:
            let message = (try? JSONSerialization.jsonObject(with: data) as? [String: Any])?["message"] as? String
            throw DynaAPIError.serverError(message ?? "Unexpected server error (\(http.statusCode)).")
        }
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Dyna/Networking DynaTests/DynaAPIClientTests.swift
git commit -m "feat(dyna-app): add DynaAPIClient with login()"
```

---

### Task 4: `LoginViewModel` + `LoginView`

**Files:**
- Create: `Dyna/Auth/LoginViewModel.swift`
- Create: `Dyna/Auth/LoginView.swift`
- Test: `DynaTests/LoginViewModelTests.swift`

**Interfaces:**
- Consumes: `DynaAPIClient.login()` (Task 3), `KeychainStore.save(token:)` (Task 2).
- Produces: `LoginViewModel` (`@MainActor @Observable`) with `email: String`, `password: String`,
  `isLoading: Bool`, `errorMessage: String?`, `isAuthenticated: Bool`, and
  `func signIn() async`. Task 6 (`ChatView`/root scene) reads `isAuthenticated` to decide
  which screen to show.

**Swift 6 note (found during execution):** without `@MainActor` on the class, `LoginView`'s
`Task { await viewModel.signIn() }` fails to compile — "sending 'self.viewModel' risks causing
data races," because a SwiftUI view's body is implicitly MainActor-isolated and Swift 6 flags
sending a non-isolated `@Observable` class across that boundary. Apply `@MainActor` to any
Dyna view model (this one and Task 6's `ChatViewModel`) that a SwiftUI view drives via `Task {
await ... }` — it's also just correct: view models exist to back UI state, so they belong on
the main actor. Test classes that touch a `@MainActor` view model synchronously (as this one
does — setting `viewModel.email` directly) need `@MainActor` on the test class too.

- [ ] **Step 1: Write the failing test**

```swift
import XCTest
@testable import Dyna

@MainActor
final class LoginViewModelTests: XCTestCase {
    override func tearDown() {
        KeychainStore.deleteToken()
        super.tearDown()
    }

    func test_signIn_stores_the_token_and_sets_isAuthenticated_on_success() async {
        let session = URLSession.mockSession(status: 200, json: """
        {"token":"abc123","user":{"name":"Junlou Tordos","email":"junlou@example.com"}}
        """)
        let viewModel = LoginViewModel(client: DynaAPIClient(session: session))
        viewModel.email = "junlou@example.com"
        viewModel.password = "correct"

        await viewModel.signIn()

        XCTAssertTrue(viewModel.isAuthenticated)
        XCTAssertNil(viewModel.errorMessage)
        XCTAssertEqual(KeychainStore.loadToken(), "abc123")
    }

    func test_signIn_sets_a_readable_error_on_invalid_credentials() async {
        let session = URLSession.mockSession(status: 422, json: """
        {"message":"nope"}
        """)
        let viewModel = LoginViewModel(client: DynaAPIClient(session: session))
        viewModel.email = "junlou@example.com"
        viewModel.password = "wrong"

        await viewModel.signIn()

        XCTAssertFalse(viewModel.isAuthenticated)
        XCTAssertEqual(viewModel.errorMessage, "Incorrect email or password.")
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: FAIL — `LoginViewModel` doesn't exist.

- [ ] **Step 3: Write `LoginViewModel` and `LoginView`**

```swift
import Foundation
import Observation

@MainActor
@Observable
final class LoginViewModel {
    var email = ""
    var password = ""
    var isLoading = false
    var errorMessage: String?
    var isAuthenticated = false

    private let client: DynaAPIClient

    init(client: DynaAPIClient = DynaAPIClient()) {
        self.client = client
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
}
```

```swift
import SwiftUI

struct LoginView: View {
    @State private var viewModel = LoginViewModel()
    var onAuthenticated: () -> Void

    var body: some View {
        VStack(spacing: 16) {
            Text("Sign in to Dyna").font(.title2).bold()
            Text("Use your Atlas Account to continue.").foregroundStyle(.secondary)

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
git add Dyna/Auth/LoginViewModel.swift Dyna/Auth/LoginView.swift DynaTests/LoginViewModelTests.swift
git commit -m "feat(dyna-app): add LoginViewModel and LoginView"
```

---

### Task 5: `DynaAPIClient` — chat + conversations

**Files:**
- Modify: `Dyna/Networking/DynaAPIClient.swift`
- Modify: `DynaTests/DynaAPIClientTests.swift`

**Interfaces:**
- Consumes: `KeychainStore.loadToken()` (Task 2) for the `Authorization: Bearer` header.
- Produces: `DynaAPIClient.sendMessage(conversationId: Int?, message: String) async throws -> ChatResponse`
  (`ChatResponse { conversationId: Int; answer: String }`, matching backend Task 10's
  `{conversation_id, answer}`), `DynaAPIClient.fetchConversations() async throws -> [ConversationSummary]`
  (`ConversationSummary { id: Int; title: String?; updatedAt: Date }`, matching backend Task 10's
  `GET /api/dyna/conversations` response), and
  `DynaAPIClient.fetchMessages(conversationId: Int) async throws -> [MessageDTO]` (matching
  backend Task 11's `GET /api/dyna/conversations/{id}` response —
  `{id, title, messages: [{role, content, created_at}]}`, of which this method decodes just the
  `messages` array; `MessageDTO { role: String; content: String }` is a networking-layer DTO,
  deliberately separate from the `Message` UI model Task 6 defines — Task 6's `ChatViewModel`
  maps `MessageDTO` to `Message` when loading history). Task 6 depends on all three methods.

- [ ] **Step 1: Write the failing tests (append to `DynaAPIClientTests.swift`)**

```swift
extension DynaAPIClientTests {
    func test_sendMessage_decodes_conversationId_and_answer() async throws {
        try KeychainStore.save(token: "abc123")
        let session = URLSession.mockSession(status: 200, json: """
        {"conversation_id":7,"answer":"There are 42 active employees."}
        """)
        let client = DynaAPIClient(session: session)

        let response = try await client.sendMessage(conversationId: nil, message: "How many employees?")

        XCTAssertEqual(response.conversationId, 7)
        XCTAssertEqual(response.answer, "There are 42 active employees.")
        KeychainStore.deleteToken()
    }

    func test_fetchConversations_decodes_a_list() async throws {
        try KeychainStore.save(token: "abc123")
        let session = URLSession.mockSession(status: 200, json: """
        [{"id":7,"title":"Leave trends","updated_at":"2026-08-02T10:00:00.000000Z"}]
        """)
        let client = DynaAPIClient(session: session)

        let conversations = try await client.fetchConversations()

        XCTAssertEqual(conversations.first?.id, 7)
        XCTAssertEqual(conversations.first?.title, "Leave trends")
        KeychainStore.deleteToken()
    }

    func test_fetchMessages_decodes_the_conversations_message_history() async throws {
        try KeychainStore.save(token: "abc123")
        let session = URLSession.mockSession(status: 200, json: """
        {"id":7,"title":"Leave trends","messages":[
            {"role":"user","content":"How many pending?","created_at":"2026-08-02T10:00:00.000000Z"},
            {"role":"assistant","content":"3 pending.","created_at":"2026-08-02T10:00:05.000000Z"}
        ]}
        """)
        let client = DynaAPIClient(session: session)

        let messages = try await client.fetchMessages(conversationId: 7)

        XCTAssertEqual(messages.count, 2)
        XCTAssertEqual(messages[0].role, "user")
        XCTAssertEqual(messages[1].content, "3 pending.")
        KeychainStore.deleteToken()
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: FAIL — `sendMessage`/`fetchConversations`/`fetchMessages` don't exist.

- [ ] **Step 3: Extend `DynaAPIClient`**

Add to `DynaAPIClient.swift`:

```swift
struct ChatResponse: Decodable {
    let conversationId: Int
    let answer: String

    enum CodingKeys: String, CodingKey { case conversationId = "conversation_id", answer }
}

struct ConversationSummary: Decodable, Identifiable {
    let id: Int
    let title: String?
    let updatedAt: Date

    enum CodingKeys: String, CodingKey { case id, title, updatedAt = "updated_at" }
}

/// Networking-layer wire format for one message, decoded from `GET /api/dyna/conversations/{id}`.
/// Deliberately distinct from the UI's `Message` model (Task 6) — the API's `role` is a raw
/// string ("user"/"assistant"), the UI model's `Role` is a Swift enum.
struct MessageDTO: Decodable {
    let role: String
    let content: String
}

private struct ConversationDetailResponse: Decodable {
    let id: Int
    let title: String?
    let messages: [MessageDTO]
}
```

Add to the `DynaAPIClient` class body:

```swift
    func sendMessage(conversationId: Int?, message: String) async throws -> ChatResponse {
        var body: [String: Any] = ["message": message]
        if let conversationId { body["conversation_id"] = conversationId }

        let data = try await authorizedRequest(path: "/api/dyna/chat", method: "POST", body: body)
        guard let decoded = try? JSONDecoder().decode(ChatResponse.self, from: data) else {
            throw DynaAPIError.decodingFailed
        }
        return decoded
    }

    func fetchConversations() async throws -> [ConversationSummary] {
        let data = try await authorizedRequest(path: "/api/dyna/conversations", method: "GET", body: nil)
        let decoder = JSONDecoder()
        decoder.dateDecodingStrategy = .iso8601
        guard let decoded = try? decoder.decode([ConversationSummary].self, from: data) else {
            throw DynaAPIError.decodingFailed
        }
        return decoded
    }

    func fetchMessages(conversationId: Int) async throws -> [MessageDTO] {
        let data = try await authorizedRequest(path: "/api/dyna/conversations/\(conversationId)", method: "GET", body: nil)
        guard let decoded = try? JSONDecoder().decode(ConversationDetailResponse.self, from: data) else {
            throw DynaAPIError.decodingFailed
        }
        return decoded.messages
    }

    private func authorizedRequest(path: String, method: String, body: [String: Any]?) async throws -> Data {
        guard let token = KeychainStore.loadToken() else { throw DynaAPIError.noAccess }

        var request = URLRequest(url: baseURL.appending(path: path))
        request.httpMethod = method
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        if let body {
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
            request.httpBody = try JSONSerialization.data(withJSONObject: body)
        }

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

        return data
    }
```

- [ ] **Step 4: Run test to verify it passes**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Dyna/Networking/DynaAPIClient.swift DynaTests/DynaAPIClientTests.swift
git commit -m "feat(dyna-app): add sendMessage, fetchConversations, fetchMessages to DynaAPIClient"
```

---

### Task 6: `ChatViewModel` + `Message`

**Files:**
- Create: `Dyna/Chat/Message.swift`
- Create: `Dyna/Chat/ChatViewModel.swift`
- Test: `DynaTests/ChatViewModelTests.swift`

**Interfaces:**
- Consumes: `DynaAPIClient.sendMessage()`, `DynaAPIClient.fetchConversations()`,
  `DynaAPIClient.fetchMessages()`, `MessageDTO` (Task 5).
- Produces: `Message { id: UUID; role: Role; text: String }` (`Role` is `.user`/`.assistant`);
  `ChatViewModel` (`@Observable`) with `messages: [Message]`, `isSending: Bool`,
  `errorMessage: String?`, `conversationId: Int?`, `func send(_ text: String) async`,
  `func loadConversations() async -> [ConversationSummary]`,
  `func openConversation(_ id: Int) async` (fetches and replaces `messages` with that
  conversation's history, sets `conversationId`). Task 7 (`ChatView`/`ConversationListView`)
  binds to all of these — selecting a sidebar item calls `openConversation`, which is what
  makes resuming a past thread actually show its prior messages.

- [ ] **Step 1: Write the failing test**

```swift
import XCTest
@testable import Dyna

@MainActor
final class ChatViewModelTests: XCTestCase {
    func test_send_appends_the_user_message_then_the_assistant_reply() async throws {
        try KeychainStore.save(token: "abc123")
        let session = URLSession.mockSession(status: 200, json: """
        {"conversation_id":7,"answer":"There are 42 active employees."}
        """)
        let viewModel = ChatViewModel(client: DynaAPIClient(session: session))

        await viewModel.send("How many active employees do we have?")

        XCTAssertEqual(viewModel.messages.count, 2)
        XCTAssertEqual(viewModel.messages[0].role, .user)
        XCTAssertEqual(viewModel.messages[0].text, "How many active employees do we have?")
        XCTAssertEqual(viewModel.messages[1].role, .assistant)
        XCTAssertEqual(viewModel.messages[1].text, "There are 42 active employees.")
        XCTAssertEqual(viewModel.conversationId, 7)
        XCTAssertFalse(viewModel.isSending)
        KeychainStore.deleteToken()
    }

    func test_send_surfaces_a_readable_error_and_keeps_the_user_message_on_failure() async throws {
        try KeychainStore.save(token: "abc123")
        let session = URLSession.mockSession(status: 500, json: """
        {"message":"boom"}
        """)
        let viewModel = ChatViewModel(client: DynaAPIClient(session: session))

        await viewModel.send("How many active employees do we have?")

        XCTAssertEqual(viewModel.messages.count, 1) // user message stays, no fake assistant reply
        XCTAssertNotNil(viewModel.errorMessage)
        KeychainStore.deleteToken()
    }

    func test_openConversation_replaces_messages_with_the_fetched_history() async throws {
        try KeychainStore.save(token: "abc123")
        let session = URLSession.mockSession(status: 200, json: """
        {"id":7,"title":"Leave trends","messages":[
            {"role":"user","content":"How many pending?","created_at":"2026-08-02T10:00:00.000000Z"},
            {"role":"assistant","content":"3 pending.","created_at":"2026-08-02T10:00:05.000000Z"}
        ]}
        """)
        let viewModel = ChatViewModel(client: DynaAPIClient(session: session))
        viewModel.messages = [Message(role: .user, text: "stale draft from a different thread")]

        await viewModel.openConversation(7)

        XCTAssertEqual(viewModel.conversationId, 7)
        XCTAssertEqual(viewModel.messages.count, 2)
        XCTAssertEqual(viewModel.messages[0].role, .user)
        XCTAssertEqual(viewModel.messages[0].text, "How many pending?")
        XCTAssertEqual(viewModel.messages[1].role, .assistant)
        KeychainStore.deleteToken()
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: FAIL — `ChatViewModel` doesn't exist.

- [ ] **Step 3: Write `Message` and `ChatViewModel`**

```swift
import Foundation

struct Message: Identifiable, Equatable {
    enum Role: String { case user, assistant }

    let id = UUID()
    let role: Role
    let text: String

    /// Maps a networking-layer `MessageDTO` (raw string role) to the UI model.
    /// Falls back to `.assistant` for any unrecognized role string rather than crashing —
    /// defensive against future backend role values this build doesn't know about yet.
    init(role: Role, text: String) {
        self.role = role
        self.text = text
    }

    init(dto: MessageDTO) {
        self.role = Role(rawValue: dto.role) ?? .assistant
        self.text = dto.content
    }
}
```

```swift
import Foundation
import Observation

@MainActor
@Observable
final class ChatViewModel {
    var messages: [Message] = []
    var isSending = false
    var errorMessage: String?
    var conversationId: Int?

    private let client: DynaAPIClient

    init(client: DynaAPIClient = DynaAPIClient()) {
        self.client = client
    }

    func send(_ text: String) async {
        errorMessage = nil
        messages.append(Message(role: .user, text: text))
        isSending = true
        defer { isSending = false }

        do {
            let response = try await client.sendMessage(conversationId: conversationId, message: text)
            conversationId = response.conversationId
            messages.append(Message(role: .assistant, text: response.answer))
        } catch {
            errorMessage = "Dyna couldn't answer that — try again in a moment."
        }
    }

    func loadConversations() async -> [ConversationSummary] {
        (try? await client.fetchConversations()) ?? []
    }

    func openConversation(_ id: Int) async {
        errorMessage = nil
        do {
            let dtos = try await client.fetchMessages(conversationId: id)
            messages = dtos.map(Message.init(dto:))
            conversationId = id
        } catch {
            errorMessage = "Couldn't load that conversation — try again in a moment."
        }
    }

    func startNewConversation() {
        conversationId = nil
        messages = []
        errorMessage = nil
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS'`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add Dyna/Chat/Message.swift Dyna/Chat/ChatViewModel.swift DynaTests/ChatViewModelTests.swift
git commit -m "feat(dyna-app): add ChatViewModel, Message, and history loading via openConversation"
```

---

### Task 7: `ChatView`, `ConversationListView`, and app entry point

**Files:**
- Create: `Dyna/Chat/ChatView.swift`
- Create: `Dyna/Chat/ConversationListView.swift`
- Modify: `Dyna/DynaApp.swift`

**Interfaces:**
- Consumes: `ChatViewModel` (Task 6), `LoginView` (Task 4).
- Produces: the wired-up app — `DynaApp` shows `LoginView` until `isAuthenticated`, then a
  `NavigationSplitView` with `ConversationListView` as the sidebar and `ChatView` as the
  detail. A menu-bar extra icon reopens/focuses the main window. No new testable logic here
  (this task is UI wiring); verified manually in Step 3.

- [ ] **Step 1: Write `ConversationListView`**

```swift
import SwiftUI

struct ConversationListView: View {
    let conversations: [ConversationSummary]
    let onSelect: (ConversationSummary) -> Void
    let onNewConversation: () -> Void

    var body: some View {
        List {
            Button("New Conversation", action: onNewConversation)
            ForEach(conversations) { conversation in
                Button(conversation.title ?? "Untitled") { onSelect(conversation) }
            }
        }
        .navigationTitle("Dyna")
    }
}
```

- [ ] **Step 2: Write `ChatView`**

```swift
import SwiftUI

struct ChatView: View {
    @State var viewModel: ChatViewModel
    @State private var draft = ""

    var body: some View {
        VStack {
            ScrollView {
                LazyVStack(alignment: .leading, spacing: 12) {
                    ForEach(viewModel.messages) { message in
                        HStack {
                            if message.role == .assistant { Spacer(minLength: 0) }
                            Text(message.text)
                                .padding(10)
                                .background(message.role == .user ? Color.accentColor.opacity(0.15) : Color.gray.opacity(0.15))
                                .clipShape(RoundedRectangle(cornerRadius: 10))
                            if message.role == .user { Spacer(minLength: 0) }
                        }
                    }
                    if viewModel.isSending {
                        ProgressView("Dyna is thinking…")
                    }
                }
                .padding()
            }

            if let error = viewModel.errorMessage {
                Text(error).foregroundStyle(.red).font(.callout)
            }

            HStack {
                TextField("Ask Dyna about leave trends, headcount…", text: $draft)
                    .onSubmit(sendDraft)
                Button("Send", action: sendDraft)
                    .disabled(draft.trimmingCharacters(in: .whitespaces).isEmpty || viewModel.isSending)
            }
            .padding()
        }
    }

    private func sendDraft() {
        let text = draft.trimmingCharacters(in: .whitespaces)
        guard !text.isEmpty else { return }
        draft = ""
        Task { await viewModel.send(text) }
    }
}
```

- [ ] **Step 3: Wire up `DynaApp`**

```swift
import SwiftUI

@main
struct DynaApp: App {
    @State private var isAuthenticated = KeychainStore.loadToken() != nil
    @State private var chatViewModel = ChatViewModel()
    @State private var conversations: [ConversationSummary] = []

    var body: some Scene {
        WindowGroup {
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

- [ ] **Step 4: Manual verification**

Run: `xcodebuild -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS' build`
then run the app from Xcode (⌘R). Confirm: unauthenticated launch shows `LoginView`; after a
successful sign-in against a locally running backend (`docker compose up`, per this repo's
`CLAUDE.md`), the split view appears; sending a message shows the "Dyna is thinking…" state
then the reply; clicking "New Conversation" then selecting the just-created conversation back
from the sidebar re-shows its prior messages (confirms `openConversation` actually round-trips
against the backend's Task 11 endpoint, not just against the mock in tests); the menu bar icon
appears and Sign Out returns to `LoginView`.

- [ ] **Step 5: Commit**

```bash
git add Dyna/Chat/ChatView.swift Dyna/Chat/ConversationListView.swift Dyna/DynaApp.swift
git commit -m "feat(dyna-app): wire up ChatView, ConversationListView, and app entry point"
```

---

### Task 8: Signing, notarization, and `.dmg` distribution

**Files:**
- Modify: `Dyna.xcodeproj` (signing settings)
- Create: `scripts/build-dyna-dmg.sh`

**Interfaces:** none (build/release tooling, not application code).

**What was actually found during execution — this task cannot be fully completed by an
agent:**
- `security find-identity -v -p codesigning` confirms the org's Apple Developer Program team
  IS already present locally: `Apple Distribution: Philippine Science High School - Caraga
  Region Campus (U376ZTY96N)` and `Apple Development: Junlou Tordos (S779ZPQ8NJ)` — Team ID
  `U376ZTY96N`, matching "reuse AtlasGo's account" from the design spec.
- No `Developer ID Application` certificate exists yet, though — confirmed by actually running
  `xcodebuild archive` (succeeded, auto-signed with the Development cert) followed by
  `xcodebuild -exportArchive` with `method: developer-id` (failed:
  `No signing certificate "Developer ID Application" found`).
- Creating that certificate requires an Apple ID interactively signed into Xcode (Xcode →
  Settings → Accounts → Manage Certificates → "+" → Developer ID Application) with
  Admin/App Manager role on the team, or the manual developer.apple.com CSR flow. Both require
  entering real Apple ID credentials — out of scope for an agent to do on the user's behalf.
- Notarization has the same shape of blocker: `xcrun notarytool store-credentials` needs the
  user's Apple ID + an app-specific password (generated at appleid.apple.com), entered
  interactively, once.
- **What was completed:** `DEVELOPMENT_TEAM: U376ZTY96N` wired into `project.yml`,
  `exportOptions.plist` written with the real team ID and `method: developer-id`, and the
  build script below — all ready to run the moment the certificate and notarization profile
  exist. The remaining action item for the user: create the Developer ID Application
  certificate, run `notarytool store-credentials "DynaNotarization" --apple-id <id> --team-id
  U376ZTY96N --password <app-specific-password>` once, then run
  `./scripts/build-dyna-dmg.sh`.

- [ ] **Step 1: Configure signing**

Team ID (`U376ZTY96N`) and `CODE_SIGN_STYLE: Automatic` are already set in `project.yml`
(regenerate with `xcodegen generate` after any project.yml change). What's left, and cannot be
scripted: creating the `Developer ID Application` certificate itself (see note above) and
confirming the bundle ID `ph.edu.pshs.crc.atlas.dyna` doesn't collide with AtlasGo's existing
bundle IDs.

- [ ] **Step 2: Write the build/notarize/package script**

```bash
#!/usr/bin/env bash
# Builds, signs, notarizes, and packages Dyna.app into a distributable .dmg.
#
# One-time prerequisites (see the note above for why these can't be automated):
#   1. A "Developer ID Application" certificate for team U376ZTY96N (PSHS-CRC).
#   2. A notarization credentials profile, stored once via:
#      xcrun notarytool store-credentials "DynaNotarization" \
#        --apple-id <your-apple-id> --team-id U376ZTY96N --password <app-specific-password>
set -euo pipefail

APP_NAME="Dyna"
SCHEME="Dyna"
PROJECT="Dyna.xcodeproj"
ARCHIVE_PATH="build/${APP_NAME}.xcarchive"
EXPORT_PATH="build/export"
DMG_PATH="build/${APP_NAME}.dmg"
NOTARY_PROFILE="DynaNotarization" # not "AtlasGoNotarization" as originally planned — no way
                                   # to verify AtlasGo's actual profile name from this repo,
                                   # so this script uses its own dedicated profile instead

rm -rf build && mkdir -p build

xcodebuild archive \
  -project "$PROJECT" -scheme "$SCHEME" \
  -archivePath "$ARCHIVE_PATH" \
  -destination 'generic/platform=macOS'

xcodebuild -exportArchive \
  -archivePath "$ARCHIVE_PATH" \
  -exportPath "$EXPORT_PATH" \
  -exportOptionsPlist exportOptions.plist

hdiutil create -volname "$APP_NAME" -srcfolder "$EXPORT_PATH/${APP_NAME}.app" -ov -format UDZO "$DMG_PATH"

xcrun notarytool submit "$DMG_PATH" --keychain-profile "$NOTARY_PROFILE" --wait

xcrun stapler staple "$DMG_PATH"

echo "Notarized DMG ready at $DMG_PATH"
```

Also create a minimal `exportOptions.plist` (`method: developer-id`, `teamID` set to the
existing team ID) alongside it — required by `xcodebuild -exportArchive`.

- [x] **Step 3: Run the script and verify — blocked, not completed**

Ran the two steps the script automates directly (`xcodebuild archive`, then
`xcodebuild -exportArchive` with `method: developer-id`) to confirm exactly what blocks it:
archive succeeded (auto-signed with the Apple Development cert, since that's all that's
available), export failed with `No signing certificate "Developer ID Application" found`.
This is the expected, correct failure mode given no such certificate exists yet — not a bug
in the script or project config. Once the certificate and notarization profile exist (see the
note above this task), run: `./scripts/build-dyna-dmg.sh`
Expected then: a `build/Dyna.dmg` that mounts, contains a signed `Dyna.app`, and passes
`spctl --assess --type execute build/export/Dyna.app` (Gatekeeper acceptance — confirms
signing + notarization worked, not just that the build succeeded).

- [x] **Step 4: Commit**

Committed `scripts/build-dyna-dmg.sh`, `exportOptions.plist`, and the `project.yml` /
`Dyna.xcodeproj` changes (Team ID). `build/` is gitignored (build output, not source) and was
cleaned up after the archive/export test above.
