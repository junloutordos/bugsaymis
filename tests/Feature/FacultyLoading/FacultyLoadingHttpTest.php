<?php

namespace Tests\Feature\FacultyLoading;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP feature tests for the Faculty Loading module.
 *
 * Covers:
 *   - SchoolYearController  (school-years + terms CRUD)
 *   - SubjectController     (subject catalog CRUD)
 *   - ClassroomController   (classroom catalog CRUD)
 *   - FacultyLoadController (index, my-load, overload approval)
 *   - ClassScheduleController (index, store with conflict detection)
 *
 * Auth/permission boundaries are verified for each group.
 */
class FacultyLoadingHttpTest extends TestCase
{
    use RefreshDatabase;

    // ── Permission helpers ────────────────────────────────────────────────────

    private function userWith(array|string $permissions): User
    {
        $permissions = (array) $permissions;
        $role = Role::create(['name' => 'TestRole_'.uniqid()]);
        foreach ($permissions as $name) {
            $perm = Permission::firstOrCreate(
                ['name' => $name],
                ['module' => 'FacultyLoading', 'description' => $name]
            );
            $role->permissions()->attach($perm->id);
        }
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->roles()->attach($role->id);

        return $user;
    }

    private function cidUser(): User
    {
        return $this->userWith([
            'faculty_loading.view', 'faculty_loading.manage',
            'faculty_loading.subjects', 'faculty_loading.classrooms',
            'faculty_loading.school_year',
        ]);
    }

    private function facultyUser(): User
    {
        return $this->userWith('faculty_loading.view_own');
    }

    private function directorUser(): User
    {
        return $this->userWith(['faculty_loading.view', 'faculty_loading.approve']);
    }

    // ── Fixtures ──────────────────────────────────────────────────────────────

    private function makeSchoolYear(array $overrides = []): SchoolYear
    {
        return SchoolYear::create(array_merge([
            'name' => '2025-2026',
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
            'is_current' => true,
            'status' => 'active',
        ], $overrides));
    }

    /**
     * Reuse whichever school year the current test already created (school_years.name
     * is unique, so makeSubject()/makeClassroom() must not blindly create a second
     * '2025-2026') — only creates one if the test genuinely hasn't made one yet.
     */
    private function anySchoolYearId(): int
    {
        return SchoolYear::query()->latest('id')->value('id') ?? $this->makeSchoolYear()->id;
    }

    private function makeTerm(SchoolYear $sy, array $overrides = []): AcademicTerm
    {
        return AcademicTerm::create(array_merge([
            'school_year_id' => $sy->id,
            'name' => '1st Semester',
            'term_type' => '1st_semester',
            'start_date' => '2025-08-01',
            'end_date' => '2025-12-31',
            'is_current' => true,
        ], $overrides));
    }

    private function makeSubject(array $overrides = []): Subject
    {
        static $i = 0;
        $i++;

        return Subject::create(array_merge([
            'school_year_id' => $this->anySchoolYearId(),
            'code' => "SUBJ{$i}",
            'name' => "Subject {$i}",
            'credit_units' => 3,
            'lecture_hours' => 3,
            'load_units' => 3,
            'subject_type' => 'lecture',
            'grade_level' => 9,
            'sessions_per_week' => 5,
            'minutes_per_session' => 60,
            'is_active' => true,
        ], $overrides));
    }

    private function makeClassroom(array $overrides = []): Classroom
    {
        static $j = 0;
        $j++;

        return Classroom::create(array_merge([
            'school_year_id' => $this->anySchoolYearId(),
            'name' => "Room {$j}",
            'code' => "R{$j}",
            'classroom_type' => 'lecture',
            'capacity' => 40,
            'is_available' => true,
        ], $overrides));
    }

    private function makeSection(SchoolYear $sy, array $overrides = []): Section
    {
        return Section::create(array_merge([
            'levelid' => 9,
            'sectionname' => 'Diamond',
            'syid' => $sy->id,
            'school_year_id' => $sy->id,
            'is_active' => true,
        ], $overrides));
    }

    private function assignDesignation(
        User $user,
        SchoolYear $schoolYear,
        AcademicTerm $term,
        string $categoryCode,
        string $code,
        string $name,
        ?Section $section = null,
    ): LoadAssignment {
        $category = DesignationCategory::create([
            'code' => $categoryCode,
            'name' => $categoryCode,
            'is_active' => true,
        ]);
        $designation = Designation::create([
            'designation_category_id' => $category->id,
            'section_id' => $section?->id,
            'code' => $code,
            'name' => $name,
            'load_units' => 3,
            'assignment_type' => 'admin',
            'is_active' => true,
        ]);
        $facultyLoad = FacultyLoad::create([
            'user_id' => $user->id,
            'school_year_id' => $schoolYear->id,
            'academic_term_id' => $term->id,
        ]);

        return LoadAssignment::create([
            'faculty_load_id' => $facultyLoad->id,
            'user_id' => $user->id,
            'school_year_id' => $schoolYear->id,
            'academic_term_id' => $term->id,
            'assignment_type' => 'admin',
            'section_id' => $section?->id,
            'load_units' => 3,
            'description' => $name,
            'designation_id' => $designation->id,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SchoolYearController
    // ══════════════════════════════════════════════════════════════════════════

    public function test_cid_can_view_school_years_index(): void
    {
        $this->actingAs($this->cidUser())
            ->get(route('faculty-loading.school-years.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('FacultyLoading/SchoolYears/Index'));
    }

    public function test_guest_cannot_view_school_years(): void
    {
        $this->get(route('faculty-loading.school-years.index'))->assertRedirect(route('login'));
    }

    public function test_unauthorized_user_cannot_view_school_years(): void
    {
        $this->actingAs($this->facultyUser())
            ->get(route('faculty-loading.school-years.index'))
            ->assertForbidden();
    }

    public function test_cid_can_create_school_year(): void
    {
        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.school-years.store'), [
                'name' => '2025-2026',
                'start_date' => '2025-08-01',
                'end_date' => '2026-06-30',
                'is_current' => true,
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('school_years', ['name' => '2025-2026']);
    }

    public function test_cannot_create_duplicate_school_year_name(): void
    {
        $this->makeSchoolYear(['name' => '2025-2026']);

        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.school-years.store'), [
                'name' => '2025-2026', 'start_date' => '2025-08-01',
                'end_date' => '2026-06-30', 'status' => 'active',
            ])
            ->assertSessionHasErrors('name');
    }

    public function test_cid_can_update_school_year(): void
    {
        $sy = $this->makeSchoolYear();

        $this->actingAs($this->cidUser())
            ->put(route('faculty-loading.school-years.update', $sy), [
                'name' => '2025-2026',
                'start_date' => '2025-08-01',
                'end_date' => '2026-06-30',
                'is_current' => true,
                'status' => 'inactive',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('school_years', ['id' => $sy->id, 'status' => 'inactive']);
    }

    public function test_cid_can_create_academic_term(): void
    {
        $sy = $this->makeSchoolYear();

        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.school-years.terms.store', $sy), [
                'name' => '1st Semester',
                'term_type' => '1st_semester',
                'start_date' => '2025-08-01',
                'end_date' => '2025-12-31',
                'is_current' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('academic_terms', ['name' => '1st Semester', 'school_year_id' => $sy->id]);
    }

    public function test_cid_can_delete_empty_school_year(): void
    {
        $sy = $this->makeSchoolYear(['name' => '2020-2021', 'is_current' => false]);

        $this->actingAs($this->cidUser())
            ->delete(route('faculty-loading.school-years.destroy', $sy))
            ->assertRedirect();

        $this->assertDatabaseMissing('school_years', ['id' => $sy->id]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // SubjectController
    // ══════════════════════════════════════════════════════════════════════════

    public function test_cid_can_view_subjects_index(): void
    {
        $this->actingAs($this->cidUser())
            ->get(route('faculty-loading.subjects.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('FacultyLoading/Subjects/Index'));
    }

    public function test_cid_can_create_subject(): void
    {
        $sy = $this->makeSchoolYear();

        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.subjects.store'), [
                'school_year_id' => $sy->id,
                'code' => 'SCI901',
                'name' => 'Science 9',
                'credit_units' => 3,
                'lecture_hours' => 3,
                'lab_hours' => 0,
                'load_units' => 3,
                'subject_type' => 'lecture',
                'grade_level' => 9,
                'sessions_per_week' => 5,
                'minutes_per_session' => 60,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('subjects', ['code' => 'SCI901']);
    }

    public function test_cannot_create_subject_with_duplicate_code(): void
    {
        $this->makeSubject(['code' => 'DUP001']);

        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.subjects.store'), [
                'code' => 'DUP001', 'name' => 'Duplicate', 'credit_units' => 3,
                'lecture_hours' => 3, 'load_units' => 3, 'subject_type' => 'lecture',
                'grade_level' => 9, 'sessions_per_week' => 5, 'minutes_per_session' => 60,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_cid_can_update_subject(): void
    {
        $sub = $this->makeSubject();

        $this->actingAs($this->cidUser())
            ->put(route('faculty-loading.subjects.update', $sub), [
                'code' => $sub->code,
                'name' => 'Updated Name',
                'credit_units' => 3,
                'lecture_hours' => 3,
                'load_units' => 3,
                'subject_type' => 'lecture',
                'grade_level' => 9,
                'sessions_per_week' => 5,
                'minutes_per_session' => 60,
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('subjects', ['id' => $sub->id, 'name' => 'Updated Name']);
    }

    public function test_cid_can_delete_unused_subject(): void
    {
        $sub = $this->makeSubject();

        $this->actingAs($this->cidUser())
            ->delete(route('faculty-loading.subjects.destroy', $sub))
            ->assertRedirect();

        $this->assertDatabaseMissing('subjects', ['id' => $sub->id]);
    }

    public function test_unauthorized_user_cannot_manage_subjects(): void
    {
        $this->actingAs($this->facultyUser())
            ->post(route('faculty-loading.subjects.store'), ['code' => 'X'])
            ->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ClassroomController
    // ══════════════════════════════════════════════════════════════════════════

    public function test_cid_can_view_classrooms_index(): void
    {
        $this->actingAs($this->cidUser())
            ->get(route('faculty-loading.classrooms.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('FacultyLoading/Classrooms/Index'));
    }

    public function test_cid_can_create_classroom(): void
    {
        $sy = $this->makeSchoolYear();

        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.classrooms.store'), [
                'school_year_id' => $sy->id,
                'name' => 'Rm 201',
                'code' => 'RM201',
                'classroom_type' => 'lecture',
                'capacity' => 40,
                'is_available' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('classrooms', ['code' => 'RM201']);
    }

    public function test_cannot_create_classroom_with_duplicate_code(): void
    {
        $this->makeClassroom(['code' => 'DUP']);

        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.classrooms.store'), [
                'name' => 'Test', 'code' => 'DUP', 'classroom_type' => 'lecture', 'capacity' => 30,
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_cid_can_update_classroom(): void
    {
        $room = $this->makeClassroom();

        $this->actingAs($this->cidUser())
            ->put(route('faculty-loading.classrooms.update', $room), [
                'name' => 'Updated Room', 'code' => $room->code,
                'classroom_type' => 'laboratory', 'capacity' => 30, 'is_available' => false,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('classrooms', ['id' => $room->id, 'classroom_type' => 'laboratory']);
    }

    public function test_cid_can_delete_unused_classroom(): void
    {
        $room = $this->makeClassroom();

        $this->actingAs($this->cidUser())
            ->delete(route('faculty-loading.classrooms.destroy', $room))
            ->assertRedirect();

        $this->assertDatabaseMissing('classrooms', ['id' => $room->id]);
    }

    // ══════════════════════════════════════════════════════════════════════════
    // FacultyLoadController
    // ══════════════════════════════════════════════════════════════════════════

    public function test_cid_can_view_faculty_loads_index(): void
    {
        $this->actingAs($this->cidUser())
            ->get(route('faculty-loading.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('FacultyLoading/Index'));
    }

    public function test_faculty_can_view_own_load(): void
    {
        $this->actingAs($this->facultyUser())
            ->get(route('faculty-loading.my-load'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('FacultyLoading/MyLoad'));
    }

    public function test_faculty_load_print_formats_the_faculty_name_first(): void
    {
        $faculty = User::factory()->create([
            'name' => 'Dela Cruz, Juan Santos',
            'email_verified_at' => now(),
        ]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $load = FacultyLoad::create([
            'user_id' => $faculty->id,
            'school_year_id' => $sy->id,
            'academic_term_id' => $term->id,
            'total_units' => 0,
        ]);

        $this->actingAs($this->cidUser())
            ->get(route('faculty-loading.print', $load))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FacultyLoading/Print')
                ->has('loads', 1)
                ->where('loads.0.faculty.name', 'JUAN S. DELA CRUZ'));

        $this->assertDatabaseCount('class_schedules', 0);
    }

    public function test_view_own_faculty_receives_current_load_assignments_and_schedule(): void
    {
        $faculty = $this->facultyUser();
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $section = $this->makeSection($sy);
        $subject = $this->makeSubject();
        $room = $this->makeClassroom();

        $load = FacultyLoad::create([
            'user_id' => $faculty->id,
            'school_year_id' => $sy->id,
            'academic_term_id' => $term->id,
            'teaching_units' => 3,
            'total_units' => 3,
            'full_load_threshold' => 18,
            'load_status' => 'underload',
        ]);
        $assignment = LoadAssignment::create([
            'faculty_load_id' => $load->id,
            'user_id' => $faculty->id,
            'school_year_id' => $sy->id,
            'academic_term_id' => $term->id,
            'assignment_type' => 'teaching',
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'load_units' => 3,
        ]);
        $schedule = ClassSchedule::create([
            'load_assignment_id' => $assignment->id,
            'user_id' => $faculty->id,
            'subject_id' => $subject->id,
            'section_id' => $section->id,
            'classroom_id' => $room->id,
            'school_year_id' => $sy->id,
            'academic_term_id' => $term->id,
            'day_of_week' => 'Monday',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'status' => 'tentative',
        ]);

        $this->actingAs($faculty)
            ->get(route('faculty-loading.my-load'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FacultyLoading/MyLoad')
                ->where('currentTerm.id', $term->id)
                ->where('load.id', $load->id)
                ->has('load.assignments', 1)
                ->where('load.assignments.0.id', $assignment->id));

        $this->actingAs($faculty)
            ->get(route('faculty-loading.my-schedule'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FacultyLoading/Schedules/Index')
                ->where('pageMode', 'my')
                ->where('capability.level', 'self')
                ->where('currentTerm.id', $term->id)
                ->has('schedules', 1)
                ->where('schedules.0.id', $schedule->id));
    }

    public function test_my_faculty_schedule_matches_the_cid_faculty_filter_and_includes_actual_elective_time(): void
    {
        $faculty = $this->facultyUser();
        $otherFaculty = User::factory()->create(['email_verified_at' => now()]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $homeroom = $this->makeSection($sy, ['levelid' => 11, 'sectionname' => 'Diamond']);
        $electiveSection = $this->makeSection($sy, ['levelid' => 11, 'sectionname' => 'ELEC-G11']);
        $regularSubject = $this->makeSubject(['grade_level' => 11]);
        $electiveSubject = $this->makeSubject(['grade_level' => 11, 'subject_type' => 'elective']);
        $room = $this->makeClassroom();

        $regular = ClassSchedule::create([
            'user_id' => $faculty->id,
            'subject_id' => $regularSubject->id,
            'section_id' => $homeroom->id,
            'classroom_id' => $room->id,
            'school_year_id' => $sy->id,
            'academic_term_id' => $term->id,
            'day_of_week' => 'Monday',
            'start_time' => '08:00:00',
            'end_time' => '08:50:00',
            'status' => 'active',
        ]);
        $elective = ClassSchedule::create([
            'user_id' => $faculty->id,
            'subject_id' => $electiveSubject->id,
            'section_id' => $electiveSection->id,
            'classroom_id' => $room->id,
            'school_year_id' => $sy->id,
            'academic_term_id' => $term->id,
            'day_of_week' => 'Tuesday',
            'start_time' => '11:10:00',
            'end_time' => '12:00:00',
            'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => $otherFaculty->id,
            'subject_id' => $electiveSubject->id,
            'section_id' => $electiveSection->id,
            'classroom_id' => $room->id,
            'school_year_id' => $sy->id,
            'academic_term_id' => $term->id,
            'day_of_week' => 'Wednesday',
            'start_time' => '10:20:00',
            'end_time' => '11:10:00',
            'status' => 'active',
        ]);

        $assertFacultyRows = fn ($page) => $page
            ->has('schedules', 2)
            ->where('schedules.0.id', $regular->id)
            ->where('schedules.1.id', $elective->id)
            ->where('schedules.1.subject.is_elective', true)
            ->where('schedules.1.is_elective_section', true)
            ->where('schedules.1.day_of_week', 'Tuesday')
            ->where('schedules.1.start_time', '11:10:00')
            ->where('schedules.1.end_time', '12:00:00');

        $this->actingAs($this->cidUser())
            ->get(route('faculty-loading.schedules.index', [
                'term_id' => $term->id,
                'faculty_id' => $faculty->id,
            ]))
            ->assertOk()
            ->assertInertia($assertFacultyRows);

        $this->actingAs($faculty)
            ->get(route('faculty-loading.my-schedule', ['term_id' => $term->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $assertFacultyRows($page)
                ->where('pageMode', 'my')
                ->where('capability.level', 'self'));
    }

    public function test_guest_cannot_view_my_load(): void
    {
        $this->get(route('faculty-loading.my-load'))->assertRedirect(route('login'));
    }

    public function test_director_can_approve_overload(): void
    {
        $faculty = $this->facultyUser();
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $load = FacultyLoad::create([
            'user_id' => $faculty->id,
            'school_year_id' => $sy->id,
            'academic_term_id' => $term->id,
            'total_units' => 21,
            'full_load_threshold' => 18,
            'load_status' => 'overload',
            'overload_approved' => false,
        ]);

        $this->actingAs($this->directorUser())
            ->post(route('faculty-loading.approve-overload', $load), [
                'approved' => true,
                'approval_remarks' => 'Approved for exigency.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('faculty_loads', [
            'id' => $load->id,
            'overload_approved' => true,
        ]);
    }

    public function test_unauthorized_user_cannot_approve_overload(): void
    {
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $load = FacultyLoad::create([
            'user_id' => $this->facultyUser()->id, 'school_year_id' => $sy->id,
            'academic_term_id' => $term->id, 'load_status' => 'overload',
            'full_load_threshold' => 18,
        ]);

        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.approve-overload', $load), ['approved' => true])
            ->assertForbidden();
    }

    // ══════════════════════════════════════════════════════════════════════════
    // ClassScheduleController
    // ══════════════════════════════════════════════════════════════════════════

    public function test_cid_can_view_schedules_index(): void
    {
        $this->actingAs($this->cidUser())
            ->get(route('faculty-loading.schedules.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('FacultyLoading/Schedules/Index'));
    }

    public function test_hr_adviser_sees_only_the_assigned_section_schedule(): void
    {
        $adviser = User::factory()->create(['email_verified_at' => now()]);
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $assigned = $this->makeSection($sy, ['levelid' => 8, 'sectionname' => 'Diamond']);
        $other = $this->makeSection($sy, ['levelid' => 8, 'sectionname' => 'Emerald']);
        $elective = $this->makeSection($sy, ['levelid' => 8, 'sectionname' => 'ELEC-G8']);
        $subject = $this->makeSubject();
        $room = $this->makeClassroom();

        $this->assignDesignation($adviser, $sy, $term, 'HR_ADV', 'HRA-G8-DIAMOND', 'HR Adviser — G8 Diamond', $assigned);

        $visible = ClassSchedule::create([
            'user_id' => $faculty->id, 'subject_id' => $subject->id, 'section_id' => $assigned->id,
            'classroom_id' => $room->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => $faculty->id, 'subject_id' => $subject->id, 'section_id' => $other->id,
            'classroom_id' => $room->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => $faculty->id, 'subject_id' => $subject->id, 'section_id' => $elective->id,
            'classroom_id' => $room->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Wednesday', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'active',
        ]);

        $this->actingAs($adviser)
            ->get(route('faculty-loading.schedules.index', ['term_id' => $term->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('capability.level', 'advisory')
                ->has('sections', 1)
                ->where('sections.0.id', $assigned->id)
                ->where('sections.0.adviser_name', $adviser->name)
                ->has('schedules', 1)
                ->where('schedules.0.id', $visible->id)
                ->where('canRequestSwap', false));

        $this->actingAs($adviser)
            ->get(route('faculty-loading.schedules.index', ['term_id' => $term->id, 'section_id' => $other->id]))
            ->assertForbidden();
    }

    public function test_hr_coordinator_sees_every_homeroom_section_in_the_designated_grade_range(): void
    {
        $coordinator = User::factory()->create(['email_verified_at' => now()]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $grade7 = $this->makeSection($sy, ['levelid' => 7, 'sectionname' => 'Diamond']);
        $grade8 = $this->makeSection($sy, ['levelid' => 8, 'sectionname' => 'Emerald']);
        $this->makeSection($sy, ['levelid' => 9, 'sectionname' => 'Ruby']);
        $grade7Elective = $this->makeSection($sy, ['levelid' => 7, 'sectionname' => 'ELEC-G7']);
        $grade8Science = $this->makeSection($sy, ['levelid' => 8, 'sectionname' => 'SCI-G8']);

        $this->assignDesignation(
            $coordinator,
            $sy,
            $term,
            'COORD',
            'COORD-HRG7&8',
            'HR Coordinator (G7-G8)',
        );

        $this->actingAs($coordinator)
            ->get(route('faculty-loading.schedules.index', ['term_id' => $term->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('capability.level', 'advisory')
                ->has('sections', 4)
                ->where('sections.0.id', $grade7->id)
                ->where('sections.1.id', $grade7Elective->id)
                ->where('sections.2.id', $grade8->id)
                ->where('sections.3.id', $grade8Science->id));
    }

    public function test_single_grade_hr_coordinator_sees_homeroom_elective_and_science_core_sections_only_for_that_grade(): void
    {
        $coordinator = User::factory()->create(['email_verified_at' => now()]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $homeroom = $this->makeSection($sy, ['levelid' => 11, 'sectionname' => 'Diamond']);
        $elective = $this->makeSection($sy, ['levelid' => 11, 'sectionname' => 'ELEC-G11']);
        $scienceCore = $this->makeSection($sy, ['levelid' => 11, 'sectionname' => 'SCI-G11']);
        $otherGrade = $this->makeSection($sy, ['levelid' => 12, 'sectionname' => 'Electron']);
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $room = $this->makeClassroom();
        $regularSubject = $this->makeSubject(['grade_level' => 11]);
        $electiveSubject = $this->makeSubject(['grade_level' => 11, 'subject_type' => 'elective']);
        $scienceSubject = $this->makeSubject(['grade_level' => 11, 'subject_type' => 'science_core']);

        $visibleSchedules = collect([
            [$homeroom, $regularSubject, 'Monday'],
            [$elective, $electiveSubject, 'Tuesday'],
            [$scienceCore, $scienceSubject, 'Wednesday'],
        ])->map(fn ($row) => ClassSchedule::create([
            'user_id' => $faculty->id,
            'subject_id' => $row[1]->id,
            'section_id' => $row[0]->id,
            'classroom_id' => $room->id,
            'school_year_id' => $sy->id,
            'academic_term_id' => $term->id,
            'day_of_week' => $row[2],
            'start_time' => '10:20:00',
            'end_time' => '11:10:00',
            'status' => 'active',
        ]));
        ClassSchedule::create([
            'user_id' => $faculty->id,
            'subject_id' => $regularSubject->id,
            'section_id' => $otherGrade->id,
            'classroom_id' => $room->id,
            'school_year_id' => $sy->id,
            'academic_term_id' => $term->id,
            'day_of_week' => 'Thursday',
            'start_time' => '10:20:00',
            'end_time' => '11:10:00',
            'status' => 'active',
        ]);

        $this->assignDesignation(
            $coordinator,
            $sy,
            $term,
            'COORD',
            'COORD-HRG11',
            'HR Coordinator Grade 11',
        );

        $this->actingAs($coordinator)
            ->get(route('faculty-loading.schedules.index', ['term_id' => $term->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('capability.level', 'advisory')
                ->has('sections', 3)
                ->where('sections.0.id', $homeroom->id)
                ->where('sections.1.id', $elective->id)
                ->where('sections.2.id', $scienceCore->id)
                ->has('schedules', 3)
                ->where('schedules.0.id', $visibleSchedules[0]->id)
                ->where('schedules.1.id', $visibleSchedules[1]->id)
                ->where('schedules.2.id', $visibleSchedules[2]->id));
    }

    public function test_schedule_index_includes_assigned_advisers_for_lower_and_upper_grade_sections(): void
    {
        $lowerAdviser = User::factory()->create(['email_verified_at' => now()]);
        $upperAdviser = User::factory()->create(['email_verified_at' => now()]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $grade7 = $this->makeSection($sy, ['levelid' => 7, 'sectionname' => 'Aquamarine']);
        $grade12 = $this->makeSection($sy, ['levelid' => 12, 'sectionname' => 'Electron']);

        $this->assignDesignation(
            $lowerAdviser,
            $sy,
            $term,
            'HR_ADV',
            'HRA-G7-AQUAMARINE',
            'HR Adviser — G7 Aquamarine',
            $grade7,
        );
        $this->assignDesignation(
            $upperAdviser,
            $sy,
            $term,
            'HR_ACAD',
            'HAC-G12-ELECTRON',
            'HR/Academic Adviser — G12 Electron',
            $grade12,
        );

        $this->actingAs($this->cidUser())
            ->get(route('faculty-loading.schedules.index', ['term_id' => $term->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('sections', 2)
                ->where('sections.0.id', $grade7->id)
                ->where('sections.0.adviser_name', $lowerAdviser->name)
                ->where('sections.1.id', $grade12->id)
                ->where('sections.1.adviser_name', $upperAdviser->name));
    }

    public function test_unrecognized_coordinator_designation_does_not_grant_schedule_access(): void
    {
        $coordinator = User::factory()->create(['email_verified_at' => now()]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $this->makeSection($sy, ['levelid' => 7]);
        $this->assignDesignation($coordinator, $sy, $term, 'COORD', 'COORD-RESEARCH', 'Research Coordinator');

        $this->actingAs($coordinator)
            ->get(route('faculty-loading.schedules.index', ['term_id' => $term->id]))
            ->assertForbidden();
    }

    public function test_advisory_viewer_can_print_only_an_assigned_section_and_cannot_mutate_schedules(): void
    {
        $adviser = User::factory()->create(['email_verified_at' => now()]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $assigned = $this->makeSection($sy, ['levelid' => 11, 'sectionname' => 'Diamond']);
        $other = $this->makeSection($sy, ['levelid' => 11, 'sectionname' => 'Emerald']);
        $this->assignDesignation($adviser, $sy, $term, 'HR_ACAD', 'HAC-G11-DIAMOND', 'HR/Academic Adviser — G11 Diamond', $assigned);

        $this->actingAs($adviser)
            ->get(route('faculty-loading.schedules.sections.print', ['section' => $assigned, 'term_id' => $term->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('owner.adviser_name', $adviser->name));

        $this->actingAs($adviser)
            ->get(route('faculty-loading.schedules.sections.print', ['section' => $other, 'term_id' => $term->id]))
            ->assertForbidden();

        $this->actingAs($adviser)
            ->post(route('faculty-loading.schedules.store'), [])
            ->assertForbidden();
    }

    public function test_cid_can_print_one_section_schedule_for_its_term(): void
    {
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $section = $this->makeSection($sy);
        $subject = $this->makeSubject();
        $room = $this->makeClassroom();

        $active = ClassSchedule::create([
            'user_id' => $faculty->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'classroom_id' => $room->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'active',
        ]);
        ClassSchedule::create([
            'user_id' => $faculty->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'classroom_id' => $room->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Tuesday', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'cancelled',
        ]);

        $this->actingAs($this->cidUser())
            ->get(route('faculty-loading.schedules.sections.print', [
                'section' => $section,
                'term_id' => $term->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FacultyLoading/Schedules/Print')
                ->where('scheduleType', 'section')
                ->where('owner.id', $section->id)
                ->where('term.id', $term->id)
                ->where('schedules.0.id', $active->id)
                ->missing('schedules.1'));
    }

    public function test_section_print_rejects_a_term_from_another_school_year(): void
    {
        $sectionYear = $this->makeSchoolYear(['name' => '2025-2026']);
        $otherYear = $this->makeSchoolYear(['name' => '2026-2027', 'is_current' => false]);
        $section = $this->makeSection($sectionYear);
        $otherTerm = $this->makeTerm($otherYear, ['is_current' => false]);

        $this->actingAs($this->cidUser())
            ->get(route('faculty-loading.schedules.sections.print', [
                'section' => $section,
                'term_id' => $otherTerm->id,
            ]))
            ->assertNotFound();
    }

    public function test_batch_section_print_includes_the_term_designation_adviser_name(): void
    {
        $adviser = User::factory()->create(['email_verified_at' => now()]);
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $section = $this->makeSection($sy, ['levelid' => 8, 'sectionname' => 'Diamond']);
        $subject = $this->makeSubject();
        $room = $this->makeClassroom();

        $this->assignDesignation($adviser, $sy, $term, 'HR_ADV', 'HRA-G8-DIAMOND', 'HR Adviser — G8 Diamond', $section);
        ClassSchedule::create([
            'user_id' => $faculty->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'classroom_id' => $room->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'status' => 'active',
        ]);

        $this->actingAs($this->cidUser())
            ->get(route('faculty-loading.schedules.print-batch', [
                'type' => 'section',
                'term_id' => $term->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FacultyLoading/Schedules/PrintBatch')
                ->has('sheets', 1)
                ->where('sheets.0.owner.adviser_name', $adviser->name));
    }

    public function test_faculty_can_print_only_their_own_schedule(): void
    {
        $faculty = $this->facultyUser();
        $other = $this->facultyUser();
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);

        $this->actingAs($faculty)
            ->get(route('faculty-loading.schedules.faculty.print', [
                'faculty' => $faculty,
                'term_id' => $term->id,
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('FacultyLoading/Schedules/Print')
                ->where('scheduleType', 'faculty')
                ->where('owner.id', $faculty->id));

        $this->actingAs($faculty)
            ->get(route('faculty-loading.schedules.faculty.print', [
                'faculty' => $other,
                'term_id' => $term->id,
            ]))
            ->assertForbidden();
    }

    /**
     * Faculty print sheets must show Flag Ceremony and Homeroom bands
     * (faculty attend/supervise these) merged from every grade the faculty
     * teaches, but must never show Recess (a student-only break) even
     * though the G8 Monday timetable has one.
     */
    public function test_faculty_print_includes_flag_and_homeroom_but_excludes_recess(): void
    {
        $faculty = $this->facultyUser();
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $section = $this->makeSection($sy, ['levelid' => 8, 'sectionname' => 'Diamond']);
        $subject = $this->makeSubject();
        $room = $this->makeClassroom();

        ClassSchedule::create([
            'user_id' => $faculty->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'classroom_id' => $room->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Monday', 'start_time' => '10:00:00', 'end_time' => '10:50:00', 'status' => 'active',
        ]);

        $this->actingAs($this->cidUser())
            ->get(route('faculty-loading.schedules.faculty.print', [
                'faculty' => $faculty,
                'term_id' => $term->id,
            ]))
            ->assertOk()
            ->assertInertia(function ($page) {
                $page->component('FacultyLoading/Schedules/Print')
                    ->where('scheduleType', 'faculty');

                $monday = $page->toArray()['props']['dayConfigs']['Monday']['blocked'] ?? [];
                $types = array_column($monday, 'type');

                $this->assertContains('FLAG', $types, 'Faculty Monday print must include Flag Ceremony');
                $this->assertContains('HOMEROOM', $types, 'Faculty Monday print must include Homeroom');
                $this->assertNotContains('RECESS', $types, 'Faculty print must never include Recess — it is a student-only break');

                return $page;
            });
    }

    public function test_cid_can_create_schedule(): void
    {
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $subject = $this->makeSubject();
        $room = $this->makeClassroom();
        $section = $this->makeSection($sy);

        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.schedules.store'), [
                'faculty_id' => $faculty->id,
                'subject_id' => $subject->id,
                'section_id' => $section->id,
                'classroom_id' => $room->id,
                'school_year_id' => $sy->id,
                'academic_term_id' => $term->id,
                'day_of_week' => 'Monday',
                'start_time' => '08:00',
                'end_time' => '10:00',
                'status' => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('class_schedules', [
            'user_id' => $faculty->id,
            'subject_id' => $subject->id,
            'day_of_week' => 'Monday',
        ]);
    }

    public function test_store_rejects_conflicting_schedule(): void
    {
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $subject = $this->makeSubject();
        $room = $this->makeClassroom();
        $section = $this->makeSection($sy);

        // First schedule
        ClassSchedule::create([
            'user_id' => $faculty->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'classroom_id' => $room->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'active',
        ]);

        // Overlapping second schedule for same faculty
        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.schedules.store'), [
                'faculty_id' => $faculty->id,
                'subject_id' => $subject->id,
                'section_id' => $section->id,
                'classroom_id' => $room->id,
                'school_year_id' => $sy->id,
                'academic_term_id' => $term->id,
                'day_of_week' => 'Monday',
                'start_time' => '09:00',
                'end_time' => '11:00',
            ])
            ->assertSessionHasErrors();
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.schedules.store'), [])
            ->assertSessionHasErrors(['faculty_id', 'subject_id', 'classroom_id', 'day_of_week']);
    }

    public function test_store_validates_end_time_after_start_time(): void
    {
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $subject = $this->makeSubject();
        $room = $this->makeClassroom();

        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.schedules.store'), [
                'faculty_id' => $faculty->id,
                'subject_id' => $subject->id,
                'section_id' => 1,
                'classroom_id' => $room->id,
                'school_year_id' => $sy->id,
                'academic_term_id' => $term->id,
                'day_of_week' => 'Monday',
                'start_time' => '10:00',
                'end_time' => '08:00', // invalid
            ])
            ->assertSessionHasErrors('end_time');
    }

    public function test_cid_can_cancel_schedule(): void
    {
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $subject = $this->makeSubject();
        $room = $this->makeClassroom();
        $section = $this->makeSection($sy);

        $schedule = ClassSchedule::create([
            'user_id' => $faculty->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'classroom_id' => $room->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'active',
        ]);

        $this->actingAs($this->cidUser())
            ->delete(route('faculty-loading.schedules.destroy', $schedule))
            ->assertRedirect();

        $this->assertDatabaseHas('class_schedules', ['id' => $schedule->id, 'status' => 'cancelled']);
    }

    public function test_cid_can_permanently_delete_an_already_cancelled_schedule(): void
    {
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $sy = $this->makeSchoolYear();
        $term = $this->makeTerm($sy);
        $subject = $this->makeSubject();
        $room = $this->makeClassroom();
        $section = $this->makeSection($sy);

        $schedule = ClassSchedule::create([
            'user_id' => $faculty->id, 'subject_id' => $subject->id, 'section_id' => $section->id,
            'classroom_id' => $room->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'cancelled',
        ]);

        $this->actingAs($this->cidUser())
            ->delete(route('faculty-loading.schedules.destroy', $schedule))
            ->assertRedirect();

        $this->assertDatabaseMissing('class_schedules', ['id' => $schedule->id]);
    }

    public function test_unauthorized_user_cannot_manage_schedules(): void
    {
        $this->actingAs($this->facultyUser())
            ->post(route('faculty-loading.schedules.store'), [])
            ->assertForbidden();
    }

    public function test_guest_cannot_access_schedules(): void
    {
        $this->get(route('faculty-loading.schedules.index'))->assertRedirect(route('login'));
    }
}
