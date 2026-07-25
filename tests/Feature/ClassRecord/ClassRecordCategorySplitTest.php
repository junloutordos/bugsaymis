<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordScore;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use Database\Seeders\StanineLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * STEM Research-style subjects split into category-labeled sibling class
 * records (e.g. "Ongoing" / "Completed") sharing the same subject+section+
 * teacher+SY — creation-flow labeling rules, student transfer between
 * siblings, and the double-active-enrollment guard.
 */
class ClassRecordCategorySplitTest extends TestCase
{
    use RefreshDatabase;

    private function baseRecords(): array
    {
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $schoolYear = SchoolYear::create([
            'name' => '2026-2027', 'start_date' => '2026-08-01', 'end_date' => '2027-05-31',
            'is_current' => true, 'status' => 'active',
        ]);
        $option = GradingOption::create(['name' => 'Standard', 'is_active' => true]);

        return [$teacher, $schoolYear, $option];
    }

    private function section(SchoolYear $schoolYear, int $grade, string $name): Section
    {
        return Section::create([
            'levelid' => $grade, 'sectionname' => $name,
            'syid' => $schoolYear->id, 'school_year_id' => $schoolYear->id, 'is_active' => true,
        ]);
    }

    private function subject(SchoolYear $schoolYear, string $code, string $name): Subject
    {
        return Subject::create([
            'school_year_id' => $schoolYear->id, 'code' => $code, 'name' => $name,
            'subject_type' => 'lecture', 'grade_level' => 12, 'is_active' => true,
        ]);
    }

    private function classRecord(User $teacher, SchoolYear $sy, GradingOption $option, Subject $subject, Section $section, ?string $categoryLabel = null): ClassRecord
    {
        return ClassRecord::create([
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'teacher_id' => $teacher->id,
            'grading_option_id' => $option->id,
            'school_year_id' => $sy->id,
            'school_year' => $sy->name,
            'subject_name' => $subject->name,
            'category_label' => $categoryLabel,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'status' => 'draft',
        ]);
    }

    private function enrolledStudent(SchoolYear $sy, Section $section, int $grade, string $lastName, string $firstName): int
    {
        $studentId = DB::table('students')->insertGetId([
            'lastname' => $lastName, 'firstname' => $firstName, 'middlename' => null,
            'sex' => 'Female', 'status' => 'active',
        ]);

        StudentEnrollment::create([
            'student_id' => $studentId, 'school_year_id' => $sy->id, 'section_id' => $section->id,
            'grade_level' => $grade, 'enrollment_type' => 'returning', 'status' => 'enrolled',
            'enrollment_date' => '2026-08-01',
        ]);

        return $studentId;
    }

    private function activeRow(ClassRecordQuarter $quarter, int $studentId, string $last, string $first, int $seq = 1): ClassRecordStudent
    {
        return ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'student_id' => $studentId,
            'family_name' => $last, 'given_name' => $first, 'sex' => 'F',
            'sequence_number' => $seq, 'is_active' => true,
        ]);
    }

    // ── Phase 1: category_label creation-flow rules ────────────────────────────

    public function test_second_class_record_for_same_subject_requires_a_category_label(): void
    {
        [$teacher, $sy, $option] = $this->baseRecords();
        $section = $this->section($sy, 12, 'Diamond');
        $subject = $this->subject($sy, 'RES3', 'STEM Research 3');
        $this->classRecord($teacher, $sy, $option, $subject, $section);

        $this->actingAs($teacher)->postJson(route('class-records.store'), [
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'grading_option_id' => $option->id,
        ])->assertStatus(422)->assertJsonValidationErrors('category_label');
    }

    public function test_second_class_record_with_a_distinct_label_succeeds(): void
    {
        [$teacher, $sy, $option] = $this->baseRecords();
        $section = $this->section($sy, 12, 'Diamond');
        $subject = $this->subject($sy, 'RES3', 'STEM Research 3');
        $this->classRecord($teacher, $sy, $option, $subject, $section, 'Ongoing');

        $response = $this->actingAs($teacher)->postJson(route('class-records.store'), [
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'grading_option_id' => $option->id,
            'category_label' => 'Completed',
        ]);

        $response->assertCreated()->assertJsonPath('data.category_label', 'Completed');
        $this->assertSame('STEM Research 3 — Completed', $response->json('data.display_name'));
    }

    public function test_duplicate_category_label_among_siblings_is_rejected(): void
    {
        [$teacher, $sy, $option] = $this->baseRecords();
        $section = $this->section($sy, 12, 'Diamond');
        $subject = $this->subject($sy, 'RES3', 'STEM Research 3');
        $this->classRecord($teacher, $sy, $option, $subject, $section, 'Ongoing');

        $this->actingAs($teacher)->postJson(route('class-records.store'), [
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'grading_option_id' => $option->id,
            'category_label' => 'ongoing', // case-insensitive collision
        ])->assertStatus(422)->assertJsonValidationErrors('category_label');
    }

    public function test_bulk_store_skips_unlabeled_duplicate_but_creates_labeled_one(): void
    {
        [$teacher, $sy, $option] = $this->baseRecords();
        $section = $this->section($sy, 12, 'Diamond');
        $subject = $this->subject($sy, 'RES3', 'STEM Research 3');
        $this->classRecord($teacher, $sy, $option, $subject, $section);

        $response = $this->actingAs($teacher)->postJson(route('class-records.bulk-store'), [
            'grading_option_id' => $option->id,
            'items' => [
                ['subject_id' => $subject->id, 'section_id' => $section->id],
                ['subject_id' => $subject->id, 'section_id' => $section->id, 'category_label' => 'Completed'],
            ],
        ]);

        $response->assertCreated();
        $this->assertCount(1, $response->json('created'));
        $this->assertCount(1, $response->json('skipped'));
        $this->assertSame('Completed', $response->json('created.0.category_label'));
    }

    public function test_editing_category_label_to_collide_with_sibling_is_rejected(): void
    {
        [$teacher, $sy, $option] = $this->baseRecords();
        $section = $this->section($sy, 12, 'Diamond');
        $subject = $this->subject($sy, 'RES3', 'STEM Research 3');
        $this->classRecord($teacher, $sy, $option, $subject, $section, 'Ongoing');
        $completed = $this->classRecord($teacher, $sy, $option, $subject, $section, 'Completed');

        $this->actingAs($teacher)->putJson(route('class-records.update', $completed->id), [
            'category_label' => 'Ongoing',
        ])->assertStatus(422)->assertJsonValidationErrors('category_label');
    }

    // ── Phase 2: transfer + double-enrollment guard ─────────────────────────────

    public function test_transfer_moves_student_to_sibling_for_the_same_quarter_only(): void
    {
        [$teacher, $sy, $option] = $this->baseRecords();
        $section = $this->section($sy, 12, 'Diamond');
        $subject = $this->subject($sy, 'RES3', 'STEM Research 3');
        $ongoing = $this->classRecord($teacher, $sy, $option, $subject, $section, 'Ongoing');
        $completed = $this->classRecord($teacher, $sy, $option, $subject, $section, 'Completed');
        $studentId = $this->enrolledStudent($sy, $section, 12, 'CRUZ', 'JUAN');

        $q1 = ClassRecordQuarter::create(['class_record_id' => $ongoing->id, 'quarter' => 1, 'is_locked' => false]);
        $q2 = ClassRecordQuarter::create(['class_record_id' => $ongoing->id, 'quarter' => 2, 'is_locked' => false]);
        $this->activeRow($q1, $studentId, 'CRUZ', 'JUAN');
        $q2Row = $this->activeRow($q2, $studentId, 'CRUZ', 'JUAN');

        $response = $this->actingAs($teacher)->postJson(route('class-records.students.transfer', [
            'classRecord' => $ongoing->id, 'q' => 2, 'classRecordStudent' => $q2Row->id,
        ]), ['target_class_record_id' => $completed->id]);

        $response->assertOk();

        // Q2 in Ongoing is now inactive; Q1 in Ongoing is untouched (historical).
        $this->assertDatabaseHas('class_record_students', ['id' => $q2Row->id, 'is_active' => false]);
        $this->assertDatabaseHas('class_record_students', [
            'class_record_quarter_id' => $q1->id, 'student_id' => $studentId, 'is_active' => true,
        ]);

        // Completed now has an active Q2 row for this student.
        $targetQuarter = ClassRecordQuarter::where('class_record_id', $completed->id)->where('quarter', 2)->first();
        $this->assertNotNull($targetQuarter);
        $this->assertDatabaseHas('class_record_students', [
            'class_record_quarter_id' => $targetQuarter->id, 'student_id' => $studentId, 'is_active' => true,
        ]);
    }

    public function test_transfer_rejects_a_non_sibling_target(): void
    {
        [$teacher, $sy, $option] = $this->baseRecords();
        $section = $this->section($sy, 12, 'Diamond');
        $subject = $this->subject($sy, 'RES3', 'STEM Research 3');
        $ongoing = $this->classRecord($teacher, $sy, $option, $subject, $section, 'Ongoing');
        $unrelatedSubject = $this->subject($sy, 'OTHER', 'Some Other Subject');
        $unrelated = $this->classRecord($teacher, $sy, $option, $unrelatedSubject, $section);
        $studentId = $this->enrolledStudent($sy, $section, 12, 'CRUZ', 'JUAN');

        $quarter = ClassRecordQuarter::create(['class_record_id' => $ongoing->id, 'quarter' => 1, 'is_locked' => false]);
        $row = $this->activeRow($quarter, $studentId, 'CRUZ', 'JUAN');

        $this->actingAs($teacher)->postJson(route('class-records.students.transfer', [
            'classRecord' => $ongoing->id, 'q' => 1, 'classRecordStudent' => $row->id,
        ]), ['target_class_record_id' => $unrelated->id])->assertStatus(422);

        $this->assertDatabaseHas('class_record_students', ['id' => $row->id, 'is_active' => true]);
    }

    public function test_transfer_blocked_when_target_quarter_is_locked(): void
    {
        [$teacher, $sy, $option] = $this->baseRecords();
        $section = $this->section($sy, 12, 'Diamond');
        $subject = $this->subject($sy, 'RES3', 'STEM Research 3');
        $ongoing = $this->classRecord($teacher, $sy, $option, $subject, $section, 'Ongoing');
        $completed = $this->classRecord($teacher, $sy, $option, $subject, $section, 'Completed');
        $studentId = $this->enrolledStudent($sy, $section, 12, 'CRUZ', 'JUAN');

        $quarter = ClassRecordQuarter::create(['class_record_id' => $ongoing->id, 'quarter' => 1, 'is_locked' => false]);
        $row = $this->activeRow($quarter, $studentId, 'CRUZ', 'JUAN');
        ClassRecordQuarter::create(['class_record_id' => $completed->id, 'quarter' => 1, 'is_locked' => true]);

        $this->actingAs($teacher)->postJson(route('class-records.students.transfer', [
            'classRecord' => $ongoing->id, 'q' => 1, 'classRecordStudent' => $row->id,
        ]), ['target_class_record_id' => $completed->id])->assertStatus(403);

        $this->assertDatabaseHas('class_record_students', ['id' => $row->id, 'is_active' => true]);
    }

    public function test_upsert_blocks_a_student_already_active_in_a_sibling_for_the_same_quarter(): void
    {
        [$teacher, $sy, $option] = $this->baseRecords();
        $section = $this->section($sy, 12, 'Diamond');
        $subject = $this->subject($sy, 'RES3', 'STEM Research 3');
        $ongoing = $this->classRecord($teacher, $sy, $option, $subject, $section, 'Ongoing');
        $completed = $this->classRecord($teacher, $sy, $option, $subject, $section, 'Completed');
        $studentId = $this->enrolledStudent($sy, $section, 12, 'CRUZ', 'JUAN');

        $ongoingQuarter = ClassRecordQuarter::create(['class_record_id' => $ongoing->id, 'quarter' => 1, 'is_locked' => false]);
        $this->activeRow($ongoingQuarter, $studentId, 'CRUZ', 'JUAN');

        // Try to also add the same student, same quarter, into Completed without transferring first.
        $response = $this->actingAs($teacher)->postJson(route('class-records.students.upsert', [
            'classRecord' => $completed->id, 'q' => 1,
        ]), [
            'students' => [[
                'student_id' => $studentId, 'family_name' => 'CRUZ', 'given_name' => 'JUAN',
                'middle_initial' => null, 'sex' => 'F', 'sequence_number' => 1,
            ]],
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('students');
    }

    // ── Phase 3: cross-record final-grade stitching ─────────────────────────────

    public function test_final_grades_stitch_across_siblings_for_a_mid_year_transfer(): void
    {
        $this->seed(StanineLookupSeeder::class);
        [$teacher, $sy, $option] = $this->baseRecords();
        $category = GradingCategory::create([
            'grading_option_id' => $option->id, 'name' => 'Assessments', 'code' => 'AA',
            'weight' => 1, 'max_assessments' => 1, 'sort_order' => 1,
        ]);
        $section = $this->section($sy, 12, 'Diamond');
        $subject = $this->subject($sy, 'RES3', 'STEM Research 3');
        $ongoing = $this->classRecord($teacher, $sy, $option, $subject, $section, 'Ongoing');
        $completed = $this->classRecord($teacher, $sy, $option, $subject, $section, 'Completed');

        $studentA = $this->enrolledStudent($sy, $section, 12, 'ALPHA', 'ANA');
        $studentB = $this->enrolledStudent($sy, $section, 12, 'BETA', 'BEN');

        // Student A stays in Ongoing all year (control case).
        // Student B: Ongoing Q1-Q2, transfers to Completed for Q3-Q4.
        foreach ([1, 2] as $qn) {
            $quarter = ClassRecordQuarter::create(['class_record_id' => $ongoing->id, 'quarter' => $qn, 'is_locked' => false]);
            $rowA = $this->activeRow($quarter, $studentA, 'ALPHA', 'ANA', 1);
            $rowB = $this->activeRow($quarter, $studentB, 'BETA', 'BEN', 2);
            $this->scoreQuarter($quarter, $category, [$rowA->id => 100, $rowB->id => 50]);
        }
        foreach ([3, 4] as $qn) {
            $quarter = ClassRecordQuarter::create(['class_record_id' => $ongoing->id, 'quarter' => $qn, 'is_locked' => false]);
            $rowA = $this->activeRow($quarter, $studentA, 'ALPHA', 'ANA', 1);
            $this->scoreQuarter($quarter, $category, [$rowA->id => 100]);
        }
        foreach ([3, 4] as $qn) {
            $quarter = ClassRecordQuarter::create(['class_record_id' => $completed->id, 'quarter' => $qn, 'is_locked' => false]);
            $rowB = $this->activeRow($quarter, $studentB, 'BETA', 'BEN', 1);
            $this->scoreQuarter($quarter, $category, [$rowB->id => 100]);
        }

        // Requested from Ongoing: both students present, none blocked despite
        // student B having no Q3/Q4 rows here.
        $fromOngoing = $this->actingAs($teacher)->getJson(route('class-records.final-grades', $ongoing))->assertOk();
        $fromOngoing->assertJsonCount(2, 'students');

        // Requested from Completed directly: this record's OWN Q1/Q2 rosters
        // are empty (nobody had finished yet) — must not block the whole
        // computation, and must still surface both students correctly.
        $fromCompleted = $this->actingAs($teacher)->getJson(route('class-records.final-grades', $completed))->assertOk();
        $fromCompleted->assertJsonCount(2, 'students');

        $studentBRow = collect($fromCompleted->json('students'))->firstWhere('studentId', $studentB);
        $this->assertNotNull($studentBRow, 'Student B must appear even though Completed has no Q1/Q2 roster of its own.');
        $this->assertNotNull($studentBRow['q3GE']);
        $this->assertNotNull($studentBRow['q4GE']);
        // Q3 must reflect Completed's own (100%) score, not a stale carry-
        // forward of Ongoing's Q2 (50%) score — proves real stitching. GE is
        // a 1.0 (best) - 5.0 (failing) scale, so the better Q3 score means a
        // LOWER GE number than Q2's.
        $this->assertLessThan($studentBRow['q2GE'], $studentBRow['q3GE']);
    }

    private function scoreQuarter(ClassRecordQuarter $quarter, GradingCategory $category, array $scoresByRowId): void
    {
        $assessment = ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id,
            'grading_category_id' => $category->id,
            'assessment_type' => 'formative',
            'is_graded' => true,
            'is_major' => false,
            'assessment_number' => 1,
            'title' => "Quarter {$quarter->quarter} Assessment",
            'max_score' => 100,
            'sort_order' => 1,
        ]);

        foreach ($scoresByRowId as $rowId => $score) {
            ClassRecordScore::create([
                'class_record_student_id' => $rowId,
                'class_record_assessment_id' => $assessment->id,
                'score' => $score,
            ]);
        }
    }
}
