<?php

namespace Tests\Feature\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\Classroom;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\SchoolYear;
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
        $role = Role::create(['name' => 'TestRole_' . uniqid()]);
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
            'name'       => '2025-2026',
            'start_date' => '2025-08-01',
            'end_date'   => '2026-06-30',
            'is_current' => true,
            'status'     => 'active',
        ], $overrides));
    }

    private function makeTerm(SchoolYear $sy, array $overrides = []): AcademicTerm
    {
        return AcademicTerm::create(array_merge([
            'school_year_id' => $sy->id,
            'name'           => '1st Semester',
            'term_type'      => '1st_semester',
            'start_date'     => '2025-08-01',
            'end_date'       => '2025-12-31',
            'is_current'     => true,
        ], $overrides));
    }

    private function makeSubject(array $overrides = []): Subject
    {
        static $i = 0; $i++;
        return Subject::create(array_merge([
            'code'                => "SUBJ{$i}",
            'name'                => "Subject {$i}",
            'credit_units'        => 3,
            'lecture_hours'       => 3,
            'load_units'          => 3,
            'subject_type'        => 'lecture',
            'grade_level'         => 9,
            'sessions_per_week'   => 5,
            'minutes_per_session' => 60,
            'is_active'           => true,
        ], $overrides));
    }

    private function makeClassroom(array $overrides = []): Classroom
    {
        static $j = 0; $j++;
        return Classroom::create(array_merge([
            'name'           => "Room {$j}",
            'code'           => "R{$j}",
            'classroom_type' => 'lecture',
            'capacity'       => 40,
            'is_available'   => true,
        ], $overrides));
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
                'name'       => '2025-2026',
                'start_date' => '2025-08-01',
                'end_date'   => '2026-06-30',
                'is_current' => true,
                'status'     => 'active',
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
                'name'       => '2025-2026',
                'start_date' => '2025-08-01',
                'end_date'   => '2026-06-30',
                'is_current' => true,
                'status'     => 'inactive',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('school_years', ['id' => $sy->id, 'status' => 'inactive']);
    }

    public function test_cid_can_create_academic_term(): void
    {
        $sy = $this->makeSchoolYear();

        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.school-years.terms.store', $sy), [
                'name'       => '1st Semester',
                'term_type'  => '1st_semester',
                'start_date' => '2025-08-01',
                'end_date'   => '2025-12-31',
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
        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.subjects.store'), [
                'code'                => 'SCI901',
                'name'                => 'Science 9',
                'credit_units'        => 3,
                'lecture_hours'       => 3,
                'lab_hours'           => 0,
                'load_units'          => 3,
                'subject_type'        => 'lecture',
                'grade_level'         => 9,
                'sessions_per_week'   => 5,
                'minutes_per_session' => 60,
                'is_active'           => true,
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
                'code'                => $sub->code,
                'name'                => 'Updated Name',
                'credit_units'        => 3,
                'lecture_hours'       => 3,
                'load_units'          => 3,
                'subject_type'        => 'lecture',
                'grade_level'         => 9,
                'sessions_per_week'   => 5,
                'minutes_per_session' => 60,
                'is_active'           => true,
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
        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.classrooms.store'), [
                'name'           => 'Rm 201',
                'code'           => 'RM201',
                'classroom_type' => 'lecture',
                'capacity'       => 40,
                'is_available'   => true,
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

    public function test_guest_cannot_view_my_load(): void
    {
        $this->get(route('faculty-loading.my-load'))->assertRedirect(route('login'));
    }

    public function test_director_can_approve_overload(): void
    {
        $faculty = $this->facultyUser();
        $sy      = $this->makeSchoolYear();
        $term    = $this->makeTerm($sy);
        $load    = FacultyLoad::create([
            'user_id'            => $faculty->id,
            'school_year_id'     => $sy->id,
            'academic_term_id'   => $term->id,
            'total_units'        => 21,
            'full_load_threshold'=> 18,
            'load_status'        => 'overload',
            'overload_approved'  => false,
        ]);

        $this->actingAs($this->directorUser())
            ->post(route('faculty-loading.approve-overload', $load), [
                'approved'         => true,
                'approval_remarks' => 'Approved for exigency.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('faculty_loads', [
            'id'               => $load->id,
            'overload_approved'=> true,
        ]);
    }

    public function test_unauthorized_user_cannot_approve_overload(): void
    {
        $sy   = $this->makeSchoolYear();
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

    public function test_cid_can_create_schedule(): void
    {
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $sy      = $this->makeSchoolYear();
        $term    = $this->makeTerm($sy);
        $subject = $this->makeSubject();
        $room    = $this->makeClassroom();

        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.schedules.store'), [
                'faculty_id'       => $faculty->id,
                'subject_id'       => $subject->id,
                'section_id'       => 1,
                'classroom_id'     => $room->id,
                'school_year_id'   => $sy->id,
                'academic_term_id' => $term->id,
                'day_of_week'      => 'Monday',
                'start_time'       => '08:00',
                'end_time'         => '10:00',
                'status'           => 'active',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('class_schedules', [
            'user_id'    => $faculty->id,
            'subject_id' => $subject->id,
            'day_of_week'=> 'Monday',
        ]);
    }

    public function test_store_rejects_conflicting_schedule(): void
    {
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $sy      = $this->makeSchoolYear();
        $term    = $this->makeTerm($sy);
        $subject = $this->makeSubject();
        $room    = $this->makeClassroom();

        // First schedule
        ClassSchedule::create([
            'user_id' => $faculty->id, 'subject_id' => $subject->id, 'section_id' => 1,
            'classroom_id' => $room->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'active',
        ]);

        // Overlapping second schedule for same faculty
        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.schedules.store'), [
                'faculty_id'       => $faculty->id,
                'subject_id'       => $subject->id,
                'section_id'       => 1,
                'classroom_id'     => $room->id,
                'school_year_id'   => $sy->id,
                'academic_term_id' => $term->id,
                'day_of_week'      => 'Monday',
                'start_time'       => '09:00',
                'end_time'         => '11:00',
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
        $sy      = $this->makeSchoolYear();
        $term    = $this->makeTerm($sy);
        $subject = $this->makeSubject();
        $room    = $this->makeClassroom();

        $this->actingAs($this->cidUser())
            ->post(route('faculty-loading.schedules.store'), [
                'faculty_id'       => $faculty->id,
                'subject_id'       => $subject->id,
                'section_id'       => 1,
                'classroom_id'     => $room->id,
                'school_year_id'   => $sy->id,
                'academic_term_id' => $term->id,
                'day_of_week'      => 'Monday',
                'start_time'       => '10:00',
                'end_time'         => '08:00', // invalid
            ])
            ->assertSessionHasErrors('end_time');
    }

    public function test_cid_can_cancel_schedule(): void
    {
        $faculty = User::factory()->create(['email_verified_at' => now()]);
        $sy      = $this->makeSchoolYear();
        $term    = $this->makeTerm($sy);
        $subject = $this->makeSubject();
        $room    = $this->makeClassroom();

        $schedule = ClassSchedule::create([
            'user_id' => $faculty->id, 'subject_id' => $subject->id, 'section_id' => 1,
            'classroom_id' => $room->id, 'school_year_id' => $sy->id, 'academic_term_id' => $term->id,
            'day_of_week' => 'Monday', 'start_time' => '08:00:00', 'end_time' => '10:00:00', 'status' => 'active',
        ]);

        $this->actingAs($this->cidUser())
            ->delete(route('faculty-loading.schedules.destroy', $schedule))
            ->assertRedirect();

        $this->assertDatabaseHas('class_schedules', ['id' => $schedule->id, 'status' => 'cancelled']);
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
