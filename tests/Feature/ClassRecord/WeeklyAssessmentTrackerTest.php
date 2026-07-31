<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\DesignationCategory;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\FacultyLoading\Subject;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ClassRecord\WatRuleService;
use App\Services\FacultyLoading\HeadAdvisoryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class WeeklyAssessmentTrackerTest extends TestCase
{
    use RefreshDatabase;

    private SchoolYear $sy;

    private AcademicTerm $term;

    private GradingOption $option;

    private GradingCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sy = SchoolYear::create([
            'name' => '2025-2026', 'start_date' => '2025-08-01', 'end_date' => '2026-06-30',
            'is_current' => true, 'status' => 'active',
        ]);
        $this->term = AcademicTerm::create([
            'school_year_id' => $this->sy->id, 'name' => '1st Semester', 'term_type' => '1st_semester',
            'start_date' => '2025-08-01', 'end_date' => '2025-12-31', 'is_current' => true,
        ]);
        $this->option = GradingOption::create(['name' => 'Default', 'is_active' => true]);
        $this->category = GradingCategory::create([
            'grading_option_id' => $this->option->id, 'name' => 'Formative', 'code' => 'FA',
            'weight' => 1.0, 'max_assessments' => 10, 'sort_order' => 1,
        ]);
    }

    private function admin(): User
    {
        $permission = Permission::firstOrCreate(
            ['name' => 'class-records.admin'],
            ['module' => 'Class Records', 'description' => 'Admin'],
        );
        $role = Role::create(['name' => 'ClassRecordAdmin_'.uniqid()]);
        $role->permissions()->attach($permission->id);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    private function makeSection(array $overrides = []): Section
    {
        return Section::create(array_merge([
            'levelid' => 8, 'sectionname' => 'Emerald', 'syid' => $this->sy->id,
            'school_year_id' => $this->sy->id, 'is_active' => true,
        ], $overrides));
    }

    private function makeSubject(array $overrides = []): Subject
    {
        static $i = 0;
        $i++;

        return Subject::create(array_merge([
            'school_year_id' => $this->sy->id, 'code' => "SUBJ{$i}", 'name' => "Subject {$i}",
            'credit_units' => 3, 'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
            'grade_level' => 8, 'sessions_per_week' => 5, 'minutes_per_session' => 60, 'is_active' => true,
        ], $overrides));
    }

    /** Designation-based Homeroom Coordinator — HR_ADV/HR_ACAD category, optionally section-scoped. */
    private function assignCoordinator(User $user, string $categoryCode, string $code, string $name, ?Section $section = null): LoadAssignment
    {
        $category = DesignationCategory::firstOrCreate(
            ['code' => $categoryCode],
            ['name' => $categoryCode, 'is_active' => true],
        );
        $designation = Designation::create([
            'designation_category_id' => $category->id, 'section_id' => $section?->id,
            'code' => $code, 'name' => $name, 'load_units' => 3,
            'assignment_type' => 'admin', 'is_active' => true,
        ]);
        $facultyLoad = FacultyLoad::create([
            'user_id' => $user->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        return LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $user->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'assignment_type' => 'admin', 'section_id' => $section?->id, 'load_units' => 3,
            'description' => $name, 'designation_id' => $designation->id,
        ]);
    }

    /** Plain subject-teacher LoadAssignment — 'teaching' type, tied to a section, no designation. */
    private function assignTeachingLoad(User $user, Subject $subject, Section $section): LoadAssignment
    {
        $facultyLoad = FacultyLoad::create([
            'user_id' => $user->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
        ]);

        return LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id, 'user_id' => $user->id,
            'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'assignment_type' => 'teaching', 'subject_id' => $subject->id, 'section_id' => $section->id,
            'load_units' => 3,
        ]);
    }

    private function makeClassRecordWithAssessment(Section $section, Subject $subject, User $teacher, string $activityDate): ClassRecordAssessment
    {
        $record = ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);
        $quarter = ClassRecordQuarter::create([
            'class_record_id' => $record->id, 'grading_option_id' => $this->option->id, 'quarter' => 1,
        ]);

        return ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $this->category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => 1, 'title' => 'Quiz 1', 'activity_date' => $activityDate,
            'plotted_at' => now(), 'max_score' => 20, 'sort_order' => 1,
        ]);
    }

    private function makeClassRecord(Section $section, Subject $subject, User $teacher): ClassRecord
    {
        return ClassRecord::create([
            'subject_id' => $subject->id, 'section_id' => $section->id,
            'grading_option_id' => $this->option->id, 'school_year_id' => $this->sy->id,
            'school_year' => $this->sy->name, 'subject_name' => $subject->name,
            'year_level_section' => "G-{$section->levelid} {$section->sectionname}",
            'teacher_id' => $teacher->id, 'status' => 'draft',
        ]);
    }

    private function makeAssessmentInRecord(ClassRecord $record, int $number, string $date): ClassRecordAssessment
    {
        $quarter = ClassRecordQuarter::firstOrCreate(
            ['class_record_id' => $record->id, 'quarter' => 1],
            ['grading_option_id' => $this->option->id],
        );

        return ClassRecordAssessment::create([
            'class_record_quarter_id' => $quarter->id, 'grading_category_id' => $this->category->id,
            'assessment_type' => 'formative', 'is_graded' => true, 'is_major' => false,
            'assessment_number' => $number, 'title' => "Quiz {$number}", 'activity_date' => $date,
            'plotted_at' => now(), 'max_score' => 20, 'sort_order' => $number,
        ]);
    }

    private function makeSchedule(
        Section $section,
        Subject $subject,
        User $teacher,
        string $start,
        string $end
    ): ClassSchedule {
        static $roomNumber = 0;
        $roomNumber++;
        $room = Classroom::create([
            'school_year_id' => $this->sy->id,
            'name' => "WAT Room {$roomNumber}",
            'code' => "WAT-R{$roomNumber}",
            'classroom_type' => 'lecture',
            'capacity' => 40,
            'is_available' => true,
        ]);

        return ClassSchedule::create([
            'user_id' => $teacher->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $this->sy->id,
            'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday',
            'start_time' => $start,
            'end_time' => $end,
            'status' => 'active',
        ]);
    }

    // ── Access control ──────────────────────────────────────────────────────

    public function test_designation_based_coordinator_can_access_wat_for_their_section(): void
    {
        $coordinator = User::factory()->create();
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->assignCoordinator($coordinator, 'HR_ADV', 'HRA-G8-EMERALD', 'HR Adviser — G8 Emerald', $section);
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $this->actingAs($coordinator)
            ->get(route('class-records.wat.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('sections', 1)
                ->where('sections.0.id', $section->id));
    }

    public function test_coordinator_with_grade_range_designation_sees_every_section_in_range(): void
    {
        $coordinator = User::factory()->create();
        $teacher = User::factory()->create();
        $g8 = $this->makeSection(['levelid' => 8, 'sectionname' => 'Emerald']);
        $g9 = $this->makeSection(['levelid' => 9, 'sectionname' => 'Sodium']);
        $subject = $this->makeSubject();
        $this->assignCoordinator($coordinator, 'COORD', 'COORD-HRG7&8', 'HR Coordinator (G7-G8)');
        $this->makeClassRecordWithAssessment($g8, $subject, $teacher, '2025-09-01');
        $this->makeClassRecordWithAssessment($g9, $subject, $teacher, '2025-09-01');

        $this->actingAs($coordinator)
            ->get(route('class-records.wat.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('sections', 1)
                ->where('sections.0.id', $g8->id));
    }

    public function test_subject_teacher_can_view_wat_for_a_section_they_teach(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');
        $this->assignTeachingLoad($teacher, $subject, $section);

        $this->actingAs($teacher)
            ->get(route('class-records.wat.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('sections', 1)
                ->where('sections.0.id', $section->id)
                ->where('canReview', false)
                ->where('isCoordinator', false));
    }

    public function test_subject_teacher_without_any_teaching_load_or_designation_cannot_access_wat(): void
    {
        $teacher = User::factory()->create();
        $otherTeacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->makeClassRecordWithAssessment($section, $subject, $otherTeacher, '2025-09-01');

        $this->actingAs($teacher)
            ->get(route('class-records.wat.index'))
            ->assertForbidden();
    }

    public function test_subject_teacher_cannot_print_wat_form_for_a_section_they_teach(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');
        $this->assignTeachingLoad($teacher, $subject, $section);

        $this->actingAs($teacher)
            ->get(route('class-records.wat.print', ['section' => $section->id]))
            ->assertForbidden();
    }

    public function test_subject_teacher_cannot_review_all_sections(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');
        $this->assignTeachingLoad($teacher, $subject, $section);

        $this->actingAs($teacher)
            ->get(route('class-records.wat.review'))
            ->assertForbidden();
    }

    public function test_subject_teacher_cannot_view_a_section_they_do_not_teach(): void
    {
        $teacher = User::factory()->create();
        $otherTeacher = User::factory()->create();
        $mySection = $this->makeSection(['levelid' => 8, 'sectionname' => 'Emerald']);
        $notMySection = $this->makeSection(['levelid' => 8, 'sectionname' => 'Anthurium']);
        $subject = $this->makeSubject();
        $this->makeClassRecordWithAssessment($mySection, $subject, $teacher, '2025-09-01');
        $this->makeClassRecordWithAssessment($notMySection, $subject, $otherTeacher, '2025-09-01');
        $this->assignTeachingLoad($teacher, $subject, $mySection);

        $this->actingAs($teacher)
            ->get(route('class-records.wat.index', ['section' => $notMySection->id]))
            ->assertForbidden();
    }

    public function test_coordinator_cannot_access_a_section_outside_their_designation(): void
    {
        $coordinator = User::factory()->create();
        $teacher = User::factory()->create();
        $mine = $this->makeSection(['levelid' => 8, 'sectionname' => 'Emerald']);
        $notMine = $this->makeSection(['levelid' => 8, 'sectionname' => 'Anthurium']);
        $subject = $this->makeSubject();
        $this->assignCoordinator($coordinator, 'HR_ADV', 'HRA-G8-EMERALD', 'HR Adviser — G8 Emerald', $mine);
        $this->makeClassRecordWithAssessment($notMine, $subject, $teacher, '2025-09-01');

        $this->actingAs($coordinator)
            ->get(route('class-records.wat.print', ['section' => $notMine->id]))
            ->assertForbidden();
    }

    public function test_admin_can_access_wat_for_any_section(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $this->actingAs($admin)
            ->get(route('class-records.wat.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('sections', 1));
    }

    // ── Print signatories ────────────────────────────────────────────────────
    // printForm() now streams a server-rendered mPDF PDF (see
    // WeeklyAssessmentTrackerController::renderPdf()) instead of an Inertia
    // page — assert on the Content-Type header and the actual rendered PDF
    // text, extracted via smalot/pdfparser, rather than Inertia props.

    private function assertPdfTextContains(TestResponse $response, string $needle): void
    {
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));

        $pdf = (new Parser)->parseContent($response->streamedContent());
        $this->assertStringContainsString($needle, $pdf->getText());
    }

    public function test_print_resolves_coordinator_name_from_designation_not_adviser_column(): void
    {
        $coordinator = User::factory()->create(['name' => 'Coordinator Person']);
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->assignCoordinator($coordinator, 'HR_ADV', 'HRA-G8-EMERALD', 'HR Adviser — G8 Emerald', $section);
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $response = $this->actingAs($coordinator)
            ->get(route('class-records.wat.print', ['section' => $section->id, 'week' => '2025-09-01']));

        $this->assertPdfTextContains($response, 'COORDINATOR PERSON');
    }

    public function test_print_resolves_grade_wide_coordinator_name_over_the_section_adviser(): void
    {
        // A campus-wide grade-band coordinator (e.g. "COORD-HRG8" — one
        // designation covering every section in the grade) is a real,
        // pre-existing role in this school, separate from the per-section
        // adviser — see AdvisoryScheduleScopeService::gradeWideCoordinatorAssignments().
        $adviser = User::factory()->create(['name' => 'Adviser Person']);
        $coordinator = User::factory()->create(['name' => 'Coordinator Person']);
        $teacher = User::factory()->create();
        $section = $this->makeSection(['adviser' => $adviser->id]);
        $subject = $this->makeSubject();

        app(HeadAdvisoryService::class)->syncSectionAdviser($section, null);
        $this->assignCoordinator($coordinator, 'COORD', 'COORD-HRG8', 'HR Coordinator (G8)');

        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $response = $this->actingAs($coordinator)
            ->get(route('class-records.wat.print', ['section' => $section->id, 'week' => '2025-09-01']));

        $this->assertPdfTextContains($response, 'COORDINATOR PERSON');

        // The section's own adviser keeps their Load Assignment credit AND
        // their own WAT access — a grade-wide coordinator existing doesn't
        // revoke it (both can legitimately manage/view the same section).
        $this->assertDatabaseHas('load_assignments', [
            'user_id' => $adviser->id, 'section_id' => $section->id, 'academic_term_id' => $this->term->id,
        ]);
        $this->actingAs($adviser)
            ->get(route('class-records.wat.print', ['section' => $section->id, 'week' => '2025-09-01']))
            ->assertOk();
    }

    public function test_print_includes_cid_chief_signatory(): void
    {
        $coordinator = User::factory()->create();
        $teacher = User::factory()->create();
        $cidChief = User::factory()->create(['name' => 'Chief Person']);
        $role = Role::create(['name' => 'CID Chief']);
        $cidChief->roles()->attach($role->id);
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->assignCoordinator($coordinator, 'HR_ADV', 'HRA-G8-EMERALD', 'HR Adviser — G8 Emerald', $section);
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $response = $this->actingAs($coordinator)
            ->get(route('class-records.wat.print', ['section' => $section->id, 'week' => '2025-09-01']));

        $this->assertPdfTextContains($response, 'CHIEF PERSON');
    }

    public function test_print_signatory_names_use_firstname_middle_initial_lastname_format(): void
    {
        $coordinator = User::factory()->create(['name' => 'Juan Dela Cruz']);
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->assignCoordinator($coordinator, 'HR_ADV', 'HRA-G8-EMERALD', 'HR Adviser — G8 Emerald', $section);
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $response = $this->actingAs($coordinator)
            ->get(route('class-records.wat.print', ['section' => $section->id, 'week' => '2025-09-01']));

        $this->assertPdfTextContains($response, 'JUAN D. CRUZ');
    }

    // ── Time column resolution ───────────────────────────────────────────────

    // ── Time column resolution ───────────────────────────────────────────────
    // Tests WatRuleService::weekData() directly rather than through the print
    // endpoint — the data-assembly logic under test is unchanged by the
    // Inertia→mPDF migration, but the structured `wat.days[].items[]` array
    // it used to inspect is no longer exposed as a response (it's rendered
    // into PDF text instead).

    public function test_wat_item_resolves_time_label_from_matching_class_schedule(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $room = Classroom::create([
            'school_year_id' => $this->sy->id, 'name' => 'Room 1', 'code' => 'R1',
            'classroom_type' => 'lecture', 'capacity' => 40, 'is_available' => true,
        ]);
        // 2025-09-01 is a Monday.
        ClassSchedule::create([
            'user_id' => $teacher->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'classroom_id' => $room->id, 'school_year_id' => $this->sy->id, 'academic_term_id' => $this->term->id,
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'active',
        ]);
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $wat = WatRuleService::weekData($section->id, $this->sy->id, '2025-09-01');
        $monday = collect($wat['days'])->firstWhere('date', '2025-09-01');
        $this->assertSame('08:00–09:00', $monday['items'][0]['time_label']);
    }

    public function test_wat_item_time_label_is_null_when_no_matching_schedule(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        // No ClassSchedule row created for this subject/section at all.
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $wat = WatRuleService::weekData($section->id, $this->sy->id, '2025-09-01');
        $monday = collect($wat['days'])->firstWhere('date', '2025-09-01');
        $this->assertNull($monday['items'][0]['time_label']);
    }

    // ── Science Core / Elective grade-pooling ────────────────────────────────

    public function test_science_core_and_elective_synthetic_sections_are_excluded_from_the_wat_dropdown(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create();
        $homeroom = $this->makeSection(['levelid' => 11, 'sectionname' => 'Venus']);
        $scienceCore = $this->makeSection(['levelid' => 11, 'sectionname' => 'SCI-PHY3L2-G11-1']);
        $elective = $this->makeSection(['levelid' => 11, 'sectionname' => 'ELEC-DATASCI-G11']);
        $subject = $this->makeSubject(['grade_level' => 11]);
        $this->makeClassRecordWithAssessment($homeroom, $subject, $teacher, '2025-09-01');
        $this->makeClassRecordWithAssessment($scienceCore, $subject, $teacher, '2025-09-01');
        $this->makeClassRecordWithAssessment($elective, $subject, $teacher, '2025-09-01');

        $this->actingAs($admin)
            ->get(route('class-records.wat.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('sections', 1)
                ->where('sections.0.id', $homeroom->id));
    }

    public function test_a_stray_section_row_not_tagged_to_the_current_school_year_is_excluded_from_the_wat_dropdown(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create();
        $homeroom = $this->makeSection(['levelid' => 11, 'sectionname' => 'Venus']);
        // A legacy/orphaned row that predates the school_year_id column (or
        // was never retired) — same grade, a different sectionname, tagged
        // to neither the legacy syid nor the FL-native school_year_id for
        // the current school year — yet a ClassRecord still references it.
        $stray = $this->makeSection(['levelid' => 11, 'sectionname' => 'Academic Class Venus', 'syid' => null, 'school_year_id' => null]);
        $subject = $this->makeSubject(['grade_level' => 11]);
        $this->makeClassRecordWithAssessment($homeroom, $subject, $teacher, '2025-09-01');
        $this->makeClassRecordWithAssessment($stray, $subject, $teacher, '2025-09-01');

        $this->actingAs($admin)
            ->get(route('class-records.wat.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('sections', 1)
                ->where('sections.0.id', $homeroom->id));
    }

    public function test_wat_week_data_pools_and_tags_science_core_and_elective_rows_into_the_homeroom(): void
    {
        $teacher = User::factory()->create();
        $homeroom = $this->makeSection(['levelid' => 11, 'sectionname' => 'Venus']);
        $scienceCore = $this->makeSection(['levelid' => 11, 'sectionname' => 'SCI-PHY3L2-G11-1']);
        $subject = $this->makeSubject(['grade_level' => 11, 'subject_type' => 'science_core']);
        $this->makeClassRecordWithAssessment($scienceCore, $subject, $teacher, '2025-09-01');

        $wat = WatRuleService::weekData($homeroom->id, $this->sy->id, '2025-09-01');
        $monday = collect($wat['days'])->firstWhere('date', '2025-09-01');

        $this->assertCount(1, $monday['items']);
        $this->assertStringContainsString('(Science Core)', $monday['items'][0]['subject_name']);
    }

    public function test_grade_wide_wat_cap_pools_elective_assessments_with_the_homeroom(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create();
        $homeroom = $this->makeSection(['levelid' => 11, 'sectionname' => 'Venus']);
        $elective = $this->makeSection(['levelid' => 11, 'sectionname' => 'ELEC-DATASCI-G11']);
        $homeroomSubject = $this->makeSubject(['grade_level' => 11]);
        $electiveSubject = $this->makeSubject(['grade_level' => 11, 'subject_type' => 'elective']);

        // Fill the grade's daily cap (3 graded/day) entirely inside the
        // synthetic elective section — the homeroom's own section has zero
        // assessments that day.
        $electiveRecord = $this->makeClassRecord($elective, $electiveSubject, $teacher);
        $this->makeAssessmentInRecord($electiveRecord, 1, '2025-09-01');
        $this->makeAssessmentInRecord($electiveRecord, 2, '2025-09-01');
        $this->makeAssessmentInRecord($electiveRecord, 3, '2025-09-01');

        $homeroomRecord = $this->makeClassRecord($homeroom, $homeroomSubject, $teacher);

        $response = $this->actingAs($admin)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $homeroomRecord->id, 'q' => 1]), [
                'assessments' => [[
                    'grading_category_id' => $this->category->id,
                    'assessment_number' => 1,
                    'title' => 'Quiz 1',
                    'is_graded' => true,
                    'activity_date' => '2025-09-01',
                    'max_score' => 20,
                ]],
            ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('4 graded assessments', $response->json('message'));
    }

    public function test_same_time_electives_count_as_one_wat_unit_but_remain_visible(): void
    {
        $teacher = User::factory()->create();
        $homeroom = $this->makeSection(['levelid' => 11, 'sectionname' => 'Venus']);
        $electiveA = $this->makeSection(['levelid' => 11, 'sectionname' => 'ELEC-A-G11']);
        $electiveB = $this->makeSection(['levelid' => 11, 'sectionname' => 'ELEC-B-G11']);
        $subjectA = $this->makeSubject(['grade_level' => 11, 'subject_type' => 'elective']);
        $subjectB = $this->makeSubject(['grade_level' => 11, 'subject_type' => 'elective']);
        $this->makeSchedule($electiveA, $subjectA, $teacher, '08:00:00', '09:00:00');
        $this->makeSchedule($electiveB, $subjectB, $teacher, '08:00:00', '09:00:00');
        $this->makeClassRecordWithAssessment($electiveA, $subjectA, $teacher, '2025-09-01');
        $this->makeClassRecordWithAssessment($electiveB, $subjectB, $teacher, '2025-09-01');

        $counts = WatRuleService::gradeCountsOnDate(
            $homeroom->id,
            11,
            $this->sy->id,
            '2025-09-01'
        );
        $wat = WatRuleService::weekData(
            $homeroom->id,
            $this->sy->id,
            '2025-09-01'
        );
        $monday = collect($wat['days'])->firstWhere('date', '2025-09-01');

        $this->assertSame(['graded' => 1, 'major' => 0], $counts);
        $this->assertSame(1, $monday['graded_count']);
        $this->assertCount(2, $monday['items']);
        $this->assertSame(1, $wat['totals']['graded']);
    }

    public function test_same_time_science_core_subjects_count_as_one_and_any_major_marks_the_block_major(): void
    {
        $teacher = User::factory()->create();
        $homeroom = $this->makeSection(['levelid' => 11, 'sectionname' => 'Venus']);
        $scienceA = $this->makeSection(['levelid' => 11, 'sectionname' => 'SCI-A-G11']);
        $scienceB = $this->makeSection(['levelid' => 11, 'sectionname' => 'SCI-B-G11']);
        $subjectA = $this->makeSubject(['grade_level' => 11, 'subject_type' => 'science_core']);
        $subjectB = $this->makeSubject(['grade_level' => 11, 'subject_type' => 'science_core']);
        $this->makeSchedule($scienceA, $subjectA, $teacher, '10:00:00', '11:00:00');
        $this->makeSchedule($scienceB, $subjectB, $teacher, '10:00:00', '11:00:00');
        $this->makeClassRecordWithAssessment($scienceA, $subjectA, $teacher, '2025-09-01');
        $major = $this->makeClassRecordWithAssessment($scienceB, $subjectB, $teacher, '2025-09-01');
        $major->update(['is_major' => true]);

        $counts = WatRuleService::gradeCountsOnDate(
            $homeroom->id,
            11,
            $this->sy->id,
            '2025-09-01'
        );

        $this->assertSame(['graded' => 1, 'major' => 1], $counts);
    }

    public function test_shared_block_grouping_requires_same_type_and_exact_unambiguous_time(): void
    {
        $teacher = User::factory()->create();
        $homeroom = $this->makeSection(['levelid' => 11, 'sectionname' => 'Venus']);
        $electiveA = $this->makeSection(['levelid' => 11, 'sectionname' => 'ELEC-A-G11']);
        $electiveB = $this->makeSection(['levelid' => 11, 'sectionname' => 'ELEC-B-G11']);
        $science = $this->makeSection(['levelid' => 11, 'sectionname' => 'SCI-A-G11']);
        $unscheduled = $this->makeSection(['levelid' => 11, 'sectionname' => 'ELEC-NO-SCHEDULE-G11']);
        $electiveSubjectA = $this->makeSubject(['grade_level' => 11, 'subject_type' => 'elective']);
        $electiveSubjectB = $this->makeSubject(['grade_level' => 11, 'subject_type' => 'elective']);
        $scienceSubject = $this->makeSubject(['grade_level' => 11, 'subject_type' => 'science_core']);
        $unscheduledSubject = $this->makeSubject(['grade_level' => 11, 'subject_type' => 'elective']);
        $this->makeSchedule($electiveA, $electiveSubjectA, $teacher, '08:00:00', '09:00:00');
        $this->makeSchedule($electiveB, $electiveSubjectB, $teacher, '09:00:00', '10:00:00');
        $this->makeSchedule($science, $scienceSubject, $teacher, '08:00:00', '09:00:00');
        $this->makeClassRecordWithAssessment($electiveA, $electiveSubjectA, $teacher, '2025-09-01');
        $this->makeClassRecordWithAssessment($electiveB, $electiveSubjectB, $teacher, '2025-09-01');
        $this->makeClassRecordWithAssessment($science, $scienceSubject, $teacher, '2025-09-01');
        $this->makeClassRecordWithAssessment($unscheduled, $unscheduledSubject, $teacher, '2025-09-01');

        $counts = WatRuleService::gradeCountsOnDate(
            $homeroom->id,
            11,
            $this->sy->id,
            '2025-09-01'
        );

        $this->assertSame(4, $counts['graded']);
    }

    public function test_new_elective_in_an_existing_full_block_does_not_consume_another_wat_slot(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create();
        $sections = collect(range(1, 4))->map(
            fn ($n) => $this->makeSection(['levelid' => 11, 'sectionname' => "ELEC-{$n}-G11"])
        );
        $subjects = collect(range(1, 4))->map(
            fn () => $this->makeSubject(['grade_level' => 11, 'subject_type' => 'elective'])
        );
        $starts = ['08:00:00', '09:00:00', '10:00:00', '08:00:00'];
        $ends = ['09:00:00', '10:00:00', '11:00:00', '09:00:00'];

        foreach (range(0, 3) as $index) {
            $this->makeSchedule($sections[$index], $subjects[$index], $teacher, $starts[$index], $ends[$index]);
        }
        foreach (range(0, 2) as $index) {
            $this->makeClassRecordWithAssessment(
                $sections[$index],
                $subjects[$index],
                $teacher,
                '2025-09-01'
            );
        }
        $target = $this->makeClassRecord($sections[3], $subjects[3], $teacher);

        $this->actingAs($admin)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $target->id, 'q' => 1]), [
                'assessments' => [[
                    'grading_category_id' => $this->category->id,
                    'assessment_number' => 1,
                    'title' => 'Shared Block Quiz',
                    'is_graded' => true,
                    'activity_date' => '2025-09-01',
                    'max_score' => 20,
                ]],
            ])
            ->assertOk();

        $counts = WatRuleService::gradeCountsOnDate(
            $sections[3]->id,
            11,
            $this->sy->id,
            '2025-09-01'
        );
        $this->assertSame(3, $counts['graded']);
    }

    // ── Sibling homerooms never pool with each other ─────────────────────────
    // Regression: poolSectionIds() briefly pooled EVERY section at a grade
    // (any homeroom, not just SCI-/ELEC- synthetics) — production symptom
    // was Grade 7 "Opal" showing 32 graded assessments that actually
    // belonged to other Grade 7 homerooms.

    public function test_wat_week_data_does_not_pool_a_sibling_homeroom(): void
    {
        $teacher = User::factory()->create();
        $opal = $this->makeSection(['levelid' => 7, 'sectionname' => 'Opal']);
        $sapphire = $this->makeSection(['levelid' => 7, 'sectionname' => 'Sapphire']);
        $subject = $this->makeSubject(['grade_level' => 7]);
        $this->makeClassRecordWithAssessment($sapphire, $subject, $teacher, '2025-09-01');

        $wat = WatRuleService::weekData($opal->id, $this->sy->id, '2025-09-01');
        $monday = collect($wat['days'])->firstWhere('date', '2025-09-01');

        $this->assertSame(0, $monday['graded_count']);
        $this->assertCount(0, $monday['items']);
    }

    public function test_grade_wide_wat_cap_does_not_pool_a_sibling_homeroom(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create();
        $opal = $this->makeSection(['levelid' => 7, 'sectionname' => 'Opal']);
        $sapphire = $this->makeSection(['levelid' => 7, 'sectionname' => 'Sapphire']);
        $subject = $this->makeSubject(['grade_level' => 7]);

        // Fill Sapphire's own daily cap entirely — Opal must be unaffected.
        $sapphireRecord = $this->makeClassRecord($sapphire, $subject, $teacher);
        $this->makeAssessmentInRecord($sapphireRecord, 1, '2025-09-01');
        $this->makeAssessmentInRecord($sapphireRecord, 2, '2025-09-01');
        $this->makeAssessmentInRecord($sapphireRecord, 3, '2025-09-01');

        $opalRecord = $this->makeClassRecord($opal, $subject, $teacher);

        $response = $this->actingAs($admin)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $opalRecord->id, 'q' => 1]), [
                'assessments' => [[
                    'grading_category_id' => $this->category->id,
                    'assessment_number' => 1,
                    'title' => 'Quiz 1',
                    'is_graded' => true,
                    'activity_date' => '2025-09-01',
                    'max_score' => 20,
                ]],
            ]);

        $response->assertOk();
    }

    public function test_review_summary_keeps_sibling_homeroom_counts_separate(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create();
        $opal = $this->makeSection(['levelid' => 7, 'sectionname' => 'Opal']);
        $sapphire = $this->makeSection(['levelid' => 7, 'sectionname' => 'Sapphire']);
        $subject = $this->makeSubject(['grade_level' => 7]);
        $this->makeClassRecordWithAssessment($opal, $subject, $teacher, '2025-09-01');
        $this->makeClassRecordWithAssessment($sapphire, $subject, $teacher, '2025-09-01');

        $this->actingAs($admin)
            ->get(route('class-records.wat.review', ['week' => '2025-09-01']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.0.name', 'Opal')
                ->where('summary.0.graded_count', 1)
                ->where('summary.1.name', 'Sapphire')
                ->where('summary.1.graded_count', 1));
    }

    // ── Archived class records are excluded from the WAT ─────────────────────
    // Regression: an archived class record's plotted assessments kept
    // surfacing in the tracker and consuming the section's daily/weekly cap,
    // double-counting against the fresh record a teacher creates to replace it.

    public function test_wat_week_data_excludes_an_archived_records_assessment(): void
    {
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();

        // Active record with a plotted assessment on Monday.
        $activeRecord = $this->makeClassRecord($section, $subject, $teacher);
        $this->makeAssessmentInRecord($activeRecord, 1, '2025-09-01');

        // Archived record with its own plotted assessment on the same day.
        $archivedRecord = $this->makeClassRecord($section, $subject, $teacher);
        $this->makeAssessmentInRecord($archivedRecord, 2, '2025-09-01');
        $archivedRecord->update(['status' => 'archived', 'archived_at' => now()]);

        $wat = WatRuleService::weekData($section->id, $this->sy->id, '2025-09-01');
        $monday = collect($wat['days'])->firstWhere('date', '2025-09-01');

        // Only the active record's single assessment survives.
        $this->assertCount(1, $monday['items']);
        $this->assertSame(1, $monday['graded_count']);
        $this->assertSame(1, $wat['totals']['graded']);
    }

    public function test_wat_cap_ignores_an_archived_records_assessments(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();

        // An archived record already carrying the full daily cap (3 graded) —
        // it must NOT block plotting on a fresh record for the same day.
        $archivedRecord = $this->makeClassRecord($section, $subject, $teacher);
        $this->makeAssessmentInRecord($archivedRecord, 1, '2025-09-01');
        $this->makeAssessmentInRecord($archivedRecord, 2, '2025-09-01');
        $this->makeAssessmentInRecord($archivedRecord, 3, '2025-09-01');
        $archivedRecord->update(['status' => 'archived', 'archived_at' => now()]);

        $freshRecord = $this->makeClassRecord($section, $subject, $teacher);

        $this->actingAs($admin)
            ->postJson(route('class-records.assessments.upsert', ['classRecord' => $freshRecord->id, 'q' => 1]), [
                'assessments' => [[
                    'grading_category_id' => $this->category->id,
                    'assessment_number' => 1,
                    'title' => 'Quiz 1',
                    'is_graded' => true,
                    'activity_date' => '2025-09-01',
                    'max_score' => 20,
                ]],
            ])
            ->assertOk();
    }

    public function test_review_summary_excludes_an_archived_records_assessment(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();

        $activeRecord = $this->makeClassRecord($section, $subject, $teacher);
        $this->makeAssessmentInRecord($activeRecord, 1, '2025-09-01');

        $archivedRecord = $this->makeClassRecord($section, $subject, $teacher);
        $this->makeAssessmentInRecord($archivedRecord, 2, '2025-09-01');
        $archivedRecord->update(['status' => 'archived', 'archived_at' => now()]);

        $this->actingAs($admin)
            ->get(route('class-records.wat.review', ['week' => '2025-09-01']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.0.name', 'Emerald')
                ->where('summary.0.graded_count', 1));
    }

    // ── Teacher-level plotting compliance (visibility-only analytics) ────────

    public function test_teacher_breakdown_marks_a_teacher_with_plotted_assessments_as_plotted(): void
    {
        $teacher = User::factory()->create(['name' => 'Plotting Teacher']);
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->assignTeachingLoad($teacher, $subject, $section);
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $breakdown = WatRuleService::teacherBreakdown($section->id, $this->sy->id, '2025-09-01');
        $row = collect($breakdown['teachers'])->firstWhere('user_id', $teacher->id);

        $this->assertNotNull($row);
        $this->assertSame('plotted', $row['status']);
        $this->assertTrue($row['plotted']);
        $this->assertSame(1, $row['graded_count']);
    }

    public function test_teacher_breakdown_marks_a_teacher_with_no_plots_as_not_yet_due_before_the_deadline(): void
    {
        $teacher = User::factory()->create(['name' => 'Idle Teacher']);
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->assignTeachingLoad($teacher, $subject, $section);

        // A week far enough in the future that "now" is still before its
        // Friday-noon plotting deadline.
        $future = now()->addMonths(2)->startOfWeek(Carbon::MONDAY)->toDateString();

        $breakdown = WatRuleService::teacherBreakdown($section->id, $this->sy->id, $future);
        $row = collect($breakdown['teachers'])->firstWhere('user_id', $teacher->id);

        $this->assertNotNull($row);
        $this->assertSame('not_yet_due', $row['status']);
        $this->assertFalse($row['plotted']);
    }

    public function test_teacher_breakdown_marks_a_non_plotter_as_not_plotted_when_the_section_still_has_room(): void
    {
        $plotter = User::factory()->create(['name' => 'Plotter']);
        $nonPlotter = User::factory()->create(['name' => 'Non Plotter']);
        $section = $this->makeSection();
        $subjectA = $this->makeSubject();
        $subjectB = $this->makeSubject();
        $this->assignTeachingLoad($plotter, $subjectA, $section);
        $this->assignTeachingLoad($nonPlotter, $subjectB, $section);

        // A past week (deadline has passed) with only 1 of 15 weekly graded
        // slots used — plenty of room left, so the non-plotter has no excuse.
        $this->makeClassRecordWithAssessment($section, $subjectA, $plotter, '2025-09-01');

        $breakdown = WatRuleService::teacherBreakdown($section->id, $this->sy->id, '2025-09-01');
        $row = collect($breakdown['teachers'])->firstWhere('user_id', $nonPlotter->id);

        $this->assertNotNull($row);
        $this->assertSame('not_plotted', $row['status']);
        $this->assertFalse($row['plotted']);
    }

    public function test_teacher_breakdown_marks_a_non_plotter_as_blocked_by_cap_when_the_section_is_maxed_out(): void
    {
        $plotter = User::factory()->create(['name' => 'Plotter']);
        $nonPlotter = User::factory()->create(['name' => 'Blocked Teacher']);
        $section = $this->makeSection();
        $subjectA = $this->makeSubject();
        $subjectB = $this->makeSubject();
        $this->assignTeachingLoad($plotter, $subjectA, $section);
        $this->assignTeachingLoad($nonPlotter, $subjectB, $section);

        // Fill the section's entire weekly graded cap (15) via subjectA's
        // teacher across 5 weekdays (3/day) — the WAT daily/weekly max —
        // spread as: Mon-Thu 3 each (12) + Fri 3 (15) so the weekly cap of
        // 15 graded is exactly exhausted before the non-plotter ever plots.
        $record = $this->makeClassRecord($section, $subjectA, $plotter);
        $dates = ['2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05'];
        $n = 1;
        foreach ($dates as $date) {
            for ($i = 0; $i < 3; $i++) {
                $this->makeAssessmentInRecord($record, $n, $date);
                $n++;
            }
        }

        $breakdown = WatRuleService::teacherBreakdown($section->id, $this->sy->id, '2025-09-01');
        $row = collect($breakdown['teachers'])->firstWhere('user_id', $nonPlotter->id);

        $this->assertNotNull($row);
        $this->assertSame(0, $breakdown['remaining']['weekly_graded']);
        $this->assertSame('blocked_by_cap', $row['status']);
        $this->assertFalse($row['plotted']);
    }

    public function test_review_endpoint_includes_teacher_breakdown_per_section(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create(['name' => 'Section Teacher']);
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->assignTeachingLoad($teacher, $subject, $section);
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $this->actingAs($admin)
            ->get(route('class-records.wat.review', ['week' => '2025-09-01']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('summary.0.teacher_breakdown')
                ->has('summary.0.teacher_breakdown.teachers')
                ->where('summary.0.teacher_breakdown.teachers.0.status', 'plotted'));
    }
}
