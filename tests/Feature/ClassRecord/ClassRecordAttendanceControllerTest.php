<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAttendanceDate;
use App\Models\ClassRecord\ClassRecordAttendanceRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingOption;
use App\Models\Consultation;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression coverage for an IDOR caught by security review: upsert() only
 * validated that date_id/student_id existed *somewhere*, not that they
 * belonged to the class record/quarter being edited — a teacher could pass
 * IDs from another class record entirely and tamper with someone else's
 * attendance data.
 */
class ClassRecordAttendanceControllerTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;

    private GradingOption $option;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->option = GradingOption::create(['name' => 'Default', 'is_active' => true]);
    }

    private function makeSection(): Section
    {
        return Section::create([
            'levelid' => 8, 'sectionname' => 'Section-'.uniqid(), 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ]);
    }

    private function makeSubject(): Subject
    {
        return Subject::create([
            'school_year_id' => $this->sy->id, 'code' => 'SUBJ-'.uniqid(), 'name' => 'Subject 1',
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ]);
    }

    private function makeRecord(User $teacher): ClassRecord
    {
        $section = $this->makeSection();
        $subject = $this->makeSubject();

        return ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);
    }

    private function makeQuarter(ClassRecord $record, int $q = 1): ClassRecordQuarter
    {
        return ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $this->option->id,
            'quarter' => $q, 'is_locked' => false,
        ]);
    }

    public function test_teacher_can_mark_and_save_attendance(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        $quarter = $this->makeQuarter($record);
        $date = ClassRecordAttendanceDate::create(['class_record_quarter_id' => $quarter->id, 'date' => '2026-08-03', 'sort_order' => 1]);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.attendance.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'records' => [['date_id' => $date->id, 'student_id' => $student->id, 'status' => 'absent']],
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_attendance_records', [
            'class_record_attendance_date_id' => $date->id,
            'class_record_student_id' => $student->id,
            'status' => 'absent',
        ]);
    }

    /**
     * CIM 3.6/3.6.2 — cut class can only be witnessed and asserted by the
     * subject teacher (student on campus but skips/leaves this period). It
     * must be a directly-selectable status on this endpoint.
     */
    public function test_teacher_can_mark_cut_class(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        $quarter = $this->makeQuarter($record);
        $date = ClassRecordAttendanceDate::create(['class_record_quarter_id' => $quarter->id, 'date' => '2026-08-03', 'sort_order' => 1]);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.attendance.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'records' => [['date_id' => $date->id, 'student_id' => $student->id, 'status' => 'cut_class']],
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_attendance_records', [
            'class_record_attendance_date_id' => $date->id,
            'class_record_student_id' => $student->id,
            'status' => 'cut_class',
            'synced_from_homeroom' => false,
        ]);
    }

    public function test_teacher_can_add_an_optional_remark_to_an_exception_status(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        $quarter = $this->makeQuarter($record);
        $date = ClassRecordAttendanceDate::create(['class_record_quarter_id' => $quarter->id, 'date' => '2026-08-03', 'sort_order' => 1]);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.attendance.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'records' => [[
                    'date_id' => $date->id,
                    'student_id' => $student->id,
                    'status' => 'tardy',
                    'remarks' => '  Arrived 10 minutes late.  ',
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_attendance_records', [
            'class_record_attendance_date_id' => $date->id,
            'class_record_student_id' => $student->id,
            'status' => 'tardy',
            'remarks' => 'Arrived 10 minutes late.',
        ]);

        $this->actingAs($teacher)
            ->getJson(route('class-records.attendance.index', ['classRecord' => $record->id, 'q' => 1]))
            ->assertOk()
            ->assertJsonPath("records.{$student->id}_{$date->id}.remarks", 'Arrived 10 minutes late.');
    }

    public function test_remark_is_cleared_when_status_changes_to_present(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        $quarter = $this->makeQuarter($record);
        $date = ClassRecordAttendanceDate::create(['class_record_quarter_id' => $quarter->id, 'date' => '2026-08-03', 'sort_order' => 1]);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);
        ClassRecordAttendanceRecord::create([
            'class_record_attendance_date_id' => $date->id,
            'class_record_student_id' => $student->id,
            'status' => 'absent',
            'remarks' => 'Initially absent.',
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.attendance.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'records' => [[
                    'date_id' => $date->id,
                    'student_id' => $student->id,
                    'status' => 'present',
                    'remarks' => 'This must not remain.',
                ]],
            ])
            ->assertOk();

        $this->assertDatabaseHas('class_record_attendance_records', [
            'class_record_attendance_date_id' => $date->id,
            'class_record_student_id' => $student->id,
            'status' => 'present',
            'remarks' => null,
        ]);
    }

    public function test_upsert_rejects_a_remark_longer_than_500_characters(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        $quarter = $this->makeQuarter($record);
        $date = ClassRecordAttendanceDate::create(['class_record_quarter_id' => $quarter->id, 'date' => '2026-08-03', 'sort_order' => 1]);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.attendance.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'records' => [[
                    'date_id' => $date->id,
                    'student_id' => $student->id,
                    'status' => 'absent',
                    'remarks' => str_repeat('x', 501),
                ]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('records.0.remarks');
    }

    public function test_upsert_rejects_an_invalid_status_value(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        $quarter = $this->makeQuarter($record);
        $date = ClassRecordAttendanceDate::create(['class_record_quarter_id' => $quarter->id, 'date' => '2026-08-03', 'sort_order' => 1]);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.attendance.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'records' => [['date_id' => $date->id, 'student_id' => $student->id, 'status' => 'excused']],
            ])
            ->assertStatus(422);
    }

    public function test_upsert_rejects_a_date_belonging_to_another_class_record(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        $quarter = $this->makeQuarter($record);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'family_name' => 'Doe', 'given_name' => 'Jane',
            'sequence_number' => 1, 'is_active' => true,
        ]);

        $otherRecord = $this->makeRecord(User::factory()->create());
        $otherQuarter = $this->makeQuarter($otherRecord);
        $foreignDate = ClassRecordAttendanceDate::create(['class_record_quarter_id' => $otherQuarter->id, 'date' => '2026-08-03', 'sort_order' => 1]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.attendance.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'records' => [['date_id' => $foreignDate->id, 'student_id' => $student->id, 'status' => 'absent']],
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'One or more dates do not belong to this quarter.']);

        $this->assertDatabaseMissing('class_record_attendance_records', ['class_record_attendance_date_id' => $foreignDate->id]);
    }

    public function test_upsert_rejects_a_student_belonging_to_another_class_record(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        $quarter = $this->makeQuarter($record);
        $date = ClassRecordAttendanceDate::create(['class_record_quarter_id' => $quarter->id, 'date' => '2026-08-03', 'sort_order' => 1]);

        $otherRecord = $this->makeRecord(User::factory()->create());
        $otherQuarter = $this->makeQuarter($otherRecord);
        $foreignStudent = ClassRecordStudent::create([
            'class_record_quarter_id' => $otherQuarter->id, 'family_name' => 'Roe', 'given_name' => 'Richard',
            'sequence_number' => 1, 'is_active' => true,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.attendance.upsert', ['classRecord' => $record->id, 'q' => 1]), [
                'records' => [['date_id' => $date->id, 'student_id' => $foreignStudent->id, 'status' => 'absent']],
            ])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'One or more students do not belong to this class record.']);

        $this->assertDatabaseMissing('class_record_attendance_records', ['class_record_student_id' => $foreignStudent->id]);
    }

    public function test_locked_quarter_blocks_attendance_changes(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        $quarter = ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $this->option->id,
            'quarter' => 1, 'is_locked' => true,
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.attendance.store-dates', ['classRecord' => $record->id, 'q' => 1]), [
                'date' => '2026-08-03',
            ])
            ->assertStatus(403);
    }

    public function test_teacher_can_verify_matching_completed_clinic_slip_and_mark_cc_as_ecc(): void
    {
        $teacher = User::factory()->create();
        $nurse = User::factory()->create(['name' => 'Nurse Santos']);
        $record = $this->makeRecord($teacher);
        $quarter = $this->makeQuarter($record);
        $date = ClassRecordAttendanceDate::create([
            'class_record_quarter_id' => $quarter->id,
            'date' => '2026-06-03',
            'sort_order' => 1,
        ]);
        $globalStudentId = DB::table('students')->insertGetId([
            'lastname' => 'Doe',
            'firstname' => 'Jane',
            'middlename' => '',
            'sex' => 'F',
            'batch' => '2026',
        ]);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id,
            'student_id' => $globalStudentId,
            'family_name' => 'Doe',
            'given_name' => 'Jane',
            'sequence_number' => 1,
            'is_active' => true,
        ]);
        $attendance = ClassRecordAttendanceRecord::create([
            'class_record_attendance_date_id' => $date->id,
            'class_record_student_id' => $student->id,
            'status' => 'cut_class',
            'excused_status' => 'n_a',
        ]);
        $consultation = Consultation::create([
            'student_id' => $globalStudentId,
            'status' => 'Completed',
            'reason' => 'Private medical complaint',
            'date_attended' => '2026-06-03 10:05:00',
            'time_start' => '10:05:00',
            'nurse_id' => $nurse->id,
        ]);

        $this->actingAs($teacher)
            ->getJson(route('class-records.attendance.clinic-consultations', [
                'classRecord' => $record->id,
                'q' => 1,
                'attendanceRecord' => $attendance->id,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'consultations')
            ->assertJsonPath('consultations.0.id', $consultation->id)
            ->assertJsonPath('consultations.0.verified_by', 'Nurse Santos')
            ->assertJsonMissing(['reason' => 'Private medical complaint']);

        $this->actingAs($teacher)
            ->postJson(route('class-records.attendance.excuse-via-clinic', [
                'classRecord' => $record->id,
                'q' => 1,
                'attendanceRecord' => $attendance->id,
            ]), ['consultation_id' => $consultation->id])
            ->assertOk()
            ->assertJsonPath('excused_status', 'excused');

        $this->assertDatabaseHas('class_record_attendance_records', [
            'id' => $attendance->id,
            'status' => 'cut_class',
            'excused_status' => 'excused',
            'clinic_consultation_id' => $consultation->id,
            'excused_by' => $teacher->id,
        ]);
    }

    public function test_clinic_verification_rejects_wrong_student_or_date_and_unauthorized_teacher(): void
    {
        $teacher = User::factory()->create();
        $otherTeacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        $quarter = $this->makeQuarter($record);
        $date = ClassRecordAttendanceDate::create([
            'class_record_quarter_id' => $quarter->id,
            'date' => '2026-06-03',
            'sort_order' => 1,
        ]);
        $globalStudentId = DB::table('students')->insertGetId([
            'lastname' => 'Doe',
            'firstname' => 'Jane',
            'middlename' => '',
            'sex' => 'F',
            'batch' => '2026',
        ]);
        $otherStudentId = DB::table('students')->insertGetId([
            'lastname' => 'Roe',
            'firstname' => 'John',
            'middlename' => '',
            'sex' => 'M',
            'batch' => '2026',
        ]);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id,
            'student_id' => $globalStudentId,
            'family_name' => 'Doe',
            'given_name' => 'Jane',
            'sequence_number' => 1,
            'is_active' => true,
        ]);
        $attendance = ClassRecordAttendanceRecord::create([
            'class_record_attendance_date_id' => $date->id,
            'class_record_student_id' => $student->id,
            'status' => 'cut_class',
            'excused_status' => 'n_a',
        ]);
        $wrongConsultation = Consultation::create([
            'student_id' => $otherStudentId,
            'status' => 'Completed',
            'date_attended' => '2026-06-04 10:05:00',
        ]);

        $route = route('class-records.attendance.excuse-via-clinic', [
            'classRecord' => $record->id,
            'q' => 1,
            'attendanceRecord' => $attendance->id,
        ]);

        $this->actingAs($otherTeacher)
            ->postJson($route, ['consultation_id' => $wrongConsultation->id])
            ->assertForbidden();

        $this->actingAs($teacher)
            ->postJson($route, ['consultation_id' => $wrongConsultation->id])
            ->assertStatus(422);

        $this->assertDatabaseHas('class_record_attendance_records', [
            'id' => $attendance->id,
            'excused_status' => 'n_a',
            'clinic_consultation_id' => null,
        ]);
    }

    public function test_normal_attendance_save_cannot_change_a_resolved_ecc_record(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        $quarter = $this->makeQuarter($record);
        $date = ClassRecordAttendanceDate::create([
            'class_record_quarter_id' => $quarter->id,
            'date' => '2026-06-03',
            'sort_order' => 1,
        ]);
        $student = ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id,
            'family_name' => 'Doe',
            'given_name' => 'Jane',
            'sequence_number' => 1,
            'is_active' => true,
        ]);
        ClassRecordAttendanceRecord::create([
            'class_record_attendance_date_id' => $date->id,
            'class_record_student_id' => $student->id,
            'status' => 'cut_class',
            'excused_status' => 'excused',
            'excused_by' => $teacher->id,
            'excused_at' => now(),
        ]);

        $this->actingAs($teacher)
            ->postJson(route('class-records.attendance.upsert', [
                'classRecord' => $record->id,
                'q' => 1,
            ]), [
                'records' => [[
                    'date_id' => $date->id,
                    'student_id' => $student->id,
                    'status' => 'present',
                ]],
            ])
            ->assertStatus(422);

        $this->actingAs($teacher)
            ->deleteJson(route('class-records.attendance.destroy-date', [
                'classRecord' => $record->id,
                'q' => 1,
                'attendanceDate' => $date->id,
            ]))
            ->assertStatus(422);

        $this->assertDatabaseHas('class_record_attendance_records', [
            'class_record_attendance_date_id' => $date->id,
            'class_record_student_id' => $student->id,
            'status' => 'cut_class',
            'excused_status' => 'excused',
        ]);
    }
}
