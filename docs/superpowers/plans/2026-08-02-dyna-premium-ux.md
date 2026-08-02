# Dyna Premium UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Restyle Dyna (native macOS app at `~/bugsaymis-dyna`) from bare-bones default SwiftUI styling to a warm, avatar-led, royal-navy-accented design with full Light/Dark/System theming and Markdown-rendered assistant messages.

**Architecture:** A new `Dyna/DesignSystem/` group holds theming (adaptive `Color` values + a persisted `ThemePreference`) and a reusable `Avatar` view built from a new "DynaMark" image asset. Existing views (`ChatView`, `ConversationListView`, `LoginView`, `DynaApp`'s `MenuBarExtra`) get restyled in place using these building blocks — no changes to `ChatViewModel`, `LoginViewModel`, `DynaAPIClient`, or any networking/data-model code. Markdown rendering is added via the `swift-markdown-ui` SPM package, used only inside `ChatView` for assistant messages.

**Tech Stack:** Swift 6 (strict concurrency), SwiftUI, `@Observable` (Observation framework), XCTest, XcodeGen (`project.yml`), SPM (adding `swift-markdown-ui`).

## Global Constraints

- Swift 6 strict concurrency: any new `@Observable` class touching UI state must be `@MainActor` (matches `ChatViewModel`/`LoginViewModel`).
- Every target (`Dyna` and `DynaTests`) must share `DEVELOPMENT_TEAM: U376ZTY96N` — already set at the `settings.base` level in `project.yml`, do not override per-target.
- After any `project.yml` change, run `cd ~/bugsaymis-dyna && xcodegen generate` before building — the `.xcodeproj` is committed, not gitignored.
- Color values (exact, from the approved spec): accent `#1e3a5f` (light) / `#2b4c78` (dark); avatar tint `#3a5f8f` (light) / `#5b84b8` (dark); assistant bubble background `#eef1f6` (light) / `#1a2029` (dark); sidebar background `#f0f2f7` (light) / `#11151d` (dark); main background `#ffffff` (light) / `#0d1117` (dark); border `#dfe3ee` (light) / `#232a38` (dark); secondary text `#33415c` (light) / `#9aa5b8` (dark).
- This plan covers Sections 1+2 of `docs/superpowers/specs/2026-08-02-dyna-premium-ux-and-full-profile-design.md` only. No backend/tool changes, no changes to `get_employee_info`/`get_student_info` — that's a separate plan.

---

## Task 1: Adaptive color palette + hex parsing

**Files:**
- Create: `Dyna/DesignSystem/Color+Hex.swift`
- Create: `Dyna/DesignSystem/DynaPalette.swift`
- Test: `DynaTests/ColorHexTests.swift`

**Interfaces:**
- Produces: `Color.init(hex:)` — `Color(hex: "1e3a5f")`, a 6-digit hex string (no `#` prefix) → opaque `Color`.
- Produces: `Color.init(light:dark:)` — adaptive color that resolves via `NSColor`'s dynamic-provider appearance callback.
- Produces: `enum DynaPalette` with static `Color` properties: `accent`, `avatarTint`, `assistantBubbleBackground`, `sidebarBackground`, `mainBackground`, `border`, `secondaryText`.

- [ ] **Step 1: Write the failing test for hex parsing**

```swift
// DynaTests/ColorHexTests.swift
import XCTest
import SwiftUI
@testable import Dyna

final class ColorHexTests: XCTestCase {
    func test_hex_init_parses_a_six_digit_hex_string_into_the_correct_rgb_components() {
        let color = Color(hex: "1e3a5f")
        let nsColor = NSColor(color).usingColorSpace(.deviceRGB)!

        XCTAssertEqual(nsColor.redComponent, 0x1e.doubleValue / 255, accuracy: 0.01)
        XCTAssertEqual(nsColor.greenComponent, 0x3a.doubleValue / 255, accuracy: 0.01)
        XCTAssertEqual(nsColor.blueComponent, 0x5f.doubleValue / 255, accuracy: 0.01)
    }
}

private extension UInt8 {
    var doubleValue: Double { Double(self) }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/bugsaymis-dyna && xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS' -only-testing:DynaTests/ColorHexTests 2>&1 | tail -30`
Expected: FAIL — `Color` has no member `init(hex:)` (build error, not a runtime failure).

- [ ] **Step 3: Implement `Color(hex:)` and `Color(light:dark:)`**

```swift
// Dyna/DesignSystem/Color+Hex.swift
import SwiftUI
import AppKit

extension Color {
    /// `hex` is a 6-digit RGB hex string with no `#` prefix, e.g. "1e3a5f".
    init(hex: String) {
        var value: UInt64 = 0
        Scanner(string: hex).scanHexInt64(&value)

        let red = Double((value & 0xFF0000) >> 16) / 255
        let green = Double((value & 0x00FF00) >> 8) / 255
        let blue = Double(value & 0x0000FF) / 255

        self.init(red: red, green: green, blue: blue)
    }

    /// An adaptive color that resolves `light` or `dark` based on the current NSAppearance.
    init(light: Color, dark: Color) {
        self.init(NSColor(name: nil) { appearance in
            let isDark = appearance.bestMatch(from: [.aqua, .darkAqua]) == .darkAqua
            return NSColor(isDark ? dark : light)
        })
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ~/bugsaymis-dyna && xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS' -only-testing:DynaTests/ColorHexTests 2>&1 | tail -30`
Expected: PASS

- [ ] **Step 5: Add the palette (no test — pure declarative color constants, covered by usage in later tasks' manual verification)**

```swift
// Dyna/DesignSystem/DynaPalette.swift
import SwiftUI

enum DynaPalette {
    static let accent = Color(light: Color(hex: "1e3a5f"), dark: Color(hex: "2b4c78"))
    static let avatarTint = Color(light: Color(hex: "3a5f8f"), dark: Color(hex: "5b84b8"))
    static let assistantBubbleBackground = Color(light: Color(hex: "eef1f6"), dark: Color(hex: "1a2029"))
    static let sidebarBackground = Color(light: Color(hex: "f0f2f7"), dark: Color(hex: "11151d"))
    static let mainBackground = Color(light: .white, dark: Color(hex: "0d1117"))
    static let border = Color(light: Color(hex: "dfe3ee"), dark: Color(hex: "232a38"))
    static let secondaryText = Color(light: Color(hex: "33415c"), dark: Color(hex: "9aa5b8"))
}
```

- [ ] **Step 6: Commit**

```bash
cd ~/bugsaymis-dyna
git add Dyna/DesignSystem/Color+Hex.swift Dyna/DesignSystem/DynaPalette.swift DynaTests/ColorHexTests.swift
git commit -m "feat: add adaptive royal-navy color palette"
```

---

## Task 2: Theme preference (System/Light/Dark), persisted

**Files:**
- Create: `Dyna/DesignSystem/ThemePreference.swift`
- Test: `DynaTests/ThemeManagerTests.swift`

**Interfaces:**
- Consumes: nothing from Task 1.
- Produces: `enum ThemePreference: String, CaseIterable` with cases `system`, `light`, `dark`; computed `var colorScheme: ColorScheme?` (nil for `.system`); computed `var label: String` ("System"/"Light"/"Dark").
- Produces: `@MainActor @Observable final class ThemeManager` with `var preference: ThemePreference { get set }` (persists on set) and `init(defaults: UserDefaults = .standard)`. Later tasks (Task 7) read `themeManager.preference.colorScheme` and write `themeManager.preference = ...`.

- [ ] **Step 1: Write the failing tests**

```swift
// DynaTests/ThemeManagerTests.swift
import XCTest
@testable import Dyna

@MainActor
final class ThemeManagerTests: XCTestCase {
    private var defaults: UserDefaults!

    override func setUp() {
        super.setUp()
        defaults = UserDefaults(suiteName: #file)
        defaults.removePersistentDomain(forName: #file)
    }

    override func tearDown() {
        defaults.removePersistentDomain(forName: #file)
        super.tearDown()
    }

    func test_defaults_to_system_when_nothing_is_stored() {
        let manager = ThemeManager(defaults: defaults)

        XCTAssertEqual(manager.preference, .system)
        XCTAssertNil(manager.preference.colorScheme)
    }

    func test_setting_preference_persists_it_to_user_defaults() {
        let manager = ThemeManager(defaults: defaults)

        manager.preference = .dark

        XCTAssertEqual(defaults.string(forKey: "dyna.themePreference"), "dark")
    }

    func test_a_new_manager_instance_loads_the_previously_persisted_preference() {
        let first = ThemeManager(defaults: defaults)
        first.preference = .light

        let second = ThemeManager(defaults: defaults)

        XCTAssertEqual(second.preference, .light)
    }

    func test_light_and_dark_preferences_map_to_the_matching_colorScheme() {
        XCTAssertEqual(ThemePreference.light.colorScheme, .light)
        XCTAssertEqual(ThemePreference.dark.colorScheme, .dark)
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd ~/bugsaymis-dyna && xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS' -only-testing:DynaTests/ThemeManagerTests 2>&1 | tail -40`
Expected: FAIL — `ThemeManager`/`ThemePreference` not defined.

- [ ] **Step 3: Implement**

```swift
// Dyna/DesignSystem/ThemePreference.swift
import SwiftUI
import Observation

enum ThemePreference: String, CaseIterable {
    case system, light, dark

    var colorScheme: ColorScheme? {
        switch self {
        case .system: return nil
        case .light: return .light
        case .dark: return .dark
        }
    }

    var label: String {
        switch self {
        case .system: return "System"
        case .light: return "Light"
        case .dark: return "Dark"
        }
    }
}

@MainActor
@Observable
final class ThemeManager {
    private static let userDefaultsKey = "dyna.themePreference"
    private let defaults: UserDefaults

    var preference: ThemePreference {
        didSet { defaults.set(preference.rawValue, forKey: Self.userDefaultsKey) }
    }

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
        let stored = defaults.string(forKey: Self.userDefaultsKey) ?? ThemePreference.system.rawValue
        self.preference = ThemePreference(rawValue: stored) ?? .system
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd ~/bugsaymis-dyna && xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS' -only-testing:DynaTests/ThemeManagerTests 2>&1 | tail -40`
Expected: PASS (4 tests)

- [ ] **Step 5: Commit**

```bash
cd ~/bugsaymis-dyna
git add Dyna/DesignSystem/ThemePreference.swift DynaTests/ThemeManagerTests.swift
git commit -m "feat: add persisted System/Light/Dark theme preference"
```

---

## Task 3: Atlas-mark avatar asset + reusable Avatar view

**Files:**
- Create: `Dyna/Assets.xcassets/DynaMark.imageset/Contents.json`
- Create: `Dyna/Assets.xcassets/DynaMark.imageset/mark.png` (copy of the existing icon source)
- Create: `Dyna/DesignSystem/Avatar.swift`

**Interfaces:**
- Produces: `struct Avatar: View` with `init(size: CGFloat = 20)` — a circular view showing the Dyna mark tinted with `DynaPalette.avatarTint`. Consumed by `ChatView` (Task 4) and `LoginView` (Task 6).

- [ ] **Step 1: Add the image asset**

The existing icon source lives at `Dyna/Assets.xcassets/AppIcon.appiconset/icon_512x512@2x.png` (1024×1024, the highest-res source generated in the earlier icon session). Reuse it as a new standalone imageset so it can be referenced by name from SwiftUI (an `AppIcon.appiconset` entry can't be loaded via `Image("AppIcon")` reliably).

```bash
mkdir -p ~/bugsaymis-dyna/Dyna/Assets.xcassets/DynaMark.imageset
cp ~/bugsaymis-dyna/Dyna/Assets.xcassets/AppIcon.appiconset/icon_512x512@2x.png \
   ~/bugsaymis-dyna/Dyna/Assets.xcassets/DynaMark.imageset/mark.png
```

```json
// Dyna/Assets.xcassets/DynaMark.imageset/Contents.json
{
  "images" : [
    {
      "filename" : "mark.png",
      "idiom" : "universal",
      "scale" : "1x"
    }
  ],
  "info" : {
    "author" : "xcode",
    "version" : 1
  }
}
```

- [ ] **Step 2: Implement the Avatar view**

No unit test — this is a pure SwiftUI layout wrapper around an asset catalog image. Verified visually in Task 8's manual pass, same as the rest of the styling work in this plan.

```swift
// Dyna/DesignSystem/Avatar.swift
import SwiftUI

struct Avatar: View {
    var size: CGFloat = 20

    var body: some View {
        Image("DynaMark")
            .resizable()
            .aspectRatio(contentMode: .fill)
            .frame(width: size, height: size)
            .clipShape(Circle())
            .overlay(Circle().stroke(DynaPalette.avatarTint.opacity(0.3), lineWidth: 0.5))
    }
}
```

- [ ] **Step 3: Regenerate the Xcode project and build**

```bash
cd ~/bugsaymis-dyna && xcodegen generate && xcodebuild -project Dyna.xcodeproj -scheme Dyna -configuration Debug build 2>&1 | tail -20
```

Expected: `** BUILD SUCCEEDED **` — this confirms the new asset and file are picked up correctly.

- [ ] **Step 4: Commit**

```bash
cd ~/bugsaymis-dyna
git add Dyna/Assets.xcassets/DynaMark.imageset Dyna/DesignSystem/Avatar.swift
git commit -m "feat: add reusable Dyna-mark avatar view"
```

---

## Task 4: Markdown rendering for assistant messages

**Files:**
- Modify: `project.yml`
- Create: `Dyna/Chat/AssistantMarkdownView.swift`

**Interfaces:**
- Consumes: `DynaPalette.secondaryText`... no — assistant text color comes from context, not palette, per Step 2 below.
- Produces: `struct AssistantMarkdownView: View` with `init(text: String)`. Consumed by `ChatView` (Task 5).

- [ ] **Step 1: Add the swift-markdown-ui package dependency**

Edit `project.yml`, adding to the top-level `packages:` block (alongside the existing `GoogleSignIn` entry) and to the `Dyna` target's `dependencies:`:

```yaml
packages:
  GoogleSignIn:
    url: https://github.com/google/GoogleSignIn-iOS
    from: 7.0.0
  MarkdownUI:
    url: https://github.com/gonzalezreal/swift-markdown-ui
    from: 2.0.0
```

```yaml
    dependencies:
      - package: GoogleSignIn
        product: GoogleSignIn
      - package: MarkdownUI
        product: MarkdownUI
```

- [ ] **Step 2: Regenerate the project and resolve packages**

```bash
cd ~/bugsaymis-dyna && xcodegen generate && xcodebuild -resolvePackageDependencies -project Dyna.xcodeproj 2>&1 | tail -20
```

Expected: package resolution succeeds, no errors.

- [ ] **Step 3: Implement the wrapper view (no test — thin styling wrapper over a third-party renderer; correctness is "does Markdown actually render," verified in Task 8's manual pass)**

```swift
// Dyna/Chat/AssistantMarkdownView.swift
import SwiftUI
import MarkdownUI

struct AssistantMarkdownView: View {
    let text: String

    var body: some View {
        Markdown(text)
            .markdownTextStyle {
                FontSize(13)
            }
    }
}
```

- [ ] **Step 4: Build to confirm it compiles**

```bash
cd ~/bugsaymis-dyna && xcodebuild -project Dyna.xcodeproj -scheme Dyna -configuration Debug build 2>&1 | tail -20
```

Expected: `** BUILD SUCCEEDED **`

- [ ] **Step 5: Commit**

```bash
cd ~/bugsaymis-dyna
git add project.yml Dyna.xcodeproj Dyna/Chat/AssistantMarkdownView.swift
git commit -m "feat: add swift-markdown-ui and an assistant-message Markdown wrapper"
```

---

## Task 5: Restyle ChatView

**Files:**
- Modify: `Dyna/Chat/ChatView.swift`

**Interfaces:**
- Consumes: `DynaPalette` (Task 1), `Avatar` (Task 3), `AssistantMarkdownView` (Task 4). `ChatViewModel`'s public interface (`messages`, `isSending`, `errorMessage`, `send`) is unchanged — this task only touches the view body.

- [ ] **Step 1: Replace the view body**

No new automated test — `ChatViewModel`'s existing test suite (`ChatViewModelTests.swift`) already covers the logic this view calls into, and that suite is untouched by this task. This step is verified by Task 8's manual pass and the build check below.

```swift
// Dyna/Chat/ChatView.swift
import SwiftUI

struct ChatView: View {
    @State var viewModel: ChatViewModel
    @State private var draft = ""

    var body: some View {
        VStack(spacing: 0) {
            if viewModel.messages.isEmpty {
                emptyState
            } else {
                messageList
            }

            if let error = viewModel.errorMessage {
                Text(error)
                    .foregroundStyle(.red)
                    .font(.callout)
                    .padding(.horizontal)
                    .padding(.top, 4)
            }

            inputBar
        }
        .background(DynaPalette.mainBackground)
    }

    private var emptyState: some View {
        VStack(spacing: 12) {
            Spacer()
            Avatar(size: 40)
            Text("Ask Dyna anything")
                .font(.title3)
                .fontWeight(.semibold)
            Text("Leave trends, headcount, a specific employee or student — Dyna pulls real data from Atlas.")
                .font(.callout)
                .foregroundStyle(DynaPalette.secondaryText)
                .multilineTextAlignment(.center)
                .frame(maxWidth: 320)
            Spacer()
        }
        .frame(maxWidth: .infinity)
    }

    private var messageList: some View {
        ScrollView {
            LazyVStack(alignment: .leading, spacing: 14) {
                ForEach(viewModel.messages) { message in
                    messageRow(message)
                }
                if viewModel.isSending {
                    HStack(spacing: 8) {
                        Avatar(size: 20)
                        ProgressView("Dyna is thinking…")
                    }
                }
            }
            .padding()
        }
    }

    /// NOTE: the shipped app currently has this inverted (assistant bubbles right-aligned,
    /// user bubbles left-aligned) — visible by tracing the original `Spacer` placement in
    /// `ChatView.swift`. That's backwards from every chat convention and from the approved
    /// mockups (user → right, assistant → left + avatar). This corrects it; it's a
    /// deliberate, in-scope fix, not an accidental behavior change.
    @ViewBuilder
    private func messageRow(_ message: Message) -> some View {
        HStack(alignment: .top, spacing: 8) {
            if message.role == .user {
                Spacer(minLength: 0)
            } else {
                Avatar(size: 20)
            }

            Group {
                if message.role == .assistant {
                    AssistantMarkdownView(text: message.text)
                } else {
                    Text(message.text)
                }
            }
            .padding(10)
            .background(message.role == .user ? DynaPalette.accent : DynaPalette.assistantBubbleBackground)
            .foregroundStyle(message.role == .user ? Color.white : Color.primary)
            .clipShape(RoundedRectangle(cornerRadius: 12))
            .frame(maxWidth: 420, alignment: .leading)

            if message.role == .assistant {
                Spacer(minLength: 0)
            }
        }
    }

    private var inputBar: some View {
        HStack {
            TextField("Ask Dyna about leave trends, headcount…", text: $draft)
                .textFieldStyle(.plain)
                .padding(8)
                .background(DynaPalette.assistantBubbleBackground)
                .clipShape(RoundedRectangle(cornerRadius: 8))
                .onSubmit(sendDraft)
            Button("Send", action: sendDraft)
                .tint(DynaPalette.accent)
                .disabled(draft.trimmingCharacters(in: .whitespaces).isEmpty || viewModel.isSending)
        }
        .padding()
        .background(DynaPalette.sidebarBackground)
        .overlay(Rectangle().frame(height: 1).foregroundStyle(DynaPalette.border), alignment: .top)
    }

    private func sendDraft() {
        let text = draft.trimmingCharacters(in: .whitespaces)
        guard !text.isEmpty else { return }
        draft = ""
        Task { await viewModel.send(text) }
    }
}
```

- [ ] **Step 2: Build to confirm it compiles**

```bash
cd ~/bugsaymis-dyna && xcodebuild -project Dyna.xcodeproj -scheme Dyna -configuration Debug build 2>&1 | tail -20
```

Expected: `** BUILD SUCCEEDED **`

- [ ] **Step 3: Run the full existing test suite to confirm no regression**

```bash
cd ~/bugsaymis-dyna && xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS' 2>&1 | tail -40
```

Expected: all existing tests still pass (this task doesn't touch `ChatViewModel`, so its tests must be unaffected).

- [ ] **Step 4: Commit**

```bash
cd ~/bugsaymis-dyna
git add Dyna/Chat/ChatView.swift
git commit -m "feat: restyle ChatView with navy palette, avatar, Markdown, and empty state"
```

---

## Task 6: Restyle ConversationListView

**Files:**
- Modify: `Dyna/Chat/ConversationListView.swift`

**Interfaces:**
- Consumes: `DynaPalette` (Task 1). Public interface (`conversations`, `onSelect`, `onNewConversation`) unchanged.

- [ ] **Step 1: Replace the view body**

No new automated test — pure view restyling, same rationale as Task 5. Verified by the build check and Task 8's manual pass.

```swift
// Dyna/Chat/ConversationListView.swift
import SwiftUI

struct ConversationListView: View {
    let conversations: [ConversationSummary]
    let onSelect: (ConversationSummary) -> Void
    let onNewConversation: () -> Void

    var body: some View {
        List {
            Button(action: onNewConversation) {
                Label("New Conversation", systemImage: "plus.bubble")
            }
            .tint(DynaPalette.accent)

            if conversations.isEmpty {
                Text("No conversations yet")
                    .font(.callout)
                    .foregroundStyle(DynaPalette.secondaryText)
                    .padding(.vertical, 4)
            } else {
                ForEach(conversations) { conversation in
                    Button(conversation.title ?? "Untitled") { onSelect(conversation) }
                        .foregroundStyle(.primary)
                }
            }
        }
        .listStyle(.sidebar)
        .background(DynaPalette.sidebarBackground)
        .navigationTitle("Dyna")
    }
}
```

- [ ] **Step 2: Build to confirm it compiles**

```bash
cd ~/bugsaymis-dyna && xcodebuild -project Dyna.xcodeproj -scheme Dyna -configuration Debug build 2>&1 | tail -20
```

Expected: `** BUILD SUCCEEDED **`

- [ ] **Step 3: Commit**

```bash
cd ~/bugsaymis-dyna
git add Dyna/Chat/ConversationListView.swift
git commit -m "feat: restyle ConversationListView with navy palette and empty state"
```

---

## Task 7: Restyle LoginView

**Files:**
- Modify: `Dyna/Auth/LoginView.swift`

**Interfaces:**
- Consumes: `DynaPalette` (Task 1), `Avatar` (Task 3). `LoginViewModel`'s public interface unchanged.

- [ ] **Step 1: Replace the view body**

No new automated test — pure view restyling; `LoginViewModel`'s existing test suites (`LoginViewModelTests.swift`, `LoginViewModelGoogleSignInTests.swift`) are untouched and keep covering the logic. Verified by build check + Task 8's manual pass.

```swift
// Dyna/Auth/LoginView.swift
import SwiftUI

struct LoginView: View {
    @State private var viewModel = LoginViewModel()
    var onAuthenticated: () -> Void

    var body: some View {
        VStack(spacing: 16) {
            Avatar(size: 48)
            Text("Sign in to Dyna").font(.title2).bold()
            Text("Use your Atlas Account to continue.").foregroundStyle(DynaPalette.secondaryText)

            Button {
                Task {
                    await viewModel.signInWithGoogle()
                    if viewModel.isAuthenticated { onAuthenticated() }
                }
            } label: {
                Label("Sign in with Google", systemImage: "globe")
                    .frame(maxWidth: .infinity)
            }
            .tint(DynaPalette.accent)
            .disabled(viewModel.isLoading)

            Divider().overlay(Text("or").font(.caption).foregroundStyle(DynaPalette.secondaryText))

            TextField("Email", text: $viewModel.email)
                .textContentType(.username)
                .textFieldStyle(.roundedBorder)
            SecureField("Password", text: $viewModel.password)
                .textContentType(.password)
                .textFieldStyle(.roundedBorder)

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
                    Text("Sign In").frame(maxWidth: .infinity)
                }
            }
            .tint(DynaPalette.accent)
            .disabled(viewModel.isLoading || viewModel.email.isEmpty || viewModel.password.isEmpty)
        }
        .padding(32)
        .frame(width: 360)
        .background(DynaPalette.mainBackground)
    }
}
```

- [ ] **Step 2: Build to confirm it compiles**

```bash
cd ~/bugsaymis-dyna && xcodebuild -project Dyna.xcodeproj -scheme Dyna -configuration Debug build 2>&1 | tail -20
```

Expected: `** BUILD SUCCEEDED **`

- [ ] **Step 3: Run the full test suite**

```bash
cd ~/bugsaymis-dyna && xcodebuild test -project Dyna.xcodeproj -scheme Dyna -destination 'platform=macOS' 2>&1 | tail -40
```

Expected: all tests pass, including `LoginViewModelTests` and `LoginViewModelGoogleSignInTests` (unaffected by this task).

- [ ] **Step 4: Commit**

```bash
cd ~/bugsaymis-dyna
git add Dyna/Auth/LoginView.swift
git commit -m "feat: restyle LoginView with navy palette and Dyna-mark branding"
```

---

## Task 8: Wire theme picker into the menu bar + apply the chosen appearance

**Files:**
- Modify: `Dyna/DynaApp.swift`

**Interfaces:**
- Consumes: `ThemeManager`/`ThemePreference` (Task 2). No new interfaces produced — this is the final wiring point.

- [ ] **Step 1: Update `DynaApp`**

No new automated test — `ThemeManager`'s logic is already covered by `ThemeManagerTests` (Task 2); this step only wires it into the view hierarchy, verified by the manual pass in Step 3.

```swift
// Dyna/DynaApp.swift
import SwiftUI
import GoogleSignIn

@main
struct DynaApp: App {
    @State private var isAuthenticated = KeychainStore.loadToken() != nil
    @State private var chatViewModel = ChatViewModel()
    @State private var conversations: [ConversationSummary] = []
    @State private var themeManager = ThemeManager()

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
            .preferredColorScheme(themeManager.preference.colorScheme)
        }

        MenuBarExtra("Dyna", systemImage: "sparkles") {
            Button("Open Dyna") { NSApp.activate(ignoringOtherApps: true) }
            Divider()
            Menu("Appearance") {
                ForEach(ThemePreference.allCases, id: \.self) { preference in
                    Button {
                        themeManager.preference = preference
                    } label: {
                        if themeManager.preference == preference {
                            Label(preference.label, systemImage: "checkmark")
                        } else {
                            Text(preference.label)
                        }
                    }
                }
            }
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

- [ ] **Step 2: Build to confirm it compiles**

```bash
cd ~/bugsaymis-dyna && xcodebuild -project Dyna.xcodeproj -scheme Dyna -configuration Debug build 2>&1 | tail -20
```

Expected: `** BUILD SUCCEEDED **`

- [ ] **Step 3: Manual verification pass**

Run the app (`open ~/bugsaymis-dyna/build/export/Dyna.app` after a fresh build, or `xcodebuild ... build && open` the built product) and confirm, for each theme setting (menu bar icon → Appearance → System/Light/Dark):
- Login screen shows the Dyna mark, navy-accented buttons, works in both light and dark
- Empty chat state shows the mark + welcome copy
- Sending a message shows the user bubble in navy, right-aligned, with the assistant bubble on the left in the light-gray/dark-slate background and the avatar next to it (confirms the alignment fix in Task 5 actually took — the original app had this backwards)
- A multi-line/list-formatted assistant answer renders as actual Markdown (bold, bullets) — ask something you know returns a longer answer, or temporarily hardcode a test message with `**bold**` and `- a bullet` to confirm rendering, then remove the hardcode
- Sidebar shows the empty state when there are no conversations, and lists conversations correctly once some exist
- System appearance change (macOS System Settings → Appearance) is reflected live when theme preference is set to "System"

- [ ] **Step 4: Commit**

```bash
cd ~/bugsaymis-dyna
git add Dyna/DynaApp.swift
git commit -m "feat: wire System/Light/Dark theme picker into the menu bar"
```

---

## Self-Review Notes

- **Spec coverage:** Section 1 (visual design, palette, theming, restyle of all four views, branding) → Tasks 1, 3, 5, 6, 7, 8. Section 2 (Markdown rendering) → Task 4, consumed in Task 5. Section 3 (full-profile backend) is explicitly out of scope for this plan per the Global Constraints note.
- **Placeholder scan:** no TBD/TODO; every step has real code or a concrete manual-verification checklist (used only where the codebase has no existing pattern for testing pure SwiftUI layout — consistent with the existing test suite, which only covers view models, never view bodies).
- **Type consistency:** `DynaPalette` (Task 1) → consumed by name in Tasks 5, 6, 7. `Avatar` (Task 3) → consumed in Tasks 5, 7. `AssistantMarkdownView` (Task 4) → consumed in Task 5. `ThemeManager`/`ThemePreference` (Task 2) → consumed in Task 8. No signature mismatches between produce/consume sides.
