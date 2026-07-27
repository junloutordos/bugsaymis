<?php

namespace Tests\Feature\ClassRecord;

use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\ClassRecordQuarter;
use App\Models\ClassRecord\GradingCategory;
use App\Models\ClassRecord\GradingOption;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Classroom;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_print_resolves_coordinator_name_from_designation_not_adviser_column(): void
    {
        $coordinator = User::factory()->create(['name' => 'Coordinator Person']);
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->assignCoordinator($coordinator, 'HR_ADV', 'HRA-G8-EMERALD', 'HR Adviser — G8 Emerald', $section);
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $this->actingAs($coordinator)
            ->get(route('class-records.wat.print', ['section' => $section->id, 'week' => '2025-09-01']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ClassRecord/Wat/Print')
                ->where('coordinatorName', 'COORDINATOR PERSON'));
    }

    public function test_print_resolves_coordinator_override_name_not_the_section_adviser(): void
    {
        $adviser = User::factory()->create(['name' => 'Adviser Person']);
        $coordinator = User::factory()->create(['name' => 'Coordinator Person']);
        $teacher = User::factory()->create();
        $section = $this->makeSection(['adviser' => $adviser->id]);
        $subject = $this->makeSubject();

        app(\App\Services\FacultyLoading\HeadAdvisoryService::class)->syncSectionAdviser($section, null);
        $section->update(['homeroom_coordinator_id' => $coordinator->id]);
        app(\App\Services\FacultyLoading\HeadAdvisoryService::class)->syncHomeroomCoordinator($section->fresh(), null);

        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $this->actingAs($coordinator)
            ->get(route('class-records.wat.print', ['section' => $section->id, 'week' => '2025-09-01']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('ClassRecord/Wat/Print')
                ->where('coordinatorName', 'COORDINATOR PERSON'));

        // The former adviser no longer holds coordinator access for this section.
        $this->actingAs($adviser)
            ->get(route('class-records.wat.print', ['section' => $section->id, 'week' => '2025-09-01']))
            ->assertForbidden();
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

        $this->actingAs($coordinator)
            ->get(route('class-records.wat.print', ['section' => $section->id, 'week' => '2025-09-01']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('cidChiefName', 'CHIEF PERSON'));
    }

    public function test_print_signatory_names_use_firstname_middle_initial_lastname_format(): void
    {
        $coordinator = User::factory()->create(['name' => 'Juan Dela Cruz']);
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->assignCoordinator($coordinator, 'HR_ADV', 'HRA-G8-EMERALD', 'HR Adviser — G8 Emerald', $section);
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $this->actingAs($coordinator)
            ->get(route('class-records.wat.print', ['section' => $section->id, 'week' => '2025-09-01']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('coordinatorName', 'JUAN D. CRUZ'));
    }

    // ── Time column resolution ───────────────────────────────────────────────

    public function test_wat_item_resolves_time_label_from_matching_class_schedule(): void
    {
        $coordinator = User::factory()->create();
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
        $this->assignCoordinator($coordinator, 'HR_ADV', 'HRA-G8-EMERALD', 'HR Adviser — G8 Emerald', $section);
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $this->actingAs($coordinator)
            ->get(route('class-records.wat.print', ['section' => $section->id, 'week' => '2025-09-01']))
            ->assertOk()
            ->assertInertia(function ($page) {
                $days = $page->toArray()['props']['wat']['days'];
                $monday = collect($days)->firstWhere('date', '2025-09-01');
                $this->assertSame('08:00–09:00', $monday['items'][0]['time_label']);

                return $page;
            });
    }

    public function test_wat_item_time_label_is_null_when_no_matching_schedule(): void
    {
        $coordinator = User::factory()->create();
        $teacher = User::factory()->create();
        $section = $this->makeSection();
        $subject = $this->makeSubject();
        $this->assignCoordinator($coordinator, 'HR_ADV', 'HRA-G8-EMERALD', 'HR Adviser — G8 Emerald', $section);
        // No ClassSchedule row created for this subject/section at all.
        $this->makeClassRecordWithAssessment($section, $subject, $teacher, '2025-09-01');

        $this->actingAs($coordinator)
            ->get(route('class-records.wat.print', ['section' => $section->id, 'week' => '2025-09-01']))
            ->assertOk()
            ->assertInertia(function ($page) {
                $days = $page->toArray()['props']['wat']['days'];
                $monday = collect($days)->firstWhere('date', '2025-09-01');
                $this->assertNull($monday['items'][0]['time_label']);

                return $page;
            });
    }
}
