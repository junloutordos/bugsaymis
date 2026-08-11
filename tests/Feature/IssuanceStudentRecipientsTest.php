<?php

namespace Tests\Feature;

use App\Jobs\ProcessIssuanceRelease;
use App\Models\FacultyLoading\GradeLevel;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Issuance;
use App\Models\Permission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Exercises student_ids / section_ids / grade_levels recipient criteria
 * through the actual HTTP endpoints (issuances.store, issuances.release) —
 * the existing IssuanceService unit tests cover buildRecipients()/
 * addRecipients() directly, but nothing previously drove student targeting
 * through the controller layer (validation rules + assertHasRecipientSelection).
 */
class IssuanceStudentRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'issuances.manage'],
            ['module' => 'Issuances', 'description' => 'issuances.manage'],
        );
        $role = Role::create(['name' => 'IssuanceAdminTester_' . uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function currentSchoolYear(): SchoolYear
    {
        return SchoolYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-07-01',
            'end_date' => '2027-06-30',
            'is_current' => true,
            'status' => 'active',
        ]);
    }

    private function makeStudent(): int
    {
        return (int) DB::table('students')->insertGetId([
            'lastname' => 'Test' . uniqid(),
            'firstname' => 'Student',
        ]);
    }

    private function enroll(int $studentId, SchoolYear $sy, ?int $sectionId, int $gradeLevel): StudentEnrollment
    {
        return StudentEnrollment::create([
            'student_id' => $studentId,
            'school_year_id' => $sy->id,
            'section_id' => $sectionId,
            'grade_level' => $gradeLevel,
            'enrollment_type' => 'returning',
            'status' => 'enrolled',
            'enrollment_date' => '2026-07-20',
        ]);
    }

    private function gradeLevel(int $grade): GradeLevel
    {
        return GradeLevel::firstOrCreate(
            ['grade' => $grade],
            ['label' => "Grade {$grade}", 'sort_order' => $grade],
        );
    }

    public function test_store_creates_a_draft_with_individual_student_recipients(): void
    {
        $admin = $this->admin();
        $sy = $this->currentSchoolYear();
        $studentId = $this->makeStudent();
        $this->enroll($studentId, $sy, null, 8);

        $response = $this->actingAs($admin)->post(route('issuances.store'), [
            'type' => 'MEMO',
            'title' => 'Test Memo for Students',
            'content' => '<p>This is the body of the memo for testing.</p>',
            'student_ids' => [$studentId],
        ]);

        $response->assertRedirect();
        $issuance = Issuance::where('title', 'Test Memo for Students')->first();
        $this->assertNotNull($issuance);
        $this->assertSame('draft', $issuance->status);
        $this->assertSame('individual_student', $issuance->recipient_type);
    }

    public function test_store_creates_a_draft_with_section_recipients(): void
    {
        $admin = $this->admin();
        $sy = $this->currentSchoolYear();
        $this->gradeLevel(9);
        $section = Section::create([
            'sectionname' => 'Test-A', 'levelid' => 9, 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'capacity' => 30, 'is_active' => true,
        ]);
        $studentId = $this->makeStudent();
        $this->enroll($studentId, $sy, $section->id, 9);

        $response = $this->actingAs($admin)->post(route('issuances.store'), [
            'type' => 'MEMO',
            'title' => 'Test Memo for Section',
            'content' => '<p>This is the body of the memo for testing.</p>',
            'section_ids' => [$section->id],
        ]);

        $response->assertRedirect();
        $issuance = Issuance::where('title', 'Test Memo for Section')->first();
        $this->assertSame('section', $issuance->recipient_type);
    }

    public function test_store_creates_a_draft_with_grade_level_recipients(): void
    {
        $admin = $this->admin();
        $sy = $this->currentSchoolYear();
        $this->gradeLevel(10);
        $studentId = $this->makeStudent();
        $this->enroll($studentId, $sy, null, 10);

        $response = $this->actingAs($admin)->post(route('issuances.store'), [
            'type' => 'MEMO',
            'title' => 'Test Memo for Grade Level',
            'content' => '<p>This is the body of the memo for testing.</p>',
            'grade_levels' => [10],
        ]);

        $response->assertRedirect();
        $issuance = Issuance::where('title', 'Test Memo for Grade Level')->first();
        $this->assertSame('grade_level', $issuance->recipient_type);
    }

    public function test_store_rejects_empty_recipient_selection_with_validation_error(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('issuances.store'), [
            'type' => 'MEMO',
            'title' => 'No Recipients Memo',
            'content' => '<p>This is the body of the memo for testing.</p>',
        ]);

        $response->assertSessionHasErrors('recipients');
        $this->assertDatabaseMissing('issuances', ['title' => 'No Recipients Memo']);
    }

    public function test_releasing_with_students_but_no_current_school_year_returns_validation_error(): void
    {
        $admin = $this->admin();
        // No SchoolYear created — none is current.

        $response = $this->actingAs($admin)->post(route('issuances.store'), [
            'type' => 'MEMO',
            'title' => 'Should Not Release',
            'content' => '<p>This is the body of the memo for testing.</p>',
            'all_students' => true,
            'should_release' => true,
        ]);

        $response->assertSessionHasErrors('recipients');
        $issuance = Issuance::where('title', 'Should Not Release')->first();
        $this->assertNotNull($issuance, 'The draft should still be saved even though release failed.');
        $this->assertSame('draft', $issuance->status, 'Release must not proceed without a current school year.');
    }

    public function test_sign_and_release_in_one_request_builds_student_recipients_and_dispatches_job(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $sy = $this->currentSchoolYear();
        $studentId = $this->makeStudent();
        $this->enroll($studentId, $sy, null, 7);

        $response = $this->actingAs($admin)->post(route('issuances.store'), [
            'type' => 'MEMO',
            'title' => 'Signed and Released Memo',
            'content' => '<p>This is the body of the memo for testing.</p>',
            'student_ids' => [$studentId],
            'should_release' => true,
        ]);

        $response->assertRedirect();
        $issuance = Issuance::where('title', 'Signed and Released Memo')->first();
        $this->assertSame('released', $issuance->status);
        $this->assertDatabaseHas('issuance_recipients', [
            'issuance_id' => $issuance->id,
            'student_id' => $studentId,
        ]);
        Queue::assertPushed(ProcessIssuanceRelease::class, fn ($job) => $job->issuanceId === $issuance->id);
    }

    public function test_release_endpoint_builds_student_recipients_for_an_existing_draft(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $sy = $this->currentSchoolYear();
        $studentId = $this->makeStudent();
        $this->enroll($studentId, $sy, null, 11);

        $issuance = Issuance::create([
            'type' => 'MEMO',
            'control_number' => 'MEMO-2026-08-' . uniqid(),
            'series_no' => 1,
            'year' => 2026,
            'month' => 8,
            'title' => 'Draft For Release',
            'content' => '<p>Draft body.</p>',
            'recipient_type' => 'individual_student',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('issuances.release', $issuance->id), [
            'student_ids' => [$studentId],
        ]);

        $response->assertRedirect();
        $this->assertSame('released', $issuance->fresh()->status);
        $this->assertDatabaseHas('issuance_recipients', [
            'issuance_id' => $issuance->id,
            'student_id' => $studentId,
        ]);
    }

    public function test_release_endpoint_rejects_empty_recipient_selection_with_validation_error(): void
    {
        $admin = $this->admin();
        $issuance = Issuance::create([
            'type' => 'MEMO',
            'control_number' => 'MEMO-2026-08-' . uniqid(),
            'series_no' => 1,
            'year' => 2026,
            'month' => 8,
            'title' => 'Draft Missing Recipients',
            'content' => '<p>Draft body.</p>',
            'recipient_type' => 'individual_student',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('issuances.release', $issuance->id), []);

        $response->assertSessionHasErrors('recipients');
        $this->assertSame('draft', $issuance->fresh()->status);
    }
}
