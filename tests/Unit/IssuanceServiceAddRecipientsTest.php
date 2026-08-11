<?php

namespace Tests\Unit;

use App\Models\Division;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Issuance;
use App\Models\IssuanceRecipient;
use App\Models\Office;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use App\Services\IssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IssuanceServiceAddRecipientsTest extends TestCase
{
    use RefreshDatabase;

    private function releasedIssuance(): Issuance
    {
        $creator = User::factory()->create();

        return Issuance::create([
            'type' => 'MEMO',
            'control_number' => 'MEMO-2026-08-' . uniqid(),
            'series_no' => 1,
            'year' => 2026,
            'month' => 8,
            'title' => 'Test Memo',
            'recipient_type' => 'individual_staff',
            'status' => 'released',
            'released_at' => now(),
            'created_by' => $creator->id,
        ]);
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

    private function makeStudent(array $overrides = []): int
    {
        return (int) DB::table('students')->insertGetId(array_merge([
            'lastname' => 'Test' . uniqid(),
            'firstname' => 'Student',
        ], $overrides));
    }

    private function enroll(int $studentId, SchoolYear $sy, ?int $sectionId = null, int $gradeLevel = 9): StudentEnrollment
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

    public function test_it_adds_new_individual_staff_recipients_and_returns_their_ids(): void
    {
        $issuance = $this->releasedIssuance();
        $user = User::factory()->create();

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'user_ids' => [$user->id],
        ]);

        $this->assertCount(1, $newIds);
        $this->assertDatabaseHas('issuance_recipients', [
            'issuance_id' => $issuance->id,
            'user_id' => $user->id,
        ]);
        $recipient = IssuanceRecipient::find($newIds[0]);
        $this->assertNotNull($recipient->notified_at);
    }

    public function test_it_skips_users_who_are_already_recipients(): void
    {
        $issuance = $this->releasedIssuance();
        $existing = User::factory()->create();
        $new = User::factory()->create();
        IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $existing->id]);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'user_ids' => [$existing->id, $new->id],
        ]);

        $this->assertCount(1, $newIds);
        $this->assertSame($new->id, IssuanceRecipient::find($newIds[0])->user_id);
        $this->assertSame(2, $issuance->recipients()->count());
    }

    public function test_it_returns_empty_array_when_everyone_selected_is_already_a_recipient(): void
    {
        $issuance = $this->releasedIssuance();
        $existing = User::factory()->create();
        IssuanceRecipient::create(['issuance_id' => $issuance->id, 'user_id' => $existing->id]);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'user_ids' => [$existing->id],
        ]);

        $this->assertSame([], $newIds);
        $this->assertSame(1, $issuance->recipients()->count());
    }

    public function test_it_adds_recipients_by_office(): void
    {
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Test Office ' . uniqid()]);
        $memberA = User::factory()->create(['office_id' => $office->id]);
        $memberB = User::factory()->create(['office_id' => $office->id]);
        User::factory()->create(); // unrelated user, must not be added

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'office_ids' => [$office->id],
        ]);

        $this->assertCount(2, $newIds);
        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $memberA->id]);
        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $memberB->id]);
    }

    public function test_it_adds_recipients_by_division(): void
    {
        $issuance = $this->releasedIssuance();
        $division = Division::create(['division_name' => 'Test Division ' . uniqid()]);
        $member = User::factory()->create(['division_id' => $division->id]);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'division_ids' => [$division->id],
        ]);

        $this->assertCount(1, $newIds);
        $this->assertSame($member->id, IssuanceRecipient::find($newIds[0])->user_id);
    }

    public function test_it_adds_all_active_employees_and_excludes_inactive_ones(): void
    {
        $issuance = $this->releasedIssuance();
        $active = User::factory()->create(['status' => 'active']);
        $inactive = User::factory()->create(['status' => 'inactive']);

        (new IssuanceService())->addRecipients($issuance, ['all_staff' => true]);

        $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $active->id]);
        $this->assertDatabaseMissing('issuance_recipients', ['issuance_id' => $issuance->id, 'user_id' => $inactive->id]);
    }

    public function test_it_combines_office_and_individual_staff_without_duplicating_overlap(): void
    {
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Combo Office ' . uniqid()]);
        $officeMember = User::factory()->create(['office_id' => $office->id]);
        $extraPerson = User::factory()->create();

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'office_ids' => [$office->id],
            'user_ids' => [$officeMember->id, $extraPerson->id], // officeMember picked both ways
        ]);

        $this->assertCount(2, $newIds); // officeMember once, extraPerson once — not 3
        $this->assertSame(2, $issuance->recipients()->count());
    }

    public function test_it_adds_students_by_individual_section_grade_and_all(): void
    {
        $issuance = $this->releasedIssuance();
        $sy = $this->currentSchoolYear();
        $section = Section::create([
            'sectionname' => 'Test-A', 'levelid' => 9, 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'capacity' => 30, 'is_active' => true,
        ]);

        $individualStudentId = $this->makeStudent();
        $this->enroll($individualStudentId, $sy, null, 8);

        $sectionStudentId = $this->makeStudent();
        $this->enroll($sectionStudentId, $sy, $section->id, 9);

        $gradeStudentId = $this->makeStudent();
        $this->enroll($gradeStudentId, $sy, null, 10);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'student_ids' => [$individualStudentId],
            'section_ids' => [$section->id],
            'grade_levels' => [10],
        ]);

        $this->assertCount(3, $newIds);
        foreach ([$individualStudentId, $sectionStudentId, $gradeStudentId] as $sid) {
            $this->assertDatabaseHas('issuance_recipients', ['issuance_id' => $issuance->id, 'student_id' => $sid]);
        }
    }

    public function test_it_dedupes_a_student_picked_both_individually_and_via_their_section(): void
    {
        $issuance = $this->releasedIssuance();
        $sy = $this->currentSchoolYear();
        $section = Section::create([
            'sectionname' => 'Test-B', 'levelid' => 7, 'syid' => $sy->id,
            'school_year_id' => $sy->id, 'capacity' => 30, 'is_active' => true,
        ]);
        $studentId = $this->makeStudent();
        $this->enroll($studentId, $sy, $section->id, 7);

        $newIds = (new IssuanceService())->addRecipients($issuance, [
            'student_ids' => [$studentId],
            'section_ids' => [$section->id],
        ]);

        $this->assertCount(1, $newIds);
    }

    public function test_it_aborts_when_targeting_students_with_no_current_school_year(): void
    {
        $issuance = $this->releasedIssuance();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        (new IssuanceService())->addRecipients($issuance, ['all_students' => true]);
    }

    public function test_it_records_criteria_rows_for_a_combined_selection(): void
    {
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Criteria Office ' . uniqid()]);
        $user = User::factory()->create();

        (new IssuanceService())->addRecipients($issuance, [
            'office_ids' => [$office->id],
            'user_ids' => [$user->id],
        ]);

        $this->assertDatabaseHas('issuance_recipient_criteria', ['issuance_id' => $issuance->id, 'type' => 'office', 'target_id' => $office->id]);
        $this->assertDatabaseHas('issuance_recipient_criteria', ['issuance_id' => $issuance->id, 'type' => 'individual_staff', 'target_id' => $user->id]);
    }

    public function test_it_sets_recipient_type_summary_to_mixed_when_multiple_types_selected(): void
    {
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Summary Office ' . uniqid()]);
        $user = User::factory()->create();

        (new IssuanceService())->addRecipients($issuance, [
            'office_ids' => [$office->id],
            'user_ids' => [$user->id],
        ]);

        $this->assertSame('mixed', $issuance->fresh()->recipient_type);
    }

    public function test_it_sets_recipient_type_summary_to_single_type_when_only_one_selected(): void
    {
        $issuance = $this->releasedIssuance();
        $office = Office::create(['name' => 'Single Office ' . uniqid()]);

        (new IssuanceService())->addRecipients($issuance, ['office_ids' => [$office->id]]);

        $this->assertSame('office', $issuance->fresh()->recipient_type);
    }
}
