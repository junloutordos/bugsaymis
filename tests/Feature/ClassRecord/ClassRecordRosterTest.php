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
use App\Services\Registrar\StudentTranscriptService;
use Database\Seeders\StanineLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ClassRecordRosterTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_candidates_are_limited_to_the_exact_section(): void
    {
        [$teacher, $schoolYear, $option] = $this->baseRecords();
        $sectionA = $this->section($schoolYear, 11, 'Electron');
        $sectionB = $this->section($schoolYear, 11, 'Photon');
        $subject = $this->subject($schoolYear, 'MATH11', 'Mathematics 5', 'lecture', 11);
        $record = $this->classRecord($teacher, $schoolYear, $option, $subject, $sectionA);

        $eligibleId = $this->enrolledStudent($schoolYear, $sectionA, 11, 'ALPHA', 'ANA');
        $this->enrolledStudent($schoolYear, $sectionB, 11, 'BETA', 'BEN');

        $response = $this->actingAs($teacher)->getJson(route('class-records.students.candidates', [
            'classRecord' => $record->id,
            'q' => 1,
            'search' => 'A',
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'students')
            ->assertJsonPath('students.0.student_id', $eligibleId)
            ->assertJsonPath('is_cross_section', false);
    }

    public function test_science_core_candidates_include_all_sections_in_the_same_grade_only(): void
    {
        [$teacher, $schoolYear, $option] = $this->baseRecords();
        $grade11A = $this->section($schoolYear, 11, 'Electron');
        $grade11B = $this->section($schoolYear, 11, 'Photon');
        $grade12 = $this->section($schoolYear, 12, 'Graviton');
        $subject = $this->subject($schoolYear, 'SCI-CORE-11', 'Science Core 11', 'science_core', 11);
        $record = $this->classRecord($teacher, $schoolYear, $option, $subject, null);

        $firstId = $this->enrolledStudent($schoolYear, $grade11A, 11, 'ALPHA', 'ANA');
        $secondId = $this->enrolledStudent($schoolYear, $grade11B, 11, 'BETA', 'BEN');
        $this->enrolledStudent($schoolYear, $grade12, 12, 'GAMMA', 'GIA');

        $response = $this->actingAs($teacher)->getJson(route('class-records.students.candidates', [
            'classRecord' => $record->id,
            'q' => 1,
        ]));

        $response->assertOk()
            ->assertJsonPath('is_cross_section', true)
            ->assertJsonPath('grade_levels', [11]);

        $this->assertEqualsCanonicalizing(
            [$firstId, $secondId],
            collect($response->json('students'))->pluck('student_id')->all(),
        );

        $this->actingAs($teacher)
            ->getJson(route('class-records.students.from-enrollment', [
                'classRecord' => $record->id,
                'q' => 1,
            ]))
            ->assertStatus(422);
    }

    public function test_roster_reordering_preserves_row_identity_and_deactivates_omitted_students(): void
    {
        [$teacher, $schoolYear, $option] = $this->baseRecords();
        $section = $this->section($schoolYear, 11, 'Electron');
        $subject = $this->subject($schoolYear, 'SCI-CORE-11', 'Science Core 11', 'science_core', 11);
        $record = $this->classRecord($teacher, $schoolYear, $option, $subject, null);
        $studentA = $this->enrolledStudent($schoolYear, $section, 11, 'ALPHA', 'ANA');
        $studentB = $this->enrolledStudent($schoolYear, $section, 11, 'BETA', 'BEN');
        $quarter = ClassRecordQuarter::create([
            'class_record_id' => $record->id,
            'quarter' => 1,
            'is_locked' => false,
        ]);
        $rowA = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id,
            'student_id' => $studentA,
            'family_name' => 'ALPHA',
            'given_name' => 'ANA',
            'sex' => 'F',
            'sequence_number' => 1,
            'is_active' => true,
        ]);
        $rowB = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id,
            'student_id' => $studentB,
            'family_name' => 'BETA',
            'given_name' => 'BEN',
            'sex' => 'M',
            'sequence_number' => 2,
            'is_active' => true,
        ]);

        $payload = [
            'students' => [[
                'id' => $rowA->id,
                'student_id' => $studentA,
                'family_name' => 'IGNORED',
                'given_name' => 'IGNORED',
                'middle_initial' => null,
                'sex' => 'F',
                'sequence_number' => 1,
            ]],
        ];

        $this->actingAs($teacher)
            ->postJson(route('class-records.students.upsert', [
                'classRecord' => $record->id,
                'q' => 1,
            ]), $payload)
            ->assertOk();

        $this->assertDatabaseHas('class_record_students', [
            'id' => $rowA->id,
            'student_id' => $studentA,
            'family_name' => 'ALPHA',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('class_record_students', [
            'id' => $rowB->id,
            'student_id' => $studentB,
            'is_active' => false,
        ]);
    }

    public function test_roster_rejects_a_student_outside_the_allowed_scope(): void
    {
        [$teacher, $schoolYear, $option] = $this->baseRecords();
        $sectionA = $this->section($schoolYear, 11, 'Electron');
        $sectionB = $this->section($schoolYear, 11, 'Photon');
        $subject = $this->subject($schoolYear, 'MATH11', 'Mathematics 5', 'lecture', 11);
        $record = $this->classRecord($teacher, $schoolYear, $option, $subject, $sectionA);
        $outsideId = $this->enrolledStudent($schoolYear, $sectionB, 11, 'OUTSIDE', 'STUDENT');

        $this->actingAs($teacher)
            ->postJson(route('class-records.students.upsert', [
                'classRecord' => $record->id,
                'q' => 1,
            ]), [
                'students' => [[
                    'student_id' => $outsideId,
                    'family_name' => 'OUTSIDE',
                    'given_name' => 'STUDENT',
                    'middle_initial' => null,
                    'sex' => 'M',
                    'sequence_number' => 1,
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('students');
    }

    public function test_science_core_class_record_can_be_created_without_a_section(): void
    {
        [$teacher, $schoolYear, $option] = $this->baseRecords();
        $subject = $this->subject($schoolYear, 'SCI-CORE-12', 'Science Core 12', 'science_core', 12);

        $response = $this->actingAs($teacher)->postJson(route('class-records.store'), [
            'subject_id' => $subject->id,
            'grading_option_id' => $option->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.section_id', null)
            ->assertJsonPath('data.year_level_section', 'Grade 12 — Science Core');
    }

    public function test_final_grades_match_the_master_student_across_different_quarter_row_ids(): void
    {
        [$teacher, $schoolYear, $option] = $this->baseRecords();
        $category = GradingCategory::create([
            'grading_option_id' => $option->id,
            'name' => 'Assessments',
            'code' => 'AA',
            'weight' => 1,
            'max_assessments' => 1,
            'sort_order' => 1,
        ]);
        $section = $this->section($schoolYear, 11, 'Electron');
        $subject = $this->subject($schoolYear, 'SCI-CORE-11', 'Science Core 11', 'science_core', 11);
        $record = $this->classRecord($teacher, $schoolYear, $option, $subject, null);
        $studentId = $this->enrolledStudent($schoolYear, $section, 11, 'ALPHA', 'ANA');
        $this->seed(StanineLookupSeeder::class);

        $rosterRowIds = [];
        foreach ([1, 2, 3, 4] as $quarterNumber) {
            $quarter = ClassRecordQuarter::create([
                'class_record_id' => $record->id,
                'quarter' => $quarterNumber,
                'is_locked' => false,
            ]);
            $rosterRow = ClassRecordStudent::create([
                'class_record_quarter_id' => $quarter->id,
                'student_id' => $studentId,
                'family_name' => 'ALPHA',
                'given_name' => 'ANA',
                'sex' => 'F',
                'sequence_number' => 1,
                'is_active' => true,
            ]);
            $assessment = ClassRecordAssessment::create([
                'class_record_quarter_id' => $quarter->id,
                'grading_category_id' => $category->id,
                'assessment_type' => 'formative',
                'is_graded' => true,
                'is_major' => false,
                'assessment_number' => 1,
                'title' => "Quarter {$quarterNumber} Assessment",
                'max_score' => 100,
                'sort_order' => 1,
            ]);
            ClassRecordScore::create([
                'class_record_student_id' => $rosterRow->id,
                'class_record_assessment_id' => $assessment->id,
                'score' => 100,
            ]);
            $rosterRowIds[] = $rosterRow->id;
        }

        $this->assertCount(4, array_unique($rosterRowIds));

        $this->actingAs($teacher)
            ->getJson(route('class-records.final-grades', $record))
            ->assertOk()
            ->assertJsonCount(1, 'students')
            ->assertJsonPath('students.0.studentId', $studentId);

        $record->update(['status' => 'checked']);
        app(StudentTranscriptService::class)->computeAndSave($studentId, $schoolYear->id);

        $this->assertDatabaseHas('student_annual_grades', [
            'student_id' => $studentId,
            'class_record_id' => $record->id,
            'subject_id' => $subject->id,
        ]);
    }

    private function baseRecords(): array
    {
        $teacher = User::factory()->create(['email_verified_at' => now()]);
        $schoolYear = SchoolYear::create([
            'name' => '2026-2027',
            'start_date' => '2026-08-01',
            'end_date' => '2027-05-31',
            'is_current' => true,
            'status' => 'active',
        ]);
        $option = GradingOption::create([
            'name' => 'Standard',
            'is_active' => true,
        ]);

        return [$teacher, $schoolYear, $option];
    }

    private function section(SchoolYear $schoolYear, int $grade, string $name): Section
    {
        return Section::create([
            'levelid' => $grade,
            'sectionname' => $name,
            'syid' => $schoolYear->id,
            'school_year_id' => $schoolYear->id,
            'is_active' => true,
        ]);
    }

    private function subject(
        SchoolYear $schoolYear,
        string $code,
        string $name,
        string $type,
        int $grade,
    ): Subject {
        return Subject::create([
            'school_year_id' => $schoolYear->id,
            'code' => $code,
            'name' => $name,
            'subject_type' => $type,
            'grade_level' => $grade,
            'is_active' => true,
        ]);
    }

    private function classRecord(
        User $teacher,
        SchoolYear $schoolYear,
        GradingOption $option,
        Subject $subject,
        ?Section $section,
    ): ClassRecord {
        return ClassRecord::create([
            'subject_id' => $subject->id,
            'section_id' => $section?->id,
            'teacher_id' => $teacher->id,
            'grading_option_id' => $option->id,
            'school_year_id' => $schoolYear->id,
            'school_year' => $schoolYear->name,
            'subject_name' => $subject->name,
            'year_level_section' => $section
                ? "G-{$section->levelid} {$section->sectionname}"
                : "Grade {$subject->grade_level} — Cross-section",
            'status' => 'draft',
        ]);
    }

    private function enrolledStudent(
        SchoolYear $schoolYear,
        Section $section,
        int $grade,
        string $lastName,
        string $firstName,
    ): int {
        $studentId = DB::table('students')->insertGetId([
            'lastname' => $lastName,
            'firstname' => $firstName,
            'middlename' => null,
            'sex' => $firstName === 'ANA' ? 'Female' : 'Male',
            'status' => 'active',
        ]);

        StudentEnrollment::create([
            'student_id' => $studentId,
            'school_year_id' => $schoolYear->id,
            'section_id' => $section->id,
            'grade_level' => $grade,
            'enrollment_type' => 'returning',
            'status' => 'enrolled',
            'enrollment_date' => '2026-08-01',
        ]);

        return $studentId;
    }
}
