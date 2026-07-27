<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Office;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Read-only monitoring for CID Chief / Academic Unit Head: they can view
 * class record activity and status, but every write path must stay exactly
 * as restricted as before — admin or the owning teacher only.
 */
class ClassRecordMonitorTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;
    private AcademicTerm $term;
    private GradingOption $option;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2026-01-15', 'is_current' => true,
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

    private function makeRecord(User $teacher, ?Section $section = null, ?Subject $subject = null): ClassRecord
    {
        $section ??= $this->makeSection();
        $subject ??= $this->makeSubject();

        return ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);
    }

    private function cidChief(): User
    {
        $role = Role::firstOrCreate(['name' => 'CID Chief']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    /** A unit head — an Office with unit_head = this user; faculty put in that office are "their unit." */
    private function unitHead(): array
    {
        $head = User::factory()->create();
        $office = Office::create(['name' => 'Test Unit '.uniqid(), 'unit_head' => $head->id]);

        return [$head, $office];
    }

    private function makeTeachingAssignment(User $teacher, Subject $subject, Section $section): LoadAssignment
    {
        $load = FacultyLoad::create([
            'user_id' => $teacher->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'teaching_units' => 3, 'total_units' => 3, 'full_load_threshold' => 18, 'load_status' => 'underload',
        ]);

        return LoadAssignment::create([
            'faculty_load_id' => $load->id, 'user_id' => $teacher->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'section_id' => $section->id, 'load_units' => 3,
        ]);
    }

    // ── CID Chief: campus-wide read access, no write access ───────────────────

    public function test_cid_chief_can_view_a_class_record_page(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        $cid = $this->cidChief();

        $this->actingAs($cid)
            ->get(route('class-records.page.show', $record->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ClassRecord/Show')
                ->where('isAdmin', false)
                ->where('isMonitorView', true));
    }

    public function test_cid_chief_cannot_update_or_check_a_class_record(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        $cid = $this->cidChief();

        $this->actingAs($cid)
            ->putJson(route('class-records.update', $record->id), ['grading_option_id' => $this->option->id])
            ->assertForbidden();

        $this->actingAs($cid)
            ->postJson(route('class-records.check', $record->id))
            ->assertForbidden();
    }

    public function test_cid_chief_can_view_scores_attendance_and_ila_endpoints(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        $quarter = ClassRecordQuarter::create(['class_record_id' => $record->id, 'quarter' => 1, 'is_locked' => false]);
        $cid = $this->cidChief();

        $this->actingAs($cid)
            ->getJson(route('class-records.scores.index', ['classRecord' => $record->id, 'q' => 1]))
            ->assertOk();

        $this->actingAs($cid)
            ->getJson(route('class-records.attendance.index', ['classRecord' => $record->id, 'q' => 1]))
            ->assertOk();

        $this->actingAs($cid)
            ->getJson(route('class-records.ila.index', ['classRecord' => $record->id, 'q' => 1]))
            ->assertOk();

        $this->actingAs($cid)
            ->getJson(route('class-records.final-grades', $record->id))
            ->assertOk();
    }

    public function test_cid_chief_cannot_write_scores_attendance_or_ila(): void
    {
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);
        ClassRecordQuarter::create(['class_record_id' => $record->id, 'quarter' => 1, 'is_locked' => false]);
        $cid = $this->cidChief();

        $this->actingAs($cid)
            ->postJson(route('class-records.attendance.store-dates', ['classRecord' => $record->id, 'q' => 1]), ['date' => '2026-08-03'])
            ->assertForbidden();

        $this->actingAs($cid)
            ->postJson(route('class-records.ila.store-date', ['classRecord' => $record->id, 'q' => 1]), ['date' => '2026-08-03'])
            ->assertForbidden();

        $this->actingAs($cid)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $record->id, 'q' => 1]), ['assessments' => []])
            ->assertForbidden();
    }

    // ── Academic Unit Head: scoped to their own unit only ──────────────────────

    public function test_unit_head_can_view_a_record_for_a_teacher_in_their_unit(): void
    {
        [$head, $office] = $this->unitHead();
        $teacher = User::factory()->create(['office_id' => $office->id]);
        $record = $this->makeRecord($teacher);

        $this->actingAs($head)
            ->get(route('class-records.page.show', $record->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('isMonitorView', true));
    }

    public function test_unit_head_cannot_view_a_record_for_a_teacher_outside_their_unit(): void
    {
        [$head, $office] = $this->unitHead();
        // A different office entirely — this teacher is NOT in the unit head's unit.
        $otherOffice = Office::create(['name' => 'Other Office '.uniqid()]);
        $outsideTeacher = User::factory()->create(['office_id' => $otherOffice->id]);
        $record = $this->makeRecord($outsideTeacher);

        $this->actingAs($head)
            ->get(route('class-records.page.show', $record->id))
            ->assertForbidden();
    }

    public function test_plain_faculty_with_no_office_headed_cannot_monitor_anyone(): void
    {
        $bystander = User::factory()->create();
        $teacher = User::factory()->create();
        $record = $this->makeRecord($teacher);

        $this->actingAs($bystander)
            ->get(route('class-records.page.show', $record->id))
            ->assertForbidden();
    }

    // ── Dashboard ──────────────────────────────────────────────────────────────

    public function test_dashboard_rejects_a_non_monitor(): void
    {
        $bystander = User::factory()->create();

        $this->actingAs($bystander)
            ->get(route('class-records.monitor.index'))
            ->assertForbidden();
    }

    public function test_cid_chief_dashboard_sees_every_teaching_assignment_campus_wide(): void
    {
        $teacherA = User::factory()->create();
        $teacherB = User::factory()->create();
        $subjectA = $this->makeSubject();
        $subjectB = $this->makeSubject();
        $sectionA = $this->makeSection();
        $sectionB = $this->makeSection();

        $this->makeTeachingAssignment($teacherA, $subjectA, $sectionA);
        $this->makeTeachingAssignment($teacherB, $subjectB, $sectionB);
        // Only teacherA has actually created a class record.
        $this->makeRecord($teacherA, $sectionA, $subjectA);

        $cid = $this->cidChief();

        $this->actingAs($cid)
            ->get(route('class-records.monitor.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ClassRecord/Monitor/Index')
                ->where('stats.total_assignments', 2)
                ->where('stats.created', 1)
                ->where('stats.missing', 1));
    }

    public function test_unit_head_dashboard_only_counts_their_own_units_assignments(): void
    {
        [$head, $office] = $this->unitHead();
        $inUnitTeacher = User::factory()->create(['office_id' => $office->id]);
        $outsideTeacher = User::factory()->create();

        $subject1 = $this->makeSubject();
        $subject2 = $this->makeSubject();
        $section1 = $this->makeSection();
        $section2 = $this->makeSection();

        $this->makeTeachingAssignment($inUnitTeacher, $subject1, $section1);
        $this->makeTeachingAssignment($outsideTeacher, $subject2, $section2);

        $this->actingAs($head)
            ->get(route('class-records.monitor.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('stats.total_assignments', 1)
                ->where('rows.0.teacher_id', $inUnitTeacher->id));
    }
}
