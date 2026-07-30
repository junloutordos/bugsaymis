<?php

namespace Tests\Feature\HomeroomAttendance;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAttendanceDate;
use App\Models\ClassRecord\ClassRecordAttendanceRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\ClassRecordStudent;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\HomeroomAttendance\AttendanceDate;
use App\Models\HomeroomAttendance\AttendanceRecord;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\HomeroomAttendance\AdmissionSlipService;
use App\Services\HomeroomAttendance\MonthlyReportService;
use App\Services\HomeroomAttendance\RosterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers CIM 3.6/3.6.2: cut class can only be witnessed and asserted by the
 * subject teacher (status = 'cut_class' on Class Record Attendance). The
 * pre-existing mismatch-derived signal (subject Absent + homeroom
 * Present/Tardy) is kept as a secondary safety net — both must be surfaced
 * to the Registrar's admission-slip queue and both must feed the Monthly
 * Report's cutting_count without double-counting.
 */
class CutClassIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private GradingOption $option;
    private Section $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->option = GradingOption::create(['name' => 'Default', 'is_active' => true]);
        $this->section = Section::create([
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

    private function makeClassRecord(User $teacher): ClassRecord
    {
        $subject = $this->makeSubject();

        return ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $this->section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$this->section->levelid} {$this->section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);
    }

    private function makeStudent(): Student
    {
        // Student is deliberately read-only ($guarded = ['*']) — the app
        // never writes to this legacy table via Eloquent, so tests insert
        // directly the same way real enrollment data already exists.
        $id = DB::table('students')->insertGetId([
            'lastname' => 'Doe', 'firstname' => 'Jane', 'middlename' => '', 'sex' => 'F', 'batch' => '2026',
        ]);

        return Student::find($id);
    }

    /** Wires a global Student into a class record's quarter roster AND the section's homeroom/enrollment roster. */
    private function enroll(Student $student, ClassRecordQuarter $quarter): ClassRecordStudent
    {
        StudentEnrollment::create([
            'student_id' => $student->id, 'section_id' => $this->section->id,
            'school_year_id' => $this->sy->id, 'status' => 'enrolled',
            'grade_level' => $this->section->levelid, 'enrollment_date' => '2025-08-01',
        ]);

        return ClassRecordStudent::create([
            'class_record_quarter_id' => $quarter->id, 'student_id' => $student->id,
            'family_name' => $student->lastname, 'given_name' => $student->firstname,
            'sequence_number' => 1, 'is_active' => true,
        ]);
    }

    private function makeHomeroomRecord(Student $student, string $date, string $status, ?User $adviser = null): AttendanceRecord
    {
        $adviser ??= User::factory()->create();

        $attendanceDate = AttendanceDate::firstOrCreate(
            ['section_id' => $this->section->id, 'date' => $date],
            ['school_year_id' => $this->sy->id, 'taken_by' => $adviser->id],
        );

        return AttendanceRecord::create([
            'homeroom_attendance_date_id' => $attendanceDate->id,
            'student_id' => $student->id,
            'status' => $status,
        ]);
    }

    // ── AdmissionSlipService::pending() ─────────────────────────────────────

    public function test_pending_surfaces_teacher_asserted_cut_class(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeClassRecord($teacher);
        $quarter = ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $this->option->id, 'quarter' => 1, 'is_locked' => false,
        ]);
        $student = $this->makeStudent();
        $crStudent = $this->enroll($student, $quarter);
        $date = ClassRecordAttendanceDate::create(['class_record_quarter_id' => $quarter->id, 'date' => '2026-08-03', 'sort_order' => 1]);

        ClassRecordAttendanceRecord::create([
            'class_record_attendance_date_id' => $date->id,
            'class_record_student_id' => $crStudent->id,
            'status' => 'cut_class',
            'excused_status' => 'n_a',
        ]);

        $pending = app(AdmissionSlipService::class)->pending($this->section->id);

        $this->assertTrue($pending->contains(fn ($item) => $item['infraction_type'] === 'cut_class' && $item['student_id'] === $student->id));
    }

    public function test_pending_still_surfaces_suspected_mismatch_as_secondary_signal(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeClassRecord($teacher);
        $quarter = ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $this->option->id, 'quarter' => 1, 'is_locked' => false,
        ]);
        $student = $this->makeStudent();
        $crStudent = $this->enroll($student, $quarter);
        $date = ClassRecordAttendanceDate::create(['class_record_quarter_id' => $quarter->id, 'date' => '2026-08-04', 'sort_order' => 1]);

        // Teacher marked Absent (not Cut Class) while homeroom says Present.
        ClassRecordAttendanceRecord::create([
            'class_record_attendance_date_id' => $date->id,
            'class_record_student_id' => $crStudent->id,
            'status' => 'absent',
            'excused_status' => 'n_a',
        ]);
        $this->makeHomeroomRecord($student, '2026-08-04', 'present');

        $pending = app(AdmissionSlipService::class)->pending($this->section->id);

        $this->assertTrue($pending->contains(fn ($item) => $item['infraction_type'] === 'cutting_suspected' && $item['student_id'] === $student->id));
    }

    public function test_asserted_cut_class_does_not_also_appear_as_suspected(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeClassRecord($teacher);
        $quarter = ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $this->option->id, 'quarter' => 1, 'is_locked' => false,
        ]);
        $student = $this->makeStudent();
        $crStudent = $this->enroll($student, $quarter);
        $date = ClassRecordAttendanceDate::create(['class_record_quarter_id' => $quarter->id, 'date' => '2026-08-05', 'sort_order' => 1]);

        ClassRecordAttendanceRecord::create([
            'class_record_attendance_date_id' => $date->id,
            'class_record_student_id' => $crStudent->id,
            'status' => 'cut_class',
            'excused_status' => 'n_a',
        ]);
        $this->makeHomeroomRecord($student, '2026-08-05', 'present');

        $pending = app(AdmissionSlipService::class)->pending($this->section->id);
        $cuttingEntries = $pending->filter(fn ($item) => $item['student_id'] === $student->id);

        $this->assertCount(1, $cuttingEntries);
        $this->assertSame('cut_class', $cuttingEntries->first()['infraction_type']);
    }

    // ── AdmissionSlipService::issue() ───────────────────────────────────────

    public function test_issue_resolves_teacher_asserted_cut_class(): void
    {
        $teacher = User::factory()->create();
        $registrar = User::factory()->create();
        $record = $this->makeClassRecord($teacher);
        $quarter = ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $this->option->id, 'quarter' => 1, 'is_locked' => false,
        ]);
        $student = $this->makeStudent();
        $crStudent = $this->enroll($student, $quarter);
        $date = ClassRecordAttendanceDate::create(['class_record_quarter_id' => $quarter->id, 'date' => '2026-08-06', 'sort_order' => 1]);

        $carRow = ClassRecordAttendanceRecord::create([
            'class_record_attendance_date_id' => $date->id,
            'class_record_student_id' => $crStudent->id,
            'status' => 'cut_class',
            'excused_status' => 'n_a',
        ]);

        $slip = app(AdmissionSlipService::class)->issue([
            'student_id' => $student->id,
            'section_id' => $this->section->id,
            'infraction_date' => '2026-08-06',
            'infraction_type' => 'cut_class',
            'excused_status' => 'unexcused',
        ], $registrar->id);

        $this->assertSame('cut_class', $slip->infraction_type);
        $this->assertSame('Cut Class', $slip->infraction_type_label);

        $carRow->refresh();
        $this->assertSame('unexcused', $carRow->excused_status);
        $this->assertSame($slip->id, $carRow->admission_slip_id);
    }

    // ── MonthlyReportService::cuttingCountsForMonth() (via generate()) ──────

    public function test_monthly_report_counts_asserted_and_suspected_cutting_without_double_counting(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeClassRecord($teacher);
        $quarter = ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $this->option->id, 'quarter' => 1, 'is_locked' => false,
        ]);
        $student = $this->makeStudent();
        $crStudent = $this->enroll($student, $quarter);

        // Day 1: teacher-asserted cut class (homeroom marks the student present that day).
        $date1 = ClassRecordAttendanceDate::create(['class_record_quarter_id' => $quarter->id, 'date' => '2026-08-03', 'sort_order' => 1]);
        ClassRecordAttendanceRecord::create([
            'class_record_attendance_date_id' => $date1->id,
            'class_record_student_id' => $crStudent->id,
            'status' => 'cut_class',
            'excused_status' => 'n_a',
        ]);
        $this->makeHomeroomRecord($student, '2026-08-03', 'present');

        // Day 2: suspected mismatch (teacher marked Absent, homeroom says Tardy).
        $date2 = ClassRecordAttendanceDate::create(['class_record_quarter_id' => $quarter->id, 'date' => '2026-08-04', 'sort_order' => 2]);
        ClassRecordAttendanceRecord::create([
            'class_record_attendance_date_id' => $date2->id,
            'class_record_student_id' => $crStudent->id,
            'status' => 'absent',
            'excused_status' => 'n_a',
        ]);
        $this->makeHomeroomRecord($student, '2026-08-04', 'tardy');

        // Homeroom attendance dates for BOTH days must exist for school_days_count.
        $report = app(MonthlyReportService::class)->generate($this->section->id, $this->sy->id, 2026, 8);
        $line = $report->lines->firstWhere('student_id', $student->id);

        $this->assertNotNull($line);
        $this->assertSame(2, $line->cutting_count, 'Expected one asserted + one suspected cutting instance = 2 total, not double-counted.');
    }
}
