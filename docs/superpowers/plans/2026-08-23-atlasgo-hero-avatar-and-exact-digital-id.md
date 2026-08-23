# AtlasGo Hero Avatar + Exact-Replica Digital ID Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** In the AtlasGo Flutter app's Student Dashboard hero card, show the student's own profile photo in a circular avatar beside their first name; and rebuild the full-screen Digital Student ID screen so its front and back faces are a field-for-field visual replica of the physical CR-80 card the school prints (`resources/js/Pages/Students/IdCard.vue`), with an explicit button to flip between front and back.

**Architecture:** Two repos change together. The Laravel backend (`bugsaymis-docker/src/bugsaymis`) adds two additive endpoints under the existing self-scoped `/api/mobile/student/*` group — a `GET .../id-card` JSON endpoint carrying every field the physical card prints (name, barcode, LRN, OCD signature, emergency contact) and a `GET .../photo` proxy that streams the authenticated student's own S3 photo, mirroring the existing web `StudentController::proxyPhoto` pattern but scoped to the token's own student (no `{id}` parameter, so there is no IDOR surface — same self-only pattern the sibling `/profile`, `/grades`, `/schedule` endpoints already use). The Flutter app (`bugsaymis-mobile`) adds a shared Sanctum-authenticated photo-image widget (mirrors the existing `AnnouncementPosterImage` pattern), wires a small circular avatar into the Student Dashboard's `HeroHeader.leading` slot, and rebuilds `StudentIdScreen` as two new widgets (`StudentIdCardFront`, `StudentIdCardBack`) whose every dimension is derived from a single `CardScale` mm/px converter so the on-screen replica stays proportionally identical to the print CSS at any screen size, flipped via an `AnimationController`-driven 3D `Transform` triggered by an explicit "Show Back"/"Show Front" button.

**Tech Stack:** Laravel 12 / PHP 8.4 (Sanctum API), Flutter (Riverpod, `go_router`, `cached_network_image`, `barcode_widget`, `screen_brightness` — all already dependencies, no new packages).

**Spec:** None — this plan was written directly from the user's request plus investigation of the existing physical-card template (`resources/js/Pages/Students/IdCard.vue`) and the existing employee/announcement patterns this mirrors; there was no separate design-decision space requiring a brainstorming pass.

## Global Constraints

- **Never** expose another student's photo or ID data — every new endpoint derives the student solely from `$request->user()->id` (the Sanctum token IS the student), exactly like the existing `/profile`, `/grades`, `/schedule` self-endpoints. No route may accept a student-id parameter.
- **Never** use `Storage::disk('public')` — the new photo proxy uses `Storage::disk('s3')`, matching `StudentController::proxyPhoto`.
- Flutter: no new pubspec dependencies. `cached_network_image`, `barcode_widget`, `screen_brightness` are already present.
- Every mm/px measurement in the new front/back card widgets must be produced via `CardScale`, not a literal pixel number — this is what keeps the replica "exact" (proportional to the print CSS) instead of an eyeballed approximation.
- Match `resources/js/Pages/Students/IdCard.vue`'s fields, order, and copy exactly (including the back's "Valid for School Year" label carrying no year value — that mirrors the physical card's printed template, which leaves the year for a validation sticker).

---

## File Structure

**Backend (`bugsaymis-docker/src/bugsaymis`):**
- Modify `app/Http/Controllers/StudentAttendance/Api/StudentSelfController.php` — add `has_photo` to `profile()`, add `idCard()`, add `photo()`.
- Modify `routes/api.php` — add the two routes inside the existing `student.` group.
- Create `tests/Feature/Mobile/StudentIdCardApiTest.php`.

**Frontend (`bugsaymis-mobile`):**
- Modify `lib/src/features/student/student_provider.dart` — add `hasPhoto` to `StudentProfile`, add `StudentIdCard` model + `studentIdCardProvider`.
- Create `lib/src/features/student/student_photo_image.dart` — shared Sanctum-authenticated photo widget.
- Modify `lib/src/features/student/student_dashboard_screen.dart` — wire the hero avatar into `HeroHeader.leading`.
- Create `lib/src/features/student/student_id_card_scale.dart` — `CardScale` mm/px converter + shared `idSymbology` barcode constant.
- Create `lib/src/features/student/student_id_card_front.dart` — front face widget.
- Create `lib/src/features/student/student_id_card_back.dart` — back face widget.
- Modify `lib/src/features/student/student_id_screen.dart` — flip mechanics, brightness lifecycle (unchanged), wiring.
- Create `assets/images/id_card_bg.jpg` — copied from `public/images/bg.jpg` (the same subtle 20%-opacity texture the print template uses).
- Create `test/features/student/student_provider_test.dart`.
- Create `test/features/student/student_photo_image_test.dart`.
- Modify `test/features/student/student_dashboard_screen_test.dart` — add hero-avatar assertions.
- Create `test/features/student/student_id_screen_test.dart`.

---

## Task 1: Backend — expose `has_photo` on the existing profile endpoint

**Files:**
- Modify: `app/Http/Controllers/StudentAttendance/Api/StudentSelfController.php:40-70`
- Test: `tests/Feature/Mobile/StudentIdCardApiTest.php` (new file, first test in it)

**Interfaces:**
- Produces: `GET /api/mobile/student/profile` response gains `student.has_photo: bool`, consumed by Task 4's `StudentProfile.hasPhoto`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Mobile/StudentIdCardApiTest.php`:

```php
<?php

namespace Tests\Feature\Mobile;

use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentIdCardApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // See StudentSosTriggerTest for why this is required for any
        // Student-authenticated Feature test.
        config(['opentelemetry.user_context' => false]);
    }

    private function tokenFor(Student $student): string
    {
        return $student->createToken('test')->plainTextToken;
    }

    private function makeStudent(array $attrs = []): Student
    {
        $id = DB::table('students')->insertGetId(array_merge([
            'lastname' => 'Dela Cruz',
            'firstname' => 'Juan',
            'status' => 'active',
        ], $attrs));

        return Student::find($id);
    }

    public function test_profile_reports_has_photo_true_when_img_is_set(): void
    {
        $student = $this->makeStudent(['img' => 'students/1/photo.jpg']);
        $token = $this->tokenFor($student);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/student/profile')
            ->assertOk()
            ->assertJson(['student' => ['has_photo' => true]]);
    }

    public function test_profile_reports_has_photo_false_when_no_img(): void
    {
        $student = $this->makeStudent();
        $token = $this->tokenFor($student);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/student/profile')
            ->assertOk()
            ->assertJson(['student' => ['has_photo' => false]]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentIdCardApiTest"`
Expected: FAIL — `has_photo` key missing from the JSON response.

- [ ] **Step 3: Add `has_photo` to `profile()`**

In `app/Http/Controllers/StudentAttendance/Api/StudentSelfController.php`, change the `profile()` method's select and response (lines ~40-69):

```php
        $student = DB::table('students')
            ->where('id', $studentId)
            ->first(['id', 'pisaysystemID', 'lastname', 'firstname', 'middlename', 'sex', 'student_email', 'img']);

        if (! $student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $schoolYear  = SchoolYear::where('is_current', true)->first();
        $enrollment  = $schoolYear
            ? StudentEnrollment::with('section')
                ->where('student_id', $studentId)
                ->where('school_year_id', $schoolYear->id)
                ->where('status', 'enrolled')
                ->first()
            : null;

        return response()->json([
            'student' => [
                'id'          => $student->id,
                'barcode'     => $student->pisaysystemID,
                'name'        => trim("{$student->lastname}, {$student->firstname}"),
                'sex'         => $student->sex,
                'email'       => $student->student_email,
                'has_photo'   => (bool) $student->img,
                'grade_level' => $enrollment?->grade_level,
                'section'     => $enrollment?->section
                    ? $enrollment->section->sectionname
                    : null,
                'school_year' => $schoolYear?->name,
            ],
        ]);
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentIdCardApiTest"`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/StudentAttendance/Api/StudentSelfController.php tests/Feature/Mobile/StudentIdCardApiTest.php
git commit -m "feat(mobile-api): expose has_photo on student self-profile"
```

---

## Task 2: Backend — self-scoped photo proxy

**Files:**
- Modify: `app/Http/Controllers/StudentAttendance/Api/StudentSelfController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Mobile/StudentIdCardApiTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `GET /api/mobile/student/photo` (name `mobile.student.photo`) — streams the authenticated student's own photo bytes; 404 if none on file. Consumed by Task 5's `StudentPhotoImage`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Mobile/StudentIdCardApiTest.php` (inside the class, add the `use` for `Storage` already imported above):

```php
    public function test_photo_streams_the_students_own_s3_photo(): void
    {
        Storage::fake('s3');
        Storage::disk('s3')->put('students/1/photo.jpg', 'fake-image-bytes');
        $student = $this->makeStudent(['img' => 'students/1/photo.jpg']);
        $token = $this->tokenFor($student);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/mobile/student/photo');

        $response->assertOk();
        $this->assertSame('fake-image-bytes', $response->getContent());
    }

    public function test_photo_returns_404_when_no_photo_on_file(): void
    {
        $student = $this->makeStudent();
        $token = $this->tokenFor($student);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->get('/api/mobile/student/photo')
            ->assertStatus(404);
    }

    public function test_photo_rejects_unauthenticated_requests(): void
    {
        // getJson (not get) — an unauthenticated non-JSON request 302s to a
        // login redirect instead of 401ing, since Sanctum's unauthenticated()
        // handler checks $request->expectsJson().
        $this->getJson('/api/mobile/student/photo')->assertStatus(401);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentIdCardApiTest"`
Expected: FAIL — route `mobile.student.photo` does not exist (404 on all, including the unauthenticated case which currently 404s instead of the expected structure — the S3/no-photo cases fail with a routing error).

- [ ] **Step 3: Add the `photo()` method and route**

In `app/Http/Controllers/StudentAttendance/Api/StudentSelfController.php`, add near the top of the class (after `resolveStudentId`) the `Storage` import:

```php
use Illuminate\Support\Facades\Storage;
```

Add the method (place it after `profile()`):

```php
    /**
     * GET /api/mobile/student/photo
     *
     * Self-scoped mirror of StudentController::proxyPhoto — deliberately
     * takes no student-id parameter so it can only ever stream the token's
     * own photo (Sanctum tokens for students are issued directly against
     * the Student model, per resolveStudentId()).
     */
    public function photo(Request $request)
    {
        $studentId = $this->resolveStudentId($request);
        abort_if(! $studentId, 404);

        $img = DB::table('students')->where('id', $studentId)->value('img');
        abort_if(! $img, 404);

        if (str_contains($img, '/')) {
            abort_if(! Storage::disk('s3')->exists($img), 404);
            $content = Storage::disk('s3')->get($img);
            $mime = Storage::disk('s3')->mimeType($img) ?: 'image/jpeg';

            return response($content, 200, [
                'Content-Type'  => $mime,
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        $localPath = storage_path("app/public/students_profile_picture/{$img}");
        abort_if(! file_exists($localPath), 404);

        return response()->file($localPath, ['Cache-Control' => 'private, max-age=3600']);
    }
```

In `routes/api.php`, inside the `Route::prefix('student')->name('student.')->group(...)` block, add right after the `/profile` route:

```php
            Route::get('/photo', [StudentSelfController::class, 'photo'])->name('photo');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentIdCardApiTest"`
Expected: PASS (5 tests total so far).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/StudentAttendance/Api/StudentSelfController.php routes/api.php tests/Feature/Mobile/StudentIdCardApiTest.php
git commit -m "feat(mobile-api): add self-scoped student photo proxy"
```

---

## Task 3: Backend — `id-card` endpoint (name, LRN, OCD signature, emergency contact)

**Files:**
- Modify: `app/Http/Controllers/StudentAttendance/Api/StudentSelfController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Mobile/StudentIdCardApiTest.php`

**Interfaces:**
- Consumes: `App\Services\DigitalSignatureService::getSignatureDataUri(User $user): ?string` (existing, returns `data:image/png;base64,...` or `null`).
- Produces: `GET /api/mobile/student/id-card` (name `mobile.student.id-card`) — JSON shape:
  ```json
  {
    "student": {"name": "...", "barcode": "...", "lrn": "...", "has_photo": true, "grade_level": 8, "section": "...", "school_year": "..."},
    "ocd": {"name": "...", "position": "...", "signature_uri": "data:image/png;base64,..."},
    "emergency": {"guardian_name": "...", "contact_no": "...", "address": "..."}
  }
  ```
  Consumed by Task 4's `StudentIdCard.fromJson`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/Mobile/StudentIdCardApiTest.php`:

```php
    public function test_id_card_returns_the_full_card_fields(): void
    {
        $role = Role::create(['name' => 'OCD']);
        $ocdUser = User::factory()->create();
        $ocdUser->roles()->attach($role->id);

        $student = $this->makeStudent([
            'lastname' => 'Dela Cruz',
            'firstname' => 'Juan',
            'lrn' => '123456789012',
            'pisaysystemID' => '2024-00123',
            'img' => 'students/1/photo.jpg',
            'contactperson' => 'Maria Dela Cruz',
            'contactno1' => '09171234567',
            'houseno' => '123',
            'barangay' => 'Poblacion',
            'municipal' => 'Butuan City',
            'province' => 'Agusan del Norte',
        ]);
        $token = $this->tokenFor($student);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/mobile/student/id-card')
            ->assertOk();

        $response->assertJson([
            'student' => [
                'name'      => 'Dela Cruz, Juan',
                'barcode'   => '2024-00123',
                'lrn'       => '123456789012',
                'has_photo' => true,
            ],
            'ocd' => [
                'name'     => 'MELBA C. PATACSIL, PhD',
                'position' => 'Campus Director',
            ],
            'emergency' => [
                'guardian_name' => 'Maria Dela Cruz',
                'contact_no'    => '09171234567',
                'address'       => '123, Brgy. Poblacion, Butuan City, Agusan del Norte',
            ],
        ]);
    }

    public function test_id_card_rejects_unauthenticated_requests(): void
    {
        $this->getJson('/api/mobile/student/id-card')->assertStatus(401);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentIdCardApiTest"`
Expected: FAIL — route `mobile.student.id-card` does not exist.

- [ ] **Step 3: Add the `idCard()` method and route**

In `app/Http/Controllers/StudentAttendance/Api/StudentSelfController.php`, add imports:

```php
use App\Models\User;
use App\Services\DigitalSignatureService;
```

Add a constructor (the class currently has none):

```php
    public function __construct(private DigitalSignatureService $sigService)
    {
    }
```

Add the method (place it after `photo()`):

```php
    /**
     * GET /api/mobile/student/id-card
     *
     * Every field the physical CR-80 card prints (see
     * StudentController::idCard, the web/print equivalent this mirrors),
     * self-scoped the same way the rest of this controller is.
     */
    public function idCard(Request $request): JsonResponse
    {
        $studentId = $this->resolveStudentId($request);

        if (! $studentId) {
            return response()->json(['message' => 'Student account not fully set up.'], 404);
        }

        $student = DB::table('students')->where('id', $studentId)->first();

        if (! $student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $schoolYear = SchoolYear::where('is_current', true)->first();
        $enrollment = $schoolYear
            ? StudentEnrollment::with('section')
                ->where('student_id', $studentId)
                ->where('school_year_id', $schoolYear->id)
                ->where('status', 'enrolled')
                ->first()
            : null;

        $ocdUser = User::whereHas('roles', fn ($q) => $q->where('name', 'OCD'))->first();

        $address = implode(', ', array_filter([
            $student->houseno,
            filled($student->barangay ?? '') ? 'Brgy. ' . $student->barangay : null,
            $student->municipal,
            $student->province,
        ], fn ($v) => filled($v)));

        return response()->json([
            'student' => [
                'name'        => trim("{$student->lastname}, {$student->firstname}"),
                'barcode'     => $student->pisaysystemID ?: null,
                'lrn'         => $student->lrn,
                'has_photo'   => (bool) $student->img,
                'grade_level' => $enrollment?->grade_level,
                'section'     => $enrollment?->section?->sectionname,
                'school_year' => $schoolYear?->name,
            ],
            'ocd' => [
                'name'          => 'MELBA C. PATACSIL, PhD',
                'position'      => 'Campus Director',
                'signature_uri' => $ocdUser ? $this->sigService->getSignatureDataUri($ocdUser) : null,
            ],
            'emergency' => [
                'guardian_name' => $student->contactperson ?: null,
                'contact_no'    => $student->contactno1 ?: null,
                'address'       => $address ?: null,
            ],
        ]);
    }
```

In `routes/api.php`, add right after the `/photo` route added in Task 2:

```php
            Route::get('/id-card', [StudentSelfController::class, 'idCard'])->name('id-card');
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentIdCardApiTest"`
Expected: PASS (7 tests total).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/StudentAttendance/Api/StudentSelfController.php routes/api.php tests/Feature/Mobile/StudentIdCardApiTest.php
git commit -m "feat(mobile-api): add student id-card endpoint (name, LRN, OCD signature, emergency contact)"
```

---

## Task 4: Flutter — `StudentProfile.hasPhoto` + `StudentIdCard` model + provider

**Files:**
- Modify: `lib/src/features/student/student_provider.dart`
- Test: `test/features/student/student_provider_test.dart` (new)

**Interfaces:**
- Produces: `StudentProfile.hasPhoto` (bool), `StudentIdCard` class + `studentIdCardProvider` (`FutureProvider.autoDispose<StudentIdCard>`). Consumed by Task 6 (hero avatar) and Task 11 (id screen).

- [ ] **Step 1: Write the failing test**

Create `test/features/student/student_provider_test.dart`:

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/features/student/student_provider.dart';

void main() {
  group('StudentProfile.fromJson', () {
    test('parses has_photo', () {
      final profile = StudentProfile.fromJson({
        'student': {'id': 1, 'name': 'Juan Dela Cruz', 'has_photo': true},
      });
      expect(profile.hasPhoto, isTrue);
    });

    test('defaults has_photo to false when absent', () {
      final profile = StudentProfile.fromJson({
        'student': {'id': 1, 'name': 'Juan Dela Cruz'},
      });
      expect(profile.hasPhoto, isFalse);
    });
  });

  group('StudentIdCard.fromJson', () {
    test('parses every field from the id-card response shape', () {
      final card = StudentIdCard.fromJson({
        'student': {
          'name': 'DELA CRUZ, JUAN',
          'barcode': '2024-00123',
          'lrn': '123456789012',
          'has_photo': true,
          'grade_level': 8,
          'section': 'Curie',
          'school_year': '2026-2027',
        },
        'ocd': {
          'name': 'MELBA C. PATACSIL, PhD',
          'position': 'Campus Director',
          'signature_uri': 'data:image/png;base64,AAAA',
        },
        'emergency': {
          'guardian_name': 'Maria Dela Cruz',
          'contact_no': '09171234567',
          'address': 'Butuan City',
        },
      });

      expect(card.name, 'DELA CRUZ, JUAN');
      expect(card.barcode, '2024-00123');
      expect(card.lrn, '123456789012');
      expect(card.hasPhoto, isTrue);
      expect(card.gradeLevel, 8);
      expect(card.section, 'Curie');
      expect(card.schoolYear, '2026-2027');
      expect(card.ocdName, 'MELBA C. PATACSIL, PhD');
      expect(card.ocdPosition, 'Campus Director');
      expect(card.ocdSignatureUri, 'data:image/png;base64,AAAA');
      expect(card.guardianName, 'Maria Dela Cruz');
      expect(card.contactNo, '09171234567');
      expect(card.address, 'Butuan City');
    });

    test('nulls out missing optional fields instead of throwing', () {
      final card = StudentIdCard.fromJson({
        'student': {'name': 'DELA CRUZ, JUAN', 'has_photo': false},
        'ocd': {'name': 'MELBA C. PATACSIL, PhD', 'position': 'Campus Director'},
        // Explicitly typed — a bare {} here infers as Map<dynamic,dynamic>
        // and fails the `as Map<String, dynamic>` cast in fromJson (real
        // JSON via jsonDecode doesn't have this issue).
        'emergency': <String, dynamic>{},
      });

      expect(card.barcode, isNull);
      expect(card.lrn, isNull);
      expect(card.ocdSignatureUri, isNull);
      expect(card.guardianName, isNull);
    });
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_provider_test.dart`
Expected: FAIL — compile error, `hasPhoto`/`StudentIdCard` don't exist yet.

- [ ] **Step 3: Implement**

In `lib/src/features/student/student_provider.dart`, modify `StudentProfile`:

```dart
class StudentProfile {
  final int id;
  final String? barcode;
  final String name;
  final String? sex;
  final String? email;
  final int? gradeLevel;
  final String? section;
  final String? schoolYear;
  final bool hasPhoto;

  const StudentProfile({
    required this.id,
    this.barcode,
    required this.name,
    this.sex,
    this.email,
    this.gradeLevel,
    this.section,
    this.schoolYear,
    this.hasPhoto = false,
  });

  factory StudentProfile.fromJson(Map<String, dynamic> json) {
    final s = json['student'] as Map<String, dynamic>;
    return StudentProfile(
      id: s['id'] as int,
      barcode: s['barcode'] as String?,
      name: s['name'] as String? ?? '—',
      sex: s['sex'] as String?,
      email: s['email'] as String?,
      gradeLevel: s['grade_level'] as int?,
      section: s['section'] as String?,
      schoolYear: s['school_year'] as String?,
      hasPhoto: s['has_photo'] as bool? ?? false,
    );
  }
}
```

Add the new model and provider (place after `StudentTodaySummary`, before `studentProfileProvider`):

```dart
/// Every field the physical CR-80 card prints — see
/// resources/js/Pages/Students/IdCard.vue in the backend repo, the
/// print/web equivalent this mirrors.
class StudentIdCard {
  final String name;
  final String? barcode;
  final String? lrn;
  final bool hasPhoto;
  final int? gradeLevel;
  final String? section;
  final String? schoolYear;
  final String ocdName;
  final String ocdPosition;
  final String? ocdSignatureUri;
  final String? guardianName;
  final String? contactNo;
  final String? address;

  const StudentIdCard({
    required this.name,
    this.barcode,
    this.lrn,
    this.hasPhoto = false,
    this.gradeLevel,
    this.section,
    this.schoolYear,
    required this.ocdName,
    required this.ocdPosition,
    this.ocdSignatureUri,
    this.guardianName,
    this.contactNo,
    this.address,
  });

  factory StudentIdCard.fromJson(Map<String, dynamic> json) {
    final s = json['student'] as Map<String, dynamic>;
    final ocd = json['ocd'] as Map<String, dynamic>;
    final emergency = json['emergency'] as Map<String, dynamic>;
    return StudentIdCard(
      name: s['name'] as String? ?? '—',
      barcode: s['barcode'] as String?,
      lrn: s['lrn'] as String?,
      hasPhoto: s['has_photo'] as bool? ?? false,
      gradeLevel: s['grade_level'] as int?,
      section: s['section'] as String?,
      schoolYear: s['school_year'] as String?,
      ocdName: ocd['name'] as String? ?? '',
      ocdPosition: ocd['position'] as String? ?? '',
      ocdSignatureUri: ocd['signature_uri'] as String?,
      guardianName: emergency['guardian_name'] as String?,
      contactNo: emergency['contact_no'] as String?,
      address: emergency['address'] as String?,
    );
  }
}
```

Add the provider (place after `studentProfileProvider`):

```dart
final studentIdCardProvider =
    FutureProvider.autoDispose<StudentIdCard>((ref) async {
  final client = ref.read(apiClientProvider);
  final response = await client.get('/student/id-card');
  return StudentIdCard.fromJson(response.data as Map<String, dynamic>);
});
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_provider_test.dart`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/student/student_provider.dart test/features/student/student_provider_test.dart
git commit -m "feat(student): add hasPhoto to StudentProfile and a StudentIdCard model/provider"
```

---

## Task 5: Flutter — shared Sanctum-authenticated photo widget

**Files:**
- Create: `lib/src/features/student/student_photo_image.dart`
- Test: `test/features/student/student_photo_image_test.dart` (new)

**Interfaces:**
- Consumes: `AppConstants.baseUrl`, `AppConstants.tokenKey` (existing).
- Produces: `studentAuthHeadersProvider` (`FutureProvider<Map<String,String>>`), `StudentPhotoImage` widget (`{required Widget child, BoxFit fit = BoxFit.cover, Alignment alignment = Alignment.center}`) — shows `child` while loading/erroring/absent, otherwise the authenticated photo. Consumed by Task 6 (hero avatar) and Task 10 (front card photo box).

- [ ] **Step 1: Write the failing tests**

Create `test/features/student/student_photo_image_test.dart` (mirrors `test/features/notices/announcement_poster_image_test.dart`):

```dart
import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:atlasgo/src/features/student/student_photo_image.dart';

void main() {
  testWidgets('shows the child, not CachedNetworkImage, while auth headers are still resolving', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentAuthHeadersProvider.overrideWith((ref) => Completer<Map<String, String>>().future),
        ],
        child: const MaterialApp(
          home: Scaffold(body: StudentPhotoImage(child: Icon(Icons.person))),
        ),
      ),
    );

    expect(find.byType(CachedNetworkImage), findsNothing);
    expect(find.byIcon(Icons.person), findsOneWidget);
  });

  testWidgets('falls back to the child when auth headers fail to load', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentAuthHeadersProvider.overrideWith((ref) => Future<Map<String, String>>.error('no token')),
        ],
        child: const MaterialApp(
          home: Scaffold(body: StudentPhotoImage(child: Icon(Icons.person))),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(find.byIcon(Icons.person), findsOneWidget);
    expect(find.byType(CachedNetworkImage), findsNothing);
  });

  testWidgets('renders CachedNetworkImage once auth headers resolve', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          studentAuthHeadersProvider.overrideWith((ref) async => {'Authorization': 'Bearer test'}),
        ],
        child: const MaterialApp(
          home: Scaffold(body: StudentPhotoImage(child: Icon(Icons.person))),
        ),
      ),
    );
    await tester.pump();

    expect(find.byType(CachedNetworkImage), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_photo_image_test.dart`
Expected: FAIL — file doesn't exist.

- [ ] **Step 3: Implement**

Create `lib/src/features/student/student_photo_image.dart`:

```dart
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../../core/constants.dart';

/// CachedNetworkImage takes a plain header map, not a Dio interceptor, so
/// this duplicates the one line of ApiClient's auth logic that images need
/// (same duplication AnnouncementPosterImage carries for posters).
final studentAuthHeadersProvider = FutureProvider<Map<String, String>>((ref) async {
  const storage = FlutterSecureStorage();
  final token = await storage.read(key: AppConstants.tokenKey);
  return token != null ? {'Authorization': 'Bearer $token'} : {};
});

/// Renders the authenticated student's own profile photo via the
/// Sanctum-reachable self-scoped proxy (GET /api/mobile/student/photo) —
/// never a direct S3 URL. There is no per-student parameter in that URL,
/// so this always shows the signed-in student's own photo. [child] is
/// shown instead whenever there is no photo on file or it fails to load
/// (e.g. an initials fallback).
class StudentPhotoImage extends ConsumerWidget {
  final Widget child;
  final BoxFit fit;
  final Alignment alignment;

  const StudentPhotoImage({
    super.key,
    required this.child,
    this.fit = BoxFit.cover,
    this.alignment = Alignment.center,
  });

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final headersAsync = ref.watch(studentAuthHeadersProvider);

    return headersAsync.when(
      loading: () => child,
      error: (_, _) => child,
      data: (headers) => CachedNetworkImage(
        imageUrl: '${AppConstants.baseUrl}/student/photo',
        httpHeaders: headers,
        fit: fit,
        alignment: alignment,
        placeholder: (_, _) => child,
        errorWidget: (_, _, _) => child,
      ),
    );
  }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_photo_image_test.dart`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/student/student_photo_image.dart test/features/student/student_photo_image_test.dart
git commit -m "feat(student): add Sanctum-authenticated student photo widget"
```

---

## Task 6: Flutter — hero avatar on Student Dashboard

**Files:**
- Modify: `lib/src/features/student/student_dashboard_screen.dart`
- Modify: `test/features/student/student_dashboard_screen_test.dart`

**Interfaces:**
- Consumes: `StudentPhotoImage` (Task 5), `StudentProfile.hasPhoto` (Task 4).

- [ ] **Step 1: Write the failing test**

In `test/features/student/student_dashboard_screen_test.dart`, add a new `testWidgets` at the end of `main()` (before the closing `}`):

```dart
  testWidgets('shows initials in the hero avatar when the student has no photo', (tester) async {
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

    expect(find.text('J'), findsOneWidget);
  });
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_dashboard_screen_test.dart`
Expected: FAIL — no `'J'` text found (no avatar exists yet).

- [ ] **Step 3: Implement**

In `lib/src/features/student/student_dashboard_screen.dart`, add the import:

```dart
import 'student_photo_image.dart';
```

Wire `leading:` into the existing `HeroHeader(...)` call (around line 47):

```dart
            HeroHeader(
              leading: _HeroAvatar(
                firstName: firstName,
                hasPhoto: profile.valueOrNull?.hasPhoto ?? false,
              ),
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
```

Add the private widget near the bottom of the file, next to `_dashboardHeroTrailing`:

```dart
/// Circular avatar for the Student Dashboard's HeroHeader — the student's
/// own photo when they have one on file, otherwise their first initial
/// (same fallback the old, since-removed standalone profile card used).
class _HeroAvatar extends StatelessWidget {
  final String firstName;
  final bool hasPhoto;

  const _HeroAvatar({required this.firstName, required this.hasPhoto});

  @override
  Widget build(BuildContext context) {
    final fallback = Center(
      child: Text(
        firstName.isNotEmpty ? firstName[0].toUpperCase() : '?',
        style: AppTextStyles.custom(
            fontSize: 18, fontWeight: FontWeight.w700, color: Colors.white),
      ),
    );

    return Container(
      width: 44,
      height: 44,
      decoration: const BoxDecoration(color: Colors.white24, shape: BoxShape.circle),
      clipBehavior: Clip.antiAlias,
      child: hasPhoto ? StudentPhotoImage(child: fallback) : fallback,
    );
  }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_dashboard_screen_test.dart`
Expected: PASS (all tests in the file, including the new one).

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/student/student_dashboard_screen.dart test/features/student/student_dashboard_screen_test.dart
git commit -m "feat(student): show the student's profile photo in the dashboard hero avatar"
```

---

## Task 7: Flutter — `CardScale` mm/px converter + shared barcode symbology

**Files:**
- Create: `lib/src/features/student/student_id_card_scale.dart`
- Test: `test/features/student/student_id_card_scale_test.dart` (new)

**Interfaces:**
- Produces: `CardScale` class (`CardScale(double cardWidth)` with `.mm(double)` and `.px(double)` methods), `idSymbology` (`Barcode`) constant. Consumed by Task 9 (front) and Task 10 (back).

- [ ] **Step 1: Write the failing test**

Create `test/features/student/student_id_card_scale_test.dart`:

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/features/student/student_id_card_scale.dart';

void main() {
  test('mm(54) equals the full card width, at any card width', () {
    expect(const CardScale(216).mm(54), closeTo(216, 0.001));
    expect(const CardScale(108).mm(54), closeTo(108, 0.001));
  });

  test('px scales proportionally with mm at the same card width', () {
    // At the print template's reference size (54mm rendered at 96dpi),
    // 1mm and (96/25.4)px are the same physical length.
    const s = CardScale(54 * 3.7795275591);
    expect(s.mm(1), closeTo(s.px(3.7795275591), 0.001));
  });
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_id_card_scale_test.dart`
Expected: FAIL — file doesn't exist.

- [ ] **Step 3: Implement**

Create `lib/src/features/student/student_id_card_scale.dart`:

```dart
import 'package:barcode_widget/barcode_widget.dart';

/// The physical school IDs' symbology is not verifiable from this repo —
/// flip to Barcode.code39() if the gate scanners reject Code 128. Shared
/// by the front and back card faces (both print a copy of the barcode).
final idSymbology = Barcode.code128();

/// Converts the physical CR-80 print template's mm/px units (from
/// resources/js/Pages/Students/IdCard.vue in the backend repo, calibrated
/// at the browser's 96dpi print reference: the card is 54mm wide) into
/// logical pixels for a card rendered at [cardWidth] — so every
/// proportion (photo size, band padding, font sizes) stays faithful to
/// the physical card at any on-screen size.
class CardScale {
  static const double _mmToPx96 = 3.7795275591;
  static const double _cardWidthMm = 54;

  final double cardWidth;
  const CardScale(this.cardWidth);

  double get _factor => cardWidth / (_cardWidthMm * _mmToPx96);

  /// Converts a physical mm measurement from the print CSS.
  double mm(double v) => v * _mmToPx96 * _factor;

  /// Converts a browser px measurement from the print CSS (already
  /// expressed at the same 96dpi reference as the mm values).
  double px(double v) => v * _factor;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_id_card_scale_test.dart`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/student/student_id_card_scale.dart test/features/student/student_id_card_scale_test.dart
git commit -m "feat(student): add CardScale mm/px converter for the digital ID replica"
```

---

## Task 8: Flutter — copy the print template's background texture asset

**Files:**
- Create: `assets/images/id_card_bg.jpg` (binary copy)

**Interfaces:**
- Produces: `assets/images/id_card_bg.jpg`, consumed by Task 9's front-card background.

- [ ] **Step 1: Copy the asset**

Run:

```bash
cp /Users/junlou/bugsaymis-docker/src/bugsaymis/public/images/bg.jpg /Users/junlou/bugsaymis-mobile/assets/images/id_card_bg.jpg
```

`pubspec.yaml` already registers the whole `assets/images/` directory (`flutter: assets: - assets/images/`), so no pubspec change is needed.

- [ ] **Step 2: Verify it's picked up**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter pub get && ls assets/images/id_card_bg.jpg`
Expected: file listed, `flutter pub get` completes without error.

- [ ] **Step 3: Commit**

```bash
git add assets/images/id_card_bg.jpg
git commit -m "chore(student): add the CR-80 print template's background texture asset"
```

---

## Task 9: Flutter — `StudentIdCardFront` widget

**Files:**
- Create: `lib/src/features/student/student_id_card_front.dart`
- Test: `test/features/student/student_id_card_front_test.dart` (new)

**Interfaces:**
- Consumes: `CardScale`, `idSymbology` (Task 7), `StudentPhotoImage` (Task 5), `StudentIdCard` (Task 4), `assets/images/id_card_bg.jpg` (Task 8), `assets/images/pshs_logo.png` (existing asset).
- Produces: `StudentIdCardFront` widget (`{required StudentIdCard card, required double cardWidth}`). Consumed by Task 11.

- [ ] **Step 1: Write the failing test**

Create `test/features/student/student_id_card_front_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/features/student/student_id_card_front.dart';
import 'package:atlasgo/src/features/student/student_provider.dart';

void main() {
  const card = StudentIdCard(
    name: 'DELA CRUZ, JUAN',
    barcode: '2024-00123',
    lrn: '123456789012',
    hasPhoto: false,
    gradeLevel: 8,
    section: 'Curie',
    schoolYear: '2026-2027',
    ocdName: 'MELBA C. PATACSIL, PhD',
    ocdPosition: 'Campus Director',
  );

  testWidgets('shows name, LRN, and OCD signature block', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: StudentIdCardFront(card: card, cardWidth: 216)),
      ),
    );

    expect(find.text('DELA CRUZ, JUAN'), findsOneWidget);
    expect(find.text('123456789012'), findsOneWidget);
    expect(find.text('MELBA C. PATACSIL, PhD'), findsOneWidget);
    expect(find.text('Campus Director'), findsOneWidget);
    expect(find.text('SCHOLAR'), findsOneWidget);
  });

  testWidgets('shows an em dash for a missing LRN', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: StudentIdCardFront(
            card: StudentIdCard(
              name: 'DELA CRUZ, JUAN',
              hasPhoto: false,
              ocdName: 'MELBA C. PATACSIL, PhD',
              ocdPosition: 'Campus Director',
            ),
            cardWidth: 216,
          ),
        ),
      ),
    );

    expect(find.text('—'), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_id_card_front_test.dart`
Expected: FAIL — file doesn't exist.

- [ ] **Step 3: Implement**

Create `lib/src/features/student/student_id_card_front.dart`:

```dart
import 'dart:convert';
import 'package:barcode_widget/barcode_widget.dart';
import 'package:flutter/material.dart';
import '../../core/theme.dart';
import 'student_id_card_scale.dart';
import 'student_photo_image.dart';
import 'student_provider.dart';

const _kBandGradient = LinearGradient(
  begin: Alignment.topLeft,
  end: Alignment.bottomRight,
  colors: [Color(0xFF060E50), Color(0xFF1447C0), Color(0xFF0093B8)],
  stops: [0.0, 0.65, 1.0],
);
const _kSlateDark = Color(0xFF1E293B);
const _kSlate = Color(0xFF475569);
const _kSlateLight = Color(0xFF94A3B8);
const _kBorderLight = Color(0xFFE2E8F0);
const _kDivider = Color(0xFFF1F5F9);

/// Front face of the digital student ID — mirrors
/// resources/js/Pages/Students/IdCard.vue (the backend's printable CR-80
/// template) field-for-field: header band, photo, name, barcode, LRN,
/// OCD signature block, "SCHOLAR" footer band.
class StudentIdCardFront extends StatelessWidget {
  final StudentIdCard card;
  final double cardWidth;

  const StudentIdCardFront({super.key, required this.card, required this.cardWidth});

  @override
  Widget build(BuildContext context) {
    final s = CardScale(cardWidth);
    final cardHeight = cardWidth * 86 / 54;

    return Container(
      width: cardWidth,
      height: cardHeight,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(s.mm(2.5)),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.12), blurRadius: 16, offset: const Offset(0, 4)),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          Positioned.fill(
            child: Opacity(
              opacity: 0.2,
              child: Image.asset('assets/images/id_card_bg.jpg', fit: BoxFit.cover,
                  errorBuilder: (_, _, _) => const SizedBox.shrink()),
            ),
          ),
          Column(
            children: [
              _band(s),
              Expanded(
                child: Padding(
                  padding: EdgeInsets.symmetric(horizontal: s.mm(3), vertical: s.mm(2.5)),
                  // Wraps in a scroll view as a safety net: the print CSS's
                  // "margin-top: auto" only has a few mm of slack between the
                  // LRN block and the signature, and platform font-metric
                  // variance can eat that — a graceful scroll beats a red
                  // overflow banner clipping the signature block.
                  child: SingleChildScrollView(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        _photo(s),
                        SizedBox(height: s.mm(1.5)),
                        Text(
                          card.name,
                          textAlign: TextAlign.center,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: AppTextStyles.custom(
                            fontSize: s.px(9), fontWeight: FontWeight.w700, color: _kSlateDark, height: 1.3,
                          ),
                        ),
                        SizedBox(height: s.mm(1)),
                        _barcode(s),
                        SizedBox(height: s.mm(1)),
                        _lrn(s),
                        SizedBox(height: s.mm(1.5)),
                        _signature(s),
                      ],
                    ),
                  ),
                ),
              ),
              _footerBand(s),
            ],
          ),
        ],
      ),
    );
  }

  Widget _band(CardScale s) => Container(
        decoration: const BoxDecoration(gradient: _kBandGradient),
        padding: EdgeInsets.symmetric(horizontal: s.mm(2), vertical: s.mm(1.5)),
        child: Row(
          children: [
            Image.asset('assets/images/pshs_logo.png', width: s.mm(9), height: s.mm(9), fit: BoxFit.contain,
                errorBuilder: (_, _, _) => Icon(Icons.school_rounded, color: Colors.white, size: s.mm(9))),
            SizedBox(width: s.mm(1.5)),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text('Republic of the Philippines',
                      style: AppTextStyles.custom(fontSize: s.px(5.5), color: Colors.white)),
                  Text('Department of Science and Technology',
                      style: AppTextStyles.custom(fontSize: s.px(5.5), color: Colors.white)),
                  Text('PHILIPPINE SCIENCE HIGH SCHOOL',
                      style: AppTextStyles.custom(fontSize: s.px(6.5), fontWeight: FontWeight.w700, color: Colors.white)),
                  Text('CARAGA REGION CAMPUS IN BUTUAN CITY',
                      style: AppTextStyles.custom(fontSize: s.px(5.5), fontWeight: FontWeight.w700, color: Colors.white)),
                ],
              ),
            ),
          ],
        ),
      );

  Widget _photo(CardScale s) {
    final empty = Center(
      child: Text('No Photo', style: AppTextStyles.custom(fontSize: s.px(6), color: _kSlateLight)),
    );
    return Container(
      width: s.mm(30),
      height: s.mm(30),
      margin: EdgeInsets.only(top: s.mm(1)),
      decoration: BoxDecoration(
        border: Border.all(color: _kBorderLight),
        borderRadius: BorderRadius.circular(s.mm(1.5)),
        color: Colors.white,
      ),
      clipBehavior: Clip.antiAlias,
      child: card.hasPhoto
          ? StudentPhotoImage(alignment: const Alignment(0, -0.6), child: empty)
          : empty,
    );
  }

  Widget _barcode(CardScale s) {
    final barcode = card.barcode;
    if (barcode == null || barcode.isEmpty) {
      return SizedBox(
        height: s.mm(7),
        child: Center(
          child: Text('No barcode on file', style: AppTextStyles.custom(fontSize: s.px(6.5), color: _kSlate)),
        ),
      );
    }
    return Column(
      children: [
        SizedBox(
          width: double.infinity,
          height: s.mm(7),
          child: BarcodeWidget(
            barcode: idSymbology,
            data: barcode,
            drawText: false,
            color: Colors.black,
            errorBuilder: (_, _) => Text(barcode, textAlign: TextAlign.center),
          ),
        ),
        SizedBox(height: s.mm(0.5)),
        Text(barcode,
            style: AppTextStyles.custom(fontSize: s.px(6.5), fontWeight: FontWeight.w600, color: _kSlate, letterSpacing: 1)),
      ],
    );
  }

  Widget _lrn(CardScale s) => Container(
        width: double.infinity,
        padding: EdgeInsets.only(top: s.mm(1)),
        decoration: const BoxDecoration(border: Border(top: BorderSide(color: _kDivider))),
        child: Column(
          children: [
            Text('LEARNER REFERENCE NUMBER',
                style: AppTextStyles.custom(fontSize: s.px(6), fontWeight: FontWeight.w700, color: _kSlateLight, letterSpacing: 0.5)),
            SizedBox(height: s.mm(0.5)),
            Text(card.lrn ?? '—',
                style: AppTextStyles.custom(fontSize: s.px(9), fontWeight: FontWeight.w700, color: _kSlateDark, letterSpacing: 1)),
          ],
        ),
      );

  Widget _signature(CardScale s) => Column(
        children: [
          if (card.ocdSignatureUri != null)
            Image.memory(
              base64Decode(card.ocdSignatureUri!.split(',').last),
              height: s.mm(5),
              fit: BoxFit.contain,
              errorBuilder: (_, _, _) => const SizedBox.shrink(),
            ),
          Text(card.ocdName,
              style: AppTextStyles.custom(fontSize: s.px(7), fontWeight: FontWeight.w700, color: _kSlateDark)),
          Text(card.ocdPosition,
              style: AppTextStyles.custom(fontSize: s.px(6), color: _kSlateDark, letterSpacing: 0.5)),
        ],
      );

  Widget _footerBand(CardScale s) => Container(
        width: double.infinity,
        padding: EdgeInsets.symmetric(vertical: s.mm(1.5)),
        decoration: const BoxDecoration(gradient: _kBandGradient),
        child: Text('SCHOLAR',
            textAlign: TextAlign.center,
            style: AppTextStyles.custom(fontSize: s.px(8), fontWeight: FontWeight.w700, color: Colors.white, letterSpacing: 2)),
      );
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_id_card_front_test.dart`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/student/student_id_card_front.dart test/features/student/student_id_card_front_test.dart
git commit -m "feat(student): add StudentIdCardFront replicating the printed CR-80 front face"
```

---

## Task 10: Flutter — `StudentIdCardBack` widget

**Files:**
- Create: `lib/src/features/student/student_id_card_back.dart`
- Test: `test/features/student/student_id_card_back_test.dart` (new)

**Interfaces:**
- Consumes: `CardScale`, `idSymbology` (Task 7), `StudentIdCard` (Task 4).
- Produces: `StudentIdCardBack` widget (`{required StudentIdCard card, required double cardWidth}`). Consumed by Task 11.

- [ ] **Step 1: Write the failing test**

Create `test/features/student/student_id_card_back_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/features/student/student_id_card_back.dart';
import 'package:atlasgo/src/features/student/student_provider.dart';

void main() {
  testWidgets('shows emergency contact fields and the notice text', (tester) async {
    const card = StudentIdCard(
      name: 'DELA CRUZ, JUAN',
      barcode: '2024-00123',
      hasPhoto: false,
      ocdName: 'MELBA C. PATACSIL, PhD',
      ocdPosition: 'Campus Director',
      guardianName: 'Maria Dela Cruz',
      contactNo: '09171234567',
      address: 'Butuan City',
    );

    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: StudentIdCardBack(card: card, cardWidth: 216)),
      ),
    );

    expect(find.text('IN CASE OF EMERGENCY, NOTIFY'), findsOneWidget);
    expect(find.text('Maria Dela Cruz'), findsOneWidget);
    expect(find.text('09171234567'), findsOneWidget);
    expect(find.text('Butuan City'), findsOneWidget);
    expect(find.textContaining('non-transferable'), findsOneWidget);
  });

  testWidgets('shows an em dash for missing emergency fields', (tester) async {
    const card = StudentIdCard(
      name: 'DELA CRUZ, JUAN',
      hasPhoto: false,
      ocdName: 'MELBA C. PATACSIL, PhD',
      ocdPosition: 'Campus Director',
    );

    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: StudentIdCardBack(card: card, cardWidth: 216)),
      ),
    );

    expect(find.text('—'), findsNWidgets(3));
  });
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_id_card_back_test.dart`
Expected: FAIL — file doesn't exist.

- [ ] **Step 3: Implement**

Create `lib/src/features/student/student_id_card_back.dart`:

```dart
import 'package:barcode_widget/barcode_widget.dart';
import 'package:flutter/material.dart';
import '../../core/theme.dart';
import 'student_id_card_scale.dart';
import 'student_provider.dart';

const _kBlue = Color(0xFF1447C0);
const _kSlate = Color(0xFF475569);
const _kSlateLight = Color(0xFF94A3B8);
const _kSlateDark = Color(0xFF1E293B);
const _kDivider = Color(0xFFF1F5F9);

/// Back face of the digital student ID — mirrors
/// resources/js/Pages/Students/IdCard.vue's back side field-for-field:
/// emergency contact block, notice paragraphs, "Valid for School Year"
/// label (no value, matching the physical card's printed template — the
/// year is meant for a validation sticker), and a repeated barcode footer.
class StudentIdCardBack extends StatelessWidget {
  final StudentIdCard card;
  final double cardWidth;

  const StudentIdCardBack({super.key, required this.card, required this.cardWidth});

  @override
  Widget build(BuildContext context) {
    final s = CardScale(cardWidth);
    final cardHeight = cardWidth * 86 / 54;

    return Container(
      width: cardWidth,
      height: cardHeight,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(s.mm(2.5)),
        boxShadow: [
          BoxShadow(color: Colors.black.withValues(alpha: 0.12), blurRadius: 16, offset: const Offset(0, 4)),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          Container(
            width: double.infinity,
            padding: EdgeInsets.symmetric(horizontal: s.mm(2), vertical: s.mm(1.5)),
            decoration: const BoxDecoration(border: Border(bottom: BorderSide(color: _kBlue, width: 1.5))),
            child: Text('IN CASE OF EMERGENCY, NOTIFY',
                textAlign: TextAlign.center,
                style: AppTextStyles.custom(fontSize: s.px(9), fontWeight: FontWeight.w700, color: _kBlue, letterSpacing: 0.5)),
          ),
          Expanded(
            child: Padding(
              padding: EdgeInsets.symmetric(horizontal: s.mm(3), vertical: s.mm(2.5)),
              // Same scroll-safety net as StudentIdCardFront — the back
              // face packs four notice paragraphs into a similarly tight
              // mm budget as the print CSS.
              child: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    _field(s, 'NAME OF PARENT / GUARDIAN', card.guardianName, first: true),
                    _field(s, 'CONTACT NUMBER', card.contactNo),
                    _field(s, 'ADDRESS', card.address),
                    Container(width: double.infinity, height: 1, color: _kDivider, margin: EdgeInsets.symmetric(vertical: s.mm(1))),
                    Text('IMPORTANT',
                        textAlign: TextAlign.center,
                        style: AppTextStyles.custom(fontSize: s.px(6.5), fontWeight: FontWeight.w700, color: _kBlue, letterSpacing: 0.5)),
                    SizedBox(height: s.mm(1)),
                    _notice(s, 'This ID is valid for the period indicated on the validation sticker.'),
                    _notice(s, 'This ID is non-transferable and should be worn visibly at all times while inside the campus.'),
                    _notice(s, 'This ID must be surrendered upon graduation.'),
                    _notice(s,
                        'Lost ID cards will be replaced only upon presentation of an affidavit of loss to the Office of the Registrar.',
                        last: true),
                    SizedBox(height: s.mm(3)),
                    Container(
                      width: double.infinity,
                      padding: EdgeInsets.only(top: s.mm(1.5)),
                      decoration: const BoxDecoration(border: Border(top: BorderSide(color: _kDivider))),
                      child: Text('VALID FOR SCHOOL YEAR',
                          textAlign: TextAlign.center,
                          style: AppTextStyles.custom(fontSize: s.px(7), fontWeight: FontWeight.w700, color: _kBlue, letterSpacing: 0.5)),
                    ),
                  ],
                ),
              ),
            ),
          ),
          Container(
            width: double.infinity,
            height: s.mm(11),
            padding: EdgeInsets.symmetric(horizontal: s.mm(3), vertical: s.mm(1.5)),
            decoration: const BoxDecoration(border: Border(top: BorderSide(color: _kBlue, width: 1.5))),
            alignment: Alignment.center,
            child: (card.barcode == null || card.barcode!.isEmpty)
                ? const SizedBox.shrink()
                : BarcodeWidget(barcode: idSymbology, data: card.barcode!, drawText: false, color: Colors.black),
          ),
        ],
      ),
    );
  }

  Widget _field(CardScale s, String label, String? value, {bool first = false}) => Container(
        width: double.infinity,
        margin: EdgeInsets.only(top: first ? s.mm(0.5) : s.mm(1.5)),
        child: Column(
          children: [
            Text(label,
                textAlign: TextAlign.center,
                style: AppTextStyles.custom(fontSize: s.px(6), fontWeight: FontWeight.w700, color: _kSlateLight, letterSpacing: 0.5)),
            SizedBox(height: s.mm(0.5)),
            Text(value ?? '—',
                textAlign: TextAlign.center,
                style: AppTextStyles.custom(fontSize: s.px(8), fontWeight: FontWeight.w600, color: _kSlateDark)),
          ],
        ),
      );

  Widget _notice(CardScale s, String text, {bool last = false}) => Padding(
        padding: EdgeInsets.only(bottom: last ? 0 : s.mm(1)),
        child: Text(text,
            textAlign: TextAlign.justify,
            style: AppTextStyles.custom(fontSize: s.px(6), color: _kSlate, height: 1.4)),
      );
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_id_card_back_test.dart`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/student/student_id_card_back.dart test/features/student/student_id_card_back_test.dart
git commit -m "feat(student): add StudentIdCardBack replicating the printed CR-80 back face"
```

---

## Task 11: Flutter — rebuild `StudentIdScreen` with the flip button

**Files:**
- Modify: `lib/src/features/student/student_id_screen.dart` (full rewrite)
- Test: `test/features/student/student_id_screen_test.dart` (new)

**Interfaces:**
- Consumes: `StudentIdCardFront` (Task 9), `StudentIdCardBack` (Task 10), `studentIdCardProvider` (Task 4).

- [ ] **Step 1: Write the failing tests**

Create `test/features/student/student_id_screen_test.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:atlasgo/src/features/student/student_id_card_back.dart';
import 'package:atlasgo/src/features/student/student_id_card_front.dart';
import 'package:atlasgo/src/features/student/student_id_screen.dart';
import 'package:atlasgo/src/features/student/student_provider.dart';

void main() {
  const card = StudentIdCard(
    name: 'DELA CRUZ, JUAN',
    barcode: '2024-00123',
    hasPhoto: false,
    ocdName: 'MELBA C. PATACSIL, PhD',
    ocdPosition: 'Campus Director',
    guardianName: 'Maria Dela Cruz',
  );

  testWidgets('shows the front face and a Show Back button by default', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [studentIdCardProvider.overrideWith((ref) async => card)],
        child: const MaterialApp(home: StudentIdScreen()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.byType(StudentIdCardFront), findsOneWidget);
    expect(find.byType(StudentIdCardBack), findsNothing);
    expect(find.text('Show Back'), findsOneWidget);
  });

  testWidgets('tapping the flip button swaps to the back face', (tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [studentIdCardProvider.overrideWith((ref) async => card)],
        child: const MaterialApp(home: StudentIdScreen()),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Show Back'));
    await tester.pumpAndSettle();

    expect(find.byType(StudentIdCardBack), findsOneWidget);
    expect(find.byType(StudentIdCardFront), findsNothing);
    expect(find.text('Show Front'), findsOneWidget);
  });
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_id_screen_test.dart`
Expected: FAIL — `StudentIdCardFront`/`StudentIdCardBack` are not rendered by the current screen, `studentIdCardProvider` isn't wired in, "Show Back" text doesn't exist.

- [ ] **Step 3: Rewrite `student_id_screen.dart`**

Replace the full contents of `lib/src/features/student/student_id_screen.dart`:

```dart
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:screen_brightness/screen_brightness.dart';
import 'dart:math' as math;
import '../../core/theme.dart';
import '../../shared/widgets/pressable.dart';
import 'student_id_card_back.dart';
import 'student_id_card_front.dart';
import 'student_provider.dart';

/// Full-screen digital student ID, opened from Profile → "Digital Student
/// ID". Mirrors the physical CR-80 card printed via
/// resources/js/Pages/Students/IdCard.vue field-for-field (see
/// StudentIdCardFront/StudentIdCardBack); the button below the card flips
/// between the front and back faces the same way the physical card's two
/// sides do. Boosts screen brightness while visible so gate scanners can
/// read the front's barcode.
class StudentIdScreen extends ConsumerStatefulWidget {
  const StudentIdScreen({super.key});

  @override
  ConsumerState<StudentIdScreen> createState() => _StudentIdScreenState();
}

class _StudentIdScreenState extends ConsumerState<StudentIdScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _flipController;
  bool _showingBack = false;

  @override
  void initState() {
    super.initState();
    _flipController = AnimationController(vsync: this, duration: AppMotion.slow);
    ScreenBrightness.instance
        .setApplicationScreenBrightness(1.0)
        .catchError((_) {});
  }

  @override
  void dispose() {
    _flipController.dispose();
    ScreenBrightness.instance
        .resetApplicationScreenBrightness()
        .catchError((_) {});
    super.dispose();
  }

  void _flip() {
    setState(() => _showingBack = !_showingBack);
    if (_showingBack) {
      _flipController.forward();
    } else {
      _flipController.reverse();
    }
  }

  @override
  Widget build(BuildContext context) {
    final card = ref.watch(studentIdCardProvider);

    return Scaffold(
      backgroundColor: AppColors.primary,
      body: SafeArea(
        child: Column(
          children: [
            Align(
              alignment: Alignment.centerRight,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(8, 8, 12, 0),
                child: Pressable(
                  onTap: () => context.pop(),
                  borderRadius: BorderRadius.circular(24),
                  child: const Padding(
                    padding: EdgeInsets.all(8),
                    child: Icon(Icons.close_rounded, color: Colors.white, size: 26),
                  ),
                ),
              ),
            ),
            Expanded(
              child: Center(
                child: card.when(
                  loading: () => const CircularProgressIndicator(
                      color: Colors.white, strokeWidth: 2.5),
                  error: (_, _) => _ErrorView(
                    onRetry: () => ref.invalidate(studentIdCardProvider),
                  ),
                  data: (c) => LayoutBuilder(
                    builder: (context, constraints) {
                      final cardWidth = constraints.maxWidth < 236
                          ? constraints.maxWidth
                          : 236.0;
                      return Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 20),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            AnimatedBuilder(
                              animation: _flipController,
                              builder: (context, _) {
                                final angle = _flipController.value * math.pi;
                                final isBackHalf = _flipController.value >= 0.5;
                                return Transform(
                                  alignment: Alignment.center,
                                  transform: Matrix4.identity()
                                    ..setEntry(3, 2, 0.0015)
                                    ..rotateY(angle),
                                  child: isBackHalf
                                      ? Transform(
                                          alignment: Alignment.center,
                                          transform: Matrix4.identity()..rotateY(math.pi),
                                          child: StudentIdCardBack(card: c, cardWidth: cardWidth),
                                        )
                                      : StudentIdCardFront(card: c, cardWidth: cardWidth),
                                );
                              },
                            ),
                            SizedBox(height: AppSpacing.lg),
                            OutlinedButton.icon(
                              onPressed: _flip,
                              icon: const Icon(Icons.flip_camera_ios_outlined, color: Colors.white, size: 18),
                              label: Text(
                                _showingBack ? 'Show Front' : 'Show Back',
                                style: AppTextStyles.custom(
                                    fontSize: 14, fontWeight: FontWeight.w600, color: Colors.white),
                              ),
                              style: OutlinedButton.styleFrom(
                                foregroundColor: Colors.white,
                                side: const BorderSide(color: Colors.white38),
                                padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                                shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(AppRadius.button)),
                              ),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  final VoidCallback onRetry;
  const _ErrorView({required this.onRetry});

  @override
  Widget build(BuildContext context) => Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.wifi_off_rounded, color: Colors.white54, size: 44),
          const SizedBox(height: 12),
          Text('Could not load your ID',
              style: AppTextStyles.bodyMedium.copyWith(color: Colors.white)),
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: onRetry,
            style: OutlinedButton.styleFrom(
              foregroundColor: Colors.white,
              side: const BorderSide(color: Colors.white38),
            ),
            child: const Text('Retry'),
          ),
        ],
      );
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test test/features/student/student_id_screen_test.dart`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add lib/src/features/student/student_id_screen.dart test/features/student/student_id_screen_test.dart
git commit -m "feat(student): rebuild the digital ID screen with a front/back flip button"
```

---

## Task 12: Full-suite verification

**Files:** none (verification only).

- [ ] **Step 1: Run the full Laravel test suite for touched areas**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php artisan test --filter=StudentIdCardApiTest"`
Expected: PASS, all tests from Tasks 1-3.

- [ ] **Step 2: Run PHP syntax/lint check on touched files**

Run: `cd /Users/junlou/bugsaymis-docker && docker compose exec php bash -c "cd /var/www/html/bugsaymis && php -l app/Http/Controllers/StudentAttendance/Api/StudentSelfController.php"`
Expected: `No syntax errors detected`.

- [ ] **Step 3: Run the full Flutter test suite**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter test`
Expected: PASS — no regressions in `student_dashboard_screen_test.dart` (the "no separate identity card" test from before this plan) or elsewhere.

- [ ] **Step 4: Run Flutter analyzer**

Run: `cd /Users/junlou/bugsaymis-mobile && flutter analyze`
Expected: No new errors/warnings introduced by the new files.

- [ ] **Step 5: Manual verification in the simulator**

Launch the app against the local Docker backend (`flutter run`), sign in as a student with a photo on file (`img` set + an S3 object present), and confirm:
- Student Dashboard hero shows the round photo beside the greeting; a student with no `img` shows their initial instead.
- Profile → "Digital Student ID" opens the full-screen card; the front matches the physical card's fields (photo, name, barcode, LRN, OCD signature, "SCHOLAR" footer).
- Tapping "Show Back" flips to the back face (guardian name/contact/address, notice text, barcode footer) and the button now reads "Show Front".
- Screen brightness increases while the ID screen is open and resets on close.

No commit for this task — it's verification of the prior 11 commits.
