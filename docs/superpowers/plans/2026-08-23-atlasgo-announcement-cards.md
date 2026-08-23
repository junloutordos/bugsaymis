# AtlasGo Announcement Cards Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace AtlasGo's plain-text 3-item announcement teaser with swipeable square poster cards (capped at 5) on the dashboard, each tappable to open a detail modal with the full announcement, plus a "See all" entry point into a new paginated full-history list page whose tiles are also tappable into the same detail modal.

**Architecture:** Two small backend additions (a paginated `GET /api/mobile/notices/history` endpoint returning read+unread announcements with an `is_read` flag, and a Sanctum-reachable `GET /api/mobile/media/{path}` proxy reusing the existing `StorageProxyController` used by web) support three new/rewritten Flutter pieces: a shared `AnnouncementPosterImage` widget (authenticated poster loading via the new proxy), a shared `AnnouncementDetailSheet` modal (used by both the dashboard card and the list page — this is the piece added mid-plan per explicit follow-up request: tapping any announcement card or tile must open this modal with the full details), and the dashboard card itself rewritten into a swipeable `PageView` of square cards. A new `AnnouncementListScreen` (paginated grid, infinite scroll) is reachable via a new `/announcements` route.

**Tech Stack:** Laravel 12 / PHP 8.4, PHPUnit (`RefreshDatabase`), Flutter (Riverpod 2, go_router, dio, cached_network_image — already a pubspec dependency, unused elsewhere in the app until this plan), `flutter_test`.

**Spec:** `docs/superpowers/specs/2026-08-23-announcements-sos-atlasgo-enhancements-design.md` (Section 3 — "AtlasGo: swipeable square announcement cards + full history page"), extended per user follow-up: both the dashboard card and the list page must be tappable into a detail modal (not left as a TODO/"reuse `notice_queue_dialog.dart`'s pattern if adequate" as the spec originally hedged — this plan builds a dedicated, purpose-built detail sheet instead, since `notice_queue_dialog.dart`'s `NoticeQueueDialog` is a forced, non-dismissible onboarding flow tied to acknowledgment, not a freely-dismissible "view details" affordance).

## Global Constraints

- Full history (read + unread), not unread-only — explicitly decided during design approval.
- Card cap is exactly 5 on the dashboard (`data.announcements.take(5)`), regardless of how many are pending.
- Poster images go through the private-S3-via-proxy pattern (`StorageProxyController`) — never a direct S3 URL, matching the project-wide rule in `CLAUDE.md`.
- Reuse existing design tokens (`AppColors`, `AppRadius`, `AppSpacing`, `AppMotion`, `AppTextStyles`) from `lib/src/core/theme.dart` — no new design system introduced.
- `cached_network_image` is already a pubspec dependency (`pubspec.yaml`) — no dependency changes needed anywhere in this plan.

---

### Task 1: Backend — `GET /api/mobile/notices/history`

**Files:**
- Modify: `app/Http/Controllers/StudentAttendance/Api/NoticeController.php` (add `history()` method)
- Modify: `routes/api.php` (add route)
- Modify: `tests/Feature/Mobile/MobileNoticeControllerTest.php` (add 2 tests)

**Interfaces:**
- Produces: `GET /api/mobile/notices/history?page=N` (route name `mobile.notices.history`) → Laravel paginator JSON `{ data: [{id, title, body, poster_path, published_at, is_read}, ...], current_page, next_page_url, ... }`, page size 15. Consumed by Task 6 (Flutter `AnnouncementListItem`/`AnnouncementListScreen`).

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Mobile/MobileNoticeControllerTest.php`, inside the class, after `test_parent_cannot_acknowledge_an_announcement_not_addressed_to_parents`:

```php
    public function test_history_returns_all_published_announcements_for_the_audience_group_with_read_flag(): void
    {
        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-HIST-1', 'firstname' => 'Hist', 'lastname' => 'Student',
        ]);
        StudentCredential::create([
            'student_id' => $studentId, 'email' => 'histstudent@example.com',
            'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $student = Student::find($studentId);
        $token = $student->createToken('device', ['mobile'])->plainTextToken;

        $creator = User::factory()->create();
        $read = Announcement::create([
            'title' => 'Already Read', 'body' => 'Body', 'audience' => 'students',
            'status' => 'published', 'published_at' => now()->subDay(), 'created_by' => $creator->id,
        ]);
        Announcement::create([
            'title' => 'Still Unread', 'body' => 'Body', 'audience' => 'students',
            'status' => 'published', 'published_at' => now(), 'created_by' => $creator->id,
        ]);
        Announcement::create([
            'title' => 'For Parents', 'body' => 'Body', 'audience' => 'parents',
            'status' => 'published', 'published_at' => now(), 'created_by' => $creator->id,
        ]);
        $read->acknowledgeFor($student);

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/mobile/notices/history');

        $response->assertOk();
        $items = collect($response->json('data'));
        $this->assertCount(2, $items);
        $this->assertTrue($items->firstWhere('title', 'Already Read')['is_read']);
        $this->assertFalse($items->firstWhere('title', 'Still Unread')['is_read']);
        $this->assertNull($items->firstWhere('title', 'For Parents'));
    }

    public function test_history_is_paginated_at_15_per_page(): void
    {
        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-HIST-2', 'firstname' => 'Page', 'lastname' => 'Student',
        ]);
        StudentCredential::create([
            'student_id' => $studentId, 'email' => 'pagestudent@example.com',
            'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $token = Student::find($studentId)->createToken('device', ['mobile'])->plainTextToken;

        $creator = User::factory()->create();
        for ($i = 0; $i < 20; $i++) {
            Announcement::create([
                'title' => "Announcement {$i}", 'body' => 'Body', 'audience' => 'students',
                'status' => 'published', 'published_at' => now()->subMinutes($i), 'created_by' => $creator->id,
            ]);
        }

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/mobile/notices/history');

        $response->assertOk();
        $this->assertCount(15, $response->json('data'));
        $this->assertSame(20, $response->json('total'));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=MobileNoticeControllerTest"
```

Expected: the two new tests FAIL (route `mobile.notices.history` / `history()` method don't exist yet).

- [ ] **Step 3: Add the route**

In `routes/api.php`, inside the `auth:sanctum` group (immediately after the existing `notices.acknowledge` route, `routes/api.php:61-65`):

```php
        Route::get('/notices/pending', [NoticeController::class, 'pending'])->name('notices.pending');
        Route::post('/notices/{type}/{id}/acknowledge', [NoticeController::class, 'acknowledge'])
            ->whereIn('type', ['announcement', 'emergency-alert'])
            ->whereNumber('id')
            ->name('notices.acknowledge');
        Route::get('/notices/history', [NoticeController::class, 'history'])->name('notices.history');
```

- [ ] **Step 4: Add the controller method**

In `app/Http/Controllers/StudentAttendance/Api/NoticeController.php`, add a new public method right after `pending()`:

```php
    public function history(Request $request): JsonResponse
    {
        $recipient = $request->user();
        $group = $recipient instanceof Student ? 'students' : 'parents';

        $paginator = Announcement::visibleToAudienceGroup($group)
            ->with(['acknowledgments' => fn ($q) => $q
                ->where('recipient_type', get_class($recipient))
                ->where('recipient_id', $recipient->getKey())])
            ->orderByDesc('published_at')
            ->paginate(15);

        $paginator->getCollection()->transform(fn (Announcement $a) => [
            'id'           => $a->id,
            'title'        => $a->title,
            'body'         => $a->body,
            'poster_path'  => $a->poster_path,
            'published_at' => $a->published_at?->toIso8601String(),
            'is_read'      => $a->acknowledgments->isNotEmpty(),
        ]);

        return response()->json($paginator);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=MobileNoticeControllerTest"
```

Expected: all tests in `MobileNoticeControllerTest` PASS, including the 2 new ones.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/StudentAttendance/Api/NoticeController.php routes/api.php tests/Feature/Mobile/MobileNoticeControllerTest.php
git commit -m "feat(mobile-api): add paginated GET /api/mobile/notices/history"
```

---

### Task 2: Backend — mobile media proxy route

**Files:**
- Modify: `routes/api.php` (add route + `use` import)
- Create: `tests/Feature/Mobile/MobileMediaProxyTest.php`

**Interfaces:**
- Produces: `GET /api/mobile/media/{path}` (route name `mobile.media`) → streams a private S3 file, reusing `App\Http\Controllers\StorageProxyController::serve()` exactly as-is (`app/Http/Controllers/StorageProxyController.php:17-56`), now reachable via `auth:sanctum` in addition to the existing session-only `/media/{path}` used by web. Consumed by Task 4 (Flutter `AnnouncementPosterImage`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Mobile/MobileMediaProxyTest.php`:

```php
<?php

namespace Tests\Feature\Mobile;

use App\Models\Student;
use App\Models\StudentCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MobileMediaProxyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['opentelemetry.user_context' => false]);
    }

    public function test_serves_a_private_s3_file_to_a_sanctum_authenticated_student(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('announcements/test.jpg', 'fake-image-bytes');

        $studentId = DB::table('students')->insertGetId([
            'pisaysystemID' => 'TEST-MEDIA-1', 'firstname' => 'Media', 'lastname' => 'Student',
        ]);
        StudentCredential::create([
            'student_id' => $studentId, 'email' => 'mediastudent@example.com',
            'password' => bcrypt('x'), 'status' => 'active', 'email_verified_at' => now(),
        ]);
        $token = Student::find($studentId)->createToken('device', ['mobile'])->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/mobile/media/announcements/test.jpg');

        $response->assertOk();
        $this->assertSame('fake-image-bytes', $response->streamedContent());
    }

    public function test_rejects_an_unauthenticated_request(): void
    {
        $this->get('/api/mobile/media/announcements/test.jpg')->assertUnauthorized();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=MobileMediaProxyTest"
```

Expected: FAIL (route `/api/mobile/media/{path}` doesn't exist — 404).

- [ ] **Step 3: Add the route**

In `routes/api.php`, add the import at the top next to the existing `NoticeController` import (line 15):

```php
use App\Http\Controllers\StudentAttendance\Api\NoticeController;
use App\Http\Controllers\StorageProxyController;
```

Then, in the `auth:sanctum` group, right after the `notices.history` route added in Task 1:

```php
        Route::get('/notices/history', [NoticeController::class, 'history'])->name('notices.history');

        // Private-S3-via-proxy for mobile — reuses the exact same controller
        // method the web /media/{path} route uses (routes/web.php:279), just
        // reachable via Sanctum instead of the session guard mobile can't use.
        Route::get('/media/{path}', [StorageProxyController::class, 'serve'])
            ->where('path', '.+')
            ->name('media');
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=MobileMediaProxyTest"
```

Expected: both tests PASS.

- [ ] **Step 5: Commit**

```bash
git add routes/api.php tests/Feature/Mobile/MobileMediaProxyTest.php
git commit -m "feat(mobile-api): add Sanctum-reachable media proxy for AtlasGo posters"
```

---

### Task 3: Flutter — `AnnouncementListItem` model

**Files:**
- Create: `lib/src/features/notices/announcement_list_item.dart`
- Create: `test/features/notices/announcement_list_item_test.dart`

**Interfaces:**
- Produces: `class AnnouncementListItem { final int id; final String title; final String body; final String? posterPath; final DateTime? publishedAt; final bool isRead; }` with `AnnouncementListItem.fromJson(Map<String, dynamic>)`. Consumed by Task 7 (`AnnouncementListScreen`).

- [ ] **Step 1: Write the failing test**

Create `test/features/notices/announcement_list_item_test.dart`:

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/features/notices/announcement_list_item.dart';

void main() {
  test('parses a full history item from JSON', () {
    final item = AnnouncementListItem.fromJson({
      'id': 5,
      'title': 'Foundation Day',
      'body': 'Classes suspended campus-wide.',
      'poster_path': 'announcements/5_123.jpg',
      'published_at': '2026-08-20T08:00:00+00:00',
      'is_read': true,
    });

    expect(item.id, 5);
    expect(item.title, 'Foundation Day');
    expect(item.posterPath, 'announcements/5_123.jpg');
    expect(item.publishedAt, DateTime.parse('2026-08-20T08:00:00+00:00'));
    expect(item.isRead, isTrue);
  });

  test('handles a null poster_path and published_at', () {
    final item = AnnouncementListItem.fromJson({
      'id': 6, 'title': 'No Poster', 'body': 'Body', 'poster_path': null,
      'published_at': null, 'is_read': false,
    });

    expect(item.posterPath, isNull);
    expect(item.publishedAt, isNull);
    expect(item.isRead, isFalse);
  });
}
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
cd /Users/junlou/bugsaymis-mobile && flutter test test/features/notices/announcement_list_item_test.dart
```

Expected: FAIL (`announcement_list_item.dart` doesn't exist).

- [ ] **Step 3: Create the model**

Create `lib/src/features/notices/announcement_list_item.dart`:

```dart
class AnnouncementListItem {
  final int id;
  final String title;
  final String body;
  final String? posterPath;
  final DateTime? publishedAt;
  final bool isRead;

  AnnouncementListItem({
    required this.id,
    required this.title,
    required this.body,
    required this.isRead,
    this.posterPath,
    this.publishedAt,
  });

  factory AnnouncementListItem.fromJson(Map<String, dynamic> json) => AnnouncementListItem(
        id: json['id'] as int,
        title: json['title'] as String,
        body: (json['body'] ?? '') as String,
        posterPath: json['poster_path'] as String?,
        publishedAt: json['published_at'] != null
            ? DateTime.tryParse(json['published_at'] as String)
            : null,
        isRead: json['is_read'] as bool? ?? false,
      );

  AnnouncementListItem copyWithRead() => AnnouncementListItem(
        id: id, title: title, body: body, posterPath: posterPath, publishedAt: publishedAt, isRead: true,
      );
}
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
cd /Users/junlou/bugsaymis-mobile && flutter test test/features/notices/announcement_list_item_test.dart
```

Expected: both tests PASS.

- [ ] **Step 5: Commit**

```bash
cd /Users/junlou/bugsaymis-mobile && git add lib/src/features/notices/announcement_list_item.dart test/features/notices/announcement_list_item_test.dart
git commit -m "feat(notices): add AnnouncementListItem model for the history endpoint"
```

---

### Task 4: Flutter — `AnnouncementPosterImage` (authenticated poster loading)

**Files:**
- Modify: `lib/src/features/notices/notices_provider.dart` (add `posterAuthHeadersProvider`)
- Create: `lib/src/features/notices/announcement_poster_image.dart`
- Create: `test/features/notices/announcement_poster_image_test.dart`

**Interfaces:**
- Consumes: `AppConstants.tokenKey` (`lib/src/core/constants.dart:42`), `AppConstants.baseUrl` (`lib/src/core/constants.dart:12-27`).
- Produces: `final posterAuthHeadersProvider = FutureProvider<Map<String, String>>(...)`, and `class AnnouncementPosterImage extends ConsumerWidget { final String posterPath; final BoxFit fit; ... }`. Consumed by Task 6 (`AnnouncementsCard`) and Task 7 (`AnnouncementListScreen`), and by Task 5's `AnnouncementDetailSheet`.

Testing note: `CachedNetworkImage`'s actual network fetch is not exercised here — there is no existing image-mocking pattern anywhere in this app to build on, and adding one is out of scope for this plan. The tests below cover the two logic branches this widget owns (headers still loading, headers failed) without ever reaching a real network call; the "image actually renders" path is verified manually in Step 5 below, on a real device/simulator, per this project's stated policy that UI correctness for things the test harness can't cover is verified in the real app, not just asserted.

- [ ] **Step 1: Write the failing tests**

Create `test/features/notices/announcement_poster_image_test.dart`:

```dart
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:atlasgo/src/features/notices/announcement_poster_image.dart';
import 'package:atlasgo/src/features/notices/notices_provider.dart';

void main() {
  testWidgets('shows a placeholder, not CachedNetworkImage, while auth headers are still resolving', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          posterAuthHeadersProvider.overrideWith((ref) => Completer<Map<String, String>>().future),
        ],
        child: const MaterialApp(
          home: Scaffold(body: AnnouncementPosterImage(posterPath: 'announcements/1.jpg')),
        ),
      ),
    );

    expect(find.byType(CachedNetworkImage), findsNothing);
  });

  testWidgets('falls back to the campaign-icon placeholder when auth headers fail to load', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          posterAuthHeadersProvider.overrideWith((ref) => Future<Map<String, String>>.error('no token')),
        ],
        child: const MaterialApp(
          home: Scaffold(body: AnnouncementPosterImage(posterPath: 'announcements/1.jpg')),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(find.byIcon(Icons.campaign_rounded), findsOneWidget);
    expect(find.byType(CachedNetworkImage), findsNothing);
  });
}
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
cd /Users/junlou/bugsaymis-mobile && flutter test test/features/notices/announcement_poster_image_test.dart
```

Expected: FAIL (`announcement_poster_image.dart` and `posterAuthHeadersProvider` don't exist yet).

- [ ] **Step 3: Add `posterAuthHeadersProvider`**

In `lib/src/features/notices/notices_provider.dart`, add the following imports at the top:

```dart
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../../core/api_client.dart';
import '../../core/constants.dart';
```

(`flutter_riverpod` and `../../core/api_client.dart` are already imported in this file — only add `flutter_secure_storage` and `../../core/constants.dart` if not already present.)

Then add, after the existing `noticesProvider` declaration (`lib/src/features/notices/notices_provider.dart:48-61`):

```dart
/// Bearer token as HTTP headers, for authenticated poster image requests —
/// CachedNetworkImage takes a plain header map, not a Dio interceptor, so
/// this duplicates the one line of ApiClient's auth logic that images need.
final posterAuthHeadersProvider = FutureProvider<Map<String, String>>((ref) async {
  const storage = FlutterSecureStorage();
  final token = await storage.read(key: AppConstants.tokenKey);
  return token != null ? {'Authorization': 'Bearer $token'} : {};
});
```

- [ ] **Step 4: Create the widget**

Create `lib/src/features/notices/announcement_poster_image.dart`:

```dart
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/constants.dart';
import '../../core/theme.dart';
import 'notices_provider.dart';

/// Renders an announcement poster stored in private S3, via the
/// Sanctum-reachable proxy (GET /api/mobile/media/{path}) — never a direct
/// S3 URL. Falls back to a neutral campaign-icon tile if the poster fails
/// to load or auth headers can't be resolved.
class AnnouncementPosterImage extends ConsumerWidget {
  final String posterPath;
  final BoxFit fit;

  const AnnouncementPosterImage({super.key, required this.posterPath, this.fit = BoxFit.cover});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final headersAsync = ref.watch(posterAuthHeadersProvider);

    return headersAsync.when(
      loading: () => _placeholder(),
      error: (_, _) => _placeholder(),
      data: (headers) => CachedNetworkImage(
        imageUrl: '${AppConstants.baseUrl}/media/$posterPath',
        httpHeaders: headers,
        fit: fit,
        placeholder: (_, _) => _placeholder(),
        errorWidget: (_, _, _) => _placeholder(),
      ),
    );
  }

  Widget _placeholder() => Container(
        color: AppColors.accentBg,
        child: const Center(
          child: Icon(Icons.campaign_rounded, color: AppColors.accent, size: 32),
        ),
      );
}
```

- [ ] **Step 5: Run the tests to verify they pass**

```bash
cd /Users/junlou/bugsaymis-mobile && flutter test test/features/notices/announcement_poster_image_test.dart
```

Expected: both tests PASS.

Manual verification (real network image load, not covered by the tests above): once Task 6 wires this widget into the dashboard card, run the app against a real backend with a published announcement that has a poster, and confirm the actual image renders on a device/simulator — this is the "real render" check the automated tests intentionally don't attempt.

- [ ] **Step 6: Commit**

```bash
cd /Users/junlou/bugsaymis-mobile && git add lib/src/features/notices/notices_provider.dart lib/src/features/notices/announcement_poster_image.dart test/features/notices/announcement_poster_image_test.dart
git commit -m "feat(notices): add AnnouncementPosterImage with Sanctum-authenticated poster loading"
```

---

### Task 5: Flutter — `AnnouncementDetailSheet` (shared detail modal)

**Files:**
- Create: `lib/src/features/notices/announcement_detail_sheet.dart`
- Create: `test/features/notices/announcement_detail_sheet_test.dart`

**Interfaces:**
- Consumes: `AnnouncementPosterImage` (Task 4), design tokens from `lib/src/core/theme.dart`.
- Produces: `Future<void> showAnnouncementDetail(BuildContext context, {required String title, required String body, String? posterPath, DateTime? publishedAt, bool isRead = true, Future<void> Function()? onAcknowledge})`. Consumed by Task 6 (`AnnouncementsCard`) and Task 7 (`AnnouncementListScreen`) — **this is the piece that makes both the dashboard card and the list-page tiles clickable into a details view**, per the explicit follow-up request added mid-plan.

This is a freely-dismissible, user-initiated "view details" sheet — distinct from `notice_queue_dialog.dart`'s `NoticeQueueDialog`, which is a forced, non-dismissible onboarding flow (`barrierDismissible: false`, `PopScope(canPop: false)`) shown automatically for unread items. This sheet is opened only by an explicit tap, can be dismissed by dragging down or tapping outside, and only shows an acknowledge action when `isRead` is `false` and a callback is supplied.

- [ ] **Step 1: Write the failing tests**

Create `test/features/notices/announcement_detail_sheet_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/features/notices/announcement_detail_sheet.dart';

void main() {
  testWidgets('renders title, formatted date, and body; hides the acknowledge button when already read', (tester) async {
    await tester.pumpWidget(MaterialApp(home: Scaffold(body: Builder(
      builder: (context) => ElevatedButton(
        onPressed: () => showAnnouncementDetail(
          context,
          title: 'Foundation Day',
          body: 'Classes suspended campus-wide.',
          publishedAt: DateTime(2026, 8, 20),
          isRead: true,
        ),
        child: const Text('open'),
      ),
    ))));

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.text('Foundation Day'), findsOneWidget);
    expect(find.text('Classes suspended campus-wide.'), findsOneWidget);
    expect(find.text('August 20, 2026'), findsOneWidget);
    expect(find.widgetWithText(ElevatedButton, 'Mark as Read'), findsNothing);
  });

  testWidgets('shows a Mark as Read button that calls onAcknowledge and closes the sheet when unread', (tester) async {
    var acknowledged = false;

    await tester.pumpWidget(MaterialApp(home: Scaffold(body: Builder(
      builder: (context) => ElevatedButton(
        onPressed: () => showAnnouncementDetail(
          context,
          title: 'Unread Notice',
          body: 'Body text.',
          isRead: false,
          onAcknowledge: () async { acknowledged = true; },
        ),
        child: const Text('open'),
      ),
    ))));

    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    await tester.tap(find.widgetWithText(ElevatedButton, 'Mark as Read'));
    await tester.pumpAndSettle();

    expect(acknowledged, isTrue);
    expect(find.text('Unread Notice'), findsNothing);
  });
}
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
cd /Users/junlou/bugsaymis-mobile && flutter test test/features/notices/announcement_detail_sheet_test.dart
```

Expected: FAIL (`announcement_detail_sheet.dart` doesn't exist).

- [ ] **Step 3: Create the widget**

Create `lib/src/features/notices/announcement_detail_sheet.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../../core/theme.dart';
import 'announcement_poster_image.dart';

/// Opens a freely-dismissible bottom sheet with an announcement's full
/// details — used by both the dashboard swipeable cards and the full
/// history list's tiles, so tapping either always shows the same view.
Future<void> showAnnouncementDetail(
  BuildContext context, {
  required String title,
  required String body,
  String? posterPath,
  DateTime? publishedAt,
  bool isRead = true,
  Future<void> Function()? onAcknowledge,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (_) => AnnouncementDetailSheet(
      title: title,
      body: body,
      posterPath: posterPath,
      publishedAt: publishedAt,
      isRead: isRead,
      onAcknowledge: onAcknowledge,
    ),
  );
}

class AnnouncementDetailSheet extends StatelessWidget {
  final String title;
  final String body;
  final String? posterPath;
  final DateTime? publishedAt;
  final bool isRead;
  final Future<void> Function()? onAcknowledge;

  const AnnouncementDetailSheet({
    super.key,
    required this.title,
    required this.body,
    this.posterPath,
    this.publishedAt,
    this.isRead = true,
    this.onAcknowledge,
  });

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.6,
      minChildSize: 0.35,
      maxChildSize: 0.92,
      expand: false,
      builder: (context, scrollController) => Container(
        decoration: const BoxDecoration(
          color: AppColors.surface,
          borderRadius: BorderRadius.vertical(top: Radius.circular(AppRadius.sheet)),
        ),
        child: ListView(
          controller: scrollController,
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          children: [
            Center(
              child: Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(color: AppColors.border, borderRadius: BorderRadius.circular(2)),
              ),
            ),
            const SizedBox(height: 16),
            if (posterPath != null) ...[
              ClipRRect(
                borderRadius: BorderRadius.circular(AppRadius.card),
                child: AspectRatio(
                  aspectRatio: 16 / 9,
                  child: AnnouncementPosterImage(posterPath: posterPath!),
                ),
              ),
              const SizedBox(height: 16),
            ],
            Text(title, style: AppTextStyles.sectionHeader),
            if (publishedAt != null) ...[
              const SizedBox(height: 4),
              Text(DateFormat('MMMM d, yyyy').format(publishedAt!), style: AppTextStyles.caption),
            ],
            const SizedBox(height: 12),
            Text(body, style: AppTextStyles.body),
            const SizedBox(height: 20),
            if (!isRead && onAcknowledge != null)
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () async {
                    await onAcknowledge!();
                    if (context.mounted) Navigator.of(context).pop();
                  },
                  child: const Text('Mark as Read'),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
cd /Users/junlou/bugsaymis-mobile && flutter test test/features/notices/announcement_detail_sheet_test.dart
```

Expected: both tests PASS.

- [ ] **Step 5: Commit**

```bash
cd /Users/junlou/bugsaymis-mobile && git add lib/src/features/notices/announcement_detail_sheet.dart test/features/notices/announcement_detail_sheet_test.dart
git commit -m "feat(notices): add shared AnnouncementDetailSheet, opened on tap from card and list"
```

---

### Task 6: Flutter — rewrite `AnnouncementsCard` into swipeable square cards

**Files:**
- Modify: `lib/src/features/notices/announcements_card.dart` (full rewrite)
- Create: `test/features/notices/announcements_card_test.dart`

**Interfaces:**
- Consumes: `noticesProvider`/`NoticeItem` (existing, unchanged — `lib/src/features/notices/notices_provider.dart:4-61`), `AnnouncementPosterImage` (Task 4), `showAnnouncementDetail` (Task 5), `acknowledgeNotice` (existing, `lib/src/features/notices/notices_provider.dart:63-64`).
- Produces: `AnnouncementsCard` keeps its existing zero-argument constructor (`const AnnouncementsCard()`) — call sites in `lib/src/features/home/home_screen.dart:48` and `lib/src/features/student/student_dashboard_screen.dart:62` need no changes.

- [ ] **Step 1: Write the failing test**

Create `test/features/notices/announcements_card_test.dart`:

```dart
import 'dart:convert';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:atlasgo/src/core/api_client.dart';
import 'package:atlasgo/src/features/notices/announcements_card.dart';

class _StaticAdapter implements HttpClientAdapter {
  final String body;
  _StaticAdapter(this.body);

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
  testWidgets('caps the swipeable cards at 5, and See all navigates to /announcements', (tester) async {
    final apiClient = ApiClient();
    apiClient.dio.httpClientAdapter = _StaticAdapter(jsonEncode({
      'emergency_alerts': [],
      'announcements': List.generate(7, (i) => {
            'id': i, 'title': 'Announcement $i', 'body': 'Body $i', 'poster_path': null,
          }),
    }));

    final router = GoRouter(routes: [
      GoRoute(path: '/', builder: (c, s) => const AnnouncementsCard()),
      GoRoute(path: '/announcements', builder: (c, s) => const Scaffold(body: Text('List Page'))),
    ]);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(apiClient)],
        child: MaterialApp.router(routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Announcement 0'), findsOneWidget);
    expect(find.text('Announcement 5'), findsNothing);
    expect(find.text('Announcement 6'), findsNothing);

    await tester.tap(find.text('See all'));
    await tester.pumpAndSettle();

    expect(find.text('List Page'), findsOneWidget);
  });

  testWidgets('tapping a card opens the detail sheet for that announcement', (tester) async {
    final apiClient = ApiClient();
    apiClient.dio.httpClientAdapter = _StaticAdapter(jsonEncode({
      'emergency_alerts': [],
      'announcements': [
        {'id': 1, 'title': 'Tap Me', 'body': 'Full body text.', 'poster_path': null},
      ],
    }));

    final router = GoRouter(routes: [
      GoRoute(path: '/', builder: (c, s) => const AnnouncementsCard()),
      GoRoute(path: '/announcements', builder: (c, s) => const Scaffold(body: Text('List Page'))),
    ]);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(apiClient)],
        child: MaterialApp.router(routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Tap Me'));
    await tester.pumpAndSettle();

    expect(find.text('Full body text.'), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
cd /Users/junlou/bugsaymis-mobile && flutter test test/features/notices/announcements_card_test.dart
```

Expected: FAIL — current `AnnouncementsCard` renders plain title text with no "See all" control and takes only 3 items, not tappable.

- [ ] **Step 3: Rewrite the widget**

Replace the full contents of `lib/src/features/notices/announcements_card.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../core/theme.dart';
import '../../shared/widgets/app_card.dart';
import 'announcement_detail_sheet.dart';
import 'announcement_poster_image.dart';
import 'notices_provider.dart';

class AnnouncementsCard extends ConsumerWidget {
  const AnnouncementsCard({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final notices = ref.watch(noticesProvider);

    return notices.when(
      loading: () => const SizedBox.shrink(),
      error: (_, _) => const SizedBox.shrink(),
      data: (data) {
        if (data.announcements.isEmpty) return const SizedBox.shrink();
        final items = data.announcements.take(5).toList();

        return AppCard(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Text('Announcements', style: AppTextStyles.cardTitle),
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                    decoration: BoxDecoration(
                      color: AppColors.accentBg,
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      '${data.announcements.length}',
                      style: AppTextStyles.custom(fontSize: 11, fontWeight: FontWeight.w600, color: AppColors.accent),
                    ),
                  ),
                  const Spacer(),
                  GestureDetector(
                    onTap: () => context.push('/announcements'),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text('See all',
                            style: AppTextStyles.custom(
                                fontSize: 12, fontWeight: FontWeight.w600, color: AppColors.accent)),
                        const Icon(Icons.chevron_right_rounded, size: 16, color: AppColors.accent),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              SizedBox(
                height: 190,
                child: PageView.builder(
                  controller: PageController(viewportFraction: 0.7),
                  itemCount: items.length,
                  itemBuilder: (context, i) {
                    final item = items[i];
                    return Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 6),
                      child: _AnnouncementSquareCard(
                        title: item.title,
                        posterPath: item.posterPath,
                        onTap: () => showAnnouncementDetail(
                          context,
                          title: item.title,
                          body: item.body,
                          posterPath: item.posterPath,
                          isRead: false,
                          onAcknowledge: () async {
                            await acknowledgeNotice(ref.read(apiClientProvider), item);
                            // Without this, the dashboard card keeps showing the
                            // now-acknowledged item until the screen fully
                            // rebuilds — same gotcha already fixed once for the
                            // forced notice queue (notice_queue_dialog.dart:38-41).
                            ref.invalidate(noticesProvider);
                          },
                        ),
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _AnnouncementSquareCard extends StatelessWidget {
  final String title;
  final String? posterPath;
  final VoidCallback onTap;

  const _AnnouncementSquareCard({required this.title, required this.posterPath, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AspectRatio(
        aspectRatio: 1,
        child: ClipRRect(
          borderRadius: BorderRadius.circular(AppRadius.card),
          child: Stack(
            fit: StackFit.expand,
            children: [
              if (posterPath != null)
                AnnouncementPosterImage(posterPath: posterPath!)
              else
                Container(
                  color: AppColors.accentBg,
                  child: const Center(
                    child: Icon(Icons.campaign_rounded, color: AppColors.accent, size: 40),
                  ),
                ),
              Positioned(
                left: 0,
                right: 0,
                bottom: 0,
                child: Container(
                  padding: const EdgeInsets.all(10),
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [Colors.transparent, Color(0xCC0F172A)],
                    ),
                  ),
                  child: Text(
                    title,
                    style: AppTextStyles.custom(fontSize: 13, fontWeight: FontWeight.w700, color: Colors.white),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
cd /Users/junlou/bugsaymis-mobile && flutter test test/features/notices/announcements_card_test.dart
```

Expected: both tests PASS.

- [ ] **Step 5: Run the full existing notices test suite to check for regressions**

```bash
cd /Users/junlou/bugsaymis-mobile && flutter test test/features/notices/
```

Expected: all tests PASS, including the pre-existing `notices_provider_test.dart` (untouched by this task) and every test added in Tasks 3-6.

- [ ] **Step 6: Commit**

```bash
cd /Users/junlou/bugsaymis-mobile && git add lib/src/features/notices/announcements_card.dart test/features/notices/announcements_card_test.dart
git commit -m "feat(notices): redesign AnnouncementsCard into swipeable square poster cards"
```

---

### Task 7: Flutter — `AnnouncementListScreen` (paginated full history)

**Files:**
- Create: `lib/src/features/notices/announcement_list_screen.dart`
- Create: `test/features/notices/announcement_list_screen_test.dart`

**Interfaces:**
- Consumes: `GET /notices/history` (Task 1), `AnnouncementListItem` (Task 3), `AnnouncementPosterImage` (Task 4), `showAnnouncementDetail` (Task 5).
- Produces: `class AnnouncementListScreen extends ConsumerStatefulWidget`. Consumed by Task 8 (router wiring).

- [ ] **Step 1: Write the failing tests**

Create `test/features/notices/announcement_list_screen_test.dart`:

```dart
import 'dart:convert';
import 'dart:typed_data';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/core/api_client.dart';
import 'package:atlasgo/src/features/notices/announcement_list_screen.dart';

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

Map<String, dynamic> _item(int id) =>
    {'id': id, 'title': 'Item $id', 'body': 'Body $id', 'poster_path': null, 'published_at': null, 'is_read': false};

void main() {
  testWidgets('shows an empty state when there are no announcements', (tester) async {
    final apiClient = ApiClient();
    apiClient.dio.httpClientAdapter =
        _SequenceAdapter([jsonEncode({'data': [], 'next_page_url': null})]);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(apiClient)],
        child: const MaterialApp(home: AnnouncementListScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('No announcements yet.'), findsOneWidget);
  });

  testWidgets('loads the first page and tapping a tile opens the detail sheet', (tester) async {
    final apiClient = ApiClient();
    apiClient.dio.httpClientAdapter = _SequenceAdapter([
      jsonEncode({'data': [_item(1)], 'next_page_url': null}),
    ]);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(apiClient)],
        child: const MaterialApp(home: AnnouncementListScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Item 1'), findsOneWidget);

    await tester.tap(find.text('Item 1'));
    await tester.pumpAndSettle();

    expect(find.text('Body 1'), findsOneWidget);
  });

  testWidgets('scrolling near the bottom loads the next page', (tester) async {
    final apiClient = ApiClient();
    apiClient.dio.httpClientAdapter = _SequenceAdapter([
      jsonEncode({
        'data': List.generate(15, (i) => _item(i)),
        'next_page_url': 'x',
      }),
      jsonEncode({
        'data': [_item(15)],
        'next_page_url': null,
      }),
    ]);

    await tester.pumpWidget(
      ProviderScope(
        overrides: [apiClientProvider.overrideWithValue(apiClient)],
        child: const MaterialApp(home: AnnouncementListScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Item 0'), findsOneWidget);

    await tester.drag(find.byType(GridView), const Offset(0, -4000));
    await tester.pumpAndSettle();

    expect(find.text('Item 15'), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
cd /Users/junlou/bugsaymis-mobile && flutter test test/features/notices/announcement_list_screen_test.dart
```

Expected: FAIL (`announcement_list_screen.dart` doesn't exist).

- [ ] **Step 3: Create the screen**

Create `lib/src/features/notices/announcement_list_screen.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/api_client.dart';
import '../../core/theme.dart';
import 'announcement_detail_sheet.dart';
import 'announcement_list_item.dart';
import 'announcement_poster_image.dart';
import 'notices_provider.dart';

class AnnouncementListScreen extends ConsumerStatefulWidget {
  const AnnouncementListScreen({super.key});

  @override
  ConsumerState<AnnouncementListScreen> createState() => _AnnouncementListScreenState();
}

class _AnnouncementListScreenState extends ConsumerState<AnnouncementListScreen> {
  final _items = <AnnouncementListItem>[];
  final _scrollController = ScrollController();
  int _page = 1;
  bool _hasMore = true;
  bool _loadingMore = false;
  bool _initialLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    _loadPage();
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (!_hasMore || _loadingMore || !_scrollController.hasClients) return;
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 300) {
      _loadPage();
    }
  }

  Future<void> _loadPage() async {
    setState(() => _loadingMore = true);
    try {
      final res = await ref.read(apiClientProvider).get('/notices/history', params: {'page': _page});
      final data = res.data as Map<String, dynamic>;
      final newItems = (data['data'] as List<dynamic>)
          .map((e) => AnnouncementListItem.fromJson(e as Map<String, dynamic>))
          .toList();
      setState(() {
        _items.addAll(newItems);
        _hasMore = data['next_page_url'] != null;
        _page++;
        _error = null;
      });
    } catch (_) {
      setState(() => _error = 'Could not load announcements.');
    } finally {
      setState(() {
        _loadingMore = false;
        _initialLoading = false;
      });
    }
  }

  void _markRead(int index) {
    setState(() => _items[index] = _items[index].copyWithRead());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Announcements')),
      body: _initialLoading
          ? const Center(child: CircularProgressIndicator())
          : _items.isEmpty
              ? Center(
                  child: Text(_error ?? 'No announcements yet.', style: AppTextStyles.body),
                )
              : GridView.builder(
                  controller: _scrollController,
                  padding: const EdgeInsets.all(16),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 12,
                    crossAxisSpacing: 12,
                    childAspectRatio: 0.85,
                  ),
                  itemCount: _items.length + (_hasMore ? 1 : 0),
                  itemBuilder: (context, i) {
                    if (i >= _items.length) {
                      return const Center(child: CircularProgressIndicator());
                    }
                    final item = _items[i];
                    return _AnnouncementTile(
                      item: item,
                      onTap: () => showAnnouncementDetail(
                        context,
                        title: item.title,
                        body: item.body,
                        posterPath: item.posterPath,
                        publishedAt: item.publishedAt,
                        isRead: item.isRead,
                        onAcknowledge: item.isRead
                            ? null
                            : () async {
                                await ref
                                    .read(apiClientProvider)
                                    .post('/notices/announcement/${item.id}/acknowledge');
                                _markRead(i);
                                ref.invalidate(noticesProvider);
                              },
                      ),
                    );
                  },
                ),
    );
  }
}

class _AnnouncementTile extends StatelessWidget {
  final AnnouncementListItem item;
  final VoidCallback onTap;

  const _AnnouncementTile({required this.item, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(AppRadius.card),
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (item.posterPath != null)
              AnnouncementPosterImage(posterPath: item.posterPath!)
            else
              Container(
                color: AppColors.accentBg,
                child: const Center(child: Icon(Icons.campaign_rounded, color: AppColors.accent, size: 32)),
              ),
            Positioned(
              left: 0,
              right: 0,
              bottom: 0,
              child: Container(
                padding: const EdgeInsets.all(8),
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [Colors.transparent, Color(0xCC0F172A)],
                  ),
                ),
                child: Text(
                  item.title,
                  style: AppTextStyles.custom(fontSize: 12, fontWeight: FontWeight.w700, color: Colors.white),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ),
            if (!item.isRead)
              Positioned(
                top: 8,
                right: 8,
                child: Container(
                  width: 10,
                  height: 10,
                  decoration: const BoxDecoration(color: AppColors.accent, shape: BoxShape.circle),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

```bash
cd /Users/junlou/bugsaymis-mobile && flutter test test/features/notices/announcement_list_screen_test.dart
```

Expected: all 3 tests PASS.

- [ ] **Step 5: Commit**

```bash
cd /Users/junlou/bugsaymis-mobile && git add lib/src/features/notices/announcement_list_screen.dart test/features/notices/announcement_list_screen_test.dart
git commit -m "feat(notices): add paginated AnnouncementListScreen with tap-to-detail tiles"
```

---

### Task 8: Flutter — wire the `/announcements` route

**Files:**
- Modify: `lib/src/core/router.dart`

**Interfaces:**
- Consumes: `AnnouncementListScreen` (Task 7).
- Produces: nothing further — this is the final wiring leaf that makes Task 6's `context.push('/announcements')` resolve in the real app (the test in Task 6 already verifies that navigation call against a stub route; this task makes it real).

There is no existing test file for `router.dart` in this project (confirmed: no `test/core/router_test.dart` exists) — its wiring is verified manually via Simulator/device click-through, matching how every other route in this file was verified, per the project's established practice for this specific file.

- [ ] **Step 1: Add the import**

In `lib/src/core/router.dart`, add next to the existing `notification_preferences_screen.dart` import (line 15):

```dart
import '../features/notices/announcement_list_screen.dart';
import '../features/notifications/notification_preferences_screen.dart';
```

- [ ] **Step 2: Add the route**

In the "Full-screen routes (cover the bottom nav)" section (`lib/src/core/router.dart:140-164`), add it alongside the other shared, non-shell-scoped routes like `/profile` and `/notification-preferences`:

```dart
      GoRoute(path: '/notification-preferences', builder: (ctx, st) => const NotificationPreferencesScreen()),
      GoRoute(path: '/announcements', builder: (ctx, st) => const AnnouncementListScreen()),
      GoRoute(path: '/profile',        builder: (ctx, st) => const ProfileScreen()),
```

It's placed outside both `StatefulShellRoute`s (parent shell and student shell) — like `/profile`, it's reachable from either role without shell-branch scoping, since the screen itself gets audience-appropriate data purely from the authenticated principal server-side (Task 1's `history()` endpoint already branches on `Student` vs. `ParentContact`), with no client-side role logic needed.

- [ ] **Step 3: Manual verification**

Run the app (`flutter run`) as both a linked-parent account and a student account:
1. From the dashboard, confirm the swipeable announcement cards show real poster images where present (this is also where Task 4's manual image-render check happens).
2. Tap a card — confirm the detail sheet opens with the full title/body/poster, and for an unread item, tapping "Mark as Read" closes the sheet and removes that item from the dashboard card's queue.
3. Tap "See all" — confirm `AnnouncementListScreen` opens, shows a 2-column grid of tiles (including announcements already read, each marked appropriately), and scrolling to the bottom loads more.
4. Tap a tile — confirm the same detail sheet opens; for an already-read item, confirm there is no "Mark as Read" button.
5. Confirm both flows work from the student dashboard too (`lib/src/features/student/student_dashboard_screen.dart:62` uses the same `AnnouncementsCard`, unmodified call site).

- [ ] **Step 4: Commit**

```bash
cd /Users/junlou/bugsaymis-mobile && git add lib/src/core/router.dart
git commit -m "feat(notices): wire /announcements route to AnnouncementListScreen"
```

---

## Self-Review

**Spec coverage:** Section 3 of the spec — swipeable square cards capped at 5, poster display via a secure proxy, "See all" into a paginated full-history list — is covered by Tasks 1-4, 6-8. The mid-plan follow-up (both the dashboard card and the list page must be tappable into a details modal) is covered by Task 5 (`AnnouncementDetailSheet`) and its wiring into Task 6 (card) and Task 7 (list tiles) — every card and every tile has an `onTap` that calls `showAnnouncementDetail`.

**Placeholder scan:** No TBD/TODO; every step has literal, pasteable code, including full widget/test file contents.

**Type consistency:** `AnnouncementListItem` (Task 3: `id, title, body, posterPath, publishedAt, isRead`) is consumed identically by Task 7's `AnnouncementListScreen`/`_AnnouncementTile`. `showAnnouncementDetail`'s signature (Task 5: `title, body, posterPath, publishedAt, isRead, onAcknowledge`) is called with matching named arguments in both Task 6 (`isRead: false` always, since dashboard-card items come from the unread-only `/notices/pending`) and Task 7 (`isRead: item.isRead`, `onAcknowledge: item.isRead ? null : ...`). `AnnouncementPosterImage`'s single required `posterPath: String` (Task 4) is only ever called with a non-null value behind an `if (posterPath != null)` guard in both Task 6's `_AnnouncementSquareCard` and Task 7's `_AnnouncementTile` and Task 5's sheet — no nullable-to-non-nullable mismatch. `posterAuthHeadersProvider`'s return type (`FutureProvider<Map<String, String>>`) matches what `AnnouncementPosterImage` reads via `ref.watch(...).when(...)`.
